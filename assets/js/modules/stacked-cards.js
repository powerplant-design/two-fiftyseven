/**
 * Stacked Cards — Lenis scroll-driven sequential card entry.
 *
 * Cards 0..(n-1) are absolutely stacked in a sticky 100svh track. The outer
 * wrapper is given enough min-height to provide scroll runway: (count) × 100svh.
 *
 * Card 0 is the base layer (lowest z-index) and stays fixed in place.
 * Cards 1..n slide in from below in sequence, each covering the card before it.
 * The last card becomes the top of the completed stack.
 *
 * Tabs above each card let the user scroll back to any earlier state after all
 * cards have stacked.
 *
 * Mirrors the footer.js pattern: attaches to lenis.on('scroll', ...) once
 * the Lenis instance is ready.
 */

import { getScrollInstance } from './scroll.js';

const WRAPPER_SELECTOR = '[data-js="stacked-cards"]';
const CARD_SELECTOR    = '[data-js="stacked-card"]';

// Per-wrapper state so multiple instances can coexist on a page.
let wrappers       = [];
let lenisInstance  = null;
let scrollListener = null;
let tabController   = null; // AbortController for tab click listeners
let mediaQuery     = null; // MediaQueryList for desktop breakpoint

function updateWrapper( wrapper ) {
	const cards = Array.from( wrapper.querySelectorAll( CARD_SELECTOR ) );
	const count = cards.length;
	if ( count < 2 ) return; // nothing to animate with only one card

	// Only animate on desktop — mobile uses normal flex layout
	if ( ! mediaQuery || ! mediaQuery.matches ) {
		cards.forEach( ( card ) => {
			card.style.transform = '';
		} );
		return;
	}

	const rect        = wrapper.getBoundingClientRect();
	const totalRunway = wrapper.offsetHeight - window.innerHeight;
	if ( totalRunway <= 0 ) return;

	// How far we've scrolled past the wrapper's natural top.
	const scrolledIn = Math.max( 0, -rect.top );
	const progress   = Math.min( 1, scrolledIn / totalRunway );

	// Card 0 stays fixed (base layer). Cards 1..n-1 slide in from below in sequence.
	const segments = count - 1;

	cards.forEach( ( card, i ) => {
		if ( i === 0 ) {
			// Base layer — never moves.
			card.style.transform = '';
			return;
		}

		// Card i enters during segment (i - 1): progress from (i-1)/segments → i/segments.
		const segStart = ( i - 1 ) / segments;
		const segSize  = 1 / segments;
		// Local progress: 0 = off-screen below, 1 = fully covering previous card.
		const local    = Math.max( 0, Math.min( 1, ( progress - segStart ) / segSize ) );
		// Slide in from below: 110% → 0%.
		card.style.transform = `translateY( ${ ( 1 - local ) * 110 }% )`;
	} );
}

function onScroll() {
	wrappers.forEach( updateWrapper );
}

/**
 * Scroll to the position in the runway where `targetIndex` card is on top of the stack.
 * Used by tab click listeners to navigate back through stacked cards.
 */
function scrollToCard( wrapper, targetIndex ) {
	if ( ! lenisInstance ) return;
	const count    = parseInt( wrapper.dataset.cardCount ?? 1, 10 );
	const segments = count - 1;
	if ( segments <= 0 || targetIndex > segments ) return;

	const totalRunway  = wrapper.offsetHeight - window.innerHeight;
	const progress     = targetIndex / segments;
	const wrapperTop   = wrapper.getBoundingClientRect().top + window.scrollY;
	lenisInstance.scrollTo( wrapperTop + progress * totalRunway );
}

export function initStackedCards() {
	wrappers = Array.from( document.querySelectorAll( WRAPPER_SELECTOR ) );
	if ( ! wrappers.length ) return;

	// Set up media query for desktop (768px+)
	mediaQuery = window.matchMedia( '(min-width: 768px)' );

	// Wire tab click → scroll to next card.
	tabController = new AbortController();
	wrappers.forEach( ( wrapper ) => {
		const cards = Array.from( wrapper.querySelectorAll( CARD_SELECTOR ) );
		cards.forEach( ( card, i ) => {
			const tab = card.querySelector( '[data-js="stacked-card-tab"]' );
			if ( ! tab ) return;
			tab.addEventListener(
				'click',
				() => scrollToCard( wrapper, i ),
				{ signal: tabController.signal }
			);
		} );
	} );

	// Attach to Lenis — poll until instance is ready (same pattern as footer.js).
	const attach = () => {
		lenisInstance = getScrollInstance()?.lenisInstance;
		if ( lenisInstance ) {
			scrollListener = onScroll;
			lenisInstance.on( 'scroll', scrollListener );
			// Run once immediately for correct initial state.
			onScroll();
		} else {
			requestAnimationFrame( attach );
		}
	};
	attach();
}

export function destroyStackedCards() {
	if ( lenisInstance && scrollListener ) {
		lenisInstance.off( 'scroll', scrollListener );
	}
	lenisInstance  = null;
	scrollListener = null;

	tabController?.abort();
	tabController = null;

	// Remove media query listener
	if ( mediaQuery ) {
		mediaQuery = null;
	}

	// Clear inline styles and runway height so re-init starts clean.
	wrappers.forEach( ( wrapper ) => {
		wrapper.querySelectorAll( CARD_SELECTOR ).forEach( ( card ) => {
			card.style.transform = '';
		} );
	} );
	wrappers = [];
}
