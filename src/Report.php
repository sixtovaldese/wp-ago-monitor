<?php

namespace AgoLab\Monitor;

defined( 'ABSPATH' ) || exit;

class Report {

    /**
     * Send the weekly health report email.
     *
     * @return bool Whether the email was sent.
     */
    public static function send(): bool {
        $settings = Plugin::get_settings();
        $to       = $settings['report_email'] ?: get_option( 'admin_email' );

        if ( ! is_email( $to ) ) {
            return false;
        }

        $system   = Plugin::get_system_info();
        $ssl      = Checks\Ssl::check();
        $db       = Checks\Database::check();
        $disk     = Checks\Disk::check();
        $autoload = Checks\Autoload::check();
        $cron     = Checks\Cron::check();
        $updates  = Plugin::get_pending_updates();
        $count404 = Logger404::count_recent( 7 );

        $site_name = get_bloginfo( 'name' );
        $site_url  = home_url();

        /* translators: %s: site name */
        $subject = sprintf( __( '[%s] Weekly Health Report', 'ago-monitor' ), $site_name );

        $html = self::build_html( compact(
            'system', 'ssl', 'db', 'disk', 'autoload', 'cron', 'updates', 'count404', 'site_name', 'site_url'
        ) );

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: aGo Monitor <' . get_option( 'admin_email' ) . '>',
        ];

        return wp_mail( $to, $subject, $html, $headers );
    }

    /**
     * Build HTML email body.
     */
    private static function build_html( array $data ): string {
        $status_icon = fn( string $status ) => match ( $status ) {
            'ok'      => '&#9989;',
            'warning' => '&#9888;&#65039;',
            'error'   => '&#10060;',
            default   => '&#8226;',
        };

        $updates_count = count( $data['updates'] );
        $updates_list  = '';
        if ( $updates_count > 0 ) {
            foreach ( $data['updates'] as $plugin ) {
                $updates_list .= '<li>' . esc_html( $plugin['name'] ) . ', '
                    . esc_html( $plugin['current'] ) . ' &rarr; '
                    . esc_html( $plugin['new_version'] ) . '</li>';
            }
        }

        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;max-width:600px;margin:0 auto;padding:20px;color:#1d2327;background:#f9f9f9">

<div style="background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:24px;margin-bottom:16px">
    <h1 style="font-size:20px;margin:0 0 4px"><?php echo esc_html( $data['site_name'] ); ?></h1>
    <p style="color:#666;margin:0 0 20px;font-size:13px"><?php esc_html_e( 'Weekly Health Report', 'ago-monitor' ); ?> &mdash; <?php echo esc_html( wp_date( 'F j, Y' ) ); ?></p>

    <table style="width:100%;border-collapse:collapse;font-size:14px">
        <tr style="border-bottom:1px solid #eee">
            <td style="padding:10px 8px;font-weight:600"><?php esc_html_e( 'PHP Version', 'ago-monitor' ); ?></td>
            <td style="padding:10px 8px"><?php echo esc_html( $data['system']['php_version'] ); ?></td>
        </tr>
        <tr style="border-bottom:1px solid #eee">
            <td style="padding:10px 8px;font-weight:600"><?php esc_html_e( 'WordPress Version', 'ago-monitor' ); ?></td>
            <td style="padding:10px 8px"><?php echo esc_html( $data['system']['wp_version'] ); ?></td>
        </tr>
        <tr style="border-bottom:1px solid #eee">
            <td style="padding:10px 8px;font-weight:600"><?php esc_html_e( 'SSL Certificate', 'ago-monitor' ); ?></td>
            <td style="padding:10px 8px"><?php echo wp_kses_post( $status_icon( $data['ssl']['status'] ) ); ?> <?php echo esc_html( $data['ssl']['value'] ); ?></td>
        </tr>
        <tr style="border-bottom:1px solid #eee">
            <td style="padding:10px 8px;font-weight:600"><?php esc_html_e( 'Database Size', 'ago-monitor' ); ?></td>
            <td style="padding:10px 8px"><?php echo esc_html( $data['db']['size'] ); ?> (<?php echo (int) $data['db']['table_count']; ?> <?php esc_html_e( 'tables', 'ago-monitor' ); ?>)</td>
        </tr>
        <tr style="border-bottom:1px solid #eee">
            <td style="padding:10px 8px;font-weight:600"><?php esc_html_e( 'Disk Usage', 'ago-monitor' ); ?></td>
            <td style="padding:10px 8px"><?php esc_html_e( 'Uploads:', 'ago-monitor' ); ?> <?php echo esc_html( $data['disk']['uploads_size'] ); ?> &mdash; <?php esc_html_e( 'Total:', 'ago-monitor' ); ?> <?php echo esc_html( $data['disk']['total_size'] ); ?></td>
        </tr>
        <tr style="border-bottom:1px solid #eee">
            <td style="padding:10px 8px;font-weight:600"><?php esc_html_e( 'Autoloaded Options', 'ago-monitor' ); ?></td>
            <td style="padding:10px 8px"><?php echo wp_kses_post( $status_icon( $data['autoload']['status'] ) ); ?> <?php echo esc_html( $data['autoload']['size'] ); ?> (<?php echo esc_html( $data['autoload']['size_kb'] ); ?> KB)</td>
        </tr>
        <tr style="border-bottom:1px solid #eee">
            <td style="padding:10px 8px;font-weight:600"><?php esc_html_e( 'Cron Jobs', 'ago-monitor' ); ?></td>
            <td style="padding:10px 8px"><?php echo wp_kses_post( $status_icon( $data['cron']['status'] ) ); ?> <?php echo (int) $data['cron']['total']; ?> <?php esc_html_e( 'events', 'ago-monitor' ); ?><?php if ( $data['cron']['overdue'] > 0 ) : ?> (<?php echo (int) $data['cron']['overdue']; ?> <?php esc_html_e( 'overdue', 'ago-monitor' ); ?>)<?php endif; ?></td>
        </tr>
        <tr style="border-bottom:1px solid #eee">
            <td style="padding:10px 8px;font-weight:600"><?php esc_html_e( 'Pending Updates', 'ago-monitor' ); ?></td>
            <td style="padding:10px 8px"><?php echo (int) $updates_count; ?> <?php esc_html_e( 'plugins', 'ago-monitor' ); ?></td>
        </tr>
        <tr>
            <td style="padding:10px 8px;font-weight:600"><?php esc_html_e( '404 Errors (7 days)', 'ago-monitor' ); ?></td>
            <td style="padding:10px 8px"><?php echo (int) $data['count404']; ?></td>
        </tr>
    </table>

    <?php if ( $updates_count > 0 ) : ?>
    <div style="margin-top:16px;padding:12px;background:#fff8e1;border-radius:4px;border:1px solid #f0d060">
        <strong><?php esc_html_e( 'Plugins with pending updates:', 'ago-monitor' ); ?></strong>
        <ul style="margin:8px 0 0;padding-left:20px"><?php echo wp_kses_post( $updates_list ); ?></ul>
    </div>
    <?php endif; ?>
</div>

<p style="text-align:center;font-size:12px;color:#999">
    <?php
    echo wp_kses_post(
        sprintf(
            /* translators: %s: link to site dashboard */
            __( 'Sent by aGo Monitor from %s', 'ago-monitor' ),
            '<a href="' . esc_url( admin_url() ) . '" style="color:#2271b1">' . esc_html( $data['site_url'] ) . '</a>'
        )
    );
    ?>
</p>

</body>
</html>
        <?php
        return ob_get_clean();
    }
}
