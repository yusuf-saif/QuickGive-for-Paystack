<?php
/**
 * Donation logger.
 *
 * @package QuickDonate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the donations table and related queries.
 */
class QuickDonate_Logger {

	/**
	 * Current table name without prefix.
	 */
	const TABLE = 'quickdonate_donations';

	/**
	 * Current DB version.
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Current DB version option.
	 */
	const DB_VERSION_OPTION = 'quickdonate_db_version';

	/**
	 * Create or update the donations table.
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		$table           = $wpdb->prefix . self::TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			reference     VARCHAR(100)        NOT NULL,
			donor_email   VARCHAR(200)        NOT NULL,
			amount        DECIMAL(12,2)       NOT NULL DEFAULT 0.00,
			currency      VARCHAR(10)         NOT NULL DEFAULT 'NGN',
			amount_type   VARCHAR(10)         NOT NULL DEFAULT 'preset',
			gateway       VARCHAR(30)         NOT NULL DEFAULT 'paystack',
			status        VARCHAR(20)         NOT NULL DEFAULT 'pending',
			created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY   reference (reference),
			KEY          status (status),
			KEY          amount_type (amount_type),
			KEY          gateway (gateway)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Migrate and upgrade the donations table.
	 *
	 * @return void
	 */
	public static function maybe_upgrade_table() {
		if ( self::DB_VERSION === get_option( self::DB_VERSION_OPTION ) && self::table_exists( self::TABLE ) ) {
			return;
		}

		self::create_table();
	}

	/**
	 * Log or update a donation record.
	 *
	 * @param string $reference   Transaction reference.
	 * @param string $email       Donor email.
	 * @param float  $amount      Amount in major currency units.
	 * @param string $currency    Currency code.
	 * @param string $status      Donation status.
	 * @param string $amount_type Amount type.
	 * @param string $gateway     Gateway ID.
	 * @return int|false
	 */
	public static function log( $reference, $email, $amount, $currency, $status = 'pending', $amount_type = 'preset', $gateway = 'paystack' ) {
		global $wpdb;

		$table       = $wpdb->prefix . self::TABLE;
		$amount_type = 'custom' === $amount_type ? 'custom' : 'preset';
		$gateway     = sanitize_key( $gateway ? $gateway : 'paystack' );

		$existing = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE reference = %s LIMIT 1", $reference )
		);

		if ( $existing ) {
			$wpdb->update(
				$table,
				array(
					'status'      => sanitize_text_field( $status ),
					'donor_email' => sanitize_email( $email ),
					'amount'      => (float) $amount,
					'currency'    => sanitize_text_field( $currency ),
					'amount_type' => $amount_type,
					'gateway'     => $gateway,
				),
				array( 'id' => (int) $existing ),
				array( '%s', '%s', '%f', '%s', '%s', '%s' ),
				array( '%d' )
			);

			return (int) $existing;
		}

		$result = $wpdb->insert(
			$table,
			array(
				'reference'   => sanitize_text_field( $reference ),
				'donor_email' => sanitize_email( $email ),
				'amount'      => (float) $amount,
				'currency'    => sanitize_text_field( $currency ),
				'amount_type' => $amount_type,
				'gateway'     => $gateway,
				'status'      => sanitize_text_field( $status ),
			),
			array( '%s', '%s', '%f', '%s', '%s', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Count donations, optionally by status.
	 *
	 * @param string $status Optional status.
	 * @return int
	 */
	public static function get_count( $status = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;

		if ( '' !== $status ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", sanitize_text_field( $status ) )
			);
		}

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE 1 = %d", 1 ) );
	}

	/**
	 * Return per-status counts.
	 *
	 * @return array
	 */
	public static function get_status_counts() {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS cnt FROM {$table} WHERE 1 = %d GROUP BY status", 1 ) );

		$counts = array(
			'total'   => 0,
			'success' => 0,
			'failed'  => 0,
			'pending' => 0,
		);

		foreach ( $rows as $row ) {
			if ( isset( $counts[ $row->status ] ) ) {
				$counts[ $row->status ] = (int) $row->cnt;
			}

			$counts['total'] += (int) $row->cnt;
		}

		return $counts;
	}

	/**
	 * Retrieve donation rows.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public static function get_donations( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'limit'  => 50,
				'offset' => 0,
				'status' => '',
			)
		);

		$table  = $wpdb->prefix . self::TABLE;
		$limit  = absint( $args['limit'] );
		$offset = absint( $args['offset'] );

		if ( '' !== $args['status'] ) {
			return (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
					sanitize_text_field( $args['status'] ),
					$limit,
					$offset
				)
			);
		}

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);
	}

	/**
	 * Return summary dashboard data.
	 *
	 * @return array
	 */
	public static function get_summary() {
		global $wpdb;

		$table    = $wpdb->prefix . self::TABLE;
		$settings = QuickDonate_Plugin::get_settings();
		$row      = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) AS total_count,
					SUM(status = 'success') AS success_count,
					SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END) AS total_raised,
					AVG(CASE WHEN status = 'success' THEN amount ELSE NULL END) AS average_donation
				FROM {$table}
				WHERE 1 = %d",
				1
			)
		);

		$recent = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT donor_email, amount, currency, amount_type, gateway, created_at
				FROM {$table}
				WHERE status = 'success'
				ORDER BY created_at DESC
				LIMIT %d",
				5
			)
		);

		return array(
			'total_count'      => $row ? (int) $row->total_count : 0,
			'success_count'    => $row ? (int) $row->success_count : 0,
			'total_raised'     => $row ? (float) $row->total_raised : 0.0,
			'average_donation' => $row && null !== $row->average_donation ? (float) $row->average_donation : 0.0,
			'currency'         => sanitize_text_field( $settings['currency'] ?? 'NGN' ),
			'recent'           => $recent ? $recent : array(),
		);
	}

	/**
	 * Determine whether a plugin-owned table exists.
	 *
	 * @param string $table_name Table name without prefix.
	 * @return bool
	 */
	private static function table_exists( $table_name ) {
		global $wpdb;

		$table = $wpdb->prefix . $table_name;

		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}
}
