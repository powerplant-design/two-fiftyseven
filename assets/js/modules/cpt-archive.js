/**
 * CPT Archive — category + use-type tab switching and pagination via AJAX.
 *
 * Listens for tab clicks, select changes, and pagination button clicks on the
 * [data-js="cpt-archive"] element, fetches new card HTML from the
 * two57_cpt_archive AJAX action, and swaps the grid without a page reload.
 *
 * Supports two independent filter axes:
 *   - Category tabs  [data-js="cpt-tab"]       → term slug
 *   - Use-type tabs  [data-js="cpt-use-type-tab"] → use_type value
 * On mobile the tabs are mirrored by <select> dropdowns [data-js="cpt-select"].
 *
 * Cards animate in with a staggered fade + slide-up on every load.
 *
 * Requires window.two57Ajax.url and window.two57Ajax.cptNonce to be defined
 * (injected via wp_localize_script in functions.php).
 *
 * Used by: home.php (posts), archive-person.php, archive-organisation.php,
 * archive-media_item.php.
 */

import { applyThemes } from './color-theme.js';
import { getScrollInstance } from './scroll.js';

let _container      = null;
let _grid           = null;
let _postType       = '';
let _taxonomy       = '';
let _currentTerm    = '';
let _currentUseType = '';
let _tabBtns        = [];
let _selects        = [];

function updateUrl() {
	const url = new URL( window.location );
	if ( _currentTerm )    url.searchParams.set( 'category', _currentTerm );
	else                   url.searchParams.delete( 'category' );
	if ( _currentUseType ) url.searchParams.set( 'use', _currentUseType );
	else                   url.searchParams.delete( 'use' );
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
	fetchPosts( 1, false );
}

function onSelectChange( sel ) {
	const filter = sel.dataset.filter;
	const value  = sel.value;

	if ( filter === 'term' ) {
		if ( value === _currentTerm ) return;
		_currentTerm = value;
		_tabBtns.forEach( ( b ) => b.setAttribute( 'aria-selected', String( ( b.dataset.term ?? '' ) === value ) ) );
	} else if ( filter === 'use_type' ) {
		if ( value === _currentUseType ) return;
		_currentUseType = value;
		syncSelectValue( 'use_type', value );
	}

	updateUrl();
	fetchPosts( 1, false );
}

function resetFilters() {
	_currentTerm    = '';
	_currentUseType = '';
	_tabBtns.forEach( ( b ) => b.setAttribute( 'aria-selected', String( ( b.dataset.term ?? '' ) === '' ) ) );
	_selects.forEach( ( sel ) => { sel.value = ''; } );
	updateUrl();
	fetchPosts( 1, false );
}

function onGridClick( e ) {
	if ( e.target.closest( '[data-js="cpt-reset"]' ) ) {
		resetFilters();
		return;
	}

	const pager = e.target.closest( '[data-js="cpt-pager"]' );
	if ( ! pager ) return;
	const page = parseInt( pager.dataset.page, 10 );
	if ( ! page || page < 1 ) return;
	fetchPosts( page, true );
}

async function fetchPosts( paged, immediate = true ) {
	if ( ! _grid ) return;

	_grid.setAttribute( 'aria-busy', 'true' );

	const params = {
		action:    'two57_cpt_archive',
		nonce:     window.two57Ajax?.cptNonce ?? '',
		post_type: _postType,
		taxonomy:  _taxonomy,
		term:      _currentTerm,
		paged:     String( paged ),
	};

	if ( _currentUseType ) {
		params.use_type = _currentUseType;
	}

	const body = new URLSearchParams( params );

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
		applyThemes();
		animateCards();
		requestAnimationFrame( () => scrollToContainer( immediate ) );

	} catch {
		_grid.removeAttribute( 'aria-busy' );
	}
}

function animateCards() {
	if ( ! _grid ) return;

	const cards = _grid.querySelectorAll( '.post-index__item' );

	cards.forEach( ( card, i ) => {
		const delay = i * 160;
		card.style.setProperty( '--delay', `${ delay }ms` );
		card.classList.remove( 'is-visible' );
		// Force reflow so transition re-fires for newly injected cards.
		// eslint-disable-next-line no-unused-expressions
		void card.offsetHeight;
		setTimeout( () => card.classList.add( 'is-visible' ), delay );
	} );
}

export function initCptArchive() {
	_container = document.querySelector( '[data-js="cpt-archive"]' );
	if ( ! _container ) return;

	_grid        = _container.querySelector( '[data-js="cpt-grid"]' );
	_postType    = _container.dataset.postType ?? '';
	_taxonomy    = _container.dataset.taxonomy ?? '';
	_tabBtns     = Array.from( _container.querySelectorAll( '[data-js="cpt-tab"]' ) );
	_selects     = Array.from( _container.querySelectorAll( '[data-js="cpt-select"]' ) );

	// Restore filters from URL query string (enables linkable filtered views).
	const urlParams = new URLSearchParams( window.location.search );
	_currentTerm    = urlParams.get( 'category' ) ?? '';
	_currentUseType = urlParams.get( 'use' ) ?? '';

	_tabBtns.forEach( ( btn ) => {
		btn.setAttribute( 'aria-selected', String( ( btn.dataset.term ?? '' ) === _currentTerm ) );
		btn.addEventListener( 'click', () => onTabClick( btn ) );
	} );

	_selects.forEach( ( sel ) => {
		if ( sel.dataset.filter === 'term' )     sel.value = _currentTerm;
		if ( sel.dataset.filter === 'use_type' ) sel.value = _currentUseType;
		sel.addEventListener( 'change', () => onSelectChange( sel ) );
	} );

	// Delegated pagination clicks on the grid.
	if ( _grid ) {
		_grid.addEventListener( 'click', onGridClick );

		// If URL had filters, fetch the filtered results; otherwise animate SSR cards.
		if ( _currentTerm || _currentUseType ) {
			fetchPosts( 1, true );
		} else {
			animateCards();
		}
	}
}

export function destroyCptArchive() {
	// Event listeners are attached to DOM nodes that Swup will discard;
	// no explicit teardown needed.
	_container      = null;
	_grid           = null;
	_postType       = '';
	_taxonomy       = '';
	_currentTerm    = '';
	_currentUseType = '';
	_tabBtns        = [];
	_selects        = [];
}
