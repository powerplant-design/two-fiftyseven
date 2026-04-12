/**
 * Swup — AJAX page transitions.
 *
 * Swup intercepts same-origin link clicks, fetches the new page, swaps the
 * #swup container, and drives CSS fade animations (see _transitions.scss).
 *
 * Lifecycle integration with Locomotive Scroll and the colour theme engine:
 *   visit:start      → destroy Locomotive before the DOM is modified
 *   content:replace  → scroll to top, apply colour theme, reset is-inview state
 *   page:view        → reinit all non-scroll modules
 *   animation:in:end → reinit Locomotive after fade-in completes so scroll
 *                      reveals fire at the correct position, not during the fade
 */

import Swup           from 'swup';
import SwupHeadPlugin from '@swup/head-plugin';
import SwupA11yPlugin from '@swup/a11y-plugin';
import { applyThemes } from './color-theme.js';
import { initScroll, destroyScroll } from './scroll.js';
import { initMarquee, destroyMarquee } from './marquee.js';
import { syncHeader } from './header.js';
import { initFooter, destroyFooter } from './footer.js';
import { initStackedCards, destroyStackedCards } from './stacked-cards.js';
import { initFaq, destroyFaq } from './faq.js';
import { initEventsArchive, destroyEventsArchive } from './events-archive.js';
import { initCptArchive, destroyCptArchive } from './cpt-archive.js';
import { initImpact, destroyImpact } from './impact.js';

function resetScrollRevealState() {
	document.querySelectorAll( '[data-scroll].is-inview' ).forEach( ( el ) => {
		el.classList.remove( 'is-inview' );
	} );
}

export function initTransitions() {
	const swup = new Swup( {
		containers: [ '#swup' ],
		plugins: [
			// Sync <title>, <meta>, canonical, and <body> classes between pages.
			// persistTags keeps Vite-injected <style> tags alive in dev mode — Vite
			// injects CSS via JS so those tags don't appear in the fetched page's <head>,
			// and without this option SwupHeadPlugin would remove them on each navigation.
			// In production CSS is a <link> tag so this selector matches nothing harmlessly.
			new SwupHeadPlugin( { persistTags: 'style[data-vite-dev-id], style[type="text/css"]' } ),
			// Announce navigations to screen readers and manage focus.
			new SwupA11yPlugin(),
		],
	} );

	// 1. Destroy Locomotive + marquee before the DOM is swapped.
	swup.hooks.on( 'visit:start', () => {
		destroyMarquee();
		destroyStackedCards();
		destroyFaq();
		destroyFooter();
		destroyEventsArchive();
		destroyCptArchive();
		destroyImpact();
		destroyScroll();
	} );

	// 2. Fade-out is now complete. Scroll to top while content is invisible,
	//    then apply colour theme and reset animations before fade-in.
	//    This prevents repeat animations from firing during the transition.
	//    Also sync the logo hero/no-hero class from the incoming page's header.
	swup.hooks.on( 'content:replace', ( visit ) => {
		window.scrollTo( 0, 0 );

		// Reset all scroll-reveal elements so Locomotive re-triggers them fresh.
		resetScrollRevealState();

		const incomingSpace = visit.to.document?.documentElement?.getAttribute( 'data-color-space' );
		if ( incomingSpace ) {
			document.documentElement.setAttribute( 'data-color-space', incomingSpace );
		}
		applyThemes();

		// Sync no-hero modifier: the header persists across Swup navigations so
		// the PHP-rendered class won't update — copy it from the fetched document.
		const incomingHeader = visit.to.document?.querySelector( '.site-header' );
		const currentHeader  = document.querySelector( '.site-header' );
		if ( incomingHeader && currentHeader ) {
			currentHeader.classList.toggle( 'site-header--no-hero', incomingHeader.classList.contains( 'site-header--no-hero' ) );
		}
	} );

	// 3. Reinit non-scroll modules as soon as new content is in the DOM.
	//    initScroll is deferred to animation:in:end so Locomotive doesn't fire
	//    is-inview triggers while the page is still fading in.
	swup.hooks.on( 'page:view', () => {
		initMarquee();
		initFooter();
		initStackedCards();
		initFaq();
		initEventsArchive();
		initCptArchive();
		initImpact();
		syncHeader();
	} );

	// 4. Init Locomotive only after the fade-in animation completes so that
	//    scroll-triggered reveals (is-inview) fire at the correct scroll position,
	//    not while the page is still invisible.
	swup.hooks.on( 'animation:in:end', () => {
		initScroll();
	} );
}
