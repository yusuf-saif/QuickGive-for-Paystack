<?php
/**
 * Donation logging — creates and queries the custom DB table.
 *
 * v1.1 changes:
 *   - Added `amount_type` column ('preset' | 'custom').
 *   - Added `maybe_upgrade_table()` for non-destructive ALTER on existing installs.
 *   - Added `get_summary()` returning overview metrics for the dashboard.
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
	 * Table name (without WordPress prefix).
	 */
	const TABLE = 'quickgive_donations';

	/**
	 * Current schema version — bump when the table definition changes.
	 */
	const DB_VERSION = '1.1.0';

	// -------------------------------------------------------------------------
	// Schema management
	// -------------------------------------------------------------------------

	/**
	 * Create or update the custom table.
	 *
	 * Called on plugin activation AND on `plugins_loaded` (via `maybe_upgrade_table`)
	 * so that existing installs receive the v1.1 schema additions.
	 */
	public static function create_table() {
		global $wpdb;

		$table           = $wpdb->prefix . self::TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		// Full table definition including v1.1 columns.
		$sql = "CREATE TABLE {$table} (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			reference     VARCHAR(100)        NOT NULL,
			donor_email   VARCHAR(200)        NOT NULL,
			amount        DECIMAL(12,2)       NOT NULL DEFAULT 0.00,
			currency      VARCHAR(10)         NOT NULL DEFAULT 'NGN',
			amount_type   VARCHAR(10)         NOT NULL DEFAULT 'preset',
			status        VARCHAR(20)         NOT NULL DEFAULT 'pending',
			created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY   reference (reference),
			KEY          status (status),
			KEY          amount_type (amount_type)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql ); // dbDelta is idempotent — safe to call on existing tables.

		update_option( 'quickgive_db_version', self::DB_VERSION );
	}

	/**
	 * Run a lightweight ALTER for existing installs that are missing the
	 * `amount_type` column introduced in v1.1.
	 *
	 * Safe to call on every request — bails immediately if the column exists
	 * or if the DB version is already current.
	 *
	 * @return void
	 */
	public static function maybe_upgrade_table() {
		if ( get_option( 'quickgive_db_version' ) === self::DB_VERSION ) {
			return; // Already up-to-date, skip.
		}

		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;

		// Check whether the column already exists (avoids redundant ALTER).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$col = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s LIMIT 1',
				DB_NAME,
				$table,
				'amount_type'
			)
		);

		if ( ! $col ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN amount_type VARCHAR(10) NOT NULL DEFAULT 'preset' AFTER currency, ADD KEY amount_type (amount_type)" );
		}

		// Re-run dbDelta to pick up any other structural changes.
		self::create_table();
	}

	// -------------------------------------------------------------------------
	// Write operations
	// -------------------------------------------------------------------------

	/**
	 * Insert a new donation record, or update an existing one by reference.
	 *
	 * @param string $reference   Paystack transaction reference.
	 * @param string $email       Donor email address.
	 * @param float  $amount      Amount in main currency units (e.g. Naira, not kobo).
	 * @param string $currency    ISO currency code.
	 * @param string $status      'pending', 'success', or 'failed'.
	 * @param string $amount_type 'preset' or 'custom'. Default 'preset'.
	 * @return int|false Row ID on success, false on failure.
	 */
	public static function log( $reference, $email, $amount, $currency, $status = 'pending', $amount_type = 'preset' ) {
		global $wpdb;

		$table       = $wpdb->prefix . self::TABLE;
		$amount_type = in_array( $amount_type, array( 'preset', 'custom' ), true ) ? $amount_type : 'preset';

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
					'amount_type' => $amount_type,
				),
				array( 'id' => (int) $existing ),
				array( '%s', '%s', '%f', '%s', '%s' ),
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
				'amount_type' => $amount_type,
				'status'      => sanitize_text_field( $status ),
			),
			array( '%s', '%s', '%f', '%s', '%s', '%s' )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		return $result ? $wpdb->insert_id : false;
	}

	// -------------------------------------------------------------------------
	// Read operations
	// -------------------------------------------------------------------------

	/**
	 * Count total donation records, optionally filtered by status.
	 *
	 * @param string $status Optional status filter ('success', 'failed', 'pending').
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
	 * Return per-status row counts in a single query.
	 *
	 * Used by the donation log filter tabs to avoid one COUNT query per status.
	 *
	 * @return array {
	 *     @type int $total   All rows.
	 *     @type int $success Successful donations.
	 *     @type int $failed  Failed donations.
	 *     @type int $pending Pending donations.
	 * }
	 */
	public static function get_status_counts() {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			"SELECT status, COUNT(*) AS cnt FROM {$table} GROUP BY status"
		);

		$counts = array( 'total' => 0, 'success' => 0, 'failed' => 0, 'pending' => 0 );
		foreach ( $rows as $row ) {
			if ( array_key_exists( $row->status, $counts ) ) {
				$counts[ $row->status ] = (int) $row->cnt;
			}
			$counts['total'] += (int) $row->cnt;
		}

		return $counts;
	}

	/**
	 * Retrieve donation records with optional filtering and pagination.
	 *
	 * @param array $args {
	 *     Optional query args.
	 *     @type int    $limit   Records per page. Default 50.
	 *     @type string $status  Filter by status ('success', 'failed', 'pending').
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

	/**
	 * Return summary metrics for the admin overview dashboard.
	 *
	 * Returns a single database query result covering all KPIs to avoid
	 * multiple round-trips for the simple overview cards.
	 *
	 * @return array {
	 *     @type int   $total_count     Total donation attempts (all statuses).
	 *     @type int   $success_count   Successfully verified donations.
	 *     @type float $total_raised    Sum of all successful donation amounts.
	 *     @type array $recent          Five most recent successful donations.
	 * }
	 */
	public static function get_summary() {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			"SELECT
				COUNT(*)                                   AS total_count,
				SUM(status = 'success')                    AS success_count,
				SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END) AS total_raised
			FROM {$table}"
		);

		$recent = $wpdb->get_results(
			"SELECT donor_email, amount, currency, amount_type, created_at
			 FROM {$table}
			 WHERE status = 'success'
			 ORDER BY created_at DESC
			 LIMIT 5"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		return array(
			'total_count'   => $row ? (int) $row->total_count   : 0,
			'success_count' => $row ? (int) $row->success_count : 0,
			'total_raised'  => $row ? (float) $row->total_raised : 0.0,
			'recent'        => $recent ? $recent : array(),
		);
	}
}
