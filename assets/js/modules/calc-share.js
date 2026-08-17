/**
 * 257 Calculator share row — shared email + copy-link handlers.
 * ----------------------------------------------------------------------------
 * Wires the .calc__share section used by every calculator block:
 *   - Email form: POSTs { calc, email, consent, website (honeypot), page, state }
 *     to the two57/v1/calc-share-email REST endpoint.
 *   - Consent checkbox gates the submit button (disabled until accepted).
 *   - Honeypot field is checked client-side and rejected before the fetch.
 *   - Copy link: writes window.location.href (the calc's URL is kept in sync
 *     by each engine's writeURL()), falls back to execCommand.
 *
 * Markup contract (see _calc-base.scss + plan §6.1):
 *   [data-calc-share]                — the .calc__share section
 *   [data-calc-share-email]          — the <form>
 *   [data-calc-share-email-input]    — the email <input>
 *   [data-calc-share-honeypot]       — the hidden "website" input
 *   [data-calc-share-consent]        — the consent <input type="checkbox">
 *   [data-calc-share-submit]         — the submit <button>
 *   [data-calc-share-status]         — status output (email)
 *   [data-calc-share-copy]           — the "Copy link" <button> (feedback on label)
 *
 * The endpoint URL is exposed via wp_localize_script as window.two57CalcShare.
 * ============================================================================
 */

const ENDPOINT = window.two57CalcShare?.emailEndpoint || '/wp-json/two57/v1/calc-share-email';

const EMAIL_DONE = 'Sent, check your inbox';
const EMAIL_SENDING = 'Sending…';
const EMAIL_ERROR = 'Couldn’t send, try again in a few minutes.';
const EMAIL_ERROR_NO_CONSENT = 'Tick the box to agree to the contact policy first.';
const EMAIL_ERROR_NO_EMAIL = 'Enter your email address to send.';
const COPY_DONE = 'Link copied ✓';
const COPY_FALLBACK_MSG = 'Copy your browser address bar to share.';
const REVERT_MS = 4000;

function setStatus( el, text, kind ) {
	if ( ! el ) return;
	el.textContent = text;
	el.setAttribute( 'data-calc-share-status', kind || '' );
}

// ── Copy-link (client-side only) ────────────────────────────
// Feedback lives on the button label itself — no separate status box.
function handleCopy( root ) {
	const btn = root.querySelector( '[data-calc-share-copy]' );
	if ( ! btn ) return;

	const revert = ( original ) => {
		window.setTimeout( () => { btn.textContent = original; }, REVERT_MS );
	};

	btn.addEventListener( 'click', async () => {
		const url = window.location.href;
		const original = btn.textContent;

		if ( navigator.clipboard && window.isSecureContext ) {
			try {
				await navigator.clipboard.writeText( url );
				btn.textContent = COPY_DONE;
				revert( original );
				return;
			} catch ( e ) {
				/* fall through to execCommand */
			}
		}

		// Fallback: hidden textarea + execCommand('copy')
		const ta = document.createElement( 'textarea' );
		ta.value = url;
		ta.setAttribute( 'readonly', '' );
		ta.style.position = 'absolute';
		ta.style.left = '-9999px';
		document.body.appendChild( ta );
		ta.select();
		let ok = false;
		try {
			ok = document.execCommand( 'copy' );
		} catch ( e ) {
			ok = false;
		}
		document.body.removeChild( ta );

		if ( ok ) {
			btn.textContent = COPY_DONE;
		} else {
			btn.textContent = COPY_FALLBACK_MSG;
		}
		revert( original );
	} );
}

// ── Email form ──────────────────────────────────────────────
function handleEmail( root, { slug, getState } ) {
	const form = root.querySelector( '[data-calc-share-email]' );
	if ( ! form ) return;

	const emailInput = form.querySelector( '[data-calc-share-email-input]' );
	const honeypot = form.querySelector( '[data-calc-share-honeypot]' );
	const consent = form.querySelector( '[data-calc-share-consent]' );
	const submit = form.querySelector( '[data-calc-share-submit]' );
	const status = root.querySelector( '[data-calc-share-status]' );

	// Standard gating pattern: the submit button stays disabled until the form
	// is fillable — consent ticked and an email address entered.
	const sync = () => {
		if ( ! submit ) return;
		const emailReady = ! ! ( emailInput && emailInput.value.trim() );
		submit.disabled = ! ( emailReady && ( ! consent || consent.checked ) );
	};
	if ( submit ) {
		if ( consent ) consent.addEventListener( 'change', sync );
		if ( emailInput ) emailInput.addEventListener( 'input', sync );
		sync();
	}

	form.addEventListener( 'submit', async ( e ) => {
		e.preventDefault();

		const email = ( emailInput && emailInput.value.trim() ) || '';

		// Honeypot — filled by bots only.
		if ( honeypot && honeypot.value ) {
			setStatus( status, EMAIL_DONE, 'success' );
			return;
		}

		if ( ! consent || ! consent.checked ) {
			setStatus( status, EMAIL_ERROR_NO_CONSENT, 'error' );
			return;
		}

		if ( ! email ) {
			setStatus( status, EMAIL_ERROR_NO_EMAIL, 'error' );
			emailInput && emailInput.focus();
			return;
		}

		if ( submit ) {
			submit.disabled = true;
			const original = submit.textContent;
			submit.textContent = EMAIL_SENDING;
		}

		setStatus( status, '' );

		try {
			const res = await fetch( ENDPOINT, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( {
					calc: slug,
					email,
					consent: consent ? consent.checked : false,
					website: honeypot ? honeypot.value : '',
					page: window.location.pathname,
					state: typeof getState === 'function' ? getState() : {},
				} ),
			} );

			const data = await res.json().catch( () => ( {} ) );

			if ( res.ok && data && data.success ) {
				setStatus( status, EMAIL_DONE, 'success' );
				if ( form && form.reset ) form.reset();
			} else {
				setStatus( status, data && data.message ? data.message : EMAIL_ERROR, 'error' );
			}
		} catch ( err ) {
			setStatus( status, EMAIL_ERROR, 'error' );
		} finally {
			if ( submit ) {
				window.setTimeout( () => {
					submit.textContent = 'Send →';
					sync();
				}, REVERT_MS );
			}
		}
	} );
}

/**
 * Attach share-row handlers to a root that contains a [data-calc-share] node.
 *
 * @param {HTMLElement} root   The calc root (e.g. [data-js="calc-hours-to-impact"]).
 * @param {Object}      opts   { slug: string, getState: () => object }
 */
export function initCalcShare( root, opts = {} ) {
	if ( ! root ) return;
	const share = root.querySelector( '[data-calc-share]' );
	if ( ! share ) return;
	handleEmail( share, opts );
	handleCopy( share );
}