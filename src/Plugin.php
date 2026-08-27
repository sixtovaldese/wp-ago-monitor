<?php

namespace AgoLab\Monitor;

defined( 'ABSPATH' ) || exit;

class Plugin {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ $this, 'load_textdomain' ] );
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // 404 Logger.
        $settings = self::get_settings();
        if ( $settings['enable_404_logging'] ) {
            add_action( 'template_redirect', [ Logger404::class, 'maybe_log' ] );
        }

        // Weekly report cron handler.
        add_action( 'agomonitor_weekly_report', [ Report::class, 'send' ] );
    }

    /* ───── Textdomain ───── */

    public function load_textdomain(): void {
        load_plugin_textdomain( 'ago-monitor', false, dirname( plugin_basename( AGOMONITOR_FILE ) ) . '/languages' );
    }

    /* ───── Admin menu (smart pattern) ───── */

    public function register_admin_menu(): void {
        if ( empty( $GLOBALS['admin_page_hooks']['agolab-tools'] ) ) {
            add_menu_page(
                __( 'aGo Tools', 'ago-monitor' ),
                __( 'aGo Tools', 'ago-monitor' ),
                'manage_options',
                'agolab-tools',
                '__return_null',
                'dashicons-hammer',
                81
            );
        }

        add_submenu_page(
            'agolab-tools',
            __( 'aGo Monitor', 'ago-monitor' ),
            __( 'Monitor', 'ago-monitor' ),
            'manage_options',
            'agomonitor',
            [ Admin\Page::class, 'render' ]
        );

        remove_submenu_page( 'agolab-tools', 'agolab-tools' );
    }

    /* ───── REST routes ───── */

    public function register_rest_routes(): void {
        $admin_permission = function () {
            return current_user_can( 'manage_options' );
        };

        // GET all checks.
        register_rest_route( 'ago-monitor/v1', '/checks', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_get_checks' ],
            'permission_callback' => $admin_permission,
        ] );

        // GET 404 log.
        register_rest_route( 'ago-monitor/v1', '/404s', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_get_404s' ],
            'permission_callback' => $admin_permission,
            'args'                => [
                'page'     => [ 'default' => 1, 'sanitize_callback' => 'absint' ],
                'per_page' => [ 'default' => 20, 'sanitize_callback' => 'absint' ],
            ],
        ] );

        // POST clear 404 log.
        register_rest_route( 'ago-monitor/v1', '/404s/clear', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_clear_404s' ],
            'permission_callback' => $admin_permission,
        ] );

        // GET cron jobs.
        register_rest_route( 'ago-monitor/v1', '/cron', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_get_cron' ],
            'permission_callback' => $admin_permission,
        ] );

        // POST send report now.
        register_rest_route( 'ago-monitor/v1', '/report/send', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_send_report' ],
            'permission_callback' => $admin_permission,
        ] );

        // GET / POST settings.
        register_rest_route( 'ago-monitor/v1', '/settings', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_get_settings' ],
                'permission_callback' => $admin_permission,
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_save_settings' ],
                'permission_callback' => $admin_permission,
            ],
        ] );
    }

    /* ───── REST handlers ───── */

    public function handle_get_checks(): \WP_REST_Response {
        $checks = [
            'system'   => self::get_system_info(),
            'ssl'      => Checks\Ssl::check(),
            'database' => Checks\Database::check(),
            'disk'     => Checks\Disk::check(),
            'autoload' => Checks\Autoload::check(),
            'cron'     => Checks\Cron::check(),
            'updates'  => self::get_pending_updates(),
        ];
        return new \WP_REST_Response( $checks );
    }

    public function handle_get_404s( \WP_REST_Request $request ): \WP_REST_Response {
        $page     = max( 1, $request->get_param( 'page' ) );
        $per_page = min( 100, max( 1, $request->get_param( 'per_page' ) ) );
        $data     = Logger404::get_entries( $per_page, $page );
        return new \WP_REST_Response( $data );
    }

    public function handle_clear_404s(): \WP_REST_Response {
        Logger404::clear_all();
        return new \WP_REST_Response( [ 'cleared' => true ] );
    }

    public function handle_get_cron(): \WP_REST_Response {
        return new \WP_REST_Response( Checks\Cron::get_all_events() );
    }

    public function handle_send_report(): \WP_REST_Response {
        $sent = Report::send();
        return new \WP_REST_Response( [ 'sent' => $sent ] );
    }

    public function handle_get_settings(): \WP_REST_Response {
        return new \WP_REST_Response( self::get_settings() );
    }

    public function handle_save_settings( \WP_REST_Request $request ): \WP_REST_Response {
        $input    = $request->get_json_params();
        $settings = [
            'enable_404_logging'  => ! empty( $input['enable_404_logging'] ),
            'enable_weekly_report' => ! empty( $input['enable_weekly_report'] ),
            'report_email'        => sanitize_email( $input['report_email'] ?? '' ),
            'autoload_warn_kb'    => max( 100, absint( $input['autoload_warn_kb'] ?? 800 ) ),
        ];

        update_option( 'agomonitor_settings', $settings );

        // Manage cron schedule.
        $timestamp = wp_next_scheduled( 'agomonitor_weekly_report' );
        if ( $settings['enable_weekly_report'] && ! $timestamp ) {
            wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'agomonitor_weekly_report' );
        } elseif ( ! $settings['enable_weekly_report'] && $timestamp ) {
            wp_unschedule_event( $timestamp, 'agomonitor_weekly_report' );
        }

        return new \WP_REST_Response( [ 'saved' => true, 'settings' => $settings ] );
    }

    /* ───── Assets ───── */

    public function enqueue_assets( string $hook ): void {
        if ( ! str_ends_with( $hook, '_page_agomonitor' ) ) {
            return;
        }

        wp_enqueue_style(
            'agomonitor-admin',
            AGOMONITOR_URL . 'assets/css/admin.css',
            [],
            AGOMONITOR_VERSION
        );

        wp_enqueue_script(
            'agomonitor-admin',
            AGOMONITOR_URL . 'assets/js/admin.js',
            [],
            AGOMONITOR_VERSION,
            true
        );

        wp_localize_script( 'agomonitor-admin', 'agomonitorData', [
            'restUrl'  => rest_url( 'ago-monitor/v1' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'settings' => self::get_settings(),
            'i18n'     => [
                'loading'   => __( 'Loading...', 'ago-monitor' ),
                'saved'     => __( 'Settings saved.', 'ago-monitor' ),
                'error'     => __( 'An error occurred.', 'ago-monitor' ),
                'cleared'   => __( '404 log cleared.', 'ago-monitor' ),
                'sending'   => __( 'Sending report...', 'ago-monitor' ),
                'sent'      => __( 'Report sent successfully.', 'ago-monitor' ),
                'noEntries' => __( 'No 404 entries found.', 'ago-monitor' ),
            ],
        ] );
    }

    /* ───── Helpers ───── */

    /** @return array<string, mixed> */
    public static function get_settings(): array {
        $defaults = [
            'enable_404_logging'   => true,
            'enable_weekly_report' => true,
            'report_email'         => '',
            'autoload_warn_kb'     => 800,
        ];
        $saved = get_option( 'agomonitor_settings', [] );
        return wp_parse_args( $saved, $defaults );
    }

    /** @return array<string, string> */
    public static function get_system_info(): array {
        global $wpdb;
        return [
            'php_version' => PHP_VERSION,
            'wp_version'  => get_bloginfo( 'version' ),
            'db_version'  => $wpdb->db_version(),
        ];
    }

    /** @return array<int, array<string, string>> */
    public static function get_pending_updates(): array {
        if ( ! function_exists( 'get_plugin_updates' ) ) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        wp_update_plugins();
        $updates = get_plugin_updates();
        $list    = [];

        foreach ( $updates as $file => $data ) {
            $list[] = [
                'name'        => $data->Name ?? $file,
                'current'     => $data->Version ?? '',
                'new_version' => $data->update->new_version ?? '',
            ];
        }

        return $list;
    }
}
