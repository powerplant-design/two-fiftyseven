/**
 * Events Archive — tab switching and pagination via AJAX.
 *
 * Listens for tab clicks (UPCOMING / PAST) and pagination button clicks on
 * the [data-js="events-archive"] element, fetches new card HTML from the
 * two57_events AJAX action, and swaps the grid content without a page reload.
 *
 * Cards animate in with a staggered fade + slide-up on every load,
 * independent of scroll position.
 *
 * Requires window.two57Ajax.url and window.two57Ajax.nonce to be defined
 * (injected via wp_localize_script in functions.php).
 */

import { getScrollInstance } from './scroll.js';

let _container   = null;
let _grid        = null;
let _currentTab  = 'upcoming';
let _tabBtns     = [];

function scrollToContainer( immediate = false ) {
	if ( ! _container ) return;
	const y = _container.getBoundingClientRect().top + window.scrollY - 150;
	const scroll = getScrollInstance();
	if ( scroll?.lenisInstance ) {
		scroll.lenisInstance.scrollTo( y, { immediate, duration: immediate ? undefined : 1 } );
	} else {
		window.scrollTo( { top: y, behavior: immediate ? 'instant' : 'smooth' } );
	}
}

function onTabClick( btn ) {
	const tab = btn.dataset.tab;
	if ( tab === _currentTab ) return;

	_currentTab = tab;
	_tabBtns.forEach( ( b ) => b.setAttribute( 'aria-selected', String( b === btn ) ) );
	fetchEvents( tab, 1, false );
}

function onGridClick( e ) {
	const pager = e.target.closest( '[data-js="events-pager"]' );
	if ( ! pager ) return;
	const page = parseInt( pager.dataset.page, 10 );
	if ( ! page || page < 1 ) return;
	fetchEvents( _currentTab, page, true );
}

async function fetchEvents( tab, paged, immediate = true ) {
	if ( ! _grid ) return;

	_grid.setAttribute( 'aria-busy', 'true' );

	const body = new URLSearchParams( {
		action: 'two57_events',
		nonce:  window.two57Ajax?.nonce ?? '',
		tab,
		paged:  String( paged ),
	} );

	try {
		const response = await fetch( window.two57Ajax?.url ?? '/wp-admin/admin-ajax.php', {
			method:  'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body,
		} );

		if ( ! response.ok ) {
			_grid.removeAttribute( 'aria-busy' );
			return;
		}

		const data = await response.json();
		if ( ! data.success ) {
			_grid.removeAttribute( 'aria-busy' );
			return;
		}

		_grid.innerHTML = data.data.html;
		_grid.removeAttribute( 'aria-busy' );
		animateCards();
		requestAnimationFrame( () => scrollToContainer( immediate ) );

	} catch {
		_grid.removeAttribute( 'aria-busy' );
	}
}

function animateCards() {
	if ( ! _grid ) return;

	const cards = _grid.querySelectorAll( '.event-card' );

	cards.forEach( ( card, i ) => {
		const delay = i * 160;
		card.style.setProperty( '--delay', `${ delay }ms` );
		// Remove class first so the transition can re-fire for newly injected cards.
		card.classList.remove( 'is-visible' );
		// Force reflow so the browser registers the removed state before re-adding.
		// eslint-disable-next-line no-unused-expressions
		void card.offsetHeight;
		setTimeout( () => card.classList.add( 'is-visible' ), delay );
	} );
}

export function initEventsArchive() {
	_container = document.querySelector( '[data-js="events-archive"]' );
	if ( ! _container ) return;

	_grid    = _container.querySelector( '[data-js="events-grid"]' );
	_tabBtns = Array.from( _container.querySelectorAll( '[data-js="events-tab"]' ) );

	// Reset tab state to upcoming on every init (handles Swup back-navigation).
	_currentTab = 'upcoming';
	_tabBtns.forEach( ( btn ) => {
		btn.setAttribute( 'aria-selected', String( btn.dataset.tab === 'upcoming' ) );
		btn.addEventListener( 'click', () => onTabClick( btn ) );
	} );

	// Delegated pagination clicks on the grid.
	if ( _grid ) {
		_grid.addEventListener( 'click', onGridClick );
		// Animate cards that were server-side rendered on initial load.
		animateCards();
	}
}

export function destroyEventsArchive() {
	// Event listeners are attached to DOM nodes that Swup will discard;
	// no explicit teardown needed.
	_container  = null;
	_grid       = null;
	_tabBtns    = [];
	_currentTab = 'upcoming';
}
