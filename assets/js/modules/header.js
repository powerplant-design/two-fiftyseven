/**
 * Site header — scroll state.
 *
 * Adds `site-header--scrolled` to `.site-header` once the page is no longer
 * at the very top. The class gates the backdrop-blur `::before` pseudo-element
 * so the blur only appears after the user has scrolled away from the top.
 *
 * Called once from main.js. The `.site-header` element persists across Swup
 * navigations, so the listener never needs to be torn down. `syncHeader()` is
 * exported so transitions.js can call it after each page swap to immediately
 * reflect the (reset-to-zero) scroll position.
 */

const SCROLLED_CLASS = 'site-header--scrolled';

function getHeader() {
	return document.querySelector( '.site-header' );
}

export function syncHeader() {
	const header = getHeader();
	if ( ! header ) return;
	header.classList.toggle( SCROLLED_CLASS, window.scrollY > 0 );
}

export function initHeader() {
	syncHeader(); // set correct state on first load

	window.addEventListener( 'scroll', syncHeader, { passive: true } );
}
