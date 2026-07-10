<?php
/**
 * Shortcode renderer.
 *
 * @package QuickDonate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders donation popup shortcodes.
 */
class QuickDonate_Shortcode {

	/**
	 * Whether assets were already enqueued.
	 *
	 * @var bool
	 */
	private $assets_enqueued = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_shortcode( 'quickdonate_popup', array( $this, 'render' ) );
	}

	/**
	 * Render the donation popup shortcode.
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @param string $tag     Shortcode tag.
	 * @return string
	 */
	public function render( $atts, $content = '', $tag = '' ) {
		unset( $content, $tag );

		$settings   = QuickDonate_Plugin::get_settings();
		$gateway_id = QuickDonate_Plugin::get_active_gateway_id( $settings );
		$gateway    = QuickDonate_Plugin::get_gateway( $gateway_id );

		if ( '1' !== $settings['donations_enabled'] ) {
			return current_user_can( 'manage_options' ) ? '<p class="quickdonate-notice">' . esc_html__( 'QuickDonate is currently disabled in settings.', 'quickdonate' ) . '</p>' : '';
		}

		if ( ! $gateway instanceof QuickDonate_Gateway_Interface || ! $gateway->is_configured( $settings ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<p class="quickdonate-notice">' . esc_html__( 'QuickDonate: configure the active gateway keys before showing the donation popup.', 'quickdonate' ) . '</p>';
			}

			return '';
		}

		$public_key = $gateway->get_public_key( $settings );
		$this->enqueue_assets( $settings, $public_key, $gateway_id );

		$button_label = ! empty( $settings['button_label'] ) ? $settings['button_label'] : __( 'Donate now', 'quickdonate' );

		ob_start();
		include QUICKDONATE_DIR . 'templates/donation-popup.php';
		return ob_get_clean();
	}

	/**
	 * Enqueue frontend assets exactly once.
	 *
	 * @param array  $settings   Plugin settings.
	 * @param string $public_key Active public key.
	 * @param string $gateway_id Active gateway ID.
	 * @return void
	 */
	private function enqueue_assets( $settings, $public_key, $gateway_id ) {
		if ( $this->assets_enqueued ) {
			return;
		}

		$this->assets_enqueued = true;

		wp_enqueue_script(
			'paystack-inline',
			'https://js.paystack.co/v2/inline.js',
			array(),
			'2.0',
			true
		);

		wp_enqueue_style(
			'quickdonate-frontend',
			QUICKDONATE_URL . 'assets/css/frontend.css',
			array(),
			QUICKDONATE_VERSION
		);

		wp_enqueue_script(
			'quickdonate-frontend',
			QUICKDONATE_URL . 'assets/js/frontend.js',
			array( 'paystack-inline' ),
			QUICKDONATE_VERSION,
			true
		);

		$raw_presets = explode( ',', $settings['preset_amounts'] ?? '500,1000,2500,5000' );
		$presets     = array_values( array_filter( array_map( 'floatval', $raw_presets ) ) );

		wp_localize_script(
			'quickdonate-frontend',
			'quickdonateConfig',
			array(
				'gateway'        => $gateway_id,
				'publicKey'      => sanitize_text_field( $public_key ),
				'currency'       => sanitize_text_field( $settings['currency'] ?? 'NGN' ),
				'presets'        => $presets,
				'allowCustom'    => ! empty( $settings['allow_custom'] ) && '1' === $settings['allow_custom'],
				'minAmount'      => absint( $settings['min_amount'] ?? 0 ),
				'maxAmount'      => absint( $settings['max_amount'] ?? 0 ),
				'ajaxUrl'        => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
				'nonce'          => wp_create_nonce( QuickDonate_Ajax::NONCE_ACTION ),
				'action'         => QuickDonate_Ajax::ACTION,
				'successPageUrl' => QuickDonate_Plugin::get_page_url( (int) $settings['success_page_id'] ),
				'failurePageUrl' => QuickDonate_Plugin::get_page_url( (int) $settings['failure_page_id'] ),
				'i18n'           => array(
					'donate'          => __( 'Donate now', 'quickdonate' ),
					'selectAmount'    => __( 'Please select or enter a donation amount.', 'quickdonate' ),
					'validEmail'      => __( 'Please enter a valid email address.', 'quickdonate' ),
					'minAmountMsg'    => __( 'Minimum donation amount is', 'quickdonate' ),
					'maxAmountMsg'    => __( 'Maximum donation amount is', 'quickdonate' ),
					'processing'      => __( 'Processing...', 'quickdonate' ),
					'verifying'       => __( 'Verifying payment...', 'quickdonate' ),
					'paymentFailed'   => __( 'Payment was not completed. Please try again.', 'quickdonate' ),
					'networkError'    => __( 'A network error occurred. Please check your connection and try again.', 'quickdonate' ),
					'gatewayLoadFail' => __( 'Payment gateway failed to load. Please refresh the page.', 'quickdonate' ),
				),
			)
		);
	}
}
