/**
 * Colour theme engine — extracted module.
 *
 * Mode resolution order (highest priority first):
 *   1. data-color-mode attribute on the element  — editor-forced (blocks)
 *   2. localStorage 'color-mode'                 — user toggle preference
 *   3. OS prefers-color-scheme                   — system default
 *
 * applyThemes() is exported so Swup can call it after each page swap,
 * resolving colour tokens for the incoming page while content is still invisible.
 */

const STORAGE_KEY = 'color-mode';
const osDark      = window.matchMedia( '(prefers-color-scheme: dark)' );

/** Returns true if dark mode should be active, honouring the priority chain. */
function isDarkMode() {
	const stored = localStorage.getItem( STORAGE_KEY );
	if ( stored === 'dark' || stored === 'light' ) return stored === 'dark';
	return osDark.matches;
}

/** Resolves the full data-theme value for one element. */
function resolveTheme( el ) {
	const space      = el.getAttribute( 'data-color-space' ) || 'neutral';
	const forcedMode = el.getAttribute( 'data-color-mode' );
	if ( forcedMode === 'light' || forcedMode === 'dark' ) {
		return `${ space }-${ forcedMode }`;
	}
	return `${ space }-${ isDarkMode() ? 'dark' : 'light' }`;
}

/** Writes data-theme on all colour-space elements and syncs the toggle button UI. */
export function applyThemes() {
	document.querySelectorAll( '[data-color-space]' ).forEach( ( el ) => {
		el.setAttribute( 'data-theme', resolveTheme( el ) );
	} );
	syncToggleButton();
}

/** Keeps the toggle button label and aria-pressed in sync with the current mode. */
function syncToggleButton() {
	const dark  = isDarkMode();
	const btn   = document.querySelector( '[data-js="color-mode-toggle"]' );
	const label = document.querySelector( '[data-mode-label]' );
	if ( btn )   btn.setAttribute( 'aria-pressed', String( dark ) );
	if ( label ) label.textContent = dark ? 'Dark mode' : 'Light mode';
}

/** Toggles the user preference and persists it to localStorage. */
function toggleColorMode() {
	localStorage.setItem( STORAGE_KEY, isDarkMode() ? 'light' : 'dark' );
	applyThemes();
}

/** Wires up toggle button click, applies themes on load, and listens for OS changes. */
export function initColorTheme() {
	document.addEventListener( 'click', ( e ) => {
		if ( e.target.closest( '[data-js="color-mode-toggle"]' ) ) toggleColorMode();
	} );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', applyThemes );
	} else {
		applyThemes();
	}

	osDark.addEventListener( 'change', applyThemes );
}
