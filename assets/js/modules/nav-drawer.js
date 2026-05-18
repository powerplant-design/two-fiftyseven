/**
 * Nav drawer — secondary nav that slides from behind the pill on parent-item hover.
 *
 * - Opens when entering a .menu-item-has-children nav item.
 * - Cross-fades links (opacity out → swap → opacity in) when switching between
 *   parent items so the drawer stays open and the links change smoothly.
 * - Stays open as long as the mouse is anywhere inside the header or the drawer
 *   (the drawer is a DOM descendant of .site-header, so mouseleave only fires
 *   when the pointer truly leaves the whole combined area).
 * - Closes with CSS easing when the mouse exits.
 * - Closes immediately when hovering a top-level item that has no children.
 */

const DRAWER_CLASS       = 'site-header__drawer';
const LINKS_CLASS        = 'site-header__drawer-links';
const OPEN_CLASS         = 'is-open';
const ACTIVE_CLASS       = 'is-active';
const FADING_CLASS       = 'is-fading';
const FADE_DURATION      = 150; // ms — must match CSS transition

export function initNavDrawer() {
	const header = document.querySelector( '.site-header' );
	if ( ! header ) return;

	// Append drawer inside the wrapper so it inherits its exact dimensions via
	// position: absolute; inset: 0. The wrapper is position: relative.
	const wrapper    = header.querySelector( '.site-header__wrapper' );
	if ( ! wrapper ) return;

	const primaryNav = header.querySelector( '.site-nav--primary' );

	// Top-level items in the primary + secondary nav menus.
	// Explicitly scoped to the desktop navs — the mobile panel has its own
	// accordion logic and must not receive is-active from the drawer system.
	const topLevelItems   = Array.from( header.querySelectorAll( '.site-nav--primary .nav-menu > li, .site-nav--secondary .nav-menu > li' ) );
	const parentItems     = topLevelItems.filter( li => li.classList.contains( 'menu-item-has-children' ) );

	if ( ! parentItems.length ) return;

	// ── Build drawer DOM ────────────────────────────────────────────────────────
	const drawer    = document.createElement( 'div' );
	drawer.className = DRAWER_CLASS;
	drawer.setAttribute( 'aria-hidden', 'true' );

	const linksWrap = document.createElement( 'div' );
	linksWrap.className = LINKS_CLASS;
	drawer.appendChild( linksWrap );

	// Append drawer as a sibling of &__wrapper inside .site-header.
	// We match its position + size via JS so z-index: 1 vs wrapper's z-index: 2
	// causes the wrapper's solid background to cover the closed drawer.
	header.appendChild( drawer );

	// ── Sync drawer geometry to match wrapper ───────────────────────────────────
	function syncDrawer() {
		const wRect = wrapper.getBoundingClientRect();
		const hRect = header.getBoundingClientRect();
		drawer.style.top   = ( wRect.top  - hRect.top  ) + 'px';
		drawer.style.left  = ( wRect.left - hRect.left ) + 'px';
		drawer.style.width = wRect.width + 'px';

		// Offset drawer content below the pill so links appear in the row beneath it.
		drawer.style.paddingBlockStart = wRect.height + 'px';

		// Align sub-menu links with the left edge of the primary nav.
		if ( primaryNav ) {
			const navRect = primaryNav.getBoundingClientRect();
			linksWrap.style.paddingInlineStart = ( navRect.left - wRect.left ) + 'px';
		}
	}

	// Defer first sync to after the initial layout paint so getBoundingClientRect
	// returns accurate values (auto-margins, max-inline-size all resolved).
	requestAnimationFrame( syncDrawer );

	const ro = new ResizeObserver( syncDrawer );
	ro.observe( wrapper );
	window.addEventListener( 'resize', syncDrawer );

	// ── State ───────────────────────────────────────────────────────────────────
	let isOpen      = false;
	let currentItem = null;
	let fadeTimer   = null;

	// ── Helpers ─────────────────────────────────────────────────────────────────

	/** Return the nav-theme-* class on an element, if any. */
	function getThemeClass( el ) {
		return Array.from( el.classList ).find( c => c.startsWith( 'nav-theme-' ) ) ?? null;
	}

	/** Replace any nav-theme-* class on the drawer. */
	function applyTheme( item ) {
		drawer.classList.forEach( c => {
			if ( c.startsWith( 'nav-theme-' ) ) drawer.classList.remove( c );
		} );
		const tc = getThemeClass( item );
		if ( tc ) drawer.classList.add( tc );
	}

	/** Populate linksWrap with a deep clone of the item's .sub-menu. */
	function populateLinks( item ) {
		const subMenu = item.querySelector( ':scope > .sub-menu' );
		if ( ! subMenu ) return;
		linksWrap.innerHTML = '';
		linksWrap.appendChild( subMenu.cloneNode( true ) );
	}

	// ── Open / switch / close ───────────────────────────────────────────────────

	function open( item ) {
		applyTheme( item );
		populateLinks( item );
		linksWrap.classList.remove( FADING_CLASS );
		drawer.classList.add( OPEN_CLASS );
		drawer.setAttribute( 'aria-hidden', 'false' );
		item.classList.add( ACTIVE_CLASS );
		isOpen      = true;
		currentItem = item;
	}

	/**
	 * Cross-fade to a different item's links without closing the drawer.
	 * Phase 1: fade out (CSS opacity transition).
	 * Phase 2 (after FADE_DURATION): swap content, fade in.
	 */
	function switchTo( item ) {
		if ( item === currentItem ) return;
		clearTimeout( fadeTimer );

		if ( currentItem ) currentItem.classList.remove( ACTIVE_CLASS );
		item.classList.add( ACTIVE_CLASS );
		currentItem = item; // update immediately so close() always targets the right item

		linksWrap.classList.add( FADING_CLASS );

		fadeTimer = setTimeout( () => {
			applyTheme( item );
			populateLinks( item );
			// Remove fading class in the next frame so the browser registers
			// the opacity: 0 state before transitioning back to 1.
			requestAnimationFrame( () => {
				linksWrap.classList.remove( FADING_CLASS );
			} );
		}, FADE_DURATION );
	}

	function close() {
		if ( ! isOpen ) return;
		clearTimeout( fadeTimer );
		if ( currentItem ) currentItem.classList.remove( ACTIVE_CLASS );
		drawer.classList.remove( OPEN_CLASS );
		drawer.setAttribute( 'aria-hidden', 'true' );
		isOpen      = false;
		currentItem = null;
	}

	// ── Event listeners ─────────────────────────────────────────────────────────

	// Parent items: open or cross-fade.
	parentItems.forEach( item => {
		item.addEventListener( 'mouseenter', () => {
			if ( ! isOpen ) {
				open( item );
			} else {
				switchTo( item );
			}
		} );
	} );

	// Close when any nav link is clicked (drawer or pill row).
	// .no-link items open the drawer but don't navigate — skip close() for them.
	header.addEventListener( 'click', e => {
		const link = e.target.closest( 'a' );
		if ( ! link ) return;
		if ( link.closest( '.no-link' ) ) {
			e.preventDefault();
			return;
		}
		close();
	} );

	// Items WITHOUT children: close the drawer when hovered.
	topLevelItems
		.filter( li => ! li.classList.contains( 'menu-item-has-children' ) )
		.forEach( li => li.addEventListener( 'mouseenter', close ) );

	// Close when the pointer leaves the entire header (pill + drawer are both
	// descendants, so mouseleave only fires when leaving the whole area).
	header.addEventListener( 'mouseleave', close );
}
