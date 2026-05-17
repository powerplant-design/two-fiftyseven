/**
 * Gallery Slider — Lenis scroll-driven horizontal image entry.
 *
 * Desktop: images are 100vw × 100svh. Each image slides in from the right,
 * stacking on top of the previous.
 *
 * Mobile: as image N+1 slides in from the right, image N's inner <img> pans its
 * object-position from 20% → 80% — revealing more of the image before it's
 * covered. No extra CSS width needed; the pan is driven entirely by JS.
 *
 * Image 0 is always the base layer. Images 1..n arrive in sequence; the last
 * stays as the final state. Mirrors the stacked-cards.js pattern.
 */

import { getScrollInstance } from './scroll.js';

const WRAPPER_SELECTOR = '[data-js="gallery-slider"]';
const SLIDE_SELECTOR   = '[data-js="gallery-slide"]';
const DOT_SELECTOR     = '[data-js="gallery-dot"]';

// Mobile pan: object-position interpolates from PAN_START% to PAN_END%
// as the next image slides in, so more of the image is revealed.
const PAN_START = 20;
const PAN_END   = 80;

// MediaQueryList reused across all wrappers — matches when mobile pan is active.
let mobileQuery    = null;
let wrappers       = [];
let lenisInstance  = null;
let scrollListener = null;

function updateWrapper( wrapper ) {
	const slides = Array.from( wrapper.querySelectorAll( SLIDE_SELECTOR ) );
	const count  = slides.length;
	if ( count < 2 ) return;

	const rect        = wrapper.getBoundingClientRect();
	const totalRunway = wrapper.offsetHeight - window.innerHeight;
	if ( totalRunway <= 0 ) return;

	const scrolledIn = Math.max( 0, -rect.top );
	const progress   = Math.min( 1, scrolledIn / totalRunway );
	const segments   = count - 1;
	const isMobile   = mobileQuery?.matches ?? false;

	// On mobile the pan leads the entry: entry waits until 70% of the segment
	// has elapsed (so the outgoing image has panned 70% of the way across).
	// On desktop there is no pan so the entry runs over the full segment.
	const ENTRY_DELAY = isMobile ? 0.7 : 0;

	slides.forEach( ( slide, i ) => {
		// ── Slide entry (all breakpoints) ─────────────────────────────────
		if ( i === 0 ) {
			slide.style.transform = '';
		} else {
			const segStart   = ( i - 1 ) / segments;
			const segSize    = 1 / segments;
			// Delay the start of entry; compress the remaining window to 1.
			const entryStart = segStart + ENTRY_DELAY * segSize;
			const entrySize  = segSize * ( 1 - ENTRY_DELAY );
			const local      = Math.max( 0, Math.min( 1, ( progress - entryStart ) / entrySize ) );
			// Slide in from the right: 100% → 0%.
			slide.style.transform = `translateX( ${ ( 1 - local ) * 100 }% )`;
		}

		// ── Mobile pan: outgoing image reveals its right side ─────────────
		// Image i pans while image i+1 is entering (segment i of the runway).
		// segment i: starts at progress = i/segments, size = 1/segments.
		const img = slide.querySelector( 'img' );
		if ( img ) {
			if ( isMobile && i < count - 1 ) {
				const panSegStart = i / segments;
				const panSegSize  = 1 / segments;
				const panLocal    = Math.max( 0, Math.min( 1, ( progress - panSegStart ) / panSegSize ) );
				img.style.objectPosition = `${ PAN_START + panLocal * ( PAN_END - PAN_START ) }% center`;
			} else {
				img.style.objectPosition = '';
			}
		}
	} );

	// ── Progress dots ─────────────────────────────────────────────────────
	const currentIndex = Math.min( count - 1, Math.round( progress * segments ) );
	wrapper.querySelectorAll( DOT_SELECTOR ).forEach( ( dot, i ) => {
		const active = i === currentIndex;
		dot.classList.toggle( 'is-active', active );
		dot.setAttribute( 'aria-current', active ? 'true' : 'false' );
	} );
}

function onScroll() {
	wrappers.forEach( updateWrapper );
}

export function initGallerySlider() {
	wrappers = Array.from( document.querySelectorAll( WRAPPER_SELECTOR ) );
	if ( ! wrappers.length ) return;

	mobileQuery = window.matchMedia( '(max-width: 767px)' );

	const waitForLenis = () => {
		const lenis = getScrollInstance()?.lenisInstance;
		if ( lenis ) {
			lenisInstance  = lenis;
			scrollListener = onScroll;
			lenis.on( 'scroll', scrollListener );
			wrappers.forEach( updateWrapper );
		} else {
			requestAnimationFrame( waitForLenis );
		}
	};

	waitForLenis();
}

export function destroyGallerySlider() {
	if ( lenisInstance && scrollListener ) {
		lenisInstance.off( 'scroll', scrollListener );
	}
	// Reset all inline styles set by this module so the next init starts clean.
	wrappers.forEach( ( wrapper ) => {
		wrapper.querySelectorAll( SLIDE_SELECTOR ).forEach( ( slide ) => {
			slide.style.transform = '';
			const img = slide.querySelector( 'img' );
			if ( img ) img.style.objectPosition = '';
		} );
	} );
	lenisInstance  = null;
	scrollListener = null;
	mobileQuery    = null;
	wrappers       = [];
}

