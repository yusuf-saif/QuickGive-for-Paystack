<?php
/**
 * AJAX handler for Paystack transaction verification.
 *
 * Receives the transaction reference from the frontend after the Paystack
 * success callback, validates the nonce, verifies the transaction server-side,
 * logs the result, and (on verified success) dispatches the donor thank-you email.
 *
 * v1.1 changes:
 *   - Accepts `amount_type` ('preset'|'custom') from POST.
 *   - Passes `amount_type` to QuickGive_Logger::log().
 *   - Calls QuickGive_Email::send() after verified success.
 *
 * @package QuickGive_For_Paystack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles AJAX requests for payment verification.
 */
class QuickGive_Ajax {

	/**
	 * AJAX action name (accessible to logged-in and guest donors alike).
	 */
	const ACTION = 'quickgive_verify';

	/**
	 * Nonce action string — must match what the frontend sends.
	 */
	const NONCE_ACTION = 'quickgive_nonce';

	/**
	 * Paystack transaction verification API endpoint.
	 */
	const VERIFY_URL = 'https://api.paystack.co/transaction/verify/';

	/**
	 * Constructor — register AJAX hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_' . self::ACTION,        array( $this, 'verify_transaction' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'verify_transaction' ) );
	}

	/**
	 * Verify a Paystack transaction reference.
	 *
	 * Expected POST fields:
	 *   - nonce       (string) WordPress nonce.
	 *   - reference   (string) Paystack transaction reference.
	 *   - email       (string) Donor email address.
	 *   - amount      (int)    Amount in kobo / smallest currency unit.
	 *   - currency    (string) ISO currency code.
	 *   - amount_type (string) 'preset' or 'custom'.
	 *
	 * @return void Sends JSON response and exits.
	 */
	public function verify_transaction() {

		// 1. Nonce check — prevents CSRF and replayed requests.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'quickgive-for-paystack' ) ),
				403
			);
		}

		// 2. Sanitise and validate inputs.
		$reference = isset( $_POST['reference'] )   ? sanitize_text_field( wp_unslash( $_POST['reference'] ) )  : '';
		$email     = isset( $_POST['email'] )        ? sanitize_email( wp_unslash( $_POST['email'] ) )           : '';
		$amount    = isset( $_POST['amount'] )       ? absint( $_POST['amount'] )                                : 0;

		$allowed_currencies = array( 'NGN', 'GHS', 'ZAR', 'KES', 'USD', 'GBP', 'EUR' );
		$raw_currency       = strtoupper( sanitize_text_field( wp_unslash( $_POST['currency'] ?? '' ) ) );
		$currency           = in_array( $raw_currency, $allowed_currencies, true ) ? $raw_currency : '';

		// v1.1 — amount_type: accept only 'preset' or 'custom'; default to 'preset'.
		$raw_type    = sanitize_text_field( wp_unslash( $_POST['amount_type'] ?? 'preset' ) );
		$amount_type = in_array( $raw_type, array( 'preset', 'custom' ), true ) ? $raw_type : 'preset';

		if ( empty( $reference ) || empty( $email ) || $amount <= 0 ) {
			wp_send_json_error(
				array( 'message' => __( 'Missing required fields.', 'quickgive-for-paystack' ) ),
				400
			);
		}

		// 3. Retrieve the correct secret key — NEVER sent to the browser.
		$opts       = get_option( 'quickgive_settings', array() );
		$mode       = $opts['mode'] ?? 'test';
		$secret_key = 'live' === $mode
			? ( $opts['secret_key_live'] ?? '' )
			: ( $opts['secret_key_test'] ?? '' );

		if ( empty( $secret_key ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Payment gateway not configured. Please contact the site administrator.', 'quickgive-for-paystack' ) ),
				500
			);
		}

		// 4. Call the Paystack Verify API — secret key lives here, never on frontend.
		$api_response = wp_remote_get(
			self::VERIFY_URL . rawurlencode( $reference ),
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $secret_key,
					'Cache-Control' => 'no-cache',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $api_response ) ) {
			QuickGive_Logger::log( $reference, $email, $amount / 100, $currency, 'failed', $amount_type );
			wp_send_json_error(
				array( 'message' => __( 'Could not connect to the payment gateway. Please try again later.', 'quickgive-for-paystack' ) ),
				502
			);
		}

		$body = wp_remote_retrieve_body( $api_response );
		$data = json_decode( $body, true );

		// 5. Validate the Paystack response structure and status.
		if (
			empty( $data['status'] ) ||
			true !== $data['status'] ||
			empty( $data['data']['status'] ) ||
			'success' !== $data['data']['status']
		) {
			$error_message = $data['message'] ?? __( 'Payment verification failed.', 'quickgive-for-paystack' );
			QuickGive_Logger::log( $reference, $email, $amount / 100, $currency, 'failed', $amount_type );
			wp_send_json_error( array( 'message' => esc_html( $error_message ) ), 402 );
		}

		// 6. Cross-check amount to prevent value manipulation.
		//    Paystack returns amounts in the smallest currency unit (kobo for NGN).
		$verified_amount   = absint( $data['data']['amount'] );
		$verified_currency = strtoupper( sanitize_text_field( $data['data']['currency'] ?? '' ) );

		if ( $verified_amount !== $amount ) {
			QuickGive_Logger::log( $reference, $email, $amount / 100, $currency, 'failed', $amount_type );
			wp_send_json_error(
				array( 'message' => __( 'Payment amount mismatch. Transaction rejected.', 'quickgive-for-paystack' ) ),
				402
			);
		}

		// 7. All checks passed — write the verified record.
		QuickGive_Logger::log( $reference, $email, $verified_amount / 100, $verified_currency, 'success', $amount_type );

		// 8. v1.1 — Send donor thank-you email (only fires if configured and enabled).
		QuickGive_Email::send( $email, $verified_amount / 100, $verified_currency, $reference );

		// 9. Return the thank-you message to the frontend.
		$thank_you = wp_kses_post(
			$opts['thankyou_message'] ?? __( 'Thank you for your generous donation!', 'quickgive-for-paystack' )
		);

		wp_send_json_success(
			array(
				'message'   => $thank_you,
				'reference' => $reference,
			)
		);
	}
}
