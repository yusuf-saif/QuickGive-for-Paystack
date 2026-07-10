<?php
/**
 * Plugin Name:       QuickDonate
 * Plugin URI:        https://wordpress.org/plugins/quickdonate/
 * Description:       A lightweight donation popup plugin for WordPress with secure gateway-based payment verification.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            saif2002
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       quickdonate
 * Domain Path:       /languages
 *
 * @package QuickDonate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'QUICKDONATE_VERSION', '1.0.1' );
define( 'QUICKDONATE_FILE', __FILE__ );
define( 'QUICKDONATE_DIR', plugin_dir_path( __FILE__ ) );
define( 'QUICKDONATE_URL', plugin_dir_url( __FILE__ ) );
define( 'QUICKDONATE_SLUG', 'quickdonate' );
define( 'QUICKDONATE_OPTION_NAME', 'quickdonate_settings' );

/**
 * Main plugin bootstrap.
 */
final class QuickDonate_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var QuickDonate_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Registered gateways.
	 *
	 * @var array<string,QuickDonate_Gateway_Interface>
	 */
	private static $gateways = array();

	/**
	 * Get or create the singleton instance.
	 *
	 * @return QuickDonate_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		require_once QUICKDONATE_DIR . 'includes/class-quickdonate-logger.php';
		QuickDonate_Logger::maybe_upgrade_table();
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Include plugin classes.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		require_once QUICKDONATE_DIR . 'includes/class-quickdonate-gateway-interface.php';
		require_once QUICKDONATE_DIR . 'includes/class-quickdonate-logger.php';
		require_once QUICKDONATE_DIR . 'includes/class-quickdonate-email.php';
		require_once QUICKDONATE_DIR . 'includes/class-quickdonate-paystack-gateway.php';
		require_once QUICKDONATE_DIR . 'includes/class-quickdonate-admin.php';
		require_once QUICKDONATE_DIR . 'includes/class-quickdonate-ajax.php';
		require_once QUICKDONATE_DIR . 'includes/class-quickdonate-shortcode.php';
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		QuickDonate_Logger::maybe_upgrade_table();

		new QuickDonate_Admin();
		new QuickDonate_Ajax();
		new QuickDonate_Shortcode();
	}

	/**
	 * Return default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'donations_enabled' => '1',
			'button_label'      => __( 'Donate now', 'quickdonate' ),
			'preset_amounts'    => '500,1000,2500,5000',
			'allow_custom'      => '1',
			'min_amount'        => 0,
			'max_amount'        => 0,
			'thankyou_message'  => __( 'Thank you for your generous donation!', 'quickdonate' ),
			'currency'          => 'NGN',
			'mode'              => 'test',
			'active_gateway'    => 'paystack',
			'success_page_id'   => 0,
			'failure_page_id'   => 0,
			'public_key_test'   => '',
			'secret_key_test'   => '',
			'public_key_live'   => '',
			'secret_key_live'   => '',
			'email_enabled'     => '0',
			'email_from_name'   => '',
			'email_from_email'  => '',
			'email_subject'     => '',
			'email_body'        => '',
		);
	}

	/**
	 * Normalize settings against plugin defaults.
	 *
	 * @param array $settings Raw or partial settings.
	 * @return array
	 */
	public static function normalize_settings( $settings ) {
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, self::defaults() );
	}

	/**
	 * Retrieve plugin settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = get_option( QUICKDONATE_OPTION_NAME, array() );

		return self::normalize_settings( $settings );
	}

	/**
	 * Return registered gateways.
	 *
	 * @return array<string,QuickDonate_Gateway_Interface>
	 */
	public static function get_gateways() {
		if ( empty( self::$gateways ) ) {
			$paystack = new QuickDonate_Paystack_Gateway();
			self::$gateways = array(
				$paystack->get_id() => $paystack,
			);
		}

		return self::$gateways;
	}

	/**
	 * Return the active gateway instance.
	 *
	 * @param string $gateway_id Gateway ID.
	 * @return QuickDonate_Gateway_Interface|null
	 */
	public static function get_gateway( $gateway_id ) {
		$gateways = self::get_gateways();

		return $gateways[ $gateway_id ] ?? null;
	}

	/**
	 * Return the active gateway ID from current settings.
	 *
	 * @param array|null $settings Optional settings.
	 * @return string
	 */
	public static function get_active_gateway_id( $settings = null ) {
		$settings = is_array( $settings ) ? self::normalize_settings( $settings ) : self::get_settings();
		$gateway  = sanitize_key( $settings['active_gateway'] ?? 'paystack' );

		return self::get_gateway( $gateway ) ? $gateway : 'paystack';
	}

	/**
	 * Return a page URL for an optional redirect target.
	 *
	 * @param int $page_id Page ID.
	 * @return string
	 */
	public static function get_page_url( $page_id ) {
		$page_id = absint( $page_id );

		if ( $page_id <= 0 ) {
			return '';
		}

		$url = get_permalink( $page_id );

		return $url ? $url : '';
	}
}

register_activation_hook( QUICKDONATE_FILE, array( 'QuickDonate_Plugin', 'activate' ) );
add_action( 'plugins_loaded', array( 'QuickDonate_Plugin', 'instance' ) );
