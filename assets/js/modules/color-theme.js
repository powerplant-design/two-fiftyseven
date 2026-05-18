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

/** Keeps all toggle buttons' labels and aria-pressed in sync with the current mode. */
function syncToggleButton() {
	const dark    = isDarkMode();
	const buttons = document.querySelectorAll( '[data-js="color-mode-toggle"]' );
	const labels  = document.querySelectorAll( '[data-mode-label]' );
	buttons.forEach( btn   => btn.setAttribute( 'aria-pressed', String( dark ) ) );
	labels.forEach(  label => label.textContent = dark ? '⏾' : '✴︎' );
}

/** Toggles the user preference and persists it to localStorage. */
function toggleColorMode() {
	const html = document.documentElement;
	html.classList.add( 'theme-transition' );
	localStorage.setItem( STORAGE_KEY, isDarkMode() ? 'light' : 'dark' );
	applyThemes();
	window.setTimeout( () => html.classList.remove( 'theme-transition' ), 500 );
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
