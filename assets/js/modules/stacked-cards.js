/**
 * Stacked Cards — Lenis scroll-driven sequential card exit.
 *
 * All cards start absolutely stacked on top of each other inside a
 * sticky 100svh track. The outer wrapper is given enough min-height to
 * provide scroll runway: (count) * 100svh.
 *
 * As Lenis scrolls through that runway, each card (card 0 = top of stack,
 * highest z-index) is translated downward off-screen in sequence — revealing
 * the card behind it. The last card stays in place as the final visible state.
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

function updateWrapper( wrapper ) {
	const cards = Array.from( wrapper.querySelectorAll( CARD_SELECTOR ) );
	const count = cards.length;
	if ( count < 2 ) return; // nothing to animate with only one card

	const rect        = wrapper.getBoundingClientRect();
	const totalRunway = wrapper.offsetHeight - window.innerHeight;
	if ( totalRunway <= 0 ) return;

	// How far we've scrolled past the wrapper's natural top.
	const scrolledIn = Math.max( 0, -rect.top );
	const progress   = Math.min( 1, scrolledIn / totalRunway );

	// Only the first (count - 1) cards exit; last card stays as final state.
	const segments = count - 1;

	cards.forEach( ( card, i ) => {
		if ( i === count - 1 ) {
			// Last card never exits.
			card.style.transform = '';
			return;
		}

		const segStart   = i / segments;
		const segSize    = 1 / segments;
		// Local progress for this card: 0 = hasn't started exiting, 1 = fully gone.
		const local      = Math.max( 0, Math.min( 1, ( progress - segStart ) / segSize ) );
		// Translate upward: -110% clears the card completely out of the track.
		card.style.transform = `translateY( ${ local * -110 }% )`;
	} );
}

function onScroll() {
	wrappers.forEach( updateWrapper );
}

/**
 * Scroll to the position in the runway where `targetIndex` card is fully revealed.
 * Used by tab click listeners.
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

	// Set scroll runway on each wrapper so the sticky track pins long enough.
	// 60svh per card exit + 1 full viewport to land on the last card.
	wrappers.forEach( ( wrapper ) => {
		const count = parseInt( wrapper.dataset.cardCount ?? 1, 10 );
		wrapper.style.minHeight = `calc( 100svh + ${ count - 1 } * 60svh )`;
	} );

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

	// Clear inline styles and runway height so re-init starts clean.
	wrappers.forEach( ( wrapper ) => {
		wrapper.style.minHeight = '';
		wrapper.querySelectorAll( CARD_SELECTOR ).forEach( ( card ) => {
			card.style.transform = '';
		} );
	} );
	wrappers = [];
}
