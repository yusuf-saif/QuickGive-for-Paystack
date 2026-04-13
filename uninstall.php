<?php
/**
 * Uninstall handler for QuickGive for Paystack.
 *
 * Fires when the plugin is deleted from the WordPress dashboard.
 * Removes all plugin data: settings and the custom donations table.
 *
 * @package QuickGive_For_Paystack
 */

// Bail if not called by WordPress uninstall process.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin settings.
delete_option( 'quickgive_settings' );
delete_option( 'quickgive_db_version' );

// Drop the custom donations table.
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}quickgive_donations" );
