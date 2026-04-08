/**
 * Site header — scroll state.
 *
 * Adds `site-header--scrolled` to `.site-header` once the page is no longer
 * at the very top. The class gates the backdrop-blur `::before` pseudo-element
 * so the blur only appears after the user has scrolled away from the top.
 *
 * On pages with a hero block, adds `site-header--past-hero` once the user
 * scrolls past the hero's bottom edge, switching the logo to dark colours.
 *
 * Called once from main.js. The `.site-header` element persists across Swup
 * navigations, so the listener never needs to be torn down. `syncHeader()` is
 * exported so transitions.js can call it after each page swap to immediately
 * reflect the (reset-to-zero) scroll position.
 */

const SCROLLED_CLASS  = 'site-header--scrolled';
const PAST_HERO_CLASS = 'site-header--past-hero';

function getHeader() {
	return document.querySelector( '.site-header' );
}

function getHero() {
	return document.querySelector( '.hero-home, .hero-page' );
}

export function syncHeader() {
	const header = getHeader();
	if ( ! header ) return;

	header.classList.toggle( SCROLLED_CLASS, window.scrollY > 0 );

	// Only applies on hero pages (no --no-hero class).
	if ( ! header.classList.contains( 'site-header--no-hero' ) ) {
		const hero = getHero();
		const pastHero = hero ? window.scrollY >= hero.getBoundingClientRect().bottom + window.scrollY : false;
		header.classList.toggle( PAST_HERO_CLASS, pastHero );
	}
}

export function initHeader() {
	syncHeader(); // set correct state on first load

	window.addEventListener( 'scroll', syncHeader, { passive: true } );
}
