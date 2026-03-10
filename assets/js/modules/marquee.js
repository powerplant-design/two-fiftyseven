/**
 * Hero marquee — JS-driven animation.
 *
 * Supports multiple marquee tracks on the same page via [data-js="marquee-track"].
 * Each track maintains its own scroll position. A single rAF loop drives all.
 *
 * PHP doubles the icon list for a seamless loop; we reset at -50% of total
 * track width.  The CSS animation on the track is disabled by JS on init and
 * remains as a no-JS / reduced-motion fallback.
 */

import { getScrollInstance } from './scroll.js';

// ── Tuning ────────────────────────────────────────────────────
const BASE_SPEED   = 0.4;  // px per rAF frame at 60fps (~30 px/s)
const SCROLL_SCALE = 0.3;  // fraction of scroll delta added to marquee per frame
// ─────────────────────────────────────────────────────────────

let rafId       = null;
let tracks      = []; // [{ el, position }]
let scrollBoost = 0;
let lastScroll  = null;
let unsubscribe = null;

export function initMarquee() {
	const trackEls = document.querySelectorAll( '[data-js="marquee-track"]' );
	if ( ! trackEls.length ) return;

	tracks = Array.from( trackEls ).map( ( el ) => {
		el.style.animation = 'none';
		return { el, position: 0 };
	} );

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
		const boost = scrollBoost;
		scrollBoost = 0;

		for ( const track of tracks ) {
			track.position -= BASE_SPEED + boost;
			const half = track.el.scrollWidth / 2;
			if ( Math.abs( track.position ) >= half ) {
				track.position += half;
			}
			track.el.style.transform = `translateX(${ track.position }px)`;
		}

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
	tracks = [];
}

