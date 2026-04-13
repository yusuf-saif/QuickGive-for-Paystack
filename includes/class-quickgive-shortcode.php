<?php
/**
 * Shortcode renderer for QuickGive for Paystack.
 *
 * Registers [paystack_donation_popup] and enqueues frontend assets only when
 * the shortcode is actually used on a page.
 *
 * @package QuickGive_For_Paystack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the [paystack_donation_popup] shortcode.
 */
class QuickGive_Shortcode {

	/**
	 * Whether front-end assets have been enqueued.
	 *
	 * @var bool
	 */
	private $assets_enqueued = false;

	/**
	 * Constructor — register shortcode.
	 */
	public function __construct() {
		add_shortcode( 'paystack_donation_popup', array( $this, 'render' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array $atts Shortcode attributes (currently unused; reserved for future per-form config).
	 * @return string HTML output.
	 */
	public function render( $atts ) {
		$opts = get_option( 'quickgive_settings', array() );
		$mode = $opts['mode'] ?? 'test';

		// Determine which public key to use.
		$public_key = 'live' === $mode
			? ( $opts['public_key_live'] ?? '' )
			: ( $opts['public_key_test'] ?? '' );

		if ( empty( $public_key ) ) {
			// Show a notice to admins; show nothing to regular visitors.
			if ( current_user_can( 'manage_options' ) ) {
				return '<p class="quickgive-notice">'
					. esc_html__( 'QuickGive: Please configure your Paystack public key in the plugin settings.', 'quickgive-for-paystack' )
					. '</p>';
			}
			return '';
		}

		// Enqueue frontend assets the first time the shortcode appears.
		$this->enqueue_assets( $opts, $public_key );

		$button_label = ! empty( $opts['button_label'] )
			? $opts['button_label']
			: __( 'Donate Now', 'quickgive-for-paystack' );

		// Build the popup modal markup.
		ob_start();
		include QUICKGIVE_DIR . 'templates/donation-popup.php';
		return ob_get_clean();
	}

	/**
	 * Enqueue CSS, JS, and localised data exactly once.
	 *
	 * @param array  $opts       Plugin settings array.
	 * @param string $public_key Paystack public key for this mode.
	 */
	private function enqueue_assets( $opts, $public_key ) {
		if ( $this->assets_enqueued ) {
			return;
		}
		$this->assets_enqueued = true;

		// Paystack inline JS SDK — loaded from Paystack's CDN.
		wp_enqueue_script(
			'paystack-inline',
			'https://js.paystack.co/v2/inline.js',
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			true
		);

		// Plugin frontend CSS.
		wp_enqueue_style(
			'quickgive-frontend',
			QUICKGIVE_URL . 'assets/css/quickgive-frontend.css',
			array(),
			QUICKGIVE_VERSION
		);

		// Plugin frontend JS.
		wp_enqueue_script(
			'quickgive-frontend',
			QUICKGIVE_URL . 'assets/js/quickgive-frontend.js',
			array( 'paystack-inline' ),
			QUICKGIVE_VERSION,
			true
		);

		// Build preset amounts array (strip empties and cast to float).
		$raw_presets = explode( ',', $opts['preset_amounts'] ?? '500,1000,2500,5000' );
		$presets     = array_values(
			array_filter(
				array_map( 'floatval', $raw_presets )
			)
		);

		// Pass safe config to JavaScript — SECRET KEY IS NEVER INCLUDED.
		wp_localize_script(
			'quickgive-frontend',
			'quickgiveConfig',
			array(
				'publicKey'   => $public_key,           // only public key.
				'currency'    => $opts['currency'] ?? 'NGN',
				'presets'     => $presets,
				'allowCustom' => ! empty( $opts['allow_custom'] ) && '1' === $opts['allow_custom'],
				'minAmount'   => absint( $opts['min_amount'] ?? 0 ),
				'maxAmount'   => absint( $opts['max_amount'] ?? 0 ),
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'quickgive_nonce' ),
				'action'      => 'quickgive_verify',
				'i18n'        => array(
					'selectAmount'    => __( 'Please select or enter a donation amount.', 'quickgive-for-paystack' ),
					'validEmail'      => __( 'Please enter a valid email address.', 'quickgive-for-paystack' ),
					'minAmountMsg'    => __( 'Minimum donation amount is', 'quickgive-for-paystack' ),
					'maxAmountMsg'    => __( 'Maximum donation amount is', 'quickgive-for-paystack' ),
					'processing'      => __( 'Processing…', 'quickgive-for-paystack' ),
					'verifying'       => __( 'Verifying payment…', 'quickgive-for-paystack' ),
					'paymentFailed'   => __( 'Payment was not completed. Please try again.', 'quickgive-for-paystack' ),
					'networkError'    => __( 'A network error occurred. Please check your connection and try again.', 'quickgive-for-paystack' ),
				),
			)
		);
	}
}
