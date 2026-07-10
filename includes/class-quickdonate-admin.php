<?php
/**
 * Admin UI.
 *
 * @package QuickDonate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles settings, dashboard, and donation log pages.
 */
class QuickDonate_Admin {

	/**
	 * Option name.
	 */
	const OPTION_NAME = QUICKDONATE_OPTION_NAME;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register admin pages.
	 *
	 * @return void
	 */
	public function add_menu_page() {
		add_menu_page(
			__( 'QuickDonate', 'quickdonate' ),
			__( 'QuickDonate', 'quickdonate' ),
			'manage_options',
			QUICKDONATE_SLUG,
			array( $this, 'render_settings_page' ),
			'dashicons-heart',
			56
		);

		// Add explicit Settings submenu with slug quickdonate-settings for consistency.
		add_submenu_page(
			QUICKDONATE_SLUG,
			__( 'Settings', 'quickdonate' ),
			__( 'Settings', 'quickdonate' ),
			'manage_options',
			QUICKDONATE_SLUG . '-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			QUICKDONATE_SLUG,
			__( 'Overview', 'quickdonate' ),
			__( 'Overview', 'quickdonate' ),
			'manage_options',
			QUICKDONATE_SLUG . '-overview',
			array( $this, 'render_overview_page' )
		);

		add_submenu_page(
			QUICKDONATE_SLUG,
			__( 'Donation Log', 'quickdonate' ),
			__( 'Donation Log', 'quickdonate' ),
			'manage_options',
			QUICKDONATE_SLUG . '-logs',
			array( $this, 'render_log_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			QUICKDONATE_SLUG,
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize and normalize saved settings.
	 *
	 * @param array $input Raw settings.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$defaults = QuickDonate_Plugin::defaults();
		$clean    = array();

		$clean['donations_enabled'] = ! empty( $input['donations_enabled'] ) ? '1' : '0';
		$clean['button_label']      = sanitize_text_field( $input['button_label'] ?? $defaults['button_label'] );
		$clean['thankyou_message']  = wp_kses_post( $input['thankyou_message'] ?? $defaults['thankyou_message'] );
		$clean['preset_amounts']    = preg_replace( '/[^0-9,.]/', '', sanitize_text_field( $input['preset_amounts'] ?? $defaults['preset_amounts'] ) );
		$clean['allow_custom']      = ! empty( $input['allow_custom'] ) ? '1' : '0';
		$clean['min_amount']        = max( 0, (int) ( $input['min_amount'] ?? 0 ) );
		$clean['max_amount']        = max( 0, (int) ( $input['max_amount'] ?? 0 ) );

		$allowed_currencies = array( 'NGN', 'GHS', 'ZAR', 'KES', 'USD', 'GBP', 'EUR' );
		$currency           = strtoupper( sanitize_text_field( $input['currency'] ?? $defaults['currency'] ) );
		$clean['currency']  = in_array( $currency, $allowed_currencies, true ) ? $currency : 'NGN';

		$clean['mode'] = isset( $input['mode'] ) && 'live' === $input['mode'] ? 'live' : 'test';

		$clean['public_key_test'] = sanitize_text_field( $input['public_key_test'] ?? '' );
		$clean['secret_key_test'] = sanitize_text_field( $input['secret_key_test'] ?? '' );
		$clean['public_key_live'] = sanitize_text_field( $input['public_key_live'] ?? '' );
		$clean['secret_key_live'] = sanitize_text_field( $input['secret_key_live'] ?? '' );

		$gateway_ids             = array_keys( QuickDonate_Plugin::get_gateways() );
		$active_gateway          = sanitize_key( $input['active_gateway'] ?? 'paystack' );
		$clean['active_gateway'] = in_array( $active_gateway, $gateway_ids, true ) ? $active_gateway : 'paystack';

		$clean['success_page_id'] = absint( $input['success_page_id'] ?? 0 );
		$clean['failure_page_id'] = absint( $input['failure_page_id'] ?? 0 );

		$clean['email_enabled']    = ! empty( $input['email_enabled'] ) ? '1' : '0';
		$clean['email_from_name']  = sanitize_text_field( $input['email_from_name'] ?? '' );
		$clean['email_from_email'] = sanitize_email( $input['email_from_email'] ?? '' );
		$clean['email_subject']    = sanitize_text_field( $input['email_subject'] ?? '' );
		$clean['email_body']       = sanitize_textarea_field( $input['email_body'] ?? '' );

		if ( $clean['max_amount'] > 0 && $clean['max_amount'] < $clean['min_amount'] ) {
			$clean['max_amount'] = $clean['min_amount'];
		}

		return QuickDonate_Plugin::normalize_settings( $clean );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		$our_hooks = array(
			'toplevel_page_' . QUICKDONATE_SLUG,
			QUICKDONATE_SLUG . '_page_' . QUICKDONATE_SLUG . '-settings',
			QUICKDONATE_SLUG . '_page_' . QUICKDONATE_SLUG . '-overview',
			QUICKDONATE_SLUG . '_page_' . QUICKDONATE_SLUG . '-logs',
		);

		if ( ! in_array( $hook, $our_hooks, true ) ) {
			return;
		}

		wp_enqueue_style(
			'quickdonate-admin',
			QUICKDONATE_URL . 'assets/css/admin.css',
			array(),
			QUICKDONATE_VERSION
		);
	}

	/**
	 * Render the settings page (with tabs).
	 *
	 * @return void
	 */
	public function render_settings_page() {
		$this->check_permissions();

		$settings  = QuickDonate_Plugin::get_settings();
		$docs_url  = plugins_url( 'docs/overview.md', QUICKDONATE_FILE );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab state is navigational, not form processing.
		$active    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		$tabs      = array(
			'general'  => __( 'General', 'quickdonate' ),
			'emails'   => __( 'Emails', 'quickdonate' ),
			'gateways' => __( 'Gateways', 'quickdonate' ),
			'logs'     => __( 'Logs', 'quickdonate' ),
			'advanced' => __( 'Advanced', 'quickdonate' ),
		);
		if ( ! isset( $tabs[ $active ] ) ) {
			$active = 'general';
		}

		$settings_page = admin_url( 'admin.php?page=' . QUICKDONATE_SLUG . '-settings' );
		?>
		<div class="wrap quickdonate-admin">
			<?php $this->render_page_header( __( 'Settings', 'quickdonate' ), __( 'Configure your donation flow, email copy, logging visibility, and gateway settings from one place.', 'quickdonate' ) ); ?>

			<div class="quickdonate-admin__toolbar">
				<div class="quickdonate-inline-code">
					<span><?php esc_html_e( 'Shortcode', 'quickdonate' ); ?></span>
					<code>[quickdonate_popup]</code>
				</div>
			</div>

			<div class="quickdonate-tabs">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a class="quickdonate-tab <?php echo esc_attr( $active === $key ? 'is-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( 'tab', $key, $settings_page ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
				<div class="quickdonate-tabs__spacer"></div>
				<a class="button button-secondary" href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Docs', 'quickdonate' ); ?></a>
			</div>

			<?php settings_errors(); ?>

			<form method="post" action="options.php" class="quickdonate-settings-form">
				<?php settings_fields( QUICKDONATE_SLUG ); ?>

				<div class="quickdonate-tab-panels">
					<!-- General tab: general + donations -->
					<div class="quickdonate-tab-panel <?php echo esc_attr( $active === 'general' ? 'is-active' : '' ); ?>" data-tab="general">
						<div class="quickdonate-grid quickdonate-grid--settings">
							<div class="quickdonate-panel">
								<h2><?php esc_html_e( 'General Settings', 'quickdonate' ); ?></h2>
								<p class="quickdonate-panel__intro"><?php esc_html_e( 'Control whether donations are available and how the main popup behaves.', 'quickdonate' ); ?></p>
								<?php
								$this->render_toggle_field( 'donations_enabled', __( 'Enable donations', 'quickdonate' ), $settings['donations_enabled'], __( 'Turn the donation popup on or off site-wide without removing your shortcode.', 'quickdonate' ) );
								$this->render_text_field( 'button_label', __( 'Button label', 'quickdonate' ), $settings['button_label'], __( 'Shown on the donation trigger button.', 'quickdonate' ) );
								$this->render_currency_field( 'currency', __( 'Donation currency', 'quickdonate' ), $settings['currency'] );
								?>
							</div>

							<div class="quickdonate-panel">
								<h2><?php esc_html_e( 'Donations', 'quickdonate' ); ?></h2>
								<p class="quickdonate-panel__intro"><?php esc_html_e( 'Set your available donation amounts and validation rules.', 'quickdonate' ); ?></p>
								<?php
								$this->render_text_field( 'preset_amounts', __( 'Preset amounts', 'quickdonate' ), $settings['preset_amounts'], __( 'Comma-separated values such as 500,1000,2500.', 'quickdonate' ) );
								$this->render_toggle_field( 'allow_custom', __( 'Allow custom amount', 'quickdonate' ), $settings['allow_custom'], __( 'When enabled, donors can enter their own amount.', 'quickdonate' ) );
								$this->render_number_field( 'min_amount', __( 'Minimum amount', 'quickdonate' ), (int) $settings['min_amount'], __( 'Use 0 to leave the minimum open.', 'quickdonate' ) );
								$this->render_number_field( 'max_amount', __( 'Maximum amount', 'quickdonate' ), (int) $settings['max_amount'], __( 'Use 0 to allow any amount above the minimum.', 'quickdonate' ) );
								?>
							</div>
						</div>
					</div>

					<!-- Emails tab -->
					<div class="quickdonate-tab-panel <?php echo esc_attr( $active === 'emails' ? 'is-active' : '' ); ?>" data-tab="emails">
						<div class="quickdonate-grid quickdonate-grid--settings">
							<div class="quickdonate-panel">
								<h2><?php esc_html_e( 'Emails', 'quickdonate' ); ?></h2>
								<p class="quickdonate-panel__intro"><?php esc_html_e( 'Thank-you emails are sent only after server-side payment verification succeeds.', 'quickdonate' ); ?></p>
								<?php
								$this->render_toggle_field( 'email_enabled', __( 'Enable thank-you email', 'quickdonate' ), $settings['email_enabled'], __( 'Disabled by default until you are ready to send donor emails.', 'quickdonate' ) );
								$this->render_text_field( 'email_from_name', __( 'Sender name', 'quickdonate' ), $settings['email_from_name'] );
								$this->render_text_field( 'email_from_email', __( 'Sender email', 'quickdonate' ), $settings['email_from_email'] );
								$this->render_text_field( 'email_subject', __( 'Thank-you email subject', 'quickdonate' ), $settings['email_subject'] );
								$this->render_textarea_field( 'email_body', __( 'Thank-you email body', 'quickdonate' ), $settings['email_body'], 8, __( 'Available placeholders: {amount}, {currency}, {email}, {reference}, {site_name}', 'quickdonate' ) );
								?>
							</div>
						</div>
					</div>

					<!-- Gateways tab -->
					<div class="quickdonate-tab-panel <?php echo esc_attr( $active === 'gateways' ? 'is-active' : '' ); ?>" data-tab="gateways">
						<div class="quickdonate-grid quickdonate-grid--settings">
							<div class="quickdonate-panel">
								<h2><?php esc_html_e( 'Gateways', 'quickdonate' ); ?></h2>
								<p class="quickdonate-panel__intro"><?php esc_html_e( 'QuickDonate is gateway-ready. Paystack is the first production gateway available today.', 'quickdonate' ); ?></p>
								<?php $this->render_gateway_field( $settings ); ?>
								<div class="quickdonate-gateway-card quickdonate-gateway-card--active">
									<div>
										<strong><?php esc_html_e( 'Paystack', 'quickdonate' ); ?></strong>
										<p><?php esc_html_e( 'Enabled and fully supported for checkout and verification.', 'quickdonate' ); ?></p>
									</div>
									<span class="quickdonate-badge quickdonate-badge--success"><?php esc_html_e( 'Active', 'quickdonate' ); ?></span>
								</div>
							</div>

							<div class="quickdonate-panel">
								<h2><?php esc_html_e( 'Keys & Mode', 'quickdonate' ); ?></h2>
								<p class="quickdonate-panel__intro"><?php esc_html_e( 'Enter your Paystack keys and choose test or live mode.', 'quickdonate' ); ?></p>
								<?php
								$this->render_mode_field( 'mode', __( 'Mode', 'quickdonate' ), $settings['mode'] );
								$this->render_text_field( 'public_key_test', __( 'Test public key', 'quickdonate' ), $settings['public_key_test'] );
								$this->render_password_field( 'secret_key_test', __( 'Test secret key', 'quickdonate' ), $settings['secret_key_test'] );
								$this->render_text_field( 'public_key_live', __( 'Live public key', 'quickdonate' ), $settings['public_key_live'] );
								$this->render_password_field( 'secret_key_live', __( 'Live secret key', 'quickdonate' ), $settings['secret_key_live'] );
								?>
							</div>
						</div>
					</div>

					<!-- Logs tab -->
					<div class="quickdonate-tab-panel <?php echo esc_attr( $active === 'logs' ? 'is-active' : '' ); ?>" data-tab="logs">
						<div class="quickdonate-grid quickdonate-grid--settings">
							<div class="quickdonate-panel">
								<h2><?php esc_html_e( 'Logs', 'quickdonate' ); ?></h2>
								<p class="quickdonate-panel__intro"><?php esc_html_e( 'QuickDonate records donation attempts and verification outcomes. View your full log for detailed entries.', 'quickdonate' ); ?></p>
								<div class="quickdonate-help-links">
									<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . QUICKDONATE_SLUG . '-logs' ) ); ?>"><?php esc_html_e( 'Open donation log', 'quickdonate' ); ?></a>
									<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . QUICKDONATE_SLUG . '-overview' ) ); ?>"><?php esc_html_e( 'Open dashboard', 'quickdonate' ); ?></a>
								</div>
								<ul class="quickdonate-checklist">
									<li><?php esc_html_e( 'No secret key is exposed in frontend markup or JavaScript.', 'quickdonate' ); ?></li>
								</ul>
							</div>
						</div>
					</div>

					<!-- Advanced tab -->
					<div class="quickdonate-tab-panel <?php echo esc_attr( $active === 'advanced' ? 'is-active' : '' ); ?>" data-tab="advanced">
						<div class="quickdonate-grid quickdonate-grid--settings">
							<div class="quickdonate-panel">
								<h2><?php esc_html_e( 'Advanced', 'quickdonate' ); ?></h2>
								<p class="quickdonate-panel__intro"><?php esc_html_e( 'Optional redirects and thank-you messaging after a verified successful donation.', 'quickdonate' ); ?></p>
								<?php
								$this->render_page_dropdown_field( 'success_page_id', __( 'Success page', 'quickdonate' ), (int) $settings['success_page_id'], __( 'Optional redirect after a verified successful donation.', 'quickdonate' ) );
								$this->render_page_dropdown_field( 'failure_page_id', __( 'Failure page', 'quickdonate' ), (int) $settings['failure_page_id'], __( 'Optional redirect after a cancelled or failed checkout.', 'quickdonate' ) );
								$this->render_textarea_field( 'thankyou_message', __( 'Thank-you message', 'quickdonate' ), $settings['thankyou_message'], 4, __( 'Displayed after a verified successful payment.', 'quickdonate' ) );
								?>
							</div>

							<div class="quickdonate-panel">
								<h2><?php esc_html_e( 'Help & Documentation', 'quickdonate' ); ?></h2>
								<p class="quickdonate-panel__intro"><?php esc_html_e( 'Need guidance? Read the bundled docs for configuration tips and details.', 'quickdonate' ); ?></p>
								<a class="button button-secondary" href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open documentation', 'quickdonate' ); ?></a>
							</div>
						</div>
					</div>
				</div>

				<div class="quickdonate-admin__actions">
					<?php submit_button( __( 'Save settings', 'quickdonate' ), 'primary', 'submit', false ); ?>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the overview page.
	 *
	 * @return void
	 */
	public function render_overview_page() {
		$this->check_permissions();

		$summary = QuickDonate_Logger::get_summary();
		?>
		<div class="wrap quickdonate-admin">
			<?php $this->render_page_header( __( 'Dashboard', 'quickdonate' ), __( 'Overview of your donation activity and performance.', 'quickdonate' ) ); ?>

			<div class="quickdonate-stats-grid">
				<?php
				$this->render_stat_card( __( 'Total donations', 'quickdonate' ), number_format_i18n( $summary['total_count'] ), 'primary' );
				$this->render_stat_card( __( 'Successful donations', 'quickdonate' ), number_format_i18n( $summary['success_count'] ), 'success' );
				$this->render_stat_card( __( 'Total raised', 'quickdonate' ), esc_html( $summary['currency'] . ' ' . number_format_i18n( $summary['total_raised'], 2 ) ), 'accent' );
				$this->render_stat_card( __( 'Average donation', 'quickdonate' ), esc_html( $summary['currency'] . ' ' . number_format_i18n( $summary['average_donation'], 2 ) ), 'neutral' );
				?>
			</div>

			<div class="quickdonate-panel quickdonate-panel--table">
				<div class="quickdonate-panel__heading-row">
					<div>
						<h2><?php esc_html_e( 'Recent donations', 'quickdonate' ); ?></h2>
						<p class="quickdonate-panel__intro"><?php esc_html_e( 'Most recent successful payments recorded by the plugin.', 'quickdonate' ); ?></p>
					</div>
					<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . QUICKDONATE_SLUG . '-logs' ) ); ?>"><?php esc_html_e( 'View full log', 'quickdonate' ); ?></a>
				</div>

				<?php if ( empty( $summary['recent'] ) ) : ?>
					<div class="quickdonate-empty-state">
						<h3><?php esc_html_e( 'No verified donations yet', 'quickdonate' ); ?></h3>
						<p><?php esc_html_e( 'Once successful payments are verified, they will appear here automatically.', 'quickdonate' ); ?></p>
					</div>
				<?php else : ?>
					<div class="quickdonate-table-wrap">
						<table class="quickdonate-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Donor / Email', 'quickdonate' ); ?></th>
									<th><?php esc_html_e( 'Amount', 'quickdonate' ); ?></th>
									<th><?php esc_html_e( 'Type', 'quickdonate' ); ?></th>
									<th><?php esc_html_e( 'Gateway', 'quickdonate' ); ?></th>
									<th><?php esc_html_e( 'Date', 'quickdonate' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $summary['recent'] as $row ) : ?>
									<tr>
										<td>
											<div class="quickdonate-table__primary"><?php esc_html_e( 'Guest donor', 'quickdonate' ); ?></div>
											<div class="quickdonate-table__secondary"><?php echo esc_html( $row->donor_email ); ?></div>
										</td>
										<td><?php echo esc_html( $row->currency . ' ' . number_format_i18n( $row->amount, 2 ) ); ?></td>
										<td><?php $this->render_amount_type_badge( $row->amount_type ?? 'preset' ); ?></td>
										<td><?php $this->render_gateway_badge( $row->gateway ?? 'paystack' ); ?></td>
										<td><?php echo esc_html( $row->created_at ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the donation log page.
	 *
	 * @return void
	 */
	public function render_log_page() {
		$this->check_permissions();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Status filter is navigational, not form processing.
		$status_filter    = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$allowed_statuses = array( '', 'success', 'failed', 'pending' );

		if ( ! in_array( $status_filter, $allowed_statuses, true ) ) {
			$status_filter = '';
		}

		$per_page  = 50;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Pagination is navigational, not form processing.
		$page      = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$offset    = ( $page - 1 ) * $per_page;
		$total     = QuickDonate_Logger::get_count( $status_filter );
		$pages     = $total > 0 ? (int) ceil( $total / $per_page ) : 1;
		$donations = QuickDonate_Logger::get_donations(
			array(
				'limit'  => $per_page,
				'offset' => $offset,
				'status' => $status_filter,
			)
		);

		$status_counts = QuickDonate_Logger::get_status_counts();
		$base_url      = add_query_arg( 'page', QUICKDONATE_SLUG . '-logs', admin_url( 'admin.php' ) );
		?>
		<div class="wrap quickdonate-admin">
			<?php $this->render_page_header( __( 'Donation Log', 'quickdonate' ), __( 'Review donation attempts, verification outcomes, and the exact payment references stored by the plugin.', 'quickdonate' ) ); ?>

			<div class="quickdonate-panel quickdonate-panel--table">
				<div class="quickdonate-panel__heading-row">
					<div>
						<h2><?php esc_html_e( 'All donations', 'quickdonate' ); ?></h2>
						<p class="quickdonate-panel__intro"><?php esc_html_e( 'Entries are updated when the active gateway verification succeeds or fails.', 'quickdonate' ); ?></p>
					</div>
					<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . QUICKDONATE_SLUG . '-settings' ) ); ?>"><?php esc_html_e( 'Back to settings', 'quickdonate' ); ?></a>
				</div>

				<div class="quickdonate-filter-tabs">
					<?php echo wp_kses_post( $this->get_filter_links( $base_url, $status_counts, $status_filter ) ); ?>
				</div>

				<?php if ( empty( $donations ) && 1 === $page ) : ?>
					<div class="quickdonate-empty-state">
						<h3><?php esc_html_e( 'No donations recorded yet', 'quickdonate' ); ?></h3>
						<p><?php esc_html_e( 'Your donation log will start filling as soon as donors open checkout and transactions are verified.', 'quickdonate' ); ?></p>
					</div>
				<?php else : ?>
					<p class="quickdonate-table-meta">
						<?php
						printf(
							/* translators: 1: start number, 2: end number, 3: total donations */
							esc_html__( 'Showing %1$s-%2$s of %3$s donations.', 'quickdonate' ),
							esc_html( number_format_i18n( $offset + 1 ) ),
							esc_html( number_format_i18n( min( $offset + $per_page, $total ) ) ),
							esc_html( number_format_i18n( $total ) )
						);
						?>
					</p>

					<div class="quickdonate-table-wrap">
						<table class="quickdonate-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Donor / Email', 'quickdonate' ); ?></th>
									<th><?php esc_html_e( 'Amount', 'quickdonate' ); ?></th>
									<th><?php esc_html_e( 'Currency', 'quickdonate' ); ?></th>
									<th><?php esc_html_e( 'Amount type', 'quickdonate' ); ?></th>
									<th><?php esc_html_e( 'Gateway', 'quickdonate' ); ?></th>
									<th><?php esc_html_e( 'Reference', 'quickdonate' ); ?></th>
									<th><?php esc_html_e( 'Status', 'quickdonate' ); ?></th>
									<th><?php esc_html_e( 'Date', 'quickdonate' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $donations as $row ) : ?>
									<tr>
										<td>
											<div class="quickdonate-table__primary"><?php esc_html_e( 'Guest donor', 'quickdonate' ); ?></div>
											<div class="quickdonate-table__secondary"><?php echo esc_html( $row->donor_email ); ?></div>
										</td>
										<td><?php echo esc_html( number_format_i18n( $row->amount, 2 ) ); ?></td>
										<td><?php echo esc_html( $row->currency ); ?></td>
										<td><?php $this->render_amount_type_badge( $row->amount_type ?? 'preset' ); ?></td>
										<td><?php $this->render_gateway_badge( $row->gateway ?? 'paystack' ); ?></td>
										<td><code><?php echo esc_html( $row->reference ); ?></code></td>
										<td><?php $this->render_status_badge( $row->status ); ?></td>
										<td><?php echo esc_html( $row->created_at ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>

					<?php if ( $pages > 1 ) : ?>
						<div class="quickdonate-pagination">
							<?php if ( $page > 1 ) : ?>
								<a class="button button-secondary" href="<?php echo esc_url( add_query_arg( 'paged', $page - 1, $base_url ) ); ?>"><?php esc_html_e( 'Previous', 'quickdonate' ); ?></a>
							<?php endif; ?>
							<span><?php printf(
							/* translators: 1: current page number, 2: total pages */
							esc_html__( 'Page %1$s of %2$s', 'quickdonate' ), esc_html( number_format_i18n( $page ) ), esc_html( number_format_i18n( $pages ) ) ); ?></span>
							<?php if ( $page < $pages ) : ?>
								<a class="button button-secondary" href="<?php echo esc_url( add_query_arg( 'paged', $page + 1, $base_url ) ); ?>"><?php esc_html_e( 'Next', 'quickdonate' ); ?></a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Check access.
	 *
	 * @return void
	 */
	private function check_permissions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'quickdonate' ) );
		}
	}

	/**
	 * Render a page header with logo and docs shortcut.
	 *
	 * @param string $title       Title text.
	 * @param string $description Description text.
	 * @return void
	 */
	private function render_page_header( $title, $description ) {
		$docs_url = plugins_url( 'docs/overview.md', QUICKDONATE_FILE );
		?>
		<div class="quickdonate-page-header">
			<div class="quickdonate-page-header__row">
				<div class="quickdonate-page-header__brand">
					<div class="quickdonate-page-header__logo" aria-hidden="true">
						<img class="quickdonate-page-header__logo-img" src="<?php echo esc_url( QUICKDONATE_URL . 'assets/img/quickdonate-icon.png' ); ?>" alt="<?php echo esc_attr__( 'QuickDonate icon', 'quickdonate' ); ?>" width="24" height="24" />
					</div>
					<div>
						<p class="quickdonate-eyebrow"><?php esc_html_e( 'QuickDonate', 'quickdonate' ); ?></p>
						<h1><?php echo esc_html( $title ); ?></h1>
						<p><?php echo esc_html( $description ); ?></p>
					</div>
				</div>
				<div class="quickdonate-page-header__actions">
					<a class="button button-secondary" href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Docs', 'quickdonate' ); ?></a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a statistic card (with tone color).
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param string $tone  Visual tone.
	 * @return void
	 */
	private function render_stat_card( $label, $value, $tone ) {
		?>
		<div class="quickdonate-stat-card quickdonate-stat-card--<?php echo esc_attr( $tone ); ?>">
			<span class="quickdonate-stat-card__icon quickdonate-stat-card__icon--<?php echo esc_attr( $tone ); ?>" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5A5.5 5.5 0 0 1 12 5.09 5.5 5.5 0 0 1 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg></span>
			<span class="quickdonate-stat-card__label"><?php echo esc_html( $label ); ?></span>
			<strong class="quickdonate-stat-card__value"><?php echo esc_html( $value ); ?></strong>
		</div>
		<?php
	}

	/**
	 * Render a text field.
	 *
	 * @param string $key         Option key.
	 * @param string $label       Field label.
	 * @param string $value       Field value.
	 * @param string $description Optional description.
	 * @return void
	 */
	private function render_text_field( $key, $label, $value, $description = '' ) {
		$this->render_field_wrapper_start( $label, $description );
		printf(
			'<input type="text" class="regular-text quickdonate-input" name="%1$s[%2$s]" id="%2$s" value="%3$s" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $key ),
			esc_attr( $value )
		);
		$this->render_field_wrapper_end();
	}

	/**
	 * Render a password field.
	 *
	 * @param string $key   Option key.
	 * @param string $label Field label.
	 * @param string $value Field value.
	 * @return void
	 */
	private function render_password_field( $key, $label, $value ) {
		$this->render_field_wrapper_start( $label, '' );
		printf(
			'<input type="password" class="regular-text quickdonate-input" name="%1$s[%2$s]" id="%2$s" value="%3$s" autocomplete="new-password" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $key ),
			esc_attr( $value )
		);
		$this->render_field_wrapper_end();
	}

	/**
	 * Render a number field.
	 *
	 * @param string $key         Option key.
	 * @param string $label       Field label.
	 * @param int    $value       Field value.
	 * @param string $description Optional description.
	 * @return void
	 */
	private function render_number_field( $key, $label, $value, $description = '' ) {
		$this->render_field_wrapper_start( $label, $description );
		printf(
			'<input type="number" min="0" class="small-text quickdonate-input quickdonate-input--small" name="%1$s[%2$s]" id="%2$s" value="%3$s" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $key ),
			esc_attr( (string) $value )
		);
		$this->render_field_wrapper_end();
	}

	/**
	 * Render a textarea field.
	 *
	 * @param string $key         Option key.
	 * @param string $label       Field label.
	 * @param string $value       Field value.
	 * @param int    $rows        Rows.
	 * @param string $description Optional description.
	 * @return void
	 */
	private function render_textarea_field( $key, $label, $value, $rows = 4, $description = '' ) {
		$this->render_field_wrapper_start( $label, $description );
		printf(
			'<textarea class="large-text quickdonate-input quickdonate-input--textarea" rows="%4$d" name="%1$s[%2$s]" id="%2$s">%3$s</textarea>',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $key ),
			esc_textarea( $value ),
			(int) $rows
		);
		$this->render_field_wrapper_end();
	}

	/**
	 * Render a toggle field.
	 *
	 * @param string $key         Option key.
	 * @param string $label       Field label.
	 * @param string $value       Saved value.
	 * @param string $description Optional description.
	 * @return void
	 */
	private function render_toggle_field( $key, $label, $value, $description = '' ) {
		$this->render_field_wrapper_start( $label, $description );
		?>
		<label class="quickdonate-switch">
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( '1', $value ); ?> />
			<span class="quickdonate-switch__track"></span>
			<span class="quickdonate-switch__label"><?php esc_html_e( 'Enabled', 'quickdonate' ); ?></span>
		</label>
		<?php
		$this->render_field_wrapper_end();
	}

	/**
	 * Render the currency select.
	 *
	 * @param string $key   Option key.
	 * @param string $label Field label.
	 * @param string $value Saved value.
	 * @return void
	 */
	private function render_currency_field( $key, $label, $value ) {
		$currencies = array(
			'NGN' => 'NGN - Nigerian Naira',
			'GHS' => 'GHS - Ghanaian Cedi',
			'ZAR' => 'ZAR - South African Rand',
			'KES' => 'KES - Kenyan Shilling',
			'USD' => 'USD - US Dollar',
			'GBP' => 'GBP - British Pound',
			'EUR' => 'EUR - Euro',
		);

		$this->render_field_wrapper_start( $label, '' );
		echo '<select class="quickdonate-input quickdonate-select" name="' . esc_attr( self::OPTION_NAME ) . '[' . esc_attr( $key ) . ']">';
		foreach ( $currencies as $code => $currency_label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $code ), selected( $value, $code, false ), esc_html( $currency_label ) );
		}
		echo '</select>';
		$this->render_field_wrapper_end();
	}

	/**
	 * Render the gateway selector.
	 *
	 * @param array $settings Plugin settings.
	 * @return void
	 */
	private function render_gateway_field( $settings ) {
		$gateways = QuickDonate_Plugin::get_gateways();

		$this->render_field_wrapper_start( __( 'Active gateway', 'quickdonate' ), __( 'More gateways can be added later without changing your popup shortcode.', 'quickdonate' ) );
		echo '<select class="quickdonate-input quickdonate-select" name="' . esc_attr( self::OPTION_NAME ) . '[active_gateway]">';
		foreach ( $gateways as $gateway ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $gateway->get_id() ), selected( $settings['active_gateway'], $gateway->get_id(), false ), esc_html( $gateway->get_label() ) );
		}
		echo '</select>';
		$this->render_field_wrapper_end();
	}

	/**
	 * Render mode radio buttons.
	 *
	 * @param string $key   Option key.
	 * @param string $label Field label.
	 * @param string $value Saved value.
	 * @return void
	 */
	private function render_mode_field( $key, $label, $value ) {
		$this->render_field_wrapper_start( $label, __( 'Use test mode while validating your checkout flow.', 'quickdonate' ) );
		?>
		<div class="quickdonate-segmented-control">
			<label><input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]" value="test" <?php checked( 'test', $value ); ?> /> <span><?php esc_html_e( 'Test', 'quickdonate' ); ?></span></label>
			<label><input type="radio" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]" value="live" <?php checked( 'live', $value ); ?> /> <span><?php esc_html_e( 'Live', 'quickdonate' ); ?></span></label>
		</div>
		<?php
		$this->render_field_wrapper_end();
	}

	/**
	 * Render a page dropdown.
	 *
	 * @param string $key         Option key.
	 * @param string $label       Field label.
	 * @param int    $value       Saved page ID.
	 * @param string $description Optional description.
	 * @return void
	 */
	private function render_page_dropdown_field( $key, $label, $value, $description = '' ) {
		$this->render_field_wrapper_start( $label, $description );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_dropdown_pages() handles its own output escaping internally.
		wp_dropdown_pages(
			array(
				'name'              => esc_attr( self::OPTION_NAME . '[' . $key . ']' ),
				'selected'          => $value,
				'show_option_none'  => __( 'No redirect', 'quickdonate' ),
				'option_none_value' => 0,
				'class'             => 'quickdonate-input quickdonate-select',
			)
		);
		$this->render_field_wrapper_end();
	}

	/**
	 * Render field wrapper start.
	 *
	 * @param string $label       Field label.
	 * @param string $description Optional description.
	 * @return void
	 */
	private function render_field_wrapper_start( $label, $description ) {
		?>
		<div class="quickdonate-field">
			<label class="quickdonate-field__label"><?php echo esc_html( $label ); ?></label>
			<div class="quickdonate-field__control">
		<?php if ( '' !== $description ) : ?>
			<p class="quickdonate-field__description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render field wrapper end.
	 *
	 * @return void
	 */
	private function render_field_wrapper_end() {
		echo '</div></div>';
	}

	/**
	 * Render a donation status badge.
	 *
	 * @param string $status Status.
	 * @return void
	 */
	private function render_status_badge( $status ) {
		$status = in_array( $status, array( 'success', 'failed', 'pending' ), true ) ? $status : 'pending';
		printf( '<span class="quickdonate-badge quickdonate-badge--%1$s">%2$s</span>', esc_attr( $status ), esc_html( ucfirst( $status ) ) );
	}

	/**
	 * Render a donation type badge.
	 *
	 * @param string $type Amount type.
	 * @return void
	 */
	private function render_amount_type_badge( $type ) {
		$type = 'custom' === $type ? 'custom' : 'preset';
		printf( '<span class="quickdonate-badge quickdonate-badge--type-%1$s">%2$s</span>', esc_attr( $type ), esc_html( ucfirst( $type ) ) );
	}

	/**
	 * Render a gateway badge.
	 *
	 * @param string $gateway Gateway ID.
	 * @return void
	 */
	private function render_gateway_badge( $gateway ) {
		$gateway = sanitize_key( $gateway );
		printf( '<span class="quickdonate-badge quickdonate-badge--gateway">%s</span>', esc_html( ucfirst( $gateway ) ) );
	}

	/**
	 * Build filter links.
	 *
	 * @param string $base_url       Base URL.
	 * @param array  $status_counts  Counts.
	 * @param string $status_filter  Current filter.
	 * @return string
	 */
	private function get_filter_links( $base_url, $status_counts, $status_filter ) {
		$filters = array(
			''        => array( __( 'All', 'quickdonate' ), $status_counts['total'] ),
			'success' => array( __( 'Successful', 'quickdonate' ), $status_counts['success'] ),
			'failed'  => array( __( 'Failed', 'quickdonate' ), $status_counts['failed'] ),
			'pending' => array( __( 'Pending', 'quickdonate' ), $status_counts['pending'] ),
		);

		$links = array();

		foreach ( $filters as $filter_value => $data ) {
			$url    = $filter_value ? add_query_arg( 'status', $filter_value, $base_url ) : $base_url;
			$class  = $status_filter === $filter_value ? 'quickdonate-filter-tab quickdonate-filter-tab--current' : 'quickdonate-filter-tab';
			$links[] = sprintf(
				'<a href="%1$s" class="%2$s">%3$s <span>(%4$s)</span></a>',
				esc_url( $url ),
				esc_attr( $class ),
				esc_html( $data[0] ),
				esc_html( number_format_i18n( $data[1] ) )
			);
		}

		return implode( '', $links );
	}
}
