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
		// Delay scroll-triggered animations until elements are more visible.
		// triggerRootMargin format: "top right bottom left" (like CSS margin).
		// Negative BOTTOM margin shrinks the root from the bottom, so an element
		// must travel that far above the viewport's bottom edge before triggering.
		// e.g. '-25% 0px' → trigger fires when element's top reaches 75% from top (25% from bottom).
		triggerRootMargin: '0px 0px -20% 0px',
		repeat: true,  // Remove/add is-inview on exit/enter so animations can replay
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
