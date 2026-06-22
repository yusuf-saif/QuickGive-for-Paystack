/* global quickdonateConfig, PaystackPop */
( function () {
	'use strict';

	const cfg = window.quickdonateConfig || {};
	const i18n = cfg.i18n || {};

	function formatAmount( amount, currency ) {
		const symbols = {
			NGN: '₦',
			GHS: 'GH₵',
			ZAR: 'R',
			KES: 'KSh',
			USD: '$',
			GBP: '£',
			EUR: '€',
		};

		const symbol = symbols[ currency ] || ( currency + ' ' );
		return symbol + Number( amount ).toLocaleString();
	}

	function showAlert( alertEl, message, type ) {
		if ( ! alertEl ) {
			return;
		}

		alertEl.textContent = message;
		alertEl.className = 'quickdonate-alert quickdonate-alert--' + ( type || 'error' );
		alertEl.removeAttribute( 'hidden' );
	}

	function clearAlert( alertEl ) {
		if ( ! alertEl ) {
			return;
		}

		alertEl.textContent = '';
		alertEl.className = 'quickdonate-alert';
		alertEl.setAttribute( 'hidden', '' );
	}

	function setLoading( button, isLoading, label ) {
		if ( ! button ) {
			return;
		}

		button.disabled = isLoading;
		button.classList.toggle( 'quickdonate-submit-btn--loading', isLoading );

		const textEl = button.querySelector( '.quickdonate-submit-btn__text' );
		if ( textEl ) {
			textEl.textContent = isLoading ? ( label || i18n.processing || 'Processing...' ) : ( i18n.cta || 'Continue to Checkout' );
		}
	}

	function relocateOverlays() {
		document.querySelectorAll( '.quickdonate-overlay[data-instance]' ).forEach( function ( overlay ) {
			if ( overlay.dataset.movedToBody === '1' ) {
				return;
			}

			document.body.appendChild( overlay );
			overlay.dataset.movedToBody = '1';
		} );
	}

	function getOverlay( uid ) {
		return document.getElementById( uid + '-modal' );
	}

	function openModal( uid ) {
		const overlay = getOverlay( uid );
		if ( ! overlay ) {
			return;
		}

		overlay.hidden = false;
		overlay.setAttribute( 'aria-hidden', 'false' );
		requestAnimationFrame( function () {
			overlay.classList.add( 'quickdonate-overlay--visible' );
		} );
		document.body.classList.add( 'quickdonate-body-lock' );

		const focusTarget = overlay.querySelector( 'button, input, [tabindex]' );
		if ( focusTarget ) {
			setTimeout( function () {
				focusTarget.focus();
			}, 30 );
		}

		overlay._focusTrap = function ( event ) {
			trapFocus( overlay, event );
		};
		overlay.addEventListener( 'keydown', overlay._focusTrap );
	}

	function closeModal( uid ) {
		const overlay = getOverlay( uid );
		if ( ! overlay ) {
			return;
		}

		overlay.classList.remove( 'quickdonate-overlay--visible' );
		overlay.setAttribute( 'aria-hidden', 'true' );
		document.body.classList.remove( 'quickdonate-body-lock' );

		if ( overlay._focusTrap ) {
			overlay.removeEventListener( 'keydown', overlay._focusTrap );
		}

		window.setTimeout( function () {
			overlay.hidden = true;
		}, 220 );

		resetModalState( uid );

		const trigger = document.getElementById( uid + '-trigger' );
		if ( trigger ) {
			trigger.focus();
		}
	}

	function trapFocus( container, event ) {
		if ( event.key === 'Escape' ) {
			closeModal( container.dataset.instance );
			return;
		}

		if ( event.key !== 'Tab' ) {
			return;
		}

		const focusable = Array.from( container.querySelectorAll( 'button:not([disabled]), input:not([disabled]), [href], [tabindex]:not([tabindex="-1"])' ) );
		if ( ! focusable.length ) {
			return;
		}

		const first = focusable[ 0 ];
		const last = focusable[ focusable.length - 1 ];

		if ( event.shiftKey && document.activeElement === first ) {
			last.focus();
			event.preventDefault();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			first.focus();
			event.preventDefault();
		}
	}

	function buildPresets( container, uid ) {
		const presets = cfg.presets || [];
		const currency = cfg.currency || 'NGN';

		if ( ! container || container.dataset.ready === '1' ) {
			return;
		}

		presets.forEach( function ( amount ) {
			const button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'quickdonate-preset-btn';
			button.dataset.amount = amount;
			button.setAttribute( 'aria-pressed', 'false' );
			button.textContent = formatAmount( amount, currency );
			container.appendChild( button );
		} );

		container.addEventListener( 'click', function ( event ) {
			const button = event.target.closest( '.quickdonate-preset-btn' );
			if ( ! button ) {
				return;
			}

			container.querySelectorAll( '.quickdonate-preset-btn' ).forEach( function ( item ) {
				item.classList.remove( 'quickdonate-preset-btn--active' );
				item.setAttribute( 'aria-pressed', 'false' );
			} );

			button.classList.add( 'quickdonate-preset-btn--active' );
			button.setAttribute( 'aria-pressed', 'true' );

			const customInput = document.getElementById( uid + '-custom' );
			if ( customInput ) {
				customInput.value = '';
			}
		} );

		container.dataset.ready = '1';
	}

	function wireCustomInput( uid ) {
		const customInput = document.getElementById( uid + '-custom' );
		const presetsContainer = document.getElementById( uid + '-presets' );

		if ( ! customInput || ! presetsContainer || customInput.dataset.ready === '1' ) {
			return;
		}

		customInput.addEventListener( 'input', function () {
			if ( customInput.value.trim() === '' ) {
				return;
			}

			presetsContainer.querySelectorAll( '.quickdonate-preset-btn' ).forEach( function ( button ) {
				button.classList.remove( 'quickdonate-preset-btn--active' );
				button.setAttribute( 'aria-pressed', 'false' );
			} );
		} );

		customInput.dataset.ready = '1';
	}

	function resolveAmount( uid, alertEl ) {
		const presetsContainer = document.getElementById( uid + '-presets' );
		const customInput = document.getElementById( uid + '-custom' );
		const activePreset = presetsContainer ? presetsContainer.querySelector( '.quickdonate-preset-btn--active' ) : null;

		let amount = 0;
		let amountType = 'preset';

		if ( customInput && customInput.value.trim() !== '' ) {
			amount = parseFloat( customInput.value );
			amountType = 'custom';
		} else if ( activePreset ) {
			amount = parseFloat( activePreset.dataset.amount );
		}

		if ( ! amount || amount <= 0 ) {
			showAlert( alertEl, i18n.selectAmount || 'Please select or enter a donation amount.', 'error' );
			return null;
		}

		if ( cfg.minAmount > 0 && amount < cfg.minAmount ) {
			showAlert( alertEl, ( i18n.minAmountMsg || 'Minimum donation amount is' ) + ' ' + formatAmount( cfg.minAmount, cfg.currency ) + '.', 'error' );
			return null;
		}

		if ( cfg.maxAmount > 0 && amount > cfg.maxAmount ) {
			showAlert( alertEl, ( i18n.maxAmountMsg || 'Maximum donation amount is' ) + ' ' + formatAmount( cfg.maxAmount, cfg.currency ) + '.', 'error' );
			return null;
		}

		return {
			amount: amount,
			amountType: amountType,
		};
	}

	function resolveEmail( emailInput, alertEl ) {
		const email = emailInput ? emailInput.value.trim() : '';
		const expression = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

		if ( ! expression.test( email ) ) {
			showAlert( alertEl, i18n.validEmail || 'Please enter a valid email address.', 'error' );
			return null;
		}

		return email;
	}

	function maybeRedirect( url ) {
		if ( ! url ) {
			return;
		}

		window.setTimeout( function () {
			window.location.href = url;
		}, 1200 );
	}

	function launchPaystack( uid, amount, amountType, email, submitBtn, alertEl ) {
		if ( typeof PaystackPop === 'undefined' ) {
			showAlert( alertEl, i18n.gatewayLoadFail || 'Payment gateway failed to load. Please refresh the page.', 'error' );
			setLoading( submitBtn, false );
			return;
		}

		const amountInKobo = Math.round( amount * 100 );

		PaystackPop.setup( {
			key: cfg.publicKey,
			email: email,
			amount: amountInKobo,
			currency: cfg.currency,
			ref: 'QD-' + Date.now() + '-' + Math.random().toString( 36 ).slice( 2, 11 ),
			label: 'Donation',

			onSuccess: function ( transaction ) {
				setLoading( submitBtn, true, i18n.verifying || 'Verifying payment...' );
				verifyServerSide( uid, transaction.reference, email, amountInKobo, amountType, submitBtn, alertEl );
			},

			onCancel: function () {
				setLoading( submitBtn, false );
				showAlert( alertEl, i18n.paymentFailed || 'Payment was not completed. Please try again.', 'warning' );
				maybeRedirect( cfg.failurePageUrl );
			},
		} ).openIframe();
	}

	function verifyServerSide( uid, reference, email, amountInKobo, amountType, submitBtn, alertEl ) {
		const formData = new FormData();
		formData.append( 'action', cfg.action );
		formData.append( 'nonce', cfg.nonce );
		formData.append( 'reference', reference );
		formData.append( 'email', email );
		formData.append( 'amount', amountInKobo );
		formData.append( 'currency', cfg.currency );
		formData.append( 'amount_type', amountType );

		fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				setLoading( submitBtn, false );

				if ( json.success ) {
					showSuccess( uid, json.data.message );
					maybeRedirect( cfg.successPageUrl );
					return;
				}

				showAlert( alertEl, json.data && json.data.message ? json.data.message : ( i18n.paymentFailed || 'Payment verification failed.' ), 'error' );
				maybeRedirect( cfg.failurePageUrl );
			} )
			.catch( function () {
				setLoading( submitBtn, false );
				showAlert( alertEl, i18n.networkError || 'A network error occurred. Please try again.', 'error' );
			} );
	}

	function showSuccess( uid, message ) {
		const overlay = getOverlay( uid );
		const body = overlay ? overlay.querySelector( '.quickdonate-modal__body' ) : null;
		const successEl = document.getElementById( uid + '-success' );
		const thankYou = document.getElementById( uid + '-thankyou' );

		if ( body ) {
			body.setAttribute( 'hidden', '' );
		}

		if ( thankYou ) {
			thankYou.innerHTML = message;
		}

		if ( successEl ) {
			successEl.removeAttribute( 'hidden' );
			const heading = successEl.querySelector( '.quickdonate-success__heading' );
			if ( heading ) {
				heading.focus();
			}
		}
	}

	function resetModalState( uid ) {
		const overlay = getOverlay( uid );
		const body = overlay ? overlay.querySelector( '.quickdonate-modal__body' ) : null;
		const successEl = document.getElementById( uid + '-success' );
		const alertEl = document.getElementById( uid + '-alert' );

		if ( body ) {
			body.removeAttribute( 'hidden' );
		}

		if ( successEl ) {
			successEl.setAttribute( 'hidden', '' );
		}

		clearAlert( alertEl );
	}

	function initShortcodeInstances() {
		relocateOverlays();

		document.querySelectorAll( '[id$="-trigger"].quickdonate-btn' ).forEach( function ( trigger ) {
			const uid = trigger.id.replace( '-trigger', '' );
			const presetsContainer = document.getElementById( uid + '-presets' );

			buildPresets( presetsContainer, uid );
			wireCustomInput( uid );

			if ( trigger.dataset.ready !== '1' ) {
				trigger.addEventListener( 'click', function () {
					openModal( uid );
				} );
				trigger.dataset.ready = '1';
			}
		} );

		document.querySelectorAll( '[data-close]' ).forEach( function ( button ) {
			if ( button.dataset.ready === '1' ) {
				return;
			}

			button.addEventListener( 'click', function () {
				closeModal( button.dataset.close );
			} );
			button.dataset.ready = '1';
		} );

		document.querySelectorAll( '.quickdonate-overlay' ).forEach( function ( overlay ) {
			if ( overlay.dataset.clickReady === '1' ) {
				return;
			}

			overlay.addEventListener( 'click', function ( event ) {
				if ( event.target === overlay ) {
					closeModal( overlay.dataset.instance );
				}
			} );
			overlay.dataset.clickReady = '1';
		} );

		document.querySelectorAll( '.quickdonate-submit-btn' ).forEach( function ( button ) {
			if ( button.dataset.ready === '1' ) {
				return;
			}

			button.addEventListener( 'click', function () {
				const uid = button.dataset.instance;
				const emailInput = document.getElementById( button.dataset.emailId );
				const alertEl = document.getElementById( button.dataset.alertId );

				clearAlert( alertEl );

				const amountData = resolveAmount( uid, alertEl );
				if ( ! amountData ) {
					return;
				}

				const email = resolveEmail( emailInput, alertEl );
				if ( ! email ) {
					return;
				}

				setLoading( button, true );
				launchPaystack( uid, amountData.amount, amountData.amountType, email, button, alertEl );
			} );

			button.dataset.ready = '1';
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initShortcodeInstances );
	} else {
		initShortcodeInstances();
	}
}() );
