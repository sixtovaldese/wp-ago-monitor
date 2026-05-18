<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Delete option.
delete_option( 'ago_monitor_settings' );

// Delete transients.
delete_transient( 'ago_monitor_disk_uploads' );
delete_transient( 'ago_monitor_disk_total' );

// Drop 404 table.
$table = $wpdb->prefix . 'ago_monitor_404s';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Unschedule cron.
$timestamp = wp_next_scheduled( 'ago_monitor_weekly_report' );
if ( $timestamp ) {
    wp_unschedule_event( $timestamp, 'ago_monitor_weekly_report' );
}
