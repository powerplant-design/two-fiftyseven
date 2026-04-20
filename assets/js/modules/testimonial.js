import Swiper from 'swiper';
import { Navigation, Pagination } from 'swiper/modules';

const instances = new Map();

export function initTestimonials() {
	document.querySelectorAll( '.testimonial__swiper' ).forEach( ( el ) => {
		// Skip if already initialised (e.g. repeated Swup navigations to same page)
		if ( instances.has( el ) ) return;

		const count = parseInt( el.dataset.slides, 10 );
		if ( count < 2 ) return; // Single slide — no carousel needed

		const swiper = new Swiper( el, {
			modules: [ Navigation, Pagination ],
			loop: true,
			navigation: {
				nextEl: el.querySelector( '.swiper-button-next' ),
				prevEl: el.querySelector( '.swiper-button-prev' ),
			},
			pagination: {
				el: el.querySelector( '.swiper-pagination' ),
				clickable: true,
			},
		} );

		instances.set( el, swiper );
	} );
}

export function destroyTestimonials() {
	instances.forEach( ( swiper ) => swiper.destroy( true, true ) );
	instances.clear();
}
