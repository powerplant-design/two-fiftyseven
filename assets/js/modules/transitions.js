/**
 * Swup — AJAX page transitions.
 *
 * Swup intercepts same-origin link clicks, fetches the new page, swaps the
 * #swup container, and drives CSS fade animations (see _transitions.scss).
 *
 * Lifecycle integration with Locomotive Scroll and the colour theme engine:
 *   visit:start     → destroy Locomotive before the DOM is modified
 *   content:replace → re-run colour theme engine while content is still invisible
 *                     (data-theme is resolved on the incoming page before the
 *                      fade-in begins, so the background colour cross-fades cleanly)
 *   page:view       → reinit Locomotive on the new page's content
 */

import Swup           from 'swup';
import SwupHeadPlugin from '@swup/head-plugin';
import SwupScrollPlugin from '@swup/scroll-plugin';
import SwupA11yPlugin from '@swup/a11y-plugin';
import { applyThemes } from './color-theme.js';
import { initScroll, destroyScroll } from './scroll.js';

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
			// Reset scroll position to top on each navigation.
			// Disabled during the fade so there's no visible jump.
			new SwupScrollPlugin( { doScrollingRightAway: false } ),
			// Announce navigations to screen readers and manage focus.
			new SwupA11yPlugin(),
		],
	} );

	// 1. Destroy Locomotive before the DOM is swapped.
	swup.hooks.on( 'visit:start', () => {
		destroyScroll();
	} );

	// 2. Colour theme: resolve new page's data-theme while content is at opacity 0.
	//    <html> persists across Swup navigations so we must manually transfer
	//    data-color-space from the incoming document before resolving themes —
	//    otherwise applyThemes() reads the stale value from the previous page.
	swup.hooks.on( 'content:replace', ( visit ) => {
		const incomingSpace = visit.to.document?.documentElement?.getAttribute( 'data-color-space' );
		if ( incomingSpace ) {
			document.documentElement.setAttribute( 'data-color-space', incomingSpace );
		}
		applyThemes();
	} );

	// 3. Reinit Locomotive once the new content is in the DOM and becoming visible.
	swup.hooks.on( 'page:view', () => {
		initScroll();
	} );
}
