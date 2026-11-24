<?php

namespace AgoLab\Monitor\Checks;

use AgoLab\Monitor\Plugin;

defined( 'ABSPATH' ) || exit;

class Autoload {

    /**
     * Check total size of autoloaded options.
     *
     * @return array{status: string, label: string, size: string, size_bytes: int, size_kb: float}
     */
    public static function check(): array {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a live measurement; no API returns the autoloaded size.
        $bytes = (int) $wpdb->get_var(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ('yes', 'on', 'auto')"
        );

        $kb       = round( $bytes / 1024, 1 );
        $settings = Plugin::get_settings();
        $warn_kb  = $settings['autoload_warn_kb'] ?? 800;

        if ( $kb > 1500 ) {
            $status = 'error';
        } elseif ( $kb > $warn_kb ) {
            $status = 'warning';
        } else {
            $status = 'ok';
        }

        return [
            'status'     => $status,
            'label'      => __( 'Autoloaded Options', 'ago-monitor' ),
            'size'       => size_format( $bytes, 1 ),
            'size_bytes' => $bytes,
            'size_kb'    => $kb,
        ];
    }
}
