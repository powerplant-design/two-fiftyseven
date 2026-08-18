/**
 * 257 Calc Widget — engine
 * ----------------------------------------------------------------------------
 * Lightweight quote teaser for meeting + event room bookings. Pick people +
 * hours + room (auto-recommended by capacity), see a starting estimate live.
 * Deep-links to the full meet-pricing calculator with state carried via URL
 * params. Optional Impact Discount toggle (50% off for eligible organisations).
 *
 * Reads all rates from window.twofiftyseven:
 *   - rooms   (6 × {capacity, day, hour, evening} — slugs match block.php)
 *   - impact  ({ givingRatePerPersonHour, discountPct, paidForwardDisplay })
 *
 * Markup contract: root has [data-js="calc-widget"].
 * Config attributes on root:
 *   data-pricing-url  URL of the full quote tool (default: "/meetings/pricing/")
 *   data-room-set      "all" | "host" — which rooms are visible
 *
 * Inputs:
 *   [data-cw-people-range|slider|inc|dec|out] — people slider (non-linear scale)
 *   [data-cw-hours-range|slider|inc|dec|out]   — hours slider (1-12)
 *   [data-cw-room-group] [data-cw-room] (+ [data-cw-cap])
 *   [data-cw-impact-checkbox] (+ [data-cw-impact-card])
 * Outputs:
 *   [data-cw-amount] — estimated total
 *   [data-cw-impact] [data-cw-impact-label] [data-cw-impact-amount]
 *   [data-cw-impact-context]
 *   [data-cw-cta] — deep-link to full quote tool (href updated with state)
 *
 * No URL sync, no share/email — this is a teaser that hands off to C2.
 * ============================================================================
 */

import { bindRovingRadio, bindStepper, fmt$ } from './calc-utils.js';

// --- Constants ---
const M = {
	DEFAULT_PEOPLE: 6,
	DEFAULT_HOURS: 4,
	MIN_HOURS: 1,
	MAX_HOURS: 12,
};

// Stepped people scale: 1-60 by 1, then 70-200 by 10 (same as meet-pricing).
const PEOPLE_SCALE = ( () => {
	const a = [];
	for ( let i = 1; i <= 60; i++ ) a.push( i );
	for ( let i = 70; i <= 200; i += 10 ) a.push( i );
	return a;
} )();
const MAX_IDX = PEOPLE_SCALE.length - 1;

function peopleIndexOf( n ) {
	let best = 0;
	let bestDiff = Infinity;
	for ( let i = 0; i < PEOPLE_SCALE.length; i++ ) {
		const d = Math.abs( PEOPLE_SCALE[ i ] - n );
		if ( d < bestDiff ) {
			bestDiff = d;
			best = i;
		}
	}
	return best;
}

// --- Impact copy swap (giving vs receiving) ---
const IMPACT_COPY = {
	contributing: {
		label: 'Your booking also funds',
		context: '<strong>of subsidised space</strong> which has contributed <strong>{total}</strong> paid forward since 2021',
	},
	receiving: {
		label: 'You\'re supported by',
		context: '<strong>paid forward by others</strong> so spaces like ours stay open to charities, NGOs, and community work',
	},
};

export function initCalcWidget() {
	const root = document.querySelector( '[data-js="calc-widget"]' );
	if ( ! root ) return;

	const ssot = window.twofiftyseven || {};
	const rooms = ssot.rooms || {};
	const impact = ssot.impact || {};
	const givingRate = impact.givingRatePerPersonHour || 1;
	const discountPct = impact.discountPct || 0.5;
	const paidForwardDisplay = impact.paidForwardDisplay || '$450,000+';

	const pricingUrl = root.dataset.pricingUrl || '/meetings/pricing/';

	// --- Element refs ---
	const roomGroup = root.querySelector( '[data-cw-room-group]' );
	const roomBtns = Array.from( roomGroup.querySelectorAll( '[data-cw-room]' ) );
	const amountEl = root.querySelector( '[data-cw-amount]' );
	const impactBlock = root.querySelector( '[data-cw-impact]' );
	const impactLabelEl = root.querySelector( '[data-cw-impact-label]' );
	const impactAmountEl = root.querySelector( '[data-cw-impact-amount]' );
	const impactContextEl = root.querySelector( '[data-cw-impact-context]' );
	const ctaEl = root.querySelector( '[data-cw-cta]' );
	const impactCheckbox = root.querySelector( '[data-cw-impact-checkbox]' );
	const impactCard = root.querySelector( '[data-cw-impact-card]' );

	// --- State ---
	const state = {
		room: null,
		roomName: null,
		rates: { day: 0, hour: 0, evening: 0 },
		hours: M.DEFAULT_HOURS,
		people: M.DEFAULT_PEOPLE,
		impactDiscount: false,
	};

	// --- People stepper ---
	const peopleStepper = bindStepper( root, {
		rangeSel: '[data-cw-people-range]',
		sliderSel: '[data-cw-people-slider]',
		outSel: '[data-cw-people-out]',
		decSel: '[data-cw-people-dec]',
		incSel: '[data-cw-people-inc]',
		max: MAX_IDX,
		valueFor: ( i ) => PEOPLE_SCALE[ i ],
		current: () => peopleIndexOf( state.people ),
		onUpdate: ( idx ) => {
			state.people = PEOPLE_SCALE[ idx ];
			updatePillAvailability();
			// Always re-recommend the smallest room that fits, so the
			// total tracks the people slider in both directions.
			setRoomFromPill( recommendRoomForPeople( state.people ) );
			render();
		},
	} );

	// --- Hours stepper ---
	const hoursStepper = bindStepper( root, {
		rangeSel: '[data-cw-hours-range]',
		sliderSel: '[data-cw-hours-slider]',
		outSel: '[data-cw-hours-out]',
		decSel: '[data-cw-hours-dec]',
		incSel: '[data-cw-hours-inc]',
		max: M.MAX_HOURS,
		valueFor: ( i ) => i,
		current: () => state.hours,
		onUpdate: ( idx ) => {
			state.hours = Math.max( M.MIN_HOURS, idx );
			render();
		},
	} );

	// --- Room logic ---
	function recommendRoomForPeople( people ) {
		const fit = roomBtns.find( ( p ) => +p.dataset.cwCap >= people );
		return fit || roomBtns[ roomBtns.length - 1 ];
	}

	function setRoomFromPill( pill ) {
		if ( ! pill ) return;
		roomBtns.forEach( ( p ) => p.setAttribute( 'aria-checked', 'false' ) );
		pill.setAttribute( 'aria-checked', 'true' );
		state.room = pill.dataset.cwRoom;
		state.roomName = pill.querySelector( '.calc-widget__room-name' ).textContent.trim();
		const roomData = rooms[ state.room ];
		if ( roomData ) {
			state.rates = {
				day: roomData.day || 0,
				hour: roomData.hour || 0,
				evening: roomData.evening || 0,
			};
		}
	}

	function updatePillAvailability() {
		roomBtns.forEach( ( pill ) => {
			if ( +pill.dataset.cwCap < state.people ) {
				pill.setAttribute( 'aria-disabled', 'true' );
			} else {
				pill.removeAttribute( 'aria-disabled' );
			}
		} );
	}

	// Room pill clicks
	roomBtns.forEach( ( pill ) => {
		pill.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			if ( pill.getAttribute( 'aria-disabled' ) === 'true' ) return;
			setRoomFromPill( pill );
			render();
		} );
	} );

	// Roving arrow keys on room radios
	bindRovingRadio( roomBtns, ( radio ) => {
		if ( radio.getAttribute( 'aria-disabled' ) === 'true' ) return;
		setRoomFromPill( radio );
		render();
	} );

	// --- Impact discount toggle ---
	function setImpactDiscount( on ) {
		state.impactDiscount = !! on;
		impactCheckbox.checked = state.impactDiscount;
		render();
	}

	impactCheckbox.addEventListener( 'change', () => setImpactDiscount( impactCheckbox.checked ) );

	// Whole card click target (like booking toggle in office-costs)
	if ( impactCard ) {
		impactCard.addEventListener( 'click', ( e ) => {
			if ( e.target === impactCheckbox ) return;
			e.preventDefault();
			setImpactDiscount( ! state.impactDiscount );
		} );
	}

	// --- Render ---
	function render() {
		// Total = hourly rate × hours (the widget is a quick hourly estimate)
		let total = state.rates.hour * state.hours;
		if ( state.impactDiscount ) total = total * ( 1 - discountPct );
		amountEl.textContent = fmt$( total );

		// Impact statement
		const impactDonation = Math.round( state.hours * state.people * givingRate );

		if ( state.impactDiscount ) {
			impactBlock.hidden = false;
			const copy = IMPACT_COPY.receiving;
			impactLabelEl.textContent = copy.label;
			impactAmountEl.textContent = fmt$( total );
			impactContextEl.innerHTML = copy.context;
		} else if ( impactDonation > 0 ) {
			impactBlock.hidden = false;
			const copy = IMPACT_COPY.contributing;
			impactLabelEl.textContent = copy.label;
			impactAmountEl.textContent = fmt$( impactDonation );
			impactContextEl.innerHTML = copy.context.replace( '{total}', paidForwardDisplay );
		} else {
			impactBlock.hidden = true;
		}

		// CTA deep-link carries state to the full meet-pricing calc
		const params = new URLSearchParams( {
			room: state.room || '',
			dur: 'hour',
			hours: String( state.hours ),
			people: String( state.people ),
			impact: state.impactDiscount ? '1' : '0',
		} );
		ctaEl.href = pricingUrl + '?' + params.toString();
	}

	// --- Bootstrap ---
	updatePillAvailability();

	// Auto-pick room for default people
	setRoomFromPill( recommendRoomForPeople( state.people ) );

	// Paint steppers at their default positions
	peopleStepper.paintCurrent();
	hoursStepper.paintCurrent();

	render();
}
