/**
 * Hero marquee — JS-driven animation.
 *
 * Base: slow continuous left-scroll via requestAnimationFrame.
 * On scroll: the raw Lenis scroll delta is added each frame so the marquee
 * moves in direct proportion to how far the page has scrolled — it's tied
 * to scroll position, not just speed.  When the page stops, the marquee
 * returns to its idle base pace immediately.
 *
 * PHP doubles the icon list for a seamless loop; we reset at -50% of total
 * track width.  The CSS animation on the track is disabled by JS on init and
 * restored on destroy — it remains as a no-JS / reduced-motion fallback.
 */

import { getScrollInstance } from './scroll.js';

// ── Tuning ────────────────────────────────────────────────────
const BASE_SPEED   = 0.4;  // px per rAF frame at 60fps (~30 px/s)
const SCROLL_SCALE = 0.3;  // fraction of scroll delta added to marquee per frame
// ─────────────────────────────────────────────────────────────

let rafId       = null;
let position    = 0;
let scrollBoost = 0; // px to add this frame, set by Lenis event, consumed per tick
let lastScroll  = null;
let unsubscribe = null;

export function initMarquee() {
	const track = document.querySelector( '.hero-home__marquee-track' );
	if ( ! track ) return;

	// Hand off from CSS animation to JS transform.
	track.style.animation = 'none';

	// Subscribe to Lenis scroll position.
	// Lenis fires its scroll event each rAF frame that the page is moving,
	// giving us the raw scroll position so we can compute a true positional delta.
	const lenis = getScrollInstance()?.lenisInstance;
	if ( lenis ) {
		const onScroll = ( { scroll } ) => {
			if ( lastScroll !== null ) {
				scrollBoost = Math.abs( scroll - lastScroll ) * SCROLL_SCALE;
			}
			lastScroll = scroll;
		};
		lenis.on( 'scroll', onScroll );
		unsubscribe = () => {
			lenis.off( 'scroll', onScroll );
			lastScroll  = null;
			scrollBoost = 0;
		};
	}

	function tick() {
		// Move by base speed + this frame's scroll contribution, then reset boost.
		position   -= BASE_SPEED + scrollBoost;
		scrollBoost = 0;

		// Seamless loop — reset at the halfway point (items are doubled in PHP).
		const half = track.scrollWidth / 2;
		if ( Math.abs( position ) >= half ) {
			position += half;
		}

		track.style.transform = `translateX(${ position }px)`;
		rafId = requestAnimationFrame( tick );
	}

	rafId = requestAnimationFrame( tick );
}

export function destroyMarquee() {
	if ( rafId ) {
		cancelAnimationFrame( rafId );
		rafId = null;
	}
	if ( unsubscribe ) {
		unsubscribe();
		unsubscribe = null;
	}
	position    = 0;
	scrollBoost = 0;
	lastScroll  = null;
}

