/**
 * CPT Archive — category tab switching and pagination via AJAX.
 *
 * Listens for tab clicks and pagination button clicks on the
 * [data-js="cpt-archive"] element, fetches new card HTML from the
 * two57_cpt_archive AJAX action, and swaps the grid without a page reload.
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

let _container   = null;
let _grid        = null;
let _postType    = '';
let _taxonomy    = '';
let _currentTerm = '';
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
	const term = btn.dataset.term ?? '';
	if ( term === _currentTerm ) return;

	_currentTerm = term;
	_tabBtns.forEach( ( b ) => b.setAttribute( 'aria-selected', String( b === btn ) ) );
	fetchPosts( term, 1, false );
}

function onGridClick( e ) {
	const pager = e.target.closest( '[data-js="cpt-pager"]' );
	if ( ! pager ) return;
	const page = parseInt( pager.dataset.page, 10 );
	if ( ! page || page < 1 ) return;
	fetchPosts( _currentTerm, page, true );
}

async function fetchPosts( term, paged, immediate = true ) {
	if ( ! _grid ) return;

	_grid.setAttribute( 'aria-busy', 'true' );

	const body = new URLSearchParams( {
		action:    'two57_cpt_archive',
		nonce:     window.two57Ajax?.cptNonce ?? '',
		post_type: _postType,
		taxonomy:  _taxonomy,
		term,
		paged: String( paged ),
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
		const delay = i * 80;
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

	_grid     = _container.querySelector( '[data-js="cpt-grid"]' );
	_postType = _container.dataset.postType ?? '';
	_taxonomy = _container.dataset.taxonomy ?? '';
	_tabBtns  = Array.from( _container.querySelectorAll( '[data-js="cpt-tab"]' ) );

	// Reset to "All" on every init (handles Swup back-navigation).
	_currentTerm = '';
	_tabBtns.forEach( ( btn ) => {
		btn.setAttribute( 'aria-selected', String( btn.dataset.term === '' ) );
		btn.addEventListener( 'click', () => onTabClick( btn ) );
	} );

	// Delegated pagination clicks on the grid.
	if ( _grid ) {
		_grid.addEventListener( 'click', onGridClick );
		// Animate cards that were server-side rendered on initial load.
		animateCards();
	}
}

export function destroyCptArchive() {
	// Event listeners are attached to DOM nodes that Swup will discard;
	// no explicit teardown needed.
	_container   = null;
	_grid        = null;
	_postType    = '';
	_taxonomy    = '';
	_currentTerm = '';
	_tabBtns     = [];
}
