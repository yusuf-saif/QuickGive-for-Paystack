<?php
/**
 * Admin settings page for QuickGive for Paystack.
 *
 * @package QuickGive_For_Paystack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the WordPress admin settings UI.
 */
class QuickGive_Admin {

	/**
	 * Option group / option name used to store all settings.
	 */
	const OPTION_NAME = 'quickgive_settings';

	/**
	 * Constructor — register hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Add the top-level admin menu entry.
	 */
	public function add_menu_page() {
		add_menu_page(
			__( 'QuickGive Settings', 'quickgive-for-paystack' ),
			__( 'QuickGive', 'quickgive-for-paystack' ),
			'manage_options',
			QUICKGIVE_SLUG,
			array( $this, 'render_settings_page' ),
			'dashicons-heart',
			56
		);

		// Donation log sub-page.
		add_submenu_page(
			QUICKGIVE_SLUG,
			__( 'Donation Log', 'quickgive-for-paystack' ),
			__( 'Donation Log', 'quickgive-for-paystack' ),
			'manage_options',
			QUICKGIVE_SLUG . '-log',
			array( $this, 'render_log_page' )
		);
	}

	/**
	 * Register settings, sections, and fields.
	 */
	public function register_settings() {
		register_setting(
			QUICKGIVE_SLUG,
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		// --- Paystack API section ---
		add_settings_section(
			'quickgive_api',
			__( 'Paystack API', 'quickgive-for-paystack' ),
			'__return_false',
			QUICKGIVE_SLUG
		);

		$this->add_field( 'quickgive_api', 'mode', __( 'Mode', 'quickgive-for-paystack' ), 'render_mode_field' );
		$this->add_field( 'quickgive_api', 'public_key_test', __( 'Test Public Key', 'quickgive-for-paystack' ), 'render_text_field' );
		$this->add_field( 'quickgive_api', 'secret_key_test', __( 'Test Secret Key', 'quickgive-for-paystack' ), 'render_password_field' );
		$this->add_field( 'quickgive_api', 'public_key_live', __( 'Live Public Key', 'quickgive-for-paystack' ), 'render_text_field' );
		$this->add_field( 'quickgive_api', 'secret_key_live', __( 'Live Secret Key', 'quickgive-for-paystack' ), 'render_password_field' );
		$this->add_field( 'quickgive_api', 'currency', __( 'Currency', 'quickgive-for-paystack' ), 'render_currency_field' );

		// --- Donation options section ---
		add_settings_section(
			'quickgive_donation',
			__( 'Donation Options', 'quickgive-for-paystack' ),
			'__return_false',
			QUICKGIVE_SLUG
		);

		$this->add_field( 'quickgive_donation', 'preset_amounts', __( 'Preset Amounts (comma-separated)', 'quickgive-for-paystack' ), 'render_text_field' );
		$this->add_field( 'quickgive_donation', 'allow_custom', __( 'Allow Custom Amount', 'quickgive-for-paystack' ), 'render_checkbox_field' );
		$this->add_field( 'quickgive_donation', 'min_amount', __( 'Minimum Amount', 'quickgive-for-paystack' ), 'render_number_field' );
		$this->add_field( 'quickgive_donation', 'max_amount', __( 'Maximum Amount (0 = no limit)', 'quickgive-for-paystack' ), 'render_number_field' );

		// --- UI section ---
		add_settings_section(
			'quickgive_ui',
			__( 'Button & Messages', 'quickgive-for-paystack' ),
			'__return_false',
			QUICKGIVE_SLUG
		);

		$this->add_field( 'quickgive_ui', 'button_label', __( 'Button Label', 'quickgive-for-paystack' ), 'render_text_field' );
		$this->add_field( 'quickgive_ui', 'thankyou_message', __( 'Thank-You Message', 'quickgive-for-paystack' ), 'render_textarea_field' );
	}

	/**
	 * Helper to register a field with a shared args array.
	 *
	 * @param string $section   Section ID.
	 * @param string $key       Setting key.
	 * @param string $label     Field label.
	 * @param string $callback  Render method name.
	 */
	private function add_field( $section, $key, $label, $callback ) {
		add_settings_field(
			'quickgive_' . $key,
			$label,
			array( $this, $callback ),
			QUICKGIVE_SLUG,
			$section,
			array( 'key' => $key )
		);
	}

	/**
	 * Sanitize all settings before saving to the database.
	 *
	 * @param array $input Raw form input.
	 * @return array Sanitised settings.
	 */
	public function sanitize_settings( $input ) {
		$clean = array();

		$clean['mode'] = isset( $input['mode'] ) && 'live' === $input['mode'] ? 'live' : 'test';

		$clean['public_key_test']  = sanitize_text_field( $input['public_key_test'] ?? '' );
		$clean['secret_key_test']  = sanitize_text_field( $input['secret_key_test'] ?? '' );
		$clean['public_key_live']  = sanitize_text_field( $input['public_key_live'] ?? '' );
		$clean['secret_key_live']  = sanitize_text_field( $input['secret_key_live'] ?? '' );

		$allowed_currencies = array( 'NGN', 'GHS', 'ZAR', 'KES', 'USD', 'GBP', 'EUR' );
		$clean['currency']  = in_array( $input['currency'] ?? 'NGN', $allowed_currencies, true )
			? $input['currency']
			: 'NGN';

		// Preset amounts: strip everything that isn't digits, commas, or dots.
		$raw_amounts = sanitize_text_field( $input['preset_amounts'] ?? '' );
		$clean['preset_amounts'] = preg_replace( '/[^0-9,.]/', '', $raw_amounts );

		$clean['allow_custom'] = ! empty( $input['allow_custom'] ) ? '1' : '0';

		$clean['min_amount'] = max( 0, (int) ( $input['min_amount'] ?? 0 ) );
		$clean['max_amount'] = max( 0, (int) ( $input['max_amount'] ?? 0 ) );

		$clean['button_label']    = sanitize_text_field( $input['button_label'] ?? __( 'Donate Now', 'quickgive-for-paystack' ) );
		$clean['thankyou_message'] = wp_kses_post( $input['thankyou_message'] ?? '' );

		return $clean;
	}

	/**
	 * Enqueue admin assets only on our settings page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_' . QUICKGIVE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'quickgive-admin',
			QUICKGIVE_URL . 'assets/css/quickgive-admin.css',
			array(),
			QUICKGIVE_VERSION
		);
	}

	// ----------------------------------------------------------------
	// Field renderers
	// ----------------------------------------------------------------

	/** @param array $args Field args including 'key'. */
	public function render_text_field( $args ) {
		$opts = get_option( self::OPTION_NAME, array() );
		$key  = $args['key'];
		$val  = esc_attr( $opts[ $key ] ?? '' );
		printf(
			'<input type="text" class="regular-text" name="%1$s[%2$s]" id="%2$s" value="%3$s" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $key ),
			$val
		);
	}

	/** @param array $args Field args including 'key'. */
	public function render_password_field( $args ) {
		$opts = get_option( self::OPTION_NAME, array() );
		$key  = $args['key'];
		$val  = esc_attr( $opts[ $key ] ?? '' );
		printf(
			'<input type="password" class="regular-text" name="%1$s[%2$s]" id="%2$s" value="%3$s" autocomplete="new-password" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $key ),
			$val
		);
	}

	/** @param array $args Field args including 'key'. */
	public function render_number_field( $args ) {
		$opts = get_option( self::OPTION_NAME, array() );
		$key  = $args['key'];
		$val  = absint( $opts[ $key ] ?? 0 );
		printf(
			'<input type="number" min="0" class="small-text" name="%1$s[%2$s]" id="%2$s" value="%3$d" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $key ),
			$val
		);
	}

	/** @param array $args Field args including 'key'. */
	public function render_checkbox_field( $args ) {
		$opts    = get_option( self::OPTION_NAME, array() );
		$key     = $args['key'];
		$checked = checked( '1', $opts[ $key ] ?? '1', false );
		printf(
			'<label><input type="checkbox" name="%1$s[%2$s]" id="%2$s" value="1" %3$s /> %4$s</label>',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $key ),
			$checked,
			esc_html__( 'Enabled', 'quickgive-for-paystack' )
		);
	}

	/** @param array $args Field args including 'key'. */
	public function render_textarea_field( $args ) {
		$opts = get_option( self::OPTION_NAME, array() );
		$key  = $args['key'];
		$val  = esc_textarea( $opts[ $key ] ?? '' );
		printf(
			'<textarea class="large-text" rows="4" name="%1$s[%2$s]" id="%2$s">%3$s</textarea>',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $key ),
			$val
		);
	}

	/** Render mode toggle. */
	public function render_mode_field( $args ) {
		$opts = get_option( self::OPTION_NAME, array() );
		$mode = $opts['mode'] ?? 'test';
		foreach ( array( 'test' => __( 'Test', 'quickgive-for-paystack' ), 'live' => __( 'Live', 'quickgive-for-paystack' ) ) as $value => $label ) {
			printf(
				'<label style="margin-right:16px"><input type="radio" name="%1$s[mode]" value="%2$s" %3$s /> %4$s</label>',
				esc_attr( self::OPTION_NAME ),
				esc_attr( $value ),
				checked( $mode, $value, false ),
				esc_html( $label )
			);
		}
	}

	/** Render currency select. */
	public function render_currency_field( $args ) {
		$opts       = get_option( self::OPTION_NAME, array() );
		$selected   = $opts['currency'] ?? 'NGN';
		$currencies = array(
			'NGN' => 'NGN — Nigerian Naira',
			'GHS' => 'GHS — Ghanaian Cedi',
			'ZAR' => 'ZAR — South African Rand',
			'KES' => 'KES — Kenyan Shilling',
			'USD' => 'USD — US Dollar',
			'GBP' => 'GBP — British Pound',
			'EUR' => 'EUR — Euro',
		);
		echo '<select name="' . esc_attr( self::OPTION_NAME ) . '[currency]">';
		foreach ( $currencies as $code => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $code ),
				selected( $selected, $code, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	// ----------------------------------------------------------------
	// Page renderers
	// ----------------------------------------------------------------

	/**
	 * Output the settings page HTML.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'quickgive-for-paystack' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<p class="description">
				<?php esc_html_e( 'Use the shortcode', 'quickgive-for-paystack' ); ?>
				<code>[paystack_donation_popup]</code>
				<?php esc_html_e( 'anywhere on your site to display the donation button.', 'quickgive-for-paystack' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php
				settings_fields( QUICKGIVE_SLUG );
				do_settings_sections( QUICKGIVE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Output the donation log page.
	 */
	public function render_log_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'quickgive-for-paystack' ) );
		}

		$per_page = 50;
		$page     = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$offset   = ( $page - 1 ) * $per_page;
		$total    = QuickGive_Logger::get_count();
		$pages    = $total > 0 ? (int) ceil( $total / $per_page ) : 1;

		$donations = QuickGive_Logger::get_donations(
			array(
				'limit'  => $per_page,
				'offset' => $offset,
			)
		);

		$base_url = add_query_arg( 'page', QUICKGIVE_SLUG . '-log', admin_url( 'admin.php' ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Donation Log', 'quickgive-for-paystack' ); ?></h1>
			<?php if ( empty( $donations ) && 1 === $page ) : ?>
				<p><?php esc_html_e( 'No donations recorded yet.', 'quickgive-for-paystack' ); ?></p>
			<?php else : ?>
				<p class="description">
					<?php
					printf(
						/* translators: 1: first item number, 2: last item number, 3: total count */
						esc_html__( 'Showing %1$s–%2$s of %3$s donations.', 'quickgive-for-paystack' ),
						number_format_i18n( $offset + 1 ),
						number_format_i18n( min( $offset + $per_page, $total ) ),
						number_format_i18n( $total )
					);
					?>
				</p>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'quickgive-for-paystack' ); ?></th>
							<th><?php esc_html_e( 'Email', 'quickgive-for-paystack' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'quickgive-for-paystack' ); ?></th>
							<th><?php esc_html_e( 'Currency', 'quickgive-for-paystack' ); ?></th>
							<th><?php esc_html_e( 'Status', 'quickgive-for-paystack' ); ?></th>
							<th><?php esc_html_e( 'Reference', 'quickgive-for-paystack' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $donations as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row->created_at ); ?></td>
								<td><?php echo esc_html( $row->donor_email ); ?></td>
								<td><?php echo esc_html( number_format( $row->amount, 2 ) ); ?></td>
								<td><?php echo esc_html( $row->currency ); ?></td>
								<td>
									<span class="quickgive-status quickgive-status--<?php echo esc_attr( $row->status ); ?>">
										<?php echo esc_html( ucfirst( $row->status ) ); ?>
									</span>
								</td>
								<td><code><?php echo esc_html( $row->reference ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php
							printf(
								/* translators: %s: number of pages */
								esc_html__( 'Page %1$s of %2$s', 'quickgive-for-paystack' ),
								number_format_i18n( $page ),
								number_format_i18n( $pages )
							);
							?>
						</span>
						<span class="pagination-links">
							<?php if ( $page > 1 ) : ?>
								<a class="prev-page button" href="<?php echo esc_url( add_query_arg( 'paged', $page - 1, $base_url ) ); ?>">
									&laquo; <?php esc_html_e( 'Previous', 'quickgive-for-paystack' ); ?>
								</a>
							<?php endif; ?>
							<?php if ( $page < $pages ) : ?>
								<a class="next-page button" href="<?php echo esc_url( add_query_arg( 'paged', $page + 1, $base_url ) ); ?>">
									<?php esc_html_e( 'Next', 'quickgive-for-paystack' ); ?> &raquo;
								</a>
							<?php endif; ?>
						</span>
					</div>
				</div>
				<?php endif; ?>

			<?php endif; ?>
		</div>
		<?php
	}
}
