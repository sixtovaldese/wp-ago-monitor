<?php

namespace AgoLab\Monitor\Checks;

defined( 'ABSPATH' ) || exit;

class Database {

    /**
     * Check database size and table count.
     *
     * @return array{status: string, label: string, size: string, size_bytes: int, table_count: int}
     */
    public static function check(): array {
        global $wpdb;

        $results     = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );
        $total_bytes = 0;
        $table_count = 0;

        if ( is_array( $results ) ) {
            foreach ( $results as $row ) {
                $total_bytes += ( (int) ( $row['Data_length'] ?? 0 ) ) + ( (int) ( $row['Index_length'] ?? 0 ) );
                $table_count++;
            }
        }

        return [
            'status'      => 'ok',
            'label'       => __( 'Database', 'ago-monitor' ),
            'size'        => size_format( $total_bytes, 2 ),
            'size_bytes'  => $total_bytes,
            'table_count' => $table_count,
        ];
    }
}
