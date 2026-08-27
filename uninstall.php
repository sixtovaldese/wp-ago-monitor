<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Delete option.
delete_option( 'agomonitor_settings' );

// Delete transients.
delete_transient( 'agomonitor_disk_uploads' );
delete_transient( 'agomonitor_disk_total' );

// Drop 404 table.
$table = $wpdb->prefix . 'agomonitor_404s';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Unschedule cron.
$timestamp = wp_next_scheduled( 'agomonitor_weekly_report' );
if ( $timestamp ) {
    wp_unschedule_event( $timestamp, 'agomonitor_weekly_report' );
}
