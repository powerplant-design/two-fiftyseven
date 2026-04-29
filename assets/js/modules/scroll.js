/**
 * Locomotive Scroll v5 — smooth scroll + scroll-triggered reveals.
 *
 * Uses Lenis under the hood (CSS scroll-based, no transform hacks).
 * Exposes init/destroy so Swup can manage the lifecycle around page swaps.
 *
 * Markup:
 *   data-scroll                          → observed element; gains `is-inview` on enter
 *   data-scroll-speed="1"                → parallax multiplier (positive = slow, negative = fast)
 *   data-scroll-section                  → wraps a scroll section
 *   data-scroll-direction="horizontal"   → enables horizontal scroll within a section
 */

import LocomotiveScroll from 'locomotive-scroll';

let instance = null;
let anchorAbortController = null;

export function initScroll() {
	const isMobile = window.matchMedia( '(max-width: 767px)' ).matches;

	instance = new LocomotiveScroll( {
		lenisOptions: {
			lerp: 0.15,      // inertia factor (0 = instant, 1 = no damping)
			duration: 1.2,  // fallback duration for programmatic scrolls
		},
		// Delay scroll-triggered animations until elements are more visible.
		// triggerRootMargin format: "top right bottom left" (like CSS margin).
		// Negative BOTTOM margin shrinks the root from the bottom, so an element
		// must travel that far above the viewport's bottom edge before triggering.
		triggerRootMargin: isMobile ? '0px 0px -10% 0px' : '0px 0px -15% 0px',
	} );
}

export function destroyScroll() {
	if ( instance ) {
		instance.destroy();
		instance = null;
	}
}

/** Returns the live Locomotive Scroll instance (null between page swaps). */
export function getScrollInstance() {
	return instance;
}

/**
 * Smooth-scroll to an anchor target using Lenis.
 * Call once after initScroll(). Re-call after each Swup page swap.
 */
export function initAnchorLinks() {
	// Abort any listeners registered on the previous page to prevent duplicates.
	if ( anchorAbortController ) {
		anchorAbortController.abort();
	}
	anchorAbortController = new AbortController();
	const { signal } = anchorAbortController;

	document.querySelectorAll( 'a[href^="#"]' ).forEach( ( link ) => {
		link.addEventListener( 'click', ( e ) => {
			const id = link.getAttribute( 'href' );
			if ( id === '#' ) return;
			const target = document.querySelector( id );
			if ( ! target || ! instance ) return;
			e.preventDefault();
			instance.scrollTo( target );
		}, { signal } );
	} );
}
