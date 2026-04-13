/**
 * QuickGive for Paystack — Frontend JavaScript
 *
 * v1.1 changes:
 *   - Determines `amount_type` ('preset' | 'custom') based on donor selection.
 *   - Sends `amount_type` in the AJAX verification POST.
 *   - Two-way deselection: typing in custom input clears preset selection,
 *     and clicking a preset clears the custom input value.
 *
 * Manages:
 *   1. Modal open / close / accessibility (focus trap, Escape key)
 *   2. Preset amount button selection
 *   3. Custom amount input + mutual deselection with presets
 *   4. Form validation (amount, min/max, email)
 *   5. Paystack popup checkout
 *   6. AJAX server-side verification with amount_type
 *   7. Success / failure display states
 *
 * Depends on:
 *   - quickgiveConfig  (wp_localize_script — public key only, no secret)
 *   - PaystackPop      (Paystack inline SDK from js.paystack.co)
 *
 * @package QuickGive_For_Paystack
 */

/* global quickgiveConfig, PaystackPop */
( function () {
	'use strict';

	const cfg  = window.quickgiveConfig || {};
	const i18n = cfg.i18n || {};

	// -------------------------------------------------------------------------
	// Utility helpers
	// -------------------------------------------------------------------------

	/** Format a number as a currency string (no fraction). */
	function formatAmount( amount, currency ) {
		const symbols = {
			NGN: '₦', GHS: 'GH₵', ZAR: 'R', KES: 'KSh',
			USD: '$',  GBP: '£',   EUR: '€',
		};
		const sym = symbols[ currency ] || ( currency + ' ' );
		return sym + Number( amount ).toLocaleString();
	}

	/** Show an alert inside a panel. */
	function showAlert( alertEl, message, type ) {
		alertEl.textContent = message;
		alertEl.className   = 'quickgive-alert quickgive-alert--' + ( type || 'error' );
		alertEl.removeAttribute( 'hidden' );
	}

	/** Clear alert panel. */
	function clearAlert( alertEl ) {
		alertEl.textContent = '';
		alertEl.setAttribute( 'hidden', '' );
		alertEl.className = 'quickgive-alert';
	}

	/** Set submit button to loading/normal state. */
	function setLoading( btn, isLoading, label ) {
		btn.disabled = isLoading;
		const textEl = btn.querySelector( '.quickgive-submit-btn__text' );
		if ( textEl ) {
			textEl.textContent = isLoading
				? ( label || i18n.processing || 'Processing…' )
				: ( i18n.donate || 'Donate' );
		}
		btn.classList.toggle( 'quickgive-submit-btn--loading', isLoading );
	}

	// -------------------------------------------------------------------------
	// Modal management
	// -------------------------------------------------------------------------

	function openModal( uid ) {
		const overlay = document.getElementById( uid + '-modal' );
		if ( ! overlay ) { return; }
		overlay.removeAttribute( 'aria-hidden' );
		overlay.classList.add( 'quickgive-overlay--visible' );
		document.body.classList.add( 'quickgive-body-lock' );

		const focusTarget = overlay.querySelector( 'button, input, [tabindex]' );
		if ( focusTarget ) {
			setTimeout( function () { focusTarget.focus(); }, 50 );
		}

		overlay._focusTrap = function ( e ) { trapFocus( overlay, e ); };
		overlay.addEventListener( 'keydown', overlay._focusTrap );
	}

	function closeModal( uid ) {
		const overlay = document.getElementById( uid + '-modal' );
		if ( ! overlay ) { return; }
		overlay.setAttribute( 'aria-hidden', 'true' );
		overlay.classList.remove( 'quickgive-overlay--visible' );
		document.body.classList.remove( 'quickgive-body-lock' );

		if ( overlay._focusTrap ) {
			overlay.removeEventListener( 'keydown', overlay._focusTrap );
		}

		const trigger = document.getElementById( uid + '-trigger' );
		if ( trigger ) { trigger.focus(); }
	}

	function trapFocus( container, event ) {
		if ( event.key === 'Escape' ) {
			const uid = container.dataset.instance;
			if ( uid ) { closeModal( uid ); }
			return;
		}
		if ( event.key !== 'Tab' ) { return; }

		const focusable = Array.from(
			container.querySelectorAll(
				'button:not([disabled]), input:not([disabled]), [href], [tabindex]:not([tabindex="-1"])'
			)
		);
		if ( ! focusable.length ) { return; }

		const first = focusable[ 0 ];
		const last  = focusable[ focusable.length - 1 ];

		if ( event.shiftKey && document.activeElement === first ) {
			last.focus();
			event.preventDefault();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			first.focus();
			event.preventDefault();
		}
	}

	// -------------------------------------------------------------------------
	// Preset amount buttons
	// -------------------------------------------------------------------------

	/**
	 * Build preset amount buttons and wire up mutual deselection with custom input.
	 *
	 * @param {HTMLElement} presetsContainer
	 * @param {string}      uid
	 */
	function buildPresets( presetsContainer, uid ) {
		const presets  = cfg.presets  || [];
		const currency = cfg.currency || 'NGN';

		presets.forEach( function ( amount ) {
			const btn = document.createElement( 'button' );
			btn.type               = 'button';
			btn.className          = 'quickgive-preset-btn';
			btn.dataset.amount     = amount;
			btn.setAttribute( 'aria-pressed', 'false' );
			btn.textContent        = formatAmount( amount, currency );
			presetsContainer.appendChild( btn );
		} );

		presetsContainer.addEventListener( 'click', function ( e ) {
			const btn = e.target.closest( '.quickgive-preset-btn' );
			if ( ! btn ) { return; }

			// Deselect all presets.
			presetsContainer.querySelectorAll( '.quickgive-preset-btn' ).forEach( function ( b ) {
				b.classList.remove( 'quickgive-preset-btn--active' );
				b.setAttribute( 'aria-pressed', 'false' );
			} );

			// Select clicked preset.
			btn.classList.add( 'quickgive-preset-btn--active' );
			btn.setAttribute( 'aria-pressed', 'true' );

			// v1.1 — clear custom amount when a preset is chosen.
			const customInput = document.getElementById( uid + '-custom' );
			if ( customInput ) {
				customInput.value = '';
				customInput.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			}
		} );
	}

	// -------------------------------------------------------------------------
	// Custom amount → preset deselection (v1.1)
	// -------------------------------------------------------------------------

	/**
	 * Wire up the custom amount input so that typing deselects any active preset.
	 *
	 * @param {string} uid
	 */
	function wireCustomInput( uid ) {
		const customInput      = document.getElementById( uid + '-custom' );
		const presetsContainer = document.getElementById( uid + '-presets' );

		if ( ! customInput || ! presetsContainer ) { return; }

		customInput.addEventListener( 'input', function () {
			if ( customInput.value.trim() !== '' ) {
				// Deselect all preset buttons.
				presetsContainer.querySelectorAll( '.quickgive-preset-btn' ).forEach( function ( b ) {
					b.classList.remove( 'quickgive-preset-btn--active' );
					b.setAttribute( 'aria-pressed', 'false' );
				} );
			}
		} );
	}

	// -------------------------------------------------------------------------
	// Validation
	// -------------------------------------------------------------------------

	/**
	 * Resolve the chosen donation amount and determine its type.
	 *
	 * Returns an object { amount, amountType } or null if validation fails.
	 *
	 * @param {string}      uid
	 * @param {HTMLElement} alertEl
	 * @returns {{ amount: number, amountType: string }|null}
	 */
	function resolveAmount( uid, alertEl ) {
		const presetsContainer = document.getElementById( uid + '-presets' );
		const customInput      = document.getElementById( uid + '-custom' );
		const activePreset     = presetsContainer && presetsContainer.querySelector( '.quickgive-preset-btn--active' );

		let amount     = 0;
		let amountType = 'preset';

		// Custom input takes priority if it has a non-empty value.
		if ( customInput && customInput.value.trim() !== '' ) {
			amount     = parseFloat( customInput.value );
			amountType = 'custom';
		} else if ( activePreset ) {
			amount     = parseFloat( activePreset.dataset.amount );
			amountType = 'preset';
		}

		if ( ! amount || amount <= 0 ) {
			showAlert( alertEl, i18n.selectAmount || 'Please select or enter a donation amount.', 'error' );
			return null;
		}

		const min = cfg.minAmount || 0;
		const max = cfg.maxAmount || 0;

		if ( min > 0 && amount < min ) {
			showAlert( alertEl, ( i18n.minAmountMsg || 'Minimum donation amount is' ) + ' ' + formatAmount( min, cfg.currency ) + '.', 'error' );
			return null;
		}

		if ( max > 0 && amount > max ) {
			showAlert( alertEl, ( i18n.maxAmountMsg || 'Maximum donation amount is' ) + ' ' + formatAmount( max, cfg.currency ) + '.', 'error' );
			return null;
		}

		return { amount: amount, amountType: amountType };
	}

	/**
	 * Validate email address.
	 *
	 * @param {HTMLInputElement} emailInput
	 * @param {HTMLElement}      alertEl
	 * @returns {string|null} Trimmed valid email, or null.
	 */
	function resolveEmail( emailInput, alertEl ) {
		const email = emailInput ? emailInput.value.trim() : '';
		const re    = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		if ( ! email || ! re.test( email ) ) {
			showAlert( alertEl, i18n.validEmail || 'Please enter a valid email address.', 'error' );
			return null;
		}
		return email;
	}

	// -------------------------------------------------------------------------
	// Paystack checkout + AJAX server-side verification
	// -------------------------------------------------------------------------

	/**
	 * Launch the Paystack inline popup.
	 *
	 * @param {string}      uid
	 * @param {number}      amount     In whole currency units.
	 * @param {string}      amountType 'preset' or 'custom'.
	 * @param {string}      email
	 * @param {HTMLElement} submitBtn
	 * @param {HTMLElement} alertEl
	 */
	function launchPaystack( uid, amount, amountType, email, submitBtn, alertEl ) {
		if ( typeof PaystackPop === 'undefined' ) {
			showAlert( alertEl, 'Payment gateway failed to load. Please refresh the page.', 'error' );
			setLoading( submitBtn, false );
			return;
		}

		// Paystack expects amounts in the smallest currency unit.
		const amountInKobo = Math.round( amount * 100 );

		const popup = PaystackPop.setup( {
			key:      cfg.publicKey,
			email:    email,
			amount:   amountInKobo,
			currency: cfg.currency,
			ref:      'QG-' + Date.now() + '-' + Math.random().toString( 36 ).slice( 2, 11 ),
			label:    'Donation',

			onSuccess: function ( transaction ) {
				// Frontend success callback — do NOT show success yet.
				// Must verify server-side before confirming.
				setLoading( submitBtn, true, i18n.verifying || 'Verifying payment…' );
				verifyServerSide( uid, transaction.reference, email, amountInKobo, amountType, submitBtn, alertEl );
			},

			onCancel: function () {
				setLoading( submitBtn, false );
				showAlert( alertEl, i18n.paymentFailed || 'Payment was not completed. Please try again.', 'warning' );
			},
		} );

		popup.openIframe();
	}

	/**
	 * POST the transaction reference to WordPress for server-side verification.
	 *
	 * The backend (class-quickgive-ajax.php) fetches the Paystack API using the
	 * secret key — which never touches this file.
	 *
	 * @param {string}      uid
	 * @param {string}      reference
	 * @param {string}      email
	 * @param {number}      amountInKobo
	 * @param {string}      amountType   'preset' or 'custom'  (v1.1).
	 * @param {HTMLElement} submitBtn
	 * @param {HTMLElement} alertEl
	 */
	function verifyServerSide( uid, reference, email, amountInKobo, amountType, submitBtn, alertEl ) {
		const formData = new FormData();
		formData.append( 'action',      cfg.action );
		formData.append( 'nonce',       cfg.nonce );
		formData.append( 'reference',   reference );
		formData.append( 'email',       email );
		formData.append( 'amount',      amountInKobo );
		formData.append( 'currency',    cfg.currency );
		formData.append( 'amount_type', amountType );  // v1.1

		fetch( cfg.ajaxUrl, {
			method:      'POST',
			credentials: 'same-origin',
			body:        formData,
		} )
			.then( function ( response ) { return response.json(); } )
			.then( function ( json ) {
				setLoading( submitBtn, false );

				if ( json.success ) {
					showSuccess( uid, json.data.message );
				} else {
					const msg = ( json.data && json.data.message )
						? json.data.message
						: ( i18n.paymentFailed || 'Payment verification failed.' );
					showAlert( alertEl, msg, 'error' );
				}
			} )
			.catch( function () {
				setLoading( submitBtn, false );
				showAlert( alertEl, i18n.networkError || 'A network error occurred. Please try again.', 'error' );
			} );
	}

	// -------------------------------------------------------------------------
	// Success state
	// -------------------------------------------------------------------------

	/** Hide form body and show the thank-you panel. */
	function showSuccess( uid, message ) {
		const overlay   = document.getElementById( uid + '-modal' );
		const body      = overlay && overlay.querySelector( '.quickgive-modal__body' );
		const header    = overlay && overlay.querySelector( '.quickgive-modal__header' );
		const successEl = document.getElementById( uid + '-success' );
		const thankYou  = document.getElementById( uid + '-thankyou' );

		if ( body )     { body.setAttribute( 'hidden', '' ); }
		if ( header )   { header.setAttribute( 'hidden', '' ); }
		if ( thankYou ) { thankYou.innerHTML = message; }
		if ( successEl ) {
			successEl.removeAttribute( 'hidden' );
			const heading = successEl.querySelector( '.quickgive-success__heading' );
			if ( heading ) { heading.focus(); }
		}
	}

	// -------------------------------------------------------------------------
	// Initialise each shortcode instance on the page
	// -------------------------------------------------------------------------

	function init() {
		// Trigger buttons.
		document.querySelectorAll( '[id$="-trigger"].quickgive-btn' ).forEach( function ( trigger ) {
			const uid = trigger.id.replace( '-trigger', '' );

			// Build preset buttons.
			const presetsContainer = document.getElementById( uid + '-presets' );
			if ( presetsContainer ) {
				buildPresets( presetsContainer, uid );
			}

			// v1.1 — wire custom input deselection.
			wireCustomInput( uid );

			trigger.addEventListener( 'click', function () {
				openModal( uid );
			} );
		} );

		// Close buttons (header X + success panel Close).
		document.querySelectorAll( '[data-close]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				closeModal( btn.dataset.close );
			} );
		} );

		// Click backdrop to close.
		document.querySelectorAll( '.quickgive-overlay' ).forEach( function ( overlay ) {
			overlay.addEventListener( 'click', function ( e ) {
				if ( e.target === overlay ) {
					const uid = overlay.dataset.instance;
					if ( uid ) { closeModal( uid ); }
				}
			} );
		} );

		// Submit buttons.
		document.querySelectorAll( '.quickgive-submit-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				const uid        = btn.dataset.instance;
				const emailInput = document.getElementById( btn.dataset.emailId );
				const alertEl    = document.getElementById( btn.dataset.alertId );

				clearAlert( alertEl );

				// v1.1 — resolve returns { amount, amountType } or null.
				const resolved = resolveAmount( uid, alertEl );
				if ( resolved === null ) { return; }

				const email = resolveEmail( emailInput, alertEl );
				if ( email === null ) { return; }

				setLoading( btn, true );
				launchPaystack( uid, resolved.amount, resolved.amountType, email, btn, alertEl );
			} );
		} );
	}

	// Boot after DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
