/**
 * Mobile nav — full-screen panel with accordion sub-menus.
 *
 * - Hamburger (data-js="nav-mobile-toggle") toggles the panel open/closed.
 * - Panel padding-block-start is synced to the wrapper's bottom edge so content
 *   starts below the pill, matching the same approach as nav-drawer.js.
 * - Primary nav parent items get an accordion-header div (link + chevron button).
 *   The sub-menu is wrapped in a sub-menu-wrap div for the grid-template-rows
 *   animation (0fr → 1fr) which collapses/expands to natural content height.
 * - .no-link items (no href): tapping the link row also expands the accordion.
 * - Sub-menu links close the panel on click.
 * - Body scroll is locked while the panel is open.
 */

import { getScrollInstance } from './scroll.js';

const PANEL_CLASS    = 'site-header__mobile-panel';
const OPEN_CLASS     = 'is-open';
const EXPANDED_CLASS = 'is-expanded';

export function initNavMobile() {
	const header = document.querySelector( '.site-header' );
	if ( ! header ) return;

	const toggle = header.querySelector( '[data-js="nav-mobile-toggle"]' );
	const panel  = header.querySelector( '.' + PANEL_CLASS );
	if ( ! toggle || ! panel ) return;

	const wrapper = header.querySelector( '.site-header__wrapper' );

	// ── Sync panel padding-block-start to clear the pill ────────────────────────
	function syncPanel() {
		if ( ! wrapper ) return;
		const wRect = wrapper.getBoundingClientRect();
		const hRect = header.getBoundingClientRect();
		panel.style.paddingBlockStart = ( wRect.bottom - hRect.top ) + 'px';
	}

	requestAnimationFrame( syncPanel );
	const ro = new ResizeObserver( syncPanel );
	ro.observe( wrapper );
	window.addEventListener( 'resize', syncPanel );

	// ── Open / close ─────────────────────────────────────────────────────────────

	function openPanel() {
		panel.classList.add( OPEN_CLASS );
		panel.setAttribute( 'aria-hidden', 'false' );
		toggle.setAttribute( 'aria-expanded', 'true' );
		toggle.classList.add( OPEN_CLASS );
		document.body.style.overflow = 'hidden';
		getScrollInstance()?.lenisInstance?.stop();
	}

	function closePanel() {
		panel.classList.remove( OPEN_CLASS );
		panel.setAttribute( 'aria-hidden', 'true' );
		toggle.setAttribute( 'aria-expanded', 'false' );
		toggle.classList.remove( OPEN_CLASS );
		document.body.style.overflow = '';
		getScrollInstance()?.lenisInstance?.start();
		// Collapse accordions after the panel slide-out finishes (matches 0.35s CSS transition).
		setTimeout( () => {
			panel.querySelectorAll( '.' + EXPANDED_CLASS ).forEach( item => {
				item.classList.remove( EXPANDED_CLASS );
				const chevron = item.querySelector( '.accordion-toggle' );
				if ( chevron ) chevron.classList.remove( OPEN_CLASS );
			} );
		}, 350 );
	}

	toggle.addEventListener( 'click', () => {
		panel.classList.contains( OPEN_CLASS ) ? closePanel() : openPanel();
	} );

	// Close panel when the logo is clicked so navigation isn't blocked.
	const logo = header.querySelector( '.site-logo' );
	if ( logo ) {
		logo.addEventListener( 'click', () => {
			if ( panel.classList.contains( OPEN_CLASS ) ) closePanel();
		} );
	}

	// Close when resizing back to desktop so a leftover open panel never persists.
	window.addEventListener( 'resize', () => {
		if ( window.innerWidth >= 1024 && panel.classList.contains( OPEN_CLASS ) ) {
			closePanel();
		}
	} );

	// ── Build accordion DOM ───────────────────────────────────────────────────────

	const parentItems = Array.from(
		panel.querySelectorAll( '.site-nav--mobile-primary .menu-item-has-children' )
	);

	parentItems.forEach( item => {
		const link    = item.querySelector( ':scope > a' );
		const subMenu = item.querySelector( ':scope > .sub-menu' );
		if ( ! link || ! subMenu ) return;

		// Wrap sub-menu in a grid container for height animation.
		const wrap = document.createElement( 'div' );
		wrap.className = 'sub-menu-wrap';
		subMenu.parentNode.insertBefore( wrap, subMenu );
		wrap.appendChild( subMenu );

		// Wrap link + chevron in a flex header row.
		const accordionHead = document.createElement( 'div' );
		accordionHead.className = 'accordion-header';
		link.parentNode.insertBefore( accordionHead, link );
		accordionHead.appendChild( link );

		// Chevron lives inside the <a> so the active background covers it too.
		const chevron = document.createElement( 'span' );
		chevron.className = 'accordion-toggle';
		chevron.setAttribute( 'aria-hidden', 'true' );
		link.appendChild( chevron );

		// If the parent item has a real destination, capture it before removing.
		// The href is removed from the header link so it acts as a pure toggle
		// (prevents Swup / browser from intercepting the click as navigation).
		const href = link.getAttribute( 'href' );
		if ( href ) {
			link.removeAttribute( 'href' );
		}

		function toggleAccordion( e ) {
			e.preventDefault();
			const isExpanding = ! item.classList.contains( EXPANDED_CLASS );

			// Collapse all other open items first.
			parentItems.forEach( other => {
				if ( other === item ) return;
				other.classList.remove( EXPANDED_CLASS );
				const otherChevron = other.querySelector( '.accordion-toggle' );
				if ( otherChevron ) otherChevron.classList.remove( OPEN_CLASS );
			} );

			item.classList.toggle( EXPANDED_CLASS, isExpanding );
			chevron.classList.toggle( OPEN_CLASS, isExpanding );
		}

		// Chevron is inside the <a>, so clicks bubble up — one listener is enough.
		link.addEventListener( 'click', toggleAccordion );

		// Inject the parent page as the first sub-menu entry so users can still
		// navigate there from inside the open accordion.
		if ( href ) {
			const parentLi = document.createElement( 'li' );
			parentLi.className = 'mobile-nav-parent-link';
			const parentA = document.createElement( 'a' );
			parentA.href        = href;
			parentA.textContent = link.textContent.trim();
			parentLi.appendChild( parentA );
			subMenu.insertBefore( parentLi, subMenu.firstChild );
		}
	} );

	// ── Close panel on any real link click ───────────────────────────────────────

	panel.addEventListener( 'click', e => {
		const link = e.target.closest( 'a' );
		if ( ! link ) return;
		if ( ! link.getAttribute( 'href' ) ) { e.preventDefault(); return; }
		// Accordion-header links are toggles, not navigation — handled above.
		if ( link.closest( '.accordion-header' ) ) return;
		closePanel();
	} );
}
