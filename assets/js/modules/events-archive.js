/**
 * Events Archive — category tab + happening/happened select switching and pagination via AJAX.
 *
 * Listens for category tab clicks, select changes, and pagination button clicks
 * on the [data-js="events-archive"] element, fetches new card HTML from the
 * two57_events AJAX action, and swaps the grid without a page reload.
 *
 * Supports two independent filter axes:
 *   - Category tabs  [data-js="events-tab"]    → event_category term slug
 *   - Status select  [data-js="events-select"] → upcoming | past
 * On mobile both filters are mirrored by <select> dropdowns [data-js="events-select"].
 *
 * Cards animate in with a staggered fade + slide-up on every load.
 *
 * Requires window.two57Ajax.url and window.two57Ajax.nonce to be defined
 * (injected via wp_localize_script in functions.php).
 */

import { getScrollInstance } from './scroll.js';

let _container   = null;
let _grid        = null;
let _currentTab  = 'upcoming';
let _currentTerm = '';
let _tabBtns     = [];
let _selects     = [];

function updateUrl() {
	const url = new URL( window.location );
	if ( _currentTerm ) url.searchParams.set( 'category', _currentTerm );
	else                url.searchParams.delete( 'category' );
	if ( _currentTab === 'past' ) url.searchParams.set( 'happened', 'yes' );
	else                         url.searchParams.delete( 'happened' );
	window.history.replaceState( null, '', url );
}

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

function syncSelectValue( filterName, value ) {
	_selects.forEach( ( sel ) => {
		if ( sel.dataset.filter === filterName ) {
			sel.value = value;
		}
	} );
}

function onTabClick( btn ) {
	const term = btn.dataset.term ?? '';
	if ( term === _currentTerm ) return;

	_currentTerm = term;
	_tabBtns.forEach( ( b ) => b.setAttribute( 'aria-selected', String( b === btn ) ) );
	syncSelectValue( 'term', term );
	updateUrl();
	fetchEvents( 1, false );
}

function onSelectChange( sel ) {
	const filter = sel.dataset.filter;
	const value  = sel.value;

	if ( filter === 'tab' ) {
		if ( value === _currentTab ) return;
		_currentTab = value;
		syncSelectValue( 'tab', value );
	} else if ( filter === 'term' ) {
		if ( value === _currentTerm ) return;
		_currentTerm = value;
		_tabBtns.forEach( ( b ) => b.setAttribute( 'aria-selected', String( ( b.dataset.term ?? '' ) === value ) ) );
		syncSelectValue( 'term', value );
	}

	updateUrl();
	fetchEvents( 1, false );
}

function resetFilters() {
	_currentTab  = 'upcoming';
	_currentTerm = '';
	_tabBtns.forEach( ( b ) => b.setAttribute( 'aria-selected', String( ( b.dataset.term ?? '' ) === '' ) ) );
	_selects.forEach( ( sel ) => {
		if ( sel.dataset.filter === 'tab' )  sel.value = 'upcoming';
		if ( sel.dataset.filter === 'term' ) sel.value = '';
	} );
	updateUrl();
	fetchEvents( 1, false );
}

function onGridClick( e ) {
	if ( e.target.closest( '[data-js="events-reset"]' ) ) {
		resetFilters();
		return;
	}

	const pager = e.target.closest( '[data-js="events-pager"]' );
	if ( ! pager ) return;
	const page = parseInt( pager.dataset.page, 10 );
	if ( ! page || page < 1 ) return;
	fetchEvents( page, true );
}

async function fetchEvents( paged, immediate = true ) {
	if ( ! _grid ) return;

	_grid.setAttribute( 'aria-busy', 'true' );

	const body = new URLSearchParams( {
		action: 'two57_events',
		nonce:  window.two57Ajax?.nonce ?? '',
		tab:    _currentTab,
		term:   _currentTerm,
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
		const delay = i * 100;
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
	_selects = Array.from( _container.querySelectorAll( '[data-js="events-select"]' ) );

	// Restore filters from URL query string (enables linkable filtered views).
	const urlParams  = new URLSearchParams( window.location.search );
	_currentTab      = urlParams.get( 'happened' ) === 'yes' ? 'past' : 'upcoming';
	_currentTerm     = urlParams.get( 'category' ) ?? '';

	_tabBtns.forEach( ( btn ) => {
		btn.setAttribute( 'aria-selected', String( ( btn.dataset.term ?? '' ) === _currentTerm ) );
		btn.addEventListener( 'click', () => onTabClick( btn ) );
	} );

	_selects.forEach( ( sel ) => {
		if ( sel.dataset.filter === 'tab' )  sel.value = _currentTab;
		if ( sel.dataset.filter === 'term' ) sel.value = _currentTerm;
		sel.addEventListener( 'change', () => onSelectChange( sel ) );
	} );

	// Delegated pagination clicks on the grid.
	if ( _grid ) {
		_grid.addEventListener( 'click', onGridClick );

		// If URL had filters, fetch the filtered results; otherwise animate SSR cards.
		if ( _currentTerm || _currentTab !== 'upcoming' ) {
			fetchEvents( 1, true );
		} else {
			animateCards();
		}
	}
}

export function destroyEventsArchive() {
	// Event listeners are attached to DOM nodes that Swup will discard;
	// no explicit teardown needed.
	_container   = null;
	_grid        = null;
	_currentTab  = 'upcoming';
	_currentTerm = '';
	_tabBtns     = [];
	_selects     = [];
}
