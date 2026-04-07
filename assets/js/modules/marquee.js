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

// ── Tuning ────────────────────────────────────────────────────
const BASE_SPEED = 0.4;  // px per rAF frame at 60fps (~30 px/s)
// ─────────────────────────────────────────────────────────────

let rafId  = null;
let tracks = []; // [{ el, position }]

export function initMarquee() {
	const trackEls = document.querySelectorAll( '[data-js="marquee-track"]' );
	if ( ! trackEls.length ) return;

	tracks = Array.from( trackEls ).map( ( el ) => {
		el.style.animation = 'none';
		return { el, position: 0 };
	} );

	function tick() {
		for ( const track of tracks ) {
			track.position -= BASE_SPEED;
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
	tracks = [];
}

