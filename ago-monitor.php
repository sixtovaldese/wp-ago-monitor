<?php
/**
 * Plugin Name: aGo Monitor
 * Plugin URI:  https://ago.cl/herramientas/
 * Description: Site health monitoring dashboard with SSL checks, disk usage, database size, cron inspector, autoloaded options, 404 logger and weekly email reports.
 * Version:     1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author:      aGo Lab
 * Author URI:  https://ago.cl/
 * License:     GPL-2.0-or-later
 * Text Domain: ago-monitor
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'AGOMONITOR_VERSION', '1.0.0' );
define( 'AGOMONITOR_FILE', __FILE__ );
define( 'AGOMONITOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'AGOMONITOR_URL', plugin_dir_url( __FILE__ ) );

// PSR-4 Autoloader
spl_autoload_register( function ( string $class ): void {
    $prefix = 'AgoLab\\Monitor\\';
    if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
        return;
    }
    $relative = substr( $class, strlen( $prefix ) );
    $file     = AGOMONITOR_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

// Activation / deactivation hooks, must be registered at file-include time, not inside plugins_loaded.
register_activation_hook( __FILE__, function () {
    require_once AGOMONITOR_PATH . 'src/Logger404.php';
    AgoLab\Monitor\Logger404::create_table();

    // Schedule weekly report cron if enabled.
    require_once AGOMONITOR_PATH . 'src/Plugin.php';
    $settings = AgoLab\Monitor\Plugin::get_settings();
    if ( $settings['enable_weekly_report'] && ! wp_next_scheduled( 'agomonitor_weekly_report' ) ) {
        wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'agomonitor_weekly_report' );
    }
} );

register_deactivation_hook( __FILE__, function () {
    $timestamp = wp_next_scheduled( 'agomonitor_weekly_report' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'agomonitor_weekly_report' );
    }
} );

// Boot
add_action( 'plugins_loaded', [ AgoLab\Monitor\Plugin::class, 'instance' ] );
