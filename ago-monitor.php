<?php
/**
 * Plugin Name: aGo Monitor
 * Plugin URI:  https://ago.cl/herramientas/
 * Description: Site health monitoring dashboard with SSL checks, disk usage, database size, cron inspector, autoloaded options, 404 logger and weekly email reports.
 * Version:     1.0.0
 * Requires PHP: 8.1
 * Author:      aGo Lab
 * Author URI:  https://ago.cl/
 * License:     GPL-2.0-or-later
 * Text Domain: ago-monitor
 */

defined( 'ABSPATH' ) || exit;

define( 'AGO_MONITOR_VERSION', '1.0.0' );
define( 'AGO_MONITOR_FILE', __FILE__ );
define( 'AGO_MONITOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'AGO_MONITOR_URL', plugin_dir_url( __FILE__ ) );

// PSR-4 Autoloader
spl_autoload_register( function ( string $class ): void {
    $prefix = 'AgoLab\\Monitor\\';
    if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
        return;
    }
    $relative = substr( $class, strlen( $prefix ) );
    $file     = AGO_MONITOR_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

// Activation / deactivation hooks, must be registered at file-include time, not inside plugins_loaded.
register_activation_hook( __FILE__, function () {
    require_once AGO_MONITOR_PATH . 'src/Logger404.php';
    AgoLab\Monitor\Logger404::create_table();

    // Schedule weekly report cron if enabled.
    require_once AGO_MONITOR_PATH . 'src/Plugin.php';
    $settings = AgoLab\Monitor\Plugin::get_settings();
    if ( $settings['enable_weekly_report'] && ! wp_next_scheduled( 'ago_monitor_weekly_report' ) ) {
        wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'ago_monitor_weekly_report' );
    }
} );

register_deactivation_hook( __FILE__, function () {
    $timestamp = wp_next_scheduled( 'ago_monitor_weekly_report' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'ago_monitor_weekly_report' );
    }
} );

// Boot
add_action( 'plugins_loaded', [ AgoLab\Monitor\Plugin::class, 'instance' ] );
