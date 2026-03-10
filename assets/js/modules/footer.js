/**
 * Footer height measurement + logo parallax.
 *
 * 1. Measures the footer height and writes --footer-height to :root so
 *    .site-main can push itself down to leave room for the fixed footer.
 *
 * 2. As the footer reveals from behind the main content, translates the
 *    footer logo upward slightly using Lenis scroll events.
 */

import { getScrollInstance } from './scroll.js';

/** How many px the logo travels upward when the footer is fully revealed. */
const PARALLAX_DISTANCE = 40;

export function initFooter() {
	const footer = document.querySelector( '.site-footer' );
	const logo   = document.querySelector( '.site-footer__logo' );
	if ( ! footer ) return;

	// ── 1. Height tracking ─────────────────────────────────────────────────
	const setHeight = () => {
		document.documentElement.style.setProperty(
			'--footer-height',
			`${ footer.offsetHeight }px`
		);
	};

	setHeight();
	new ResizeObserver( setHeight ).observe( footer );

	// ── 2. Logo parallax via Lenis scroll event ────────────────────────────
	if ( ! logo ) return;

	const onScroll = ( { scroll } ) => {
		const docHeight    = document.documentElement.scrollHeight;
		const winHeight    = window.innerHeight;
		const footerHeight = footer.offsetHeight;

		// The scroll position at which the footer first starts to peek out.
		const revealStart = docHeight - winHeight - footerHeight;

		if ( scroll <= revealStart ) {
			logo.style.transform = '';
			return;
		}

		// progress: 0 → footer just appearing, 1 → fully revealed.
		const progress = Math.min( ( scroll - revealStart ) / footerHeight, 1 );
		logo.style.transform = `translateY(${ -progress * PARALLAX_DISTANCE }px)`;
	};

	// Lenis may not be ready immediately — poll until it is then attach.
	const attachLenis = () => {
		const lenis = getScrollInstance()?.lenisInstance;
		if ( lenis ) {
			lenis.on( 'scroll', onScroll );
		} else {
			requestAnimationFrame( attachLenis );
		}
	};
	attachLenis();
}
