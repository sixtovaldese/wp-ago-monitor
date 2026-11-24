<?php

namespace AgoLab\Monitor\Checks;

defined( 'ABSPATH' ) || exit;

class Disk {

    /**
     * Check disk usage (uploads + total WP).
     * Results are cached in a transient for 1 hour.
     *
     * @return array{status: string, label: string, uploads_size: string, uploads_bytes: int, total_size: string, total_bytes: int}
     */
    public static function check(): array {
        $uploads_bytes = get_transient( 'agomonitor_disk_uploads' );
        $total_bytes   = get_transient( 'agomonitor_disk_total' );

        if ( false === $uploads_bytes ) {
            $upload_dir    = wp_upload_dir();
            $uploads_bytes = self::dir_size( $upload_dir['basedir'] );
            set_transient( 'agomonitor_disk_uploads', $uploads_bytes, HOUR_IN_SECONDS );
        }

        if ( false === $total_bytes ) {
            $total_bytes = self::dir_size( ABSPATH );
            set_transient( 'agomonitor_disk_total', $total_bytes, HOUR_IN_SECONDS );
        }

        return [
            'status'        => 'ok',
            'label'         => __( 'Folder Sizes', 'ago-monitor' ),
            'uploads_size'  => size_format( (int) $uploads_bytes, 2 ),
            'uploads_bytes' => (int) $uploads_bytes,
            'total_size'    => size_format( (int) $total_bytes, 2 ),
            'total_bytes'   => (int) $total_bytes,
        ];
    }

    /**
     * Recursively calculate directory size.
     */
    private static function dir_size( string $path ): int {
        $size = 0;

        if ( ! is_dir( $path ) ) {
            return 0;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $path, \RecursiveDirectoryIterator::SKIP_DOTS ),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ( $iterator as $file ) {
            if ( $file->isFile() ) {
                $size += $file->getSize();
            }
        }

        return $size;
    }
}
