<?php
/**
 * Donation popup template.
 *
 * @package QuickDonate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

static $quickdonate_instance_count = 0;
$quickdonate_instance_count++;

$quickdonate_uid          = 'qd-' . $quickdonate_instance_count;
$quickdonate_currency     = $settings['currency'] ?? 'NGN';
$quickdonate_allow_custom = isset( $settings['allow_custom'] ) && '1' === $settings['allow_custom'];
?>
<div class="quickdonate-wrap" id="<?php echo esc_attr( $quickdonate_uid . '-wrap' ); ?>">
	<button
		type="button"
		class="quickdonate-btn"
		id="<?php echo esc_attr( $quickdonate_uid . '-trigger' ); ?>"
		aria-haspopup="dialog"
		aria-controls="<?php echo esc_attr( $quickdonate_uid . '-modal' ); ?>"
	>
		<span class="quickdonate-btn__icon" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5A5.5 5.5 0 0 1 12 5.09 5.5 5.5 0 0 1 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
		</span>
		<span><?php echo esc_html( $button_label ); ?></span>
	</button>

	<div
		class="quickdonate-overlay"
		id="<?php echo esc_attr( $quickdonate_uid . '-modal' ); ?>"
		role="dialog"
		aria-modal="true"
		aria-labelledby="<?php echo esc_attr( $quickdonate_uid . '-title' ); ?>"
		aria-hidden="true"
		data-instance="<?php echo esc_attr( $quickdonate_uid ); ?>"
		hidden
	>
		<div class="quickdonate-modal" role="document">
			<div class="quickdonate-modal__header">
				<div class="quickdonate-modal__brand">
					<div class="quickdonate-modal__logo" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5A5.5 5.5 0 0 1 12 5.09 5.5 5.5 0 0 1 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
					</div>
					<div>
						<p class="quickdonate-modal__eyebrow"><?php esc_html_e( 'QuickDonate', 'quickdonate' ); ?></p>
						<h2 class="quickdonate-modal__title" id="<?php echo esc_attr( $quickdonate_uid . '-title' ); ?>"><?php esc_html_e( 'Support our mission', 'quickdonate' ); ?></h2>
						<p class="quickdonate-modal__subtitle"><?php esc_html_e( 'Choose an amount, enter your email, and complete your secure donation in a few clicks.', 'quickdonate' ); ?></p>
					</div>
				</div>
				<button type="button" class="quickdonate-modal__close" aria-label="<?php esc_attr_e( 'Close donation form', 'quickdonate' ); ?>" data-close="<?php echo esc_attr( $quickdonate_uid ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
				</button>
			</div>

			<div class="quickdonate-modal__body">
				<div class="quickdonate-card quickdonate-card--form">
					<div class="quickdonate-step">
						<label class="quickdonate-label"><?php esc_html_e( 'Choose an amount', 'quickdonate' ); ?></label>
						<div class="quickdonate-presets" id="<?php echo esc_attr( $quickdonate_uid . '-presets' ); ?>" role="group" aria-label="<?php esc_attr_e( 'Preset donation amounts', 'quickdonate' ); ?>"></div>
					</div>

					<?php if ( $quickdonate_allow_custom ) : ?>
						<div class="quickdonate-step">
							<label class="quickdonate-label" for="<?php echo esc_attr( $quickdonate_uid . '-custom' ); ?>"><?php esc_html_e( 'Or enter a custom amount', 'quickdonate' ); ?></label>
							<div class="quickdonate-input-group">
								<span class="quickdonate-currency"><?php echo esc_html( $quickdonate_currency ); ?></span>
								<input type="number" id="<?php echo esc_attr( $quickdonate_uid . '-custom' ); ?>" class="quickdonate-input quickdonate-input--amount" min="1" step="1" placeholder="0" aria-label="<?php esc_attr_e( 'Custom donation amount', 'quickdonate' ); ?>" />
							</div>
						</div>
					<?php endif; ?>

					<div class="quickdonate-step">
						<label class="quickdonate-label" for="<?php echo esc_attr( $quickdonate_uid . '-email' ); ?>"><?php esc_html_e( 'Your email address', 'quickdonate' ); ?></label>
						<input type="email" id="<?php echo esc_attr( $quickdonate_uid . '-email' ); ?>" class="quickdonate-input quickdonate-input--email" placeholder="you@example.com" autocomplete="email" aria-required="true" />
					</div>

					<div class="quickdonate-alert" id="<?php echo esc_attr( $quickdonate_uid . '-alert' ); ?>" role="alert" aria-live="polite" hidden></div>

					<div class="quickdonate-modal__footer">
						<button
							type="button"
							class="quickdonate-submit-btn"
							id="<?php echo esc_attr( $quickdonate_uid . '-submit' ); ?>"
							data-instance="<?php echo esc_attr( $quickdonate_uid ); ?>"
							data-email-id="<?php echo esc_attr( $quickdonate_uid . '-email' ); ?>"
							data-alert-id="<?php echo esc_attr( $quickdonate_uid . '-alert' ); ?>"
						>
							<span class="quickdonate-submit-btn__text"><?php esc_html_e( 'Continue to Checkout', 'quickdonate' ); ?></span>
							<span class="quickdonate-submit-btn__spinner" aria-hidden="true"></span>
						</button>
						<p class="quickdonate-secure-note">
							<span class="quickdonate-secure-note__icon" aria-hidden="true">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
							</span>
							<?php esc_html_e( 'Secure checkout via Paystack', 'quickdonate' ); ?>
						</p>
					</div>
				</div>
			</div>

			<div class="quickdonate-success" id="<?php echo esc_attr( $quickdonate_uid . '-success' ); ?>" hidden>
				<div class="quickdonate-success__icon" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52" fill="none" stroke-width="3"><circle cx="26" cy="26" r="25" stroke="currentColor" fill="none"/><path stroke="currentColor" d="M14.1 27.2l7.1 7.2 16.7-16.8"/></svg>
				</div>
				<h3 class="quickdonate-success__heading" tabindex="-1"><?php esc_html_e( 'Thank you', 'quickdonate' ); ?></h3>
				<div class="quickdonate-success__message" id="<?php echo esc_attr( $quickdonate_uid . '-thankyou' ); ?>"></div>
				<button type="button" class="quickdonate-success__close" data-close="<?php echo esc_attr( $quickdonate_uid ); ?>"><?php esc_html_e( 'Close', 'quickdonate' ); ?></button>
			</div>
		</div>
	</div>
</div>
