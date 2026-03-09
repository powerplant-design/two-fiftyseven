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

export function initScroll() {
	instance = new LocomotiveScroll( {
		lenisOptions: {
			lerp: 0.1,      // inertia factor (0 = instant, 1 = no damping)
			duration: 1.2,  // fallback duration for programmatic scrolls
		},
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
