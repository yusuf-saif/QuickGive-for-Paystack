/**
 * QuickGive for Paystack — Frontend JavaScript
 *
 * Manages:
 *   1. Modal open / close / accessibility
 *   2. Preset amount selection
 *   3. Custom amount input
 *   4. Form validation
 *   5. Paystack popup checkout
 *   6. AJAX server-side verification
 *   7. Success / failure display states
 *
 * Depends on:
 *   - quickgiveConfig  (wp_localize_script — no secret key)
 *   - PaystackPop      (Paystack inline SDK from js.paystack.co)
 *
 * @package QuickGive_For_Paystack
 */

/* global quickgiveConfig, PaystackPop */
( function () {
	'use strict';

	const cfg = window.quickgiveConfig || {};
	const i18n = cfg.i18n || {};

	// -------------------------------------------------------------------------
	// Utility helpers
	// -------------------------------------------------------------------------

	/** Format a number as a currency string (no fraction). */
	function formatAmount( amount, currency ) {
		const symbols = {
			NGN: '₦', GHS: 'GH₵', ZAR: 'R', KES: 'KSh',
			USD: '$', GBP: '£', EUR: '€',
		};
		const sym = symbols[ currency ] || ( currency + ' ' );
		return sym + Number( amount ).toLocaleString();
	}

	/** Show an alert message inside a panel. */
	function showAlert( alertEl, message, type ) {
		alertEl.textContent = message;
		alertEl.className = 'quickgive-alert quickgive-alert--' + ( type || 'error' );
		alertEl.removeAttribute( 'hidden' );
	}

	/** Clear alert panel. */
	function clearAlert( alertEl ) {
		alertEl.textContent = '';
		alertEl.setAttribute( 'hidden', '' );
		alertEl.className = 'quickgive-alert';
	}

	/** Set submit button to loading state. */
	function setLoading( btn, isLoading, label ) {
		btn.disabled = isLoading;
		const textEl = btn.querySelector( '.quickgive-submit-btn__text' );
		if ( textEl ) {
			if ( ! btn._originalLabel ) {
				btn._originalLabel = textEl.textContent.trim();
			}
			textEl.textContent = isLoading ? ( label || i18n.processing || 'Processing…' ) : btn._originalLabel;
		}
		btn.classList.toggle( 'quickgive-submit-btn--loading', isLoading );
	}

	// -------------------------------------------------------------------------
	// Modal management
	// -------------------------------------------------------------------------

	/** Open a modal overlay identified by its instance uid. */
	function openModal( uid ) {
		const overlay = document.getElementById( uid + '-modal' );
		if ( ! overlay ) { return; }
		overlay.removeAttribute( 'aria-hidden' );
		overlay.classList.add( 'quickgive-overlay--visible' );
		document.body.classList.add( 'quickgive-body-lock' );

		// Focus first focusable element inside modal.
		const focusTarget = overlay.querySelector( 'button, input, [tabindex]' );
		if ( focusTarget ) {
			setTimeout( function () { focusTarget.focus(); }, 50 );
		}

		// Trap focus within the modal.
		overlay._focusTrap = function ( e ) {
			trapFocus( overlay, e );
		};
		overlay.addEventListener( 'keydown', overlay._focusTrap );
	}

	/** Close a modal overlay. */
	function closeModal( uid ) {
		const overlay = document.getElementById( uid + '-modal' );
		if ( ! overlay ) { return; }
		overlay.setAttribute( 'aria-hidden', 'true' );
		overlay.classList.remove( 'quickgive-overlay--visible' );
		document.body.classList.remove( 'quickgive-body-lock' );

		if ( overlay._focusTrap ) {
			overlay.removeEventListener( 'keydown', overlay._focusTrap );
		}

		// Return focus to trigger button.
		const trigger = document.getElementById( uid + '-trigger' );
		if ( trigger ) { trigger.focus(); }
	}

	/** Simple focus trap — keeps Tab/Shift+Tab inside the modal. */
	function trapFocus( container, event ) {
		if ( event.key !== 'Tab' ) {
			if ( event.key === 'Escape' ) {
				const uid = container.dataset.instance;
				if ( uid ) { closeModal( uid ); }
			}
			return;
		}

		const focusable = Array.from(
			container.querySelectorAll( 'button:not([disabled]), input:not([disabled]), [href], [tabindex]:not([tabindex="-1"])' )
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
	 * Build preset amount buttons inside the given container.
	 *
	 * @param {HTMLElement} presetsContainer
	 * @param {string}      uid
	 */
	function buildPresets( presetsContainer, uid ) {
		const presets = cfg.presets || [];
		const currency = cfg.currency || 'NGN';
		presets.forEach( function ( amount ) {
			const btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'quickgive-preset-btn';
			btn.dataset.amount = amount;
			btn.setAttribute( 'aria-pressed', 'false' );
			btn.textContent = formatAmount( amount, currency );
			presetsContainer.appendChild( btn );
		} );

		// Selection logic.
		presetsContainer.addEventListener( 'click', function ( e ) {
			const btn = e.target.closest( '.quickgive-preset-btn' );
			if ( ! btn ) { return; }

			// Deselect all.
			presetsContainer.querySelectorAll( '.quickgive-preset-btn' ).forEach( function ( b ) {
				b.classList.remove( 'quickgive-preset-btn--active' );
				b.setAttribute( 'aria-pressed', 'false' );
			} );

			// Select clicked.
			btn.classList.add( 'quickgive-preset-btn--active' );
			btn.setAttribute( 'aria-pressed', 'true' );

			// Clear custom input if present.
			const customInput = document.getElementById( uid + '-custom' );
			if ( customInput ) { customInput.value = ''; }
		} );
	}

	// -------------------------------------------------------------------------
	// Validation
	// -------------------------------------------------------------------------

	/**
	 * Determine the chosen amount in whole currency units.
	 *
	 * @param {string}      uid
	 * @param {HTMLElement} alertEl
	 * @returns {number|null}  Amount in main currency units, or null if invalid.
	 */
	function resolveAmount( uid, alertEl ) {
		const presetsContainer = document.getElementById( uid + '-presets' );
		const customInput      = document.getElementById( uid + '-custom' );
		const activePreset     = presetsContainer && presetsContainer.querySelector( '.quickgive-preset-btn--active' );

		let amount = 0;

		if ( customInput && customInput.value.trim() !== '' ) {
			amount = parseFloat( customInput.value );
		} else if ( activePreset ) {
			amount = parseFloat( activePreset.dataset.amount );
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

		return amount;
	}

	/**
	 * Validate email input.
	 *
	 * @param {HTMLInputElement} emailInput
	 * @param {HTMLElement}      alertEl
	 * @returns {string|null}
	 */
	function resolveEmail( emailInput, alertEl ) {
		const email = emailInput ? emailInput.value.trim() : '';
		const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		if ( ! email || ! re.test( email ) ) {
			showAlert( alertEl, i18n.validEmail || 'Please enter a valid email address.', 'error' );
			return null;
		}
		return email;
	}

	// -------------------------------------------------------------------------
	// Paystack checkout + server-side verification
	// -------------------------------------------------------------------------

	/**
	 * Launch the Paystack inline popup and, on success, verify server-side.
	 *
	 * @param {string}      uid
	 * @param {number}      amount   In whole currency units.
	 * @param {string}      email
	 * @param {HTMLElement} submitBtn
	 * @param {HTMLElement} alertEl
	 */
	function launchPaystack( uid, amount, email, submitBtn, alertEl ) {
		if ( typeof PaystackPop === 'undefined' ) {
			showAlert( alertEl, 'Payment gateway failed to load. Please refresh the page.', 'error' );
			setLoading( submitBtn, false );
			return;
		}

		// Paystack expects amount in the smallest currency unit (kobo for NGN, pesewas for GHS, etc).
		const amountInKobo = Math.round( amount * 100 );

		const popup = PaystackPop.setup( {
			key:      cfg.publicKey,
			email:    email,
			amount:   amountInKobo,
			currency: cfg.currency,
			ref:      'QG-' + Date.now() + '-' + Math.random().toString( 36 ).slice( 2, 11 ),
			label:    'Donation',

			// ---- Paystack callbacks ----

			onSuccess: function ( transaction ) {
				// Frontend callback fires on successful payment — do NOT trust this alone.
				// MUST verify server-side before showing success.
				setLoading( submitBtn, true, i18n.verifying || 'Verifying payment…' );
				verifyServerSide( uid, transaction.reference, email, amountInKobo, submitBtn, alertEl );
			},

			onCancel: function () {
				setLoading( submitBtn, false );
				showAlert( alertEl, i18n.paymentFailed || 'Payment was not completed. Please try again.', 'warning' );
			},
		} );

		popup.openIframe();
	}

	/**
	 * Send the transaction reference to the WordPress backend for verification.
	 * The secret key lives entirely on the server — never sent here.
	 *
	 * @param {string}      uid
	 * @param {string}      reference
	 * @param {string}      email
	 * @param {number}      amountInKobo
	 * @param {HTMLElement} submitBtn
	 * @param {HTMLElement} alertEl
	 */
	function verifyServerSide( uid, reference, email, amountInKobo, submitBtn, alertEl ) {
		const formData = new FormData();
		formData.append( 'action',    cfg.action );
		formData.append( 'nonce',     cfg.nonce );
		formData.append( 'reference', reference );
		formData.append( 'email',     email );
		formData.append( 'amount',    amountInKobo );
		formData.append( 'currency',  cfg.currency );

		fetch( cfg.ajaxUrl, {
			method:      'POST',
			credentials: 'same-origin',
			body:        formData,
		} )
			.then( function ( response ) {
				return response.json();
			} )
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

	/** Hide form body and display the thank-you panel. */
	function showSuccess( uid, message ) {
		const overlay   = document.getElementById( uid + '-modal' );
		const body      = overlay && overlay.querySelector( '.quickgive-modal__body' );
		const header    = overlay && overlay.querySelector( '.quickgive-modal__header' );
		const successEl = document.getElementById( uid + '-success' );
		const thankYou  = document.getElementById( uid + '-thankyou' );

		if ( body )      { body.setAttribute( 'hidden', '' ); }
		if ( header )    { header.setAttribute( 'hidden', '' ); }
		if ( thankYou )  { thankYou.innerHTML = message; }
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

			trigger.addEventListener( 'click', function () {
				openModal( uid );
			} );
		} );

		// Close buttons (modal header X + success Close).
		document.querySelectorAll( '[data-close]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				closeModal( btn.dataset.close );
			} );
		} );

		// Click overlay backdrop to close.
		document.querySelectorAll( '.quickgive-overlay' ).forEach( function ( overlay ) {
			overlay.addEventListener( 'click', function ( e ) {
				if ( e.target === overlay ) {
					const uid = overlay.dataset.instance;
					if ( uid ) { closeModal( uid ); }
				}
			} );
		} );

		// Submit button.
		document.querySelectorAll( '.quickgive-submit-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				const uid       = btn.dataset.instance;
				const emailId   = btn.dataset.emailId;
				const alertId   = btn.dataset.alertId;
				const emailInput = document.getElementById( emailId );
				const alertEl   = document.getElementById( alertId );

				clearAlert( alertEl );

				const amount = resolveAmount( uid, alertEl );
				if ( amount === null ) { return; }

				const email = resolveEmail( emailInput, alertEl );
				if ( email === null ) { return; }

				setLoading( btn, true );
				launchPaystack( uid, amount, email, btn, alertEl );
			} );
		} );
	}

	// Boot after the DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
