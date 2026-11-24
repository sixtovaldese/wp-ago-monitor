<?php
/**
 * Smoke tests for aGo Monitor.
 *
 * Covers what the WordPress.org review rules of 2026 ask for (no bundled
 * translations, prefixed identifiers, own parent menu, translatable texts, no
 * inline script or style, a readme that promises only what a monitor running
 * inside the site can do) and the security of every REST handler: each route
 * answers 401 without a session, 403 to users who cannot manage options, and
 * a session cookie without the wp_rest nonce changes nothing.
 *
 * It also guards the behaviour the readme states: the weekly email is opt-in,
 * its cron event is cleared on deactivation and uninstall, the checks screen
 * makes no request to WordPress.org, the 404 log keeps no visitor IP or user
 * agent, and its 7 and 30 day windows follow the site timezone.
 *
 * Run with the plugin deployed:
 *   docker compose run --rm wpcli eval-file \
 *     //var/www/html/wp-content/plugins/ago-monitor/tests/smoke.php
 */

use AgoLab\Monitor\Admin\Page;
use AgoLab\Monitor\Logger404;
use AgoLab\Monitor\Plugin;
use AgoLab\Monitor\Report;

class AgoMonitorSmoke {

    public static int $ok = 0;
    public static int $fail = 0;

    private const HOOK = 'agomonitor_weekly_report';

    /** @var array<string, int> */
    private static array $users = [];

    private static int $admin = 0;

    public static function check( string $name, bool $cond, string $detail = '' ): void {
        if ( $cond ) {
            self::$ok++;
            echo "  ok    {$name}\n";
        } else {
            self::$fail++;
            echo "  FALLA {$name}" . ( $detail ? " ({$detail})" : '' ) . "\n";
        }
    }

    public static function run(): void {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/user.php';

        $saved_option = get_option( 'agomonitor_settings', null );
        $saved_tz     = get_option( 'timezone_string' );
        $saved_offset = get_option( 'gmt_offset' );
        $saved_server = $_SERVER;

        try {
            self::users();
            self::packaging();
            self::readme();
            self::identifiers();
            self::admin_menu();
            self::page();
            self::rest_access();
            self::settings();
            self::lifecycle();
            self::no_outgoing_requests();
            self::logger_privacy();
            self::logger_timezone();
            self::report();
            self::messages();
            self::http_nonce();
            self::uninstall();
        } finally {
            $_SERVER = $saved_server;
            update_option( 'timezone_string', $saved_tz );
            update_option( 'gmt_offset', $saved_offset );
            null === $saved_option ? delete_option( 'agomonitor_settings' ) : update_option( 'agomonitor_settings', $saved_option );
            wp_set_current_user( 0 );
            foreach ( self::$users as $id ) {
                wp_delete_user( $id );
            }
        }

        echo "\n" . self::$ok . ' ok, ' . self::$fail . " fallas\n";
        if ( self::$fail ) {
            exit( 1 );
        }
    }

    private static function users(): void {
        $admin       = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
        self::$admin = (int) $admin[0];
        foreach ( [ 'subscriber', 'editor' ] as $role ) {
            self::$users[ $role ] = (int) wp_insert_user( [
                'user_login' => 'agomonitor_smoke_' . $role . '_' . wp_rand(),
                'user_pass'  => wp_generate_password(),
                'role'       => $role,
            ] );
        }
    }

    /** @return string[] */
    private static function php_files(): array {
        $files = [ AGOMONITOR_FILE, AGOMONITOR_PATH . 'uninstall.php' ];
        $it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( AGOMONITOR_PATH . 'src' ) );
        foreach ( $it as $f ) {
            if ( $f->isFile() && 'php' === $f->getExtension() ) {
                $files[] = $f->getPathname();
            }
        }
        return $files;
    }

    private static function source(): string {
        $source = '';
        foreach ( self::php_files() as $file ) {
            $source .= file_get_contents( $file );
        }
        return $source;
    }

    private static function packaging(): void {
        $data = get_plugin_data( AGOMONITOR_FILE, false, false );
        self::check( 'el paquete no lleva carpeta languages', ! is_dir( AGOMONITOR_PATH . 'languages' ) );
        self::check( 'el encabezado no declara Domain Path', '' === $data['DomainPath'], $data['DomainPath'] );
        self::check( 'el text domain es el slug', 'ago-monitor' === $data['TextDomain'] );
        self::check( 'el nombre del plugin da el slug ago-monitor en WordPress.org', 'ago-monitor' === sanitize_title( $data['Name'] ), $data['Name'] );
        self::check( 'nadie llama load_plugin_textdomain', ! str_contains( self::source(), 'load_plugin_textdomain' ) );
    }

    private static function readme(): void {
        $readme = (string) file_get_contents( AGOMONITOR_PATH . 'readme.txt' );
        $data   = get_plugin_data( AGOMONITOR_FILE, false, false );

        self::check( 'readme: Tested up to 7.1', (bool) preg_match( '/^Tested up to:\s*7\.1\s*$/m', $readme ) );
        preg_match( '/^Stable tag:\s*(\S+)\s*$/m', $readme, $tag );
        self::check( 'readme: Stable tag numerico e igual a la version', isset( $tag[1] ) && $tag[1] === $data['Version'], $tag[1] ?? '' );
        foreach ( [ 'External services', 'Privacy', 'Screenshots', 'Frequently Asked Questions' ] as $section ) {
            self::check( "readme: seccion {$section}", str_contains( $readme, "== {$section} ==" ) );
        }

        $claims = [];
        foreach ( [ 'uptime', 'real time', 'real-time', 'realtime', 'instant', '24/7', 'always', 'guarantee' ] as $word ) {
            foreach ( [ 'readme' => $readme, 'codigo' => self::source() ] as $where => $text ) {
                // Pointing to an external uptime service is the honest answer, not a claim.
                $text = str_ireplace( 'uptime service', '', $text );
                if ( preg_match( '/(?<![a-z])' . preg_quote( $word, '/' ) . '(?![a-z])/i', $text ) ) {
                    $claims[] = "{$where}: {$word}";
                }
            }
        }
        self::check( 'ni el readme ni la interfaz prometen uptime ni tiempo real', [] === $claims, implode( ', ', $claims ) );
        self::check( 'readme: dice que no puede avisar si el sitio se cae', (bool) preg_match( '/cannot tell you when (the|your) site is down/i', $readme ) );
        self::check( 'readme: dice que el correo semanal depende de WP-Cron', str_contains( $readme, 'WP-Cron' ) );
    }

    private static function identifiers(): void {
        $routes = array_keys( rest_get_server()->get_routes() );
        $own    = array_values( array_filter( $routes, static fn ( $r ) => str_starts_with( $r, '/agomonitor/' ) ) );
        $old    = array_filter( $routes, static fn ( $r ) => str_starts_with( $r, '/ago-monitor/' ) );
        sort( $own );
        $expected = [ '/agomonitor/v1', '/agomonitor/v1/404s', '/agomonitor/v1/404s/clear', '/agomonitor/v1/checks', '/agomonitor/v1/cron', '/agomonitor/v1/report/send', '/agomonitor/v1/settings' ];
        self::check( 'las rutas REST usan el namespace agomonitor/v1', $expected === $own, implode( ',', $own ) );
        self::check( 'no queda el namespace ago-monitor/v1', [] === $old );

        wp_set_current_user( self::$admin );
        set_current_screen( 'tools_page_agomonitor' );
        Plugin::instance()->enqueue_assets( 'agomonitor-tools_page_agomonitor' );
        $data = (string) wp_scripts()->get_data( 'agomonitor-admin', 'data' );
        $json = json_decode( (string) preg_replace( '/^var agomonitorData = (.*);$/s', '$1', $data ), true );
        self::check( 'el script y el estilo se encolan con el handle agomonitor-admin', wp_script_is( 'agomonitor-admin', 'enqueued' ) && wp_style_is( 'agomonitor-admin', 'enqueued' ) );
        self::check( 'el objeto JS apunta a agomonitor/v1', str_ends_with( (string) ( $json['restUrl'] ?? '' ), '/agomonitor/v1' ), $data );
        self::check( 'el nonce entregado al JS es de wp_rest', ! empty( $json['nonce'] ) && false !== wp_verify_nonce( $json['nonce'], 'wp_rest' ) );
        self::check( 'los textos del JS se entregan traducibles', is_array( $json['i18n'] ?? null ) && count( $json['i18n'] ) >= 20, (string) count( (array) ( $json['i18n'] ?? [] ) ) );
        wp_set_current_user( 0 );
    }

    private static function reset_menu(): void {
        $GLOBALS['admin_page_hooks']  = [];
        $GLOBALS['menu']              = [];
        $GLOBALS['submenu']           = [];
        $GLOBALS['_registered_pages'] = [];
        $GLOBALS['_parent_pages']     = [];
    }

    /** @return string[] */
    private static function submenu_slugs( string $parent ): array {
        return array_values( array_map( static fn ( $item ) => $item[2], $GLOBALS['submenu'][ $parent ] ?? [] ) );
    }

    private static function admin_menu(): void {
        wp_set_current_user( self::$admin );

        self::reset_menu();
        Plugin::instance()->register_admin_menu();
        self::check( 'sin el menu compartido crea su propio padre agomonitor-tools', isset( $GLOBALS['admin_page_hooks']['agomonitor-tools'] ) );
        self::check( 'nunca define el slug compartido agolab-tools', ! isset( $GLOBALS['admin_page_hooks']['agolab-tools'] ) );
        self::check( 'la pagina agomonitor cuelga de agomonitor-tools', [ 'agomonitor' ] === self::submenu_slugs( 'agomonitor-tools' ), implode( ',', self::submenu_slugs( 'agomonitor-tools' ) ) );

        self::reset_menu();
        add_menu_page( 'aGo Tools', 'aGo Tools', 'manage_options', 'agolab-tools', '__return_null' );
        Plugin::instance()->register_admin_menu();
        self::check( 'con el menu compartido se engancha a el', in_array( 'agomonitor', self::submenu_slugs( 'agolab-tools' ), true ) );
        self::check( 'con el menu compartido no crea un segundo padre', ! isset( $GLOBALS['admin_page_hooks']['agomonitor-tools'] ) );

        self::check( 'en un sitio simple la capacidad es manage_options', 'manage_options' === Plugin::capability() );

        self::reset_menu();
        wp_set_current_user( 0 );
    }

    private static function page(): void {
        wp_set_current_user( self::$admin );
        ob_start();
        Page::render();
        $html = (string) ob_get_clean();
        wp_set_current_user( 0 );

        self::check( 'la pagina no imprime script en linea', ! str_contains( $html, '<script' ) );
        self::check( 'la pagina no imprime estilos en linea ni link directo', ! str_contains( $html, '<style' ) && ! str_contains( $html, '<link' ) );
        self::check( 'la pagina no trae controles deshabilitados', ! preg_match( '/\sdisabled(=|\s|>)/', $html ) );
        self::check( 'el sidebar trae Quick links', str_contains( $html, 'Quick links' ) );
        self::check( 'el sidebar trae la donacion y el pie de aGo Lab', str_contains( $html, 'ago-donation' ) && str_contains( $html, 'ago-footer' ) );
    }

    /**
     * @param array<string, mixed>|null $body
     */
    private static function call( string $method, string $route, int $user, ?array $body = null ): WP_REST_Response {
        wp_set_current_user( $user );
        $request = new WP_REST_Request( $method, $route );
        if ( null !== $body ) {
            $request->set_header( 'Content-Type', 'application/json' );
            $request->set_body( (string) wp_json_encode( $body ) );
        }
        $response = rest_do_request( $request );
        wp_set_current_user( 0 );
        return $response;
    }

    private static function rest_access(): void {
        $routes = [
            [ 'GET', '/agomonitor/v1/checks' ],
            [ 'GET', '/agomonitor/v1/404s' ],
            [ 'POST', '/agomonitor/v1/404s/clear' ],
            [ 'GET', '/agomonitor/v1/cron' ],
            [ 'POST', '/agomonitor/v1/report/send' ],
            [ 'GET', '/agomonitor/v1/settings' ],
            [ 'POST', '/agomonitor/v1/settings' ],
        ];

        $mail = 0;
        $trap = static function ( $return ) use ( &$mail ) {
            $mail++;
            return true;
        };
        add_filter( 'pre_wp_mail', $trap );
        update_option( 'agomonitor_settings', [ 'report_email' => 'smoke@example.com', 'enable_404_logging' => true ] );
        self::insert_404( 'https://example.com/smoke-acceso', current_time( 'mysql' ) );
        $before = self::count_404();

        foreach ( $routes as [ $method, $route ] ) {
            $body = 'POST' === $method ? [ 'report_email' => 'atacante@example.com' ] : null;
            $r    = self::call( $method, $route, 0, $body );
            self::check( "{$method} {$route} sin sesion responde 401", 401 === $r->get_status(), (string) $r->get_status() );
            foreach ( [ 'subscriber', 'editor' ] as $role ) {
                $r = self::call( $method, $route, self::$users[ $role ], $body );
                self::check( "{$method} {$route} con {$role} responde 403", 403 === $r->get_status(), (string) $r->get_status() );
            }
        }
        self::check( 'sin permiso no se envio ningun correo', 0 === $mail, (string) $mail );
        self::check( 'sin permiso no se vacio el registro de 404', self::count_404() === $before );
        self::check( 'sin permiso no cambio el correo del informe', 'smoke@example.com' === Plugin::get_settings()['report_email'] );

        foreach ( [ [ 'GET', '/agomonitor/v1/checks' ], [ 'GET', '/agomonitor/v1/404s' ], [ 'GET', '/agomonitor/v1/cron' ], [ 'GET', '/agomonitor/v1/settings' ] ] as [ $method, $route ] ) {
            $r = self::call( $method, $route, self::$admin );
            self::check( "{$method} {$route} con administrador responde 200", 200 === $r->get_status(), (string) $r->get_status() );
        }
        $r = self::call( 'POST', '/agomonitor/v1/report/send', self::$admin );
        self::check( 'el administrador puede enviar el informe', 200 === $r->get_status() && 1 === $mail, $r->get_status() . ' / ' . $mail );
        $r = self::call( 'POST', '/agomonitor/v1/404s/clear', self::$admin );
        self::check( 'el administrador puede vaciar el registro de 404', 200 === $r->get_status() && 0 === self::count_404() );

        remove_filter( 'pre_wp_mail', $trap );
    }

    private static function settings(): void {
        delete_option( 'agomonitor_settings' );
        self::check( 'por defecto el correo semanal esta apagado', false === Plugin::get_settings()['enable_weekly_report'] );
        self::check( 'por defecto el registro de 404 esta encendido', true === Plugin::get_settings()['enable_404_logging'] );

        wp_clear_scheduled_hook( self::HOOK );
        $r     = self::call( 'POST', '/agomonitor/v1/settings', self::$admin, [
            'enable_weekly_report' => true,
            'enable_404_logging'   => false,
            'report_email'         => '<script>x</script>no-es-correo',
            'autoload_warn_kb'     => 5,
            'otra_clave'           => 'basura',
        ] );
        $saved = get_option( 'agomonitor_settings' );
        self::check( 'guardar responde 200', 200 === $r->get_status(), (string) $r->get_status() );
        self::check( 'un correo invalido se guarda vacio', '' === ( $saved['report_email'] ?? null ), (string) ( $saved['report_email'] ?? '' ) );
        self::check( 'el umbral de autoload no baja de 100 KB', 100 === ( $saved['autoload_warn_kb'] ?? null ) );
        self::check( 'las claves desconocidas no se guardan', ! array_key_exists( 'otra_clave', (array) $saved ) );
        self::check( 'encender el correo programa el evento semanal', false !== wp_next_scheduled( self::HOOK ) && 'weekly' === wp_get_schedule( self::HOOK ) );

        self::call( 'POST', '/agomonitor/v1/settings', self::$admin, [ 'enable_weekly_report' => false, 'report_email' => 'equipo@example.com' ] );
        self::check( 'apagar el correo borra el evento semanal', false === wp_next_scheduled( self::HOOK ) );
        self::check( 'un correo valido se guarda', 'equipo@example.com' === Plugin::get_settings()['report_email'] );
    }

    private static function lifecycle(): void {
        $file = plugin_basename( AGOMONITOR_FILE );

        delete_option( 'agomonitor_settings' );
        wp_clear_scheduled_hook( self::HOOK );
        do_action( 'activate_' . $file, false );
        self::check( 'activar no programa el correo si nadie lo pidio', false === wp_next_scheduled( self::HOOK ) );
        self::check( 'activar crea la tabla del registro de 404', self::table_exists() );

        update_option( 'agomonitor_settings', [ 'enable_weekly_report' => true ] );
        do_action( 'activate_' . $file, false );
        self::check( 'activar con el correo encendido lo programa una vez', false !== wp_next_scheduled( self::HOOK ) && 1 === self::count_events() );

        do_action( 'deactivate_' . $file, false );
        self::check( 'desactivar borra el evento semanal', false === wp_next_scheduled( self::HOOK ) );
        delete_option( 'agomonitor_settings' );
    }

    private static function count_events(): int {
        $n = 0;
        foreach ( (array) _get_cron_array() as $hooks ) {
            $n += count( (array) ( $hooks[ self::HOOK ] ?? [] ) );
        }
        return $n;
    }

    /**
     * The checks screen reads what WordPress already knows about updates. It
     * does not ask WordPress.org itself, which the readme does not declare.
     */
    private static function no_outgoing_requests(): void {
        $urls = [];
        $spy  = static function ( $pre, $args, $url ) use ( &$urls ) {
            $urls[] = $url;
            return new WP_Error( 'agomonitor_smoke', 'bloqueado' );
        };
        add_filter( 'pre_http_request', $spy, 10, 3 );
        $saved = get_site_transient( 'update_plugins' );
        delete_site_transient( 'update_plugins' );

        $r = self::call( 'GET', '/agomonitor/v1/checks', self::$admin );

        remove_filter( 'pre_http_request', $spy, 10 );
        false === $saved ? delete_site_transient( 'update_plugins' ) : set_site_transient( 'update_plugins', $saved );

        self::check( 'revisar el sitio responde 200', 200 === $r->get_status() );
        self::check( 'revisar el sitio no hace peticiones HTTP', [] === $urls, implode( ', ', $urls ) );
        self::check( 'las actualizaciones pendientes vienen de lo que WordPress ya reviso', is_array( $r->get_data()['updates'] ?? null ) );
    }

    private static function table_exists(): bool {
        global $wpdb;
        return Logger404::table() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( Logger404::table() ) ) );
    }

    private static function count_404(): int {
        global $wpdb;
        return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Logger404::table() );
    }

    private static function insert_404( string $url, string $created_at ): int {
        global $wpdb;
        $wpdb->insert( Logger404::table(), [ 'url' => $url, 'referrer' => '', 'created_at' => $created_at ] );
        return (int) $wpdb->insert_id;
    }

    private static function logger_privacy(): void {
        global $wpdb, $wp_query;

        $_SERVER['REMOTE_ADDR']     = '203.0.113.77';
        $_SERVER['HTTP_USER_AGENT'] = 'AgoMonitorSmoke/1.0 (visitante)';
        $_SERVER['HTTP_HOST']       = 'example.com';
        $_SERVER['REQUEST_URI']     = '/no-existe-smoke';

        update_option( 'agomonitor_settings', [ 'enable_404_logging' => true ] );
        $saved_query = $wp_query;
        $wp_query    = new WP_Query();
        $wp_query->set_404();

        $last = (int) $wpdb->get_var( 'SELECT MAX(id) FROM ' . Logger404::table() );
        Logger404::maybe_log();
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Logger404::table() . ' WHERE id > %d ORDER BY id DESC LIMIT 1', $last ), ARRAY_A );

        $wp_query = $saved_query;

        self::check( 'una visita a una pagina inexistente queda registrada', is_array( $row ) && str_contains( (string) ( $row['url'] ?? '' ), '/no-existe-smoke' ), wp_json_encode( $row ) );
        $values = implode( '|', array_map( 'strval', (array) $row ) );
        self::check( 'el registro no guarda la IP del visitante', ! str_contains( $values, '203.0.113' ), $values );
        self::check( 'el registro no guarda el navegador del visitante', ! str_contains( $values, 'AgoMonitorSmoke' ), $values );
    }

    /**
     * Rows are written in the site timezone, so the 7 day count and the 30
     * day cleanup have to measure in the same timezone.
     */
    private static function logger_timezone(): void {
        global $wpdb;
        $wpdb->query( 'DELETE FROM ' . Logger404::table() );

        update_option( 'timezone_string', 'Pacific/Kiritimati' );
        self::insert_404( 'https://example.com/hace-un-dia', wp_date( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) );
        self::insert_404( 'https://example.com/hace-7-dias-y-6-horas', wp_date( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS - 6 * HOUR_IN_SECONDS ) );
        self::check( 'en UTC+14 la cuenta de 7 dias deja fuera lo de hace 7 dias y 6 horas', 1 === Logger404::count_recent( 7 ), (string) Logger404::count_recent( 7 ) );

        $wpdb->query( 'DELETE FROM ' . Logger404::table() );
        update_option( 'timezone_string', 'Pacific/Pago_Pago' );
        $id = self::insert_404( 'https://example.com/hace-29-dias-y-20-horas', wp_date( 'Y-m-d H:i:s', time() - 29 * DAY_IN_SECONDS - 20 * HOUR_IN_SECONDS ) );
        Logger404::clear_old();
        self::check( 'en UTC-11 la limpieza de 30 dias no borra lo de hace 29 dias y 20 horas', null !== $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . Logger404::table() . ' WHERE id = %d', $id ) ) );

        $wpdb->query( 'DELETE FROM ' . Logger404::table() );
    }

    private static function report(): void {
        $sent = [];
        $trap = static function ( $return, $atts ) use ( &$sent ) {
            $sent[] = $atts;
            return true;
        };
        $tag = static fn ( $translation, $text, $domain ) => 'ago-monitor' === $domain ? '[t]' . $translation : $translation;

        update_option( 'agomonitor_settings', [ 'report_email' => 'informe@example.com' ] );
        add_filter( 'pre_wp_mail', $trap, 10, 2 );
        add_filter( 'gettext', $tag, 10, 3 );
        $ok = Report::send();
        remove_filter( 'gettext', $tag, 10 );
        remove_filter( 'pre_wp_mail', $trap, 10 );

        $mail = $sent[0] ?? [];
        $body = (string) ( $mail['message'] ?? '' );
        self::check( 'el informe sale a la direccion configurada', $ok && 'informe@example.com' === ( $mail['to'] ?? '' ), (string) wp_json_encode( $mail['to'] ?? '' ) );
        self::check( 'el asunto pasa por traduccion', str_contains( (string) ( $mail['subject'] ?? '' ), '[t]' ) );
        self::check( 'el informe no usa emojis', ! preg_match( '/&#(9989|9888|10060|65039);|[\x{2600}-\x{27BF}\x{1F300}-\x{1FAFF}]/u', $body ) );
        self::check( 'el informe no usa raya', ! str_contains( $body, '&mdash;' ) && ! str_contains( $body, "\u{2014}" ) && ! str_contains( $body, '&ndash;' ) );
        self::check( 'los estados del informe pasan por traduccion', (bool) preg_match( '/\[t\](OK|Warning|Error)/', $body ) );
        self::check( 'el informe dice que lo mide el propio sitio', str_contains( $body, '[t]' ) && (bool) preg_match( '/measured by (the|this) site/i', $body ) );
    }

    private static function messages(): void {
        $bare = [];
        foreach ( self::php_files() as $file ) {
            foreach ( file( $file ) as $n => $line ) {
                $trim = ltrim( $line );
                if ( str_starts_with( $trim, '//' ) || str_starts_with( $trim, '*' ) || str_starts_with( $trim, '/*' ) ) {
                    continue;
                }
                if ( ! preg_match_all( "/'([A-Z][a-z]+[ :][^']*)'/", $line, $m, PREG_OFFSET_CAPTURE ) ) {
                    continue;
                }
                foreach ( $m[0] as $hit ) {
                    $before = substr( $line, 0, $hit[1] );
                    if ( ! preg_match( '/(__|_e|_x|_n|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*$/', $before ) ) {
                        $bare[] = basename( $file ) . ':' . ( $n + 1 ) . ' ' . $hit[0];
                    }
                }
            }
        }
        self::check( 'ningun texto en ingles queda fuera de una funcion de traduccion', [] === $bare, implode( ' | ', array_slice( $bare, 0, 6 ) ) );

        $js = (string) file_get_contents( AGOMONITOR_PATH . 'assets/js/admin.js' );
        self::check( 'el JS no trae textos fijos en ingles', ! preg_match( "/'[A-Z][a-z]+[ :!.?\x{2026}]/u", $js, $m1 ) && ! preg_match( '/>[A-Z][a-z]+[^<\']*</', $js, $m2 ), ( $m1[0] ?? '' ) . ' ' . ( $m2[0] ?? '' ) );
        self::check( 'el JS no imprime estados en crudo', ! str_contains( $js, 'toUpperCase()' ) && ! str_contains( $js, ", ev.status)" ) );
    }

    /**
     * The real request from the admin screen: a session cookie alone does not
     * authenticate a REST call, the wp_rest nonce is required too.
     */
    private static function http_nonce(): void {
        $admin   = self::$admin;
        $expire  = time() + HOUR_IN_SECONDS;
        $token   = WP_Session_Tokens::get_instance( $admin )->create( $expire );
        $cookies = [
            AUTH_COOKIE        => wp_generate_auth_cookie( $admin, $expire, 'auth', $token ),
            SECURE_AUTH_COOKIE => wp_generate_auth_cookie( $admin, $expire, 'secure_auth', $token ),
            LOGGED_IN_COOKIE   => wp_generate_auth_cookie( $admin, $expire, 'logged_in', $token ),
        ];
        $_COOKIE[ LOGGED_IN_COOKIE ] = $cookies[ LOGGED_IN_COOKIE ];
        wp_set_current_user( $admin );
        $nonce = wp_create_nonce( 'wp_rest' );
        wp_set_current_user( 0 );
        unset( $_COOKIE[ LOGGED_IN_COOKIE ] );

        self::insert_404( 'https://example.com/smoke-http', current_time( 'mysql' ) );

        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        $post = static function ( array $headers ) use ( $host, $cookies ) {
            return wp_remote_post( 'http://nginx/?rest_route=/agomonitor/v1/404s/clear', [
                'headers'     => array_merge( [ 'Host' => $host, 'X-Forwarded-Proto' => 'https', 'Content-Type' => 'application/json' ], $headers ),
                'cookies'     => $cookies,
                'body'        => '{}',
                'redirection' => 0,
                'timeout'     => 15,
            ] );
        };

        $r = $post( [] );
        if ( is_wp_error( $r ) ) {
            self::check( 'el sitio responde por HTTP', false, $r->get_error_message() );
        } else {
            self::check( 'con cookie y sin nonce responde 401', 401 === wp_remote_retrieve_response_code( $r ), (string) wp_remote_retrieve_response_code( $r ) );
            self::check( 'y no vacia el registro de 404', self::count_404() > 0 );

            $r = $post( [ 'X-WP-Nonce' => $nonce ] );
            self::check( 'con cookie y nonce el registro se vacia', 200 === wp_remote_retrieve_response_code( $r ) && 0 === self::count_404(), (string) wp_remote_retrieve_response_code( $r ) . ' ' . substr( wp_remote_retrieve_body( $r ), 0, 120 ) );
        }

        WP_Session_Tokens::get_instance( $admin )->destroy( $token );
    }

    private static function uninstall(): void {
        update_option( 'agomonitor_settings', [ 'enable_weekly_report' => true ] );
        wp_clear_scheduled_hook( self::HOOK );
        wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', self::HOOK );
        set_transient( 'agomonitor_disk_uploads', 1, HOUR_IN_SECONDS );
        set_transient( 'agomonitor_disk_total', 1, HOUR_IN_SECONDS );
        set_transient( 'agomonitor_404_cleanup', 1, HOUR_IN_SECONDS );

        if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
            define( 'WP_UNINSTALL_PLUGIN', plugin_basename( AGOMONITOR_FILE ) );
        }
        include AGOMONITOR_PATH . 'uninstall.php';

        self::check( 'desinstalar borra los ajustes', null === get_option( 'agomonitor_settings', null ) );
        self::check( 'desinstalar borra el evento semanal', false === wp_next_scheduled( self::HOOK ) );
        self::check( 'desinstalar borra los transients', false === get_transient( 'agomonitor_disk_uploads' ) && false === get_transient( 'agomonitor_disk_total' ) && false === get_transient( 'agomonitor_404_cleanup' ) );
        self::check( 'desinstalar borra la tabla del registro de 404', ! self::table_exists() );

        // Leave the deployed plugin as it was: active, with its table.
        do_action( 'activate_' . plugin_basename( AGOMONITOR_FILE ), false );
    }
}

AgoMonitorSmoke::run();
