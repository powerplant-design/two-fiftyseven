/**
 * FAQ Block — accessible accordion.
 *
 * Intercepts clicks on `.faq__trigger` buttons, toggles aria-expanded on the
 * trigger and aria-hidden on the matching answer panel, and sets data-open on
 * the parent item so the CSS grid-row animation can run.
 *
 * Uses event delegation on each `.faq__panel` container so multiple FAQ blocks
 * per page are handled with a single listener each.
 */

const PANEL_SELECTOR = '[data-js="faq"]';

let panels = [];

function handleClick( e ) {
	const trigger = e.target.closest( '.faq__trigger' );
	if ( ! trigger ) return;

	const expanded  = trigger.getAttribute( 'aria-expanded' ) === 'true';
	const answerId  = trigger.getAttribute( 'aria-controls' );
	const answer    = answerId ? document.getElementById( answerId ) : null;
	const item      = trigger.closest( '.faq__item' );

	trigger.setAttribute( 'aria-expanded', String( ! expanded ) );

	if ( answer ) {
		answer.setAttribute( 'aria-hidden', String( expanded ) );
	}

	if ( item ) {
		item.dataset.open = String( ! expanded );
	}
}

export function initFaq() {
	panels = Array.from( document.querySelectorAll( PANEL_SELECTOR ) );
	panels.forEach( ( panel ) => {
		panel.addEventListener( 'click', handleClick );
	} );
}

export function destroyFaq() {
	panels.forEach( ( panel ) => {
		panel.removeEventListener( 'click', handleClick );
	} );
	panels = [];
}
