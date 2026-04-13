<?php
/**
 * Donation logging — creates and queries the custom DB table.
 *
 * @package QuickGive_For_Paystack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the donation log stored in a custom DB table.
 */
class QuickGive_Logger {

	/**
	 * Table name (without prefix).
	 */
	const TABLE = 'quickgive_donations';

	/**
	 * Create the custom table on plugin activation.
	 * Called via register_activation_hook in the main file.
	 */
	public static function create_table() {
		global $wpdb;

		$table      = $wpdb->prefix . self::TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			reference     VARCHAR(100)        NOT NULL,
			donor_email   VARCHAR(200)        NOT NULL,
			amount        DECIMAL(12,2)       NOT NULL DEFAULT 0.00,
			currency      VARCHAR(10)         NOT NULL DEFAULT 'NGN',
			status        VARCHAR(20)         NOT NULL DEFAULT 'pending',
			created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY   reference (reference),
			KEY          status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'quickgive_db_version', QUICKGIVE_VERSION );
	}

	/**
	 * Insert a new donation record (or update an existing one by reference).
	 *
	 * @param string $reference   Paystack transaction reference.
	 * @param string $email       Donor email.
	 * @param float  $amount      Amount in the smallest currency unit (e.g. kobo).
	 * @param string $currency    ISO currency code.
	 * @param string $status      'pending', 'success', or 'failed'.
	 * @return int|false Inserted/updated row ID, or false on failure.
	 */
	public static function log( $reference, $email, $amount, $currency, $status = 'pending' ) {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$existing = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE reference = %s LIMIT 1", $reference )
		);

		if ( $existing ) {
			$wpdb->update(
				$table,
				array(
					'status'      => sanitize_text_field( $status ),
					'donor_email' => sanitize_email( $email ),
					'amount'      => floatval( $amount ),
					'currency'    => sanitize_text_field( $currency ),
				),
				array( 'id' => (int) $existing ),
				array( '%s', '%s', '%f', '%s' ),
				array( '%d' )
			);
			return (int) $existing;
		}

		$result = $wpdb->insert(
			$table,
			array(
				'reference'   => sanitize_text_field( $reference ),
				'donor_email' => sanitize_email( $email ),
				'amount'      => floatval( $amount ),
				'currency'    => sanitize_text_field( $currency ),
				'status'      => sanitize_text_field( $status ),
			),
			array( '%s', '%s', '%f', '%s', '%s' )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Count total donation records.
	 *
	 * @param string $status Optional status filter.
	 * @return int Total row count.
	 */
	public static function get_count( $status = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;

		if ( ! empty( $status ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", sanitize_text_field( $status ) )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Retrieve donation records.
	 *
	 * @param array $args {
	 *     Optional query args.
	 *     @type int    $limit   Number of records. Default 50.
	 *     @type string $status  Filter by status.
	 *     @type int    $offset  Pagination offset. Default 0.
	 * }
	 * @return array Array of stdClass row objects.
	 */
	public static function get_donations( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'limit'  => 50,
			'status' => '',
			'offset' => 0,
		);
		$args = wp_parse_args( $args, $defaults );

		$table  = $wpdb->prefix . self::TABLE;
		$where  = '';
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where    = 'WHERE status = %s ';
			$values[] = sanitize_text_field( $args['status'] );
		}

		$values[] = absint( $args['limit'] );
		$values[] = absint( $args['offset'] );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} {$where}ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$values
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $rows ? $rows : array();
	}
}
