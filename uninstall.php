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

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}quickdonate_donations" );
