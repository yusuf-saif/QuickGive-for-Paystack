<?php
/**
 * AJAX handler.
 *
 * @package QuickDonate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles checkout verification requests.
 */
class QuickDonate_Ajax {

	/**
	 * Current AJAX action.
	 */
	const ACTION = 'quickdonate_verify';

	/**
	 * Current nonce action.
	 */
	const NONCE_ACTION = 'quickdonate_nonce';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'verify_transaction' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'verify_transaction' ) );
	}

	/**
	 * Verify a transaction via the active gateway.
	 *
	 * @return void
	 */
	public function verify_transaction() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'quickdonate' ) ),
				403
			);
		}

		$reference = isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : '';
		$email     = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$amount    = isset( $_POST['amount'] ) ? absint( wp_unslash( $_POST['amount'] ) ) : 0;
		$currency  = strtoupper( sanitize_text_field( wp_unslash( $_POST['currency'] ?? '' ) ) );
		$type      = sanitize_text_field( wp_unslash( $_POST['amount_type'] ?? 'preset' ) );

		$allowed_currencies = array( 'NGN', 'GHS', 'ZAR', 'KES', 'USD', 'GBP', 'EUR' );
		$amount_type        = in_array( $type, array( 'preset', 'custom' ), true ) ? $type : 'preset';

		if ( '' === $reference || '' === $email || ! is_email( $email ) || $amount <= 0 || ! in_array( $currency, $allowed_currencies, true ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Missing or invalid required fields.', 'quickdonate' ) ),
				400
			);
		}

		$settings = QuickDonate_Plugin::get_settings();

		if ( '1' !== $settings['donations_enabled'] ) {
			wp_send_json_error(
				array( 'message' => __( 'Donations are currently unavailable.', 'quickdonate' ) ),
				403
			);
		}

		$gateway_id = QuickDonate_Plugin::get_active_gateway_id( $settings );
		$gateway    = QuickDonate_Plugin::get_gateway( $gateway_id );

		if ( ! $gateway instanceof QuickDonate_Gateway_Interface ) {
			wp_send_json_error(
				array( 'message' => __( 'No active payment gateway is available.', 'quickdonate' ) ),
				500
			);
		}

		$result = $gateway->verify_transaction( $reference, $email, $amount, $currency, $settings );

		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$status     = is_array( $error_data ) && ! empty( $error_data['status'] ) ? (int) $error_data['status'] : 402;

			QuickDonate_Logger::log( $reference, $email, $amount / 100, $currency, 'failed', $amount_type, $gateway_id );
			wp_send_json_error(
				array( 'message' => $result->get_error_message() ),
				$status
			);
		}

		QuickDonate_Logger::log(
			$result['reference'],
			$result['email'],
			$result['amount'] / 100,
			$result['currency'],
			'success',
			$amount_type,
			$gateway_id
		);

		QuickDonate_Email::send( $result['email'], $result['amount'] / 100, $result['currency'], $result['reference'] );

		wp_send_json_success(
			array(
				'message'   => wp_kses_post( $settings['thankyou_message'] ),
				'reference' => sanitize_text_field( $result['reference'] ),
			)
		);
	}
}
