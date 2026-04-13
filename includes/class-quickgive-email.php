<?php
/**
 * Donor thank-you email handler for QuickGive for Paystack.
 *
 * Sends a configurable email to the donor after successful server-side
 * payment verification. Supports simple {placeholder} substitution.
 *
 * Placeholders available in subject and body:
 *   {amount}    — Formatted donation amount (e.g. 5,000.00)
 *   {currency}  — ISO currency code (e.g. NGN)
 *   {email}     — Donor email address
 *   {reference} — Paystack transaction reference
 *   {site_name} — WordPress site name
 *
 * @package QuickGive_For_Paystack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles sending thank-you emails to donors.
 */
class QuickGive_Email {

	/**
	 * Default email subject template.
	 */
	const DEFAULT_SUBJECT = 'Thank you for your donation — {site_name}';

	/**
	 * Default email body template.
	 */
	const DEFAULT_BODY = "Hi,\n\nThank you for your generous donation of {currency} {amount}.\nYour support means a lot to us.\n\nTransaction reference: {reference}\n\n— {site_name}";

	/**
	 * Send a thank-you email to the donor.
	 *
	 * Called only after the server-side Paystack verification has succeeded.
	 * Never called on the frontend. Never exposes the secret key.
	 *
	 * @param string $donor_email Verified donor email.
	 * @param float  $amount      Amount in main currency units (e.g. 5000.00 for NGN).
	 * @param string $currency    ISO currency code.
	 * @param string $reference   Paystack transaction reference.
	 * @return bool True if email was sent, false otherwise.
	 */
	public static function send( $donor_email, $amount, $currency, $reference ) {
		$opts = get_option( 'quickgive_settings', array() );

		// Bail if email sending is disabled.
		if ( empty( $opts['email_enabled'] ) || '1' !== $opts['email_enabled'] ) {
			return false;
		}

		// Validate the donor email — extra safety check before sending.
		if ( ! is_email( $donor_email ) ) {
			return false;
		}

		// Build placeholder values.
		$placeholders = array(
			'{amount}'    => number_format( (float) $amount, 2 ),
			'{currency}'  => sanitize_text_field( $currency ),
			'{email}'     => sanitize_email( $donor_email ),
			'{reference}' => sanitize_text_field( $reference ),
			'{site_name}' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		);

		// Build subject.
		$raw_subject = ! empty( $opts['email_subject'] )
			? $opts['email_subject']
			: self::DEFAULT_SUBJECT;
		$subject = str_replace(
			array_keys( $placeholders ),
			array_values( $placeholders ),
			sanitize_text_field( $raw_subject )
		);

		// Build body.
		$raw_body = ! empty( $opts['email_body'] )
			? $opts['email_body']
			: self::DEFAULT_BODY;
		$body = str_replace(
			array_keys( $placeholders ),
			array_values( $placeholders ),
			wp_kses_post( $raw_body )
		);

		// Determine From header.
		$from_name  = ! empty( $opts['email_from_name'] )
			? sanitize_text_field( $opts['email_from_name'] )
			: wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$from_email = ! empty( $opts['email_from_email'] ) && is_email( $opts['email_from_email'] )
			? $opts['email_from_email']
			: get_bloginfo( 'admin_email' );

		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
		);

		return wp_mail( $donor_email, $subject, $body, $headers );
	}
}
