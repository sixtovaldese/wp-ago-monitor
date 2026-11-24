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
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY (id),
            KEY created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Log a 404 request (hooked to template_redirect).
     *
     * Only the address, the referrer and the time are kept: nothing that
     * identifies the visitor, such as the IP address or the browser.
     */
    public static function maybe_log(): void {
        if ( ! is_404() ) {
            return;
        }

        // Skip wp-cron and REST requests.
        if ( defined( 'DOING_CRON' ) || defined( 'REST_REQUEST' ) ) {
            return;
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- writes to the plugin's own log table.
        $wpdb->insert(
            self::table(),
            [
                'url'        => esc_url_raw( self::get_current_url() ),
                'referrer'   => esc_url_raw( wp_get_referer() ?: '' ),
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s' ]
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

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own log table, read live.
        $total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own log table, read live.
        $entries = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT id, url, referrer, created_at FROM %i ORDER BY created_at DESC LIMIT %d OFFSET %d',
                $table,
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
     * The moment N days ago, written like created_at: in the site timezone.
     */
    private static function local_cutoff( int $days ): string {
        return wp_date( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
    }

    /**
     * Get count of 404s in the last N days.
     */
    public static function count_recent( int $days = 7 ): int {
        global $wpdb;

        $table = self::table();
        $since = self::local_cutoff( $days );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own log table, read live.
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE created_at >= %s',
                $table,
                $since
            )
        );
    }

    /**
     * Clear all 404 entries.
     */
    public static function clear_all(): void {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own log table.
        $wpdb->query( $wpdb->prepare( 'TRUNCATE TABLE %i', self::table() ) );
    }

    /**
     * Clear entries older than 30 days.
     */
    public static function clear_old(): void {
        global $wpdb;

        $table    = self::table();
        $cutoff   = self::local_cutoff( 30 );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own log table.
        $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM %i WHERE created_at < %s',
                $table,
                $cutoff
            )
        );
    }

    /**
     * Get the current request URL.
     */
    private static function get_current_url(): string {
        $scheme = is_ssl() ? 'https' : 'http';
        $host   = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) );
        $uri    = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
        return $scheme . '://' . $host . $uri;
    }
}
