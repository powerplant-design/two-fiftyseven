/**
 * Case Studies — horizontal Swiper slider for organisation cards.
 *
 * Three cards fill the wrapper width; additional cards overflow right.
 * Nav arrows sit below slides (left); CTA sits bottom-right.
 */

import Swiper from 'swiper';
import { Navigation } from 'swiper/modules';

const instances = new Map();

export function initCaseStudies() {
	document.querySelectorAll( '.case-studies__swiper' ).forEach( ( el ) => {
		if ( instances.has( el ) ) return;

		const count = parseInt( el.dataset.slides, 10 );
		if ( count < 2 ) return;

		const swiper = new Swiper( el, {
			modules: [ Navigation ],
			slidesPerView: 'auto',
			spaceBetween: 16,
			grabCursor: true,
			allowTouchMove: true,
			breakpoints: {
				768: {
					spaceBetween: 24,
				},
			},
			navigation: {
				nextEl: el.querySelector( '.swiper-button-next' ),
				prevEl: el.querySelector( '.swiper-button-prev' ),
			},
		} );

		instances.set( el, swiper );
	} );
}

export function destroyCaseStudies() {
	instances.forEach( ( swiper ) => swiper.destroy( true, true ) );
	instances.clear();
}
