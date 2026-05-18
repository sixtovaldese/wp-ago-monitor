<?php

namespace AgoLab\Monitor\Checks;

defined( 'ABSPATH' ) || exit;

class Ssl {

    /**
     * Check SSL certificate status.
     *
     * @return array{status: string, label: string, value: string, days?: int}
     */
    public static function check(): array {
        $url = home_url();

        if ( ! str_starts_with( $url, 'https' ) ) {
            return [
                'status' => 'warning',
                'label'  => __( 'SSL Certificate', 'ago-monitor' ),
                'value'  => __( 'Not HTTPS', 'ago-monitor' ),
            ];
        }

        $host    = wp_parse_url( $url, PHP_URL_HOST );
        $context = stream_context_create( [ 'ssl' => [ 'capture_peer_cert' => true ] ] );
        $client  = @stream_socket_client(
            "ssl://{$host}:443",
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ( ! $client ) {
            return [
                'status' => 'error',
                'label'  => __( 'SSL Certificate', 'ago-monitor' ),
                'value'  => __( 'Cannot connect', 'ago-monitor' ),
            ];
        }

        $params  = stream_context_get_params( $client );
        $cert    = openssl_x509_parse( $params['options']['ssl']['peer_certificate'] );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- $client is a stream socket from stream_socket_client(), not a file handle; WP_Filesystem does not apply.
        fclose( $client );

        $expires = $cert['validTo_time_t'] ?? 0;
        $days    = max( 0, (int) ( ( $expires - time() ) / 86400 ) );
        $status  = $days > 30 ? 'ok' : ( $days > 7 ? 'warning' : 'error' );

        return [
            'status' => $status,
            'label'  => __( 'SSL Certificate', 'ago-monitor' ),
            /* translators: %d: number of days until expiry */
            'value'  => sprintf( __( 'Expires in %d days', 'ago-monitor' ), $days ),
            'days'   => $days,
        ];
    }
}
