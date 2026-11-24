<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Delete option.
delete_option( 'agomonitor_settings' );

// Delete transients.
delete_transient( 'agomonitor_disk_uploads' );
delete_transient( 'agomonitor_disk_total' );
delete_transient( 'agomonitor_404_cleanup' );

// Drop 404 table.
$agomonitor_table = $wpdb->prefix . 'agomonitor_404s'; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $agomonitor_table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

// Unschedule cron.
wp_clear_scheduled_hook( 'agomonitor_weekly_report' );
