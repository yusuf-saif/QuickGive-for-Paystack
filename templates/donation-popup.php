<?php
/**
 * Donation popup modal template.
 *
 * Variables available from class-quickgive-shortcode.php:
 *   @var array  $opts         Plugin settings.
 *   @var string $button_label Donation button label.
 *   @var string $public_key   Paystack public key (safe to include in HTML data attribute).
 *
 * This file must not be accessed directly.
 *
 * @package QuickGive_For_Paystack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Generate a unique ID so multiple shortcodes on a page don't conflict.
static $instance_count = 0;
$instance_count++;
$uid = 'qg-' . $instance_count;

$currency     = $opts['currency'] ?? 'NGN';
$allow_custom = isset( $opts['allow_custom'] ) && '1' === $opts['allow_custom'];
?>
<!-- QuickGive Donation Button -->
<div class="quickgive-wrap" id="<?php echo esc_attr( $uid . '-wrap' ); ?>">

	<button
		type="button"
		class="quickgive-btn"
		id="<?php echo esc_attr( $uid . '-trigger' ); ?>"
		aria-haspopup="dialog"
		aria-controls="<?php echo esc_attr( $uid . '-modal' ); ?>"
	>
		<svg class="quickgive-btn__icon" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
			<path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5A5.5 5.5 0 0 1 12 5.09 5.5 5.5 0 0 1 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
		</svg>
		<?php echo esc_html( $button_label ); ?>
	</button>

	<!-- Modal Overlay -->
	<div
		class="quickgive-overlay"
		id="<?php echo esc_attr( $uid . '-modal' ); ?>"
		role="dialog"
		aria-modal="true"
		aria-labelledby="<?php echo esc_attr( $uid . '-title' ); ?>"
		aria-hidden="true"
		data-instance="<?php echo esc_attr( $uid ); ?>"
		style="display:none"
	>
		<div class="quickgive-modal" role="document">

			<!-- Header -->
			<div class="quickgive-modal__header">
				<div class="quickgive-modal__logo" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="28" height="28">
						<path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5A5.5 5.5 0 0 1 12 5.09 5.5 5.5 0 0 1 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
					</svg>
				</div>
				<h2 class="quickgive-modal__title" id="<?php echo esc_attr( $uid . '-title' ); ?>">
					<?php esc_html_e( 'Make a Donation', 'quickgive-for-paystack' ); ?>
				</h2>
				<button
					type="button"
					class="quickgive-modal__close"
					aria-label="<?php esc_attr_e( 'Close donation form', 'quickgive-for-paystack' ); ?>"
					data-close="<?php echo esc_attr( $uid ); ?>"
				>
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20" aria-hidden="true">
						<line x1="18" y1="6" x2="6" y2="18"/>
						<line x1="6" y1="6" x2="18" y2="18"/>
					</svg>
				</button>
			</div>

			<!-- Body -->
			<div class="quickgive-modal__body">

				<!-- Step 1: Choose amount -->
				<div class="quickgive-step quickgive-step--amount" id="<?php echo esc_attr( $uid . '-step-amount' ); ?>">
					<p class="quickgive-label"><?php esc_html_e( 'Choose an amount', 'quickgive-for-paystack' ); ?></p>

					<div class="quickgive-presets" id="<?php echo esc_attr( $uid . '-presets' ); ?>" role="group" aria-label="<?php esc_attr_e( 'Preset donation amounts', 'quickgive-for-paystack' ); ?>">
						<!-- Populated by JS from quickgiveConfig.presets -->
					</div>

					<?php if ( $allow_custom ) : ?>
					<div class="quickgive-custom" id="<?php echo esc_attr( $uid . '-custom-wrap' ); ?>">
						<label class="quickgive-label" for="<?php echo esc_attr( $uid . '-custom' ); ?>">
							<?php esc_html_e( 'Or enter custom amount', 'quickgive-for-paystack' ); ?>
						</label>
						<div class="quickgive-input-group">
							<span class="quickgive-currency-badge"><?php echo esc_html( $currency ); ?></span>
							<input
								type="number"
								id="<?php echo esc_attr( $uid . '-custom' ); ?>"
								class="quickgive-input quickgive-input--amount"
								min="1"
								step="1"
								placeholder="0"
								aria-label="<?php esc_attr_e( 'Custom donation amount', 'quickgive-for-paystack' ); ?>"
							/>
						</div>
					</div>
					<?php endif; ?>
				</div>

				<!-- Step 2: Email -->
				<div class="quickgive-step quickgive-step--email" id="<?php echo esc_attr( $uid . '-step-email' ); ?>">
					<label class="quickgive-label" for="<?php echo esc_attr( $uid . '-email' ); ?>">
						<?php esc_html_e( 'Your email address', 'quickgive-for-paystack' ); ?>
					</label>
					<input
						type="email"
						id="<?php echo esc_attr( $uid . '-email' ); ?>"
						class="quickgive-input quickgive-input--email"
						placeholder="you@example.com"
						autocomplete="email"
						aria-required="true"
					/>
				</div>

				<!-- Error / status message -->
				<div class="quickgive-alert" id="<?php echo esc_attr( $uid . '-alert' ); ?>" role="alert" aria-live="polite" hidden></div>

				<!-- Footer -->
				<div class="quickgive-modal__footer">
					<button
						type="button"
						class="quickgive-submit-btn"
						id="<?php echo esc_attr( $uid . '-submit' ); ?>"
						data-instance="<?php echo esc_attr( $uid ); ?>"
						data-email-id="<?php echo esc_attr( $uid . '-email' ); ?>"
						data-alert-id="<?php echo esc_attr( $uid . '-alert' ); ?>"
					>
						<span class="quickgive-submit-btn__text">
							<?php esc_html_e( 'Donate', 'quickgive-for-paystack' ); ?>
						</span>
						<span class="quickgive-submit-btn__spinner" aria-hidden="true"></span>
					</button>
					<p class="quickgive-secure-note" aria-label="<?php esc_attr_e( 'Secured by Paystack', 'quickgive-for-paystack' ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="13" height="13" aria-hidden="true"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
						<?php esc_html_e( 'Secured by Paystack', 'quickgive-for-paystack' ); ?>
					</p>
				</div>

			</div><!-- /.quickgive-modal__body -->

			<!-- Success Panel (hidden initially) -->
			<div class="quickgive-success" id="<?php echo esc_attr( $uid . '-success' ); ?>" hidden>
				<div class="quickgive-success__icon" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52" fill="none" stroke-width="3">
						<circle cx="26" cy="26" r="25" stroke="currentColor" fill="none"/>
						<path stroke="currentColor" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
					</svg>
				</div>
				<h3 class="quickgive-success__heading"><?php esc_html_e( 'Thank You!', 'quickgive-for-paystack' ); ?></h3>
				<div class="quickgive-success__message" id="<?php echo esc_attr( $uid . '-thankyou' ); ?>"></div>
				<button
					type="button"
					class="quickgive-success__close"
					data-close="<?php echo esc_attr( $uid ); ?>"
				>
					<?php esc_html_e( 'Close', 'quickgive-for-paystack' ); ?>
				</button>
			</div>

		</div><!-- /.quickgive-modal -->
	</div><!-- /.quickgive-overlay -->

</div><!-- /.quickgive-wrap -->
