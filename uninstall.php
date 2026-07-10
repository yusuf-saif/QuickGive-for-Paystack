<?php
/**
 * Uninstall handler.
 *
 * @package QuickDonate
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'quickdonate_settings' );
delete_option( 'quickdonate_db_version' );

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional schema cleanup during uninstall.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}quickdonate_donations" );
