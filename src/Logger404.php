<?php

namespace AgoLab\Monitor;

defined( 'ABSPATH' ) || exit;

class Logger404 {

    /**
     * Get the 404 log table name.
     */
    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'agomonitor_404s';
    }

    /**
     * Create the 404 log table via dbDelta.
     */
    public static function create_table(): void {
        global $wpdb;

        $table   = self::table();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            url varchar(2048) NOT NULL DEFAULT '',
            referrer varchar(2048) NOT NULL DEFAULT '',
            user_agent varchar(512) NOT NULL DEFAULT '',
            ip varchar(45) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (id),
            KEY created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Log a 404 request (hooked to template_redirect).
     */
    public static function maybe_log(): void {
        if ( ! is_404() ) {
            return;
        }

        // Skip bots / wp-cron / REST requests.
        if ( defined( 'DOING_CRON' ) || defined( 'REST_REQUEST' ) ) {
            return;
        }

        global $wpdb;

        $wpdb->insert(
            self::table(),
            [
                'url'        => esc_url_raw( self::get_current_url() ),
                'referrer'   => esc_url_raw( wp_get_referer() ?: '' ),
                'user_agent' => sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
                'ip'         => self::get_ip(),
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%s' ]
        );

        // Auto-cleanup: delete entries older than 30 days (run once per day via transient).
        if ( false === get_transient( 'agomonitor_404_cleanup' ) ) {
            self::clear_old();
            set_transient( 'agomonitor_404_cleanup', 1, DAY_IN_SECONDS );
        }
    }

    /**
     * Get paginated 404 entries.
     *
     * @return array{entries: array, total: int, pages: int}
     */
    public static function get_entries( int $per_page = 20, int $page = 1 ): array {
        global $wpdb;

        $table  = self::table();
        $offset = ( $page - 1 ) * $per_page;

        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $entries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, url, referrer, user_agent, ip, created_at FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        return [
            'entries' => $entries ?: [],
            'total'   => $total,
            'pages'   => (int) ceil( $total / $per_page ),
        ];
    }

    /**
     * Get count of 404s in the last N days.
     */
    public static function count_recent( int $days = 7 ): int {
        global $wpdb;

        $table = self::table();
        $since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $since
            )
        );
    }

    /**
     * Clear all 404 entries.
     */
    public static function clear_all(): void {
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE " . self::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Clear entries older than 30 days.
     */
    public static function clear_old(): void {
        global $wpdb;

        $table    = self::table();
        $cutoff   = gmdate( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $cutoff
            )
        );
    }

    /**
     * Get the current request URL.
     */
    private static function get_current_url(): string {
        $scheme = is_ssl() ? 'https' : 'http';
        $host   = sanitize_text_field( $_SERVER['HTTP_HOST'] ?? '' );
        $uri    = sanitize_text_field( $_SERVER['REQUEST_URI'] ?? '' );
        return $scheme . '://' . $host . $uri;
    }

    /**
     * Get client IP (anonymize last octet for privacy).
     */
    private static function get_ip(): string {
        $ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
        // Anonymize last octet.
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            $ip = preg_replace( '/\.\d+$/', '.0', $ip );
        } elseif ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            $ip = preg_replace( '/:[^:]+$/', ':0', $ip );
        }
        return $ip;
    }
}
