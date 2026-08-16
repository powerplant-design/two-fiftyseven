/**
 * 257 Meet Pricing Calculator — engine
 * ----------------------------------------------------------------------------
 * Quote tool for meeting + event room bookings. Pick a room + duration + dates
 * + add-ons, see an itemised total live. Optional Impact Discount toggle for
 * eligible for-purpose organisations (50% off room rate, ACF-controlled).
 *
 * Reads all rates from window.twofiftyseven:
 *   - rooms   (6 × {capacity, day, hour, evening} — slugs match block.php)
 *   - addons  ({ av.projector, av.sound, tea.{singlePerHead, bottomlessPerHead},
 *                catering.organisingFee })
 *   - impact  ({ givingRatePerPersonHour, discountPct, paidForwardTotal,
 *                eligibilityCeiling, paidForwardDisplay })
 *
 * Working-pattern assumptions (full day = 8h, evening = 4h) are the cited
 * 257 source methodology, stay in code.
 *
 * Markup contract: root has [data-js="calc-meet-pricing"].
 * Inputs:
 *   [data-calc-people-range|slider|inc|dec|out] — people slider (non-linear scale)
 *   [data-calc-room-group] [data-calc-room] (+ [data-calc-room-rec],
 *                                              [data-calc-rec-people],
 *                                              [data-calc-rec-room])
 *   [data-calc-duration-group] [data-calc-duration="hour|day|evening"]
 *   [data-calc-days-list] + [data-calc-add-day] (repeating day rows)
 *   [data-calc-addon="<slug>"] [data-calc-addon-checkbox]
 *                              [data-calc-addon-tea-type]
 *                              [data-calc-addon-catering-perhead]
 *   [data-calc-impact-checkbox]
 * Outputs:
 *   [data-calc-quote-total] [data-calc-quote-items] [data-calc-quote-prompt]
 *   [data-calc-impact] [data-calc-impact-label] [data-calc-impact-amount]
 *   [data-calc-impact-context] [data-calc-impact-total]
 *
 * URL sync: ?people&room&dur&days&addons&impact — share-link reproduces state.
 * ============================================================================
 */

import { initCalcShare } from './calc-share.js';
import { bindRovingRadio, fmt$ } from './calc-utils.js';

// --- Methodology constants (cited, stay in code) ---
const M = {
	MAX_PEOPLE: 200,
	FULL_DAY_HOURS: 8,
	EVENING_HOURS: 4,
	DEFAULT_PEOPLE: 6,
	DEFAULT_TEA_TYPE: 5, // single serve $5 (matches ACF default)
	DEFAULT_CATERING_PER_HEAD: 25,
	MIN_CATERING_PER_HEAD: 0,
	MAX_CATERING_PER_HEAD: 200,
};

// Stepped people scale: 1-60 by 1, then 70-200 by 10. Most bookings sit at 4-60;
// rare event/whole-venue jobs extend the tail. The slider's max is the last
// index (73) and JS translates index -> actual count.
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

// --- Formatters ---
function fmtHrs( n ) {
	const v = Math.round( n * 2 ) / 2;
	return ( String( v ).replace( /\.0$/, '' ) ) + 'hrs';
}

function parseTime( str ) {
	const [ h, m ] = ( str || '0:0' ).split( ':' ).map( Number );
	return h + ( m / 60 );
}

function hoursForDay( d ) {
	return Math.max( 0, parseTime( d.end ) - parseTime( d.start ) );
}

const todayISO = () => new Date().toISOString().slice( 0, 10 );

// --- Read SSOT ---
function getSSOT() {
	const tfs = window.twofiftyseven || {};
	const rooms = tfs.rooms || {};
	const addons = tfs.addons || {};
	const impact = tfs.impact || {};
	return { rooms, addons, impact };
}

// --- Compute ---
function compute( state, ssot ) {
	const days = state.days || [];
	const numDays = days.length;
	const totalHours = days.reduce( ( s, d ) => s + hoursForDay( d ), 0 );

	if ( ! state.room || ! state.duration ) {
		return {
			empty: true,
			items: [], total: 0,
			discountAmt: 0, impactDonation: 0,
			totalHours, numDays, roomName: null,
			roomLabel: '',
		};
	}

	const room = ssot.rooms[ state.room ] || {};
	const roomName = state.roomName || room.name || state.room;
	const rates = { day: room.day || 0, hour: room.hour || 0, evening: room.evening || 0 };

	const items = [];
	let roomCost = 0;
	let roomLabel = '';

	if ( state.duration === 'hour' ) {
		roomCost = rates.hour * totalHours;
		roomLabel = roomName + ' · ' + ( numDays > 1 ? numDays + ' days × ' : '' ) + fmtHrs( totalHours ) + ' × ' + fmt$( rates.hour ) + '/hr';
	} else if ( state.duration === 'day' ) {
		roomCost = rates.day * numDays;
		roomLabel = roomName + ' · ' + numDays + ' day' + ( numDays > 1 ? 's' : '' ) + ' × ' + fmt$( rates.day );
	} else if ( state.duration === 'evening' ) {
		roomCost = rates.evening * numDays;
		roomLabel = roomName + ' · ' + numDays + ' evening' + ( numDays > 1 ? 's' : '' ) + ' × ' + fmt$( rates.evening );
	}
	items.push( { label: roomLabel, value: roomCost } );

	// Tea + coffee (per-head × people × days)
	if ( state.addons.tea ) {
		const teaCost = state.addons.teaType * state.people * numDays;
		const teaLabel = state.addons.teaType >= 10 ? 'bottomless' : 'single serve';
		items.push( {
			label: 'Tea + coffee · ' + teaLabel + ' × ' + state.people + 'pp' + ( numDays > 1 ? ' × ' + numDays + ' days' : '' ),
			value: teaCost,
		} );
	}

	// Catering (per-head × people + organising fee, × days)
	if ( state.addons.catering ) {
		const organisingFee = ( ssot.addons.catering && ssot.addons.catering.organisingFee ) || 100;
		const perHead = state.addons.cateringPerHead;
		const cateringCost = ( perHead * state.people + organisingFee ) * numDays;
		items.push( {
			label: 'Catering · ' + state.people + 'pp × ' + fmt$( perHead ) + ' + ' + fmt$( organisingFee ) + ' organising' + ( numDays > 1 ? ' × ' + numDays + ' days' : '' ),
			value: cateringCost,
		} );
	}

	// Projector ($50 flat × days)
	if ( state.addons.projector ) {
		const projectorFlat = ( ssot.addons.av && ssot.addons.av.projector && ssot.addons.av.projector.flat ) || 50;
		items.push( {
			label: 'Projector' + ( numDays > 1 ? ' × ' + numDays + ' days' : '' ),
			value: projectorFlat * numDays,
		} );
	}

	// Sound ($50 flat × days)
	if ( state.addons.sound ) {
		const soundFlat = ( ssot.addons.av && ssot.addons.av.sound && ssot.addons.av.sound.flat ) || 50;
		items.push( {
			label: 'Sound system' + ( numDays > 1 ? ' × ' + numDays + ' days' : '' ),
			value: soundFlat * numDays,
		} );
	}

	let total = items.reduce( ( s, it ) => s + it.value, 0 );
	let discountAmt = 0;
	const discountPct = ssot.impact.discountPct || 0.5;
	if ( state.addons.impact ) {
		discountAmt = roomCost * discountPct;
		total -= discountAmt;
		items.push( {
			label: 'Impact Discount · ' + ( discountPct * 100 ).toFixed( 0 ) + '% off room',
			value: -discountAmt,
			discount: true,
		} );
	}

	// Impact donation (giving $ funded by this booking): hours × people × rate.
	// Day + evening blocks are valued at the methodology constants, hourly
	// bookings use actual duration.
	let impactHours = totalHours;
	if ( state.duration === 'day' )     impactHours = numDays * M.FULL_DAY_HOURS;
	else if ( state.duration === 'evening' ) impactHours = numDays * M.EVENING_HOURS;
	const givingRate = ssot.impact.givingRatePerPersonHour || 1;
	const impactDonation = Math.round( impactHours * state.people * givingRate );

	return {
		empty: false,
		items, total,
		discountAmt, impactDonation,
		totalHours, numDays,
		roomName, roomLabel,
	};
}

// --- State <-> URL ---
function readURL( ssot ) {
	const params = new URLSearchParams( window.location.search );
	const state = {
		people: M.DEFAULT_PEOPLE,
		room: null,
		duration: null,
		days: [ { date: todayISO(), start: '09:00', end: '17:00' } ],
		addons: {
			impact: false,
			tea: false,
			teaType: M.DEFAULT_TEA_TYPE,
			catering: false,
			cateringPerHead: M.DEFAULT_CATERING_PER_HEAD,
			projector: false,
			sound: false,
		},
	};

	const p = parseInt( params.get( 'people' ), 10 );
	if ( ! isNaN( p ) && p > 0 ) state.people = Math.min( M.MAX_PEOPLE, p );

	const r = params.get( 'room' );
	if ( r && ssot.rooms[ r ] ) state.room = r;

	const dur = params.get( 'dur' );
	if ( dur === 'hour' || dur === 'day' || dur === 'evening' ) state.duration = dur;

	const days = params.get( 'days' );
	if ( days && days.trim() ) {
		const parsed = days.split( ',' ).map( ( s ) => {
			const [ date, range ] = s.split( '|' );
			const [ start, end ] = ( range || '09:00-17:00' ).split( '-' );
			return { date: date || todayISO(), start: start || '09:00', end: end || '17:00' };
		} ).filter( ( d ) => d.date );
		if ( parsed.length ) state.days = parsed;
	}

	const addons = params.get( 'addons' );
	if ( addons && addons.trim() ) {
		for ( const token of addons.split( ',' ) ) {
			const trimmed = token.trim();
			if ( ! trimmed ) continue;
			if ( trimmed === 'tea-single' )        { state.addons.tea = true;     state.addons.teaType = 5; }
			else if ( trimmed === 'tea-bottomless' ) { state.addons.tea = true;   state.addons.teaType = 10; }
			else if ( trimmed === 'projector' )      { state.addons.projector = true; }
			else if ( trimmed === 'sound' )           { state.addons.sound = true; }
			else if ( trimmed.startsWith( 'catering-' ) ) {
				const perHead = parseInt( trimmed.slice( 'catering-'.length ), 10 );
				if ( ! isNaN( perHead ) ) {
					state.addons.catering = true;
					state.addons.cateringPerHead = Math.max( M.MIN_CATERING_PER_HEAD, Math.min( M.MAX_CATERING_PER_HEAD, perHead ) );
				}
			}
		}
	}

	if ( params.get( 'impact' ) === '1' || params.get( 'impact' ) === 'true' ) {
		state.addons.impact = true;
	}

	return state;
}

function writeURL( state ) {
	const params = new URLSearchParams( window.location.search );
	params.set( 'people', state.people );
	if ( state.room ) params.set( 'room', state.room );
	else params.delete( 'room' );
	if ( state.duration ) params.set( 'dur', state.duration );
	else params.delete( 'dur' );

	if ( state.days.length ) {
		params.set( 'days', state.days.map( ( d ) => `${ d.date }|${ d.start }-${ d.end }` ).join( ',' ) );
	} else {
		params.delete( 'days' );
	}

	const tokens = [];
	if ( state.addons.tea ) tokens.push( state.addons.teaType >= 10 ? 'tea-bottomless' : 'tea-single' );
	if ( state.addons.projector ) tokens.push( 'projector' );
	if ( state.addons.sound ) tokens.push( 'sound' );
	if ( state.addons.catering ) tokens.push( 'catering-' + state.addons.cateringPerHead );
	if ( tokens.length ) params.set( 'addons', tokens.join( ',' ) );
	else params.delete( 'addons' );

	if ( state.addons.impact ) params.set( 'impact', '1' );
	else params.delete( 'impact' );

	const newURL = `${ window.location.pathname }?${ params.toString() }${ window.location.hash }`;
	window.history.replaceState( {}, '', newURL );
}

// --- Renderers ---
function renderResults( root, state, computed, ssot ) {
	// Total + tax line
	root.querySelectorAll( '[data-calc-quote-total]' ).forEach( ( el ) => {
		el.textContent = fmt$( computed.total );
	} );

	// Itemised list / empty prompt
	const itemsEl = root.querySelector( '[data-calc-quote-items]' );
	if ( ! itemsEl ) return;

	if ( computed.empty ) {
		itemsEl.innerHTML = '';
		const prompt = document.createElement( 'p' );
		prompt.className = 'calc__result-empty | text-s';
		prompt.setAttribute( 'data-calc-quote-prompt', '' );
		prompt.textContent = ! state.room
			? 'Pick a room and duration to see your itemised quote'
			: 'Pick a duration to see your itemised quote';
		itemsEl.appendChild( prompt );
		root.querySelectorAll( '[data-calc-impact]' ).forEach( ( el ) => { el.hidden = true; } );
		return;
	}

	// Items (each a .calc__compare-row with label + value)
	itemsEl.innerHTML = '';
	for ( const item of computed.items ) {
		const row = document.createElement( 'div' );
		row.className = 'calc__compare-row' + ( item.discount ? ' meet-pricing__quote-row--discount' : '' );
		const label = document.createElement( 'div' );
		label.className = 'calc__compare-row-label';
		label.textContent = item.label;
		const value = document.createElement( 'div' );
		value.className = 'calc__compare-row-value';
		value.textContent = ( item.value < 0 ? '−' : '' ) + fmt$( Math.abs( item.value ) );
		row.appendChild( label );
		row.appendChild( value );
		itemsEl.appendChild( row );
	}

	// Impact card — receiver or contributor depending on Impact Discount state
	const impactBlock = root.querySelector( '[data-calc-impact]' );
	if ( impactBlock ) {
		const givingRate = ssot.impact.givingRatePerPersonHour || 1;
		const paid = ssot.impact.paidForwardDisplay || '';
		root.querySelectorAll( '[data-calc-impact-total]' ).forEach( ( el ) => {
			el.textContent = paid;
		} );

		if ( state.addons.impact && computed.discountAmt > 0 ) {
			impactBlock.hidden = false;
			root.querySelectorAll( '[data-calc-impact-label]' ).forEach( ( el ) => {
				el.textContent = "You're supported by";
			} );
			root.querySelectorAll( '[data-calc-impact-amount]' ).forEach( ( el ) => {
				el.textContent = fmt$( computed.discountAmt );
			} );
			root.querySelectorAll( '[data-calc-impact-context]' ).forEach( ( el ) => {
				el.innerHTML = "paid forward by others, so spaces like ours stay open to charities, NGOs, and community work. Joining <strong data-calc-impact-total>" + paid + "</strong> paid forward since 2021.";
			} );
		} else if ( computed.impactDonation > 0 ) {
			impactBlock.hidden = false;
			root.querySelectorAll( '[data-calc-impact-label]' ).forEach( ( el ) => {
				el.textContent = 'Your booking also funds';
			} );
			root.querySelectorAll( '[data-calc-impact-amount]' ).forEach( ( el ) => {
				el.textContent = fmt$( computed.impactDonation );
			} );
			root.querySelectorAll( '[data-calc-impact-context]' ).forEach( ( el ) => {
				el.innerHTML = "of subsidised space for charities + community orgs. Contributing to <strong data-calc-impact-total>" + paid + "</strong> paid forward since 2021.";
			} );
		} else {
			impactBlock.hidden = true;
		}
	}

	// Update each day row's hours indicator
	state.days.forEach( ( day, i ) => {
		root.querySelectorAll( `[data-calc-day-index="${ i }"]` ).forEach( ( el ) => {
			el.textContent = fmtHrs( hoursForDay( day ) );
		} );
	} );
}

function updateRoomAvailability( root, state, ssot ) {
	const roomTiles = Array.from( root.querySelectorAll( '[data-calc-room-group] [data-calc-room]' ) );
	const tilesBySlug = new Map();
	roomTiles.forEach( ( tile ) => {
		const slug = tile.getAttribute( 'data-calc-room' );
		tilesBySlug.set( slug, tile );
		const roomData = ssot.rooms[ slug ] || {};
		const cap = roomData.capacity || 0;
		if ( cap > 0 && cap < state.people ) {
			tile.setAttribute( 'aria-disabled', 'true' );
			tile.removeAttribute( 'data-recommended' );
		} else {
			tile.removeAttribute( 'aria-disabled' );
		}
	} );

	// Smallest room with cap >= people; fallback to largest available.
	const fits = roomTiles.filter( ( t ) => t.getAttribute( 'aria-disabled' ) !== 'true' );
	let rec = null;
	for ( const tile of fits ) {
		const slug = tile.getAttribute( 'data-calc-room' );
		const cap = ( ssot.rooms[ slug ] || {} ).capacity || Infinity;
		rec = rec || { tile, cap };
		if ( cap < rec.cap ) rec = { tile, cap };
	}
	if ( rec ) rec.tile.setAttribute( 'data-recommended', 'true' );
	return rec ? rec.tile : null;
}

function pickRoomForPeople( root, state, ssot ) {
	const rec = updateRoomAvailability( root, state, ssot );
	if ( rec ) {
		const slug = rec.getAttribute( 'data-calc-room' );
		const roomData = ssot.rooms[ slug ] || {};
		return { slug, name: roomData.name || slug, tile: rec };
	}
	return null;
}

function selectRoomTile( root, tile ) {
	root.querySelectorAll( '[data-calc-room-group] [data-calc-room]' ).forEach( ( t ) => {
		t.setAttribute( 'aria-checked', 'false' );
	} );
	if ( ! tile ) return;
	tile.setAttribute( 'aria-checked', 'true' );
}

// Top-level room picker — captures state.room, state.roomName (read from
// the tile DOM so casing stays consistent across the tile label + quote
// items), and refreshes the evening-duration pill availability based on
// the selected room's evening rate.
function selectRoom( root, state, ssot, tile ) {
	if ( ! tile || tile.getAttribute( 'aria-disabled' ) === 'true' ) return;
	selectRoomTile( root, tile );
	state.room = tile.getAttribute( 'data-calc-room' );
	const nameEl = tile.querySelector( '.meet-pricing__room-name' );
	state.roomName = nameEl ? nameEl.textContent : state.room;
	const roomData = ssot.rooms[ state.room ] || {};
	const eveningPill = root.querySelector( '[data-calc-duration="evening"]' );
	if ( eveningPill ) {
		if ( ! roomData.evening || roomData.evening === 0 ) {
			eveningPill.setAttribute( 'aria-disabled', 'true' );
			if ( state.duration === 'evening' ) {
				state.duration = null;
				selectDurationPill( root, null );
			}
		} else {
			eveningPill.removeAttribute( 'aria-disabled' );
		}
	}
}

function selectDurationPill( root, pill ) {
	root.querySelectorAll( '[data-calc-duration-group] [data-calc-duration]' ).forEach( ( p ) => {
		p.setAttribute( 'aria-checked', 'false' );
	} );
	if ( ! pill ) return;
	pill.setAttribute( 'aria-checked', 'true' );
}

// --- Days list ---
function renderDaysList( root, state ) {
	const list = root.querySelector( '[data-calc-days-list]' );
	if ( ! list ) return;
	list.innerHTML = '';

	state.days.forEach( ( day, i ) => {
		const row = document.createElement( 'li' );
		row.className = 'calc__day-row';

		const label = document.createElement( 'span' );
		label.className = 'calc__day-row-label';
		label.textContent = 'Day ' + ( i + 1 );

		const inputs = document.createElement( 'div' );
		inputs.className = 'calc__day-row-inputs';

		const dateInput = document.createElement( 'input' );
		dateInput.type = 'date';
		dateInput.value = day.date;
		dateInput.setAttribute( 'aria-label', `Day ${ i + 1 } date` );
		dateInput.addEventListener( 'input', ( e ) => {
			day.date = e.target.value;
			writeURL( state );
			const c = compute( state, getSSOT() );
			renderResults( root, state, c, getSSOT() );
		} );

		const startInput = document.createElement( 'input' );
		startInput.type = 'time';
		startInput.value = day.start;
		startInput.setAttribute( 'aria-label', `Day ${ i + 1 } start time` );
		startInput.addEventListener( 'input', ( e ) => {
			day.start = e.target.value;
			writeURL( state );
			const c = compute( state, getSSOT() );
			renderResults( root, state, c, getSSOT() );
		} );

		const dash = document.createElement( 'span' );
		dash.textContent = '→';
		dash.setAttribute( 'aria-hidden', 'true' );

		const endInput = document.createElement( 'input' );
		endInput.type = 'time';
		endInput.value = day.end;
		endInput.setAttribute( 'aria-label', `Day ${ i + 1 } end time` );
		endInput.addEventListener( 'input', ( e ) => {
			day.end = e.target.value;
			writeURL( state );
			const c = compute( state, getSSOT() );
			renderResults( root, state, c, getSSOT() );
		} );

		const hoursEl = document.createElement( 'span' );
		hoursEl.className = 'calc__day-row-hours';
		hoursEl.setAttribute( 'data-calc-day-index', String( i ) );
		hoursEl.textContent = fmtHrs( hoursForDay( day ) );

		inputs.appendChild( dateInput );
		inputs.appendChild( startInput );
		inputs.appendChild( dash );
		inputs.appendChild( endInput );
		inputs.appendChild( hoursEl );

		const remove = document.createElement( 'button' );
		remove.type = 'button';
		remove.className = 'calc__day-row-remove';
		remove.setAttribute( 'aria-label', 'Remove day ' + ( i + 1 ) );
		remove.textContent = '×';
		remove.disabled = state.days.length <= 1;
		remove.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			if ( state.days.length === 1 ) return;
			state.days.splice( i, 1 );
			writeURL( state );
			renderDaysList( root, state );
			const c = compute( state, getSSOT() );
			renderResults( root, state, c, getSSOT() );
		} );

		row.appendChild( label );
		row.appendChild( inputs );
		row.appendChild( remove );
		list.appendChild( row );
	} );
}

// --- Event binding ---
function bindEvents( root, state, ssot ) {
	// ── People slider (range + −/+ steppers + readout) ────────
	const peopleRange = root.querySelector( '[data-calc-people-range]' );
	const peopleSlider = root.querySelector( '[data-calc-people-slider]' );
	const peopleOut = root.querySelector( '[data-calc-people-out]' );
	const peopleDec = root.querySelector( '[data-calc-people-dec]' );
	const peopleInc = root.querySelector( '[data-calc-people-inc]' );

	function paintPeople( idx ) {
		if ( peopleSlider ) peopleSlider.style.setProperty( '--pct', ( idx / MAX_IDX ) * 100 + '%' );
		if ( peopleOut ) {
			peopleOut.value = String( PEOPLE_SCALE[ idx ] );
		}
		if ( peopleDec ) peopleDec.disabled = idx <= 0;
		if ( peopleInc ) peopleInc.disabled = idx >= MAX_IDX;
	}

	function updatePeopleFromIdx( idx ) {
		const clamped = Math.max( 0, Math.min( MAX_IDX, idx ) );
		state.people = PEOPLE_SCALE[ clamped ];
		if ( peopleRange ) peopleRange.value = String( clamped );
		paintPeople( clamped );
		rerender();
	}

	if ( peopleRange ) {
		peopleRange.addEventListener( 'input', () => {
			updatePeopleFromIdx( parseInt( peopleRange.value, 10 ) );
		} );
	}
	if ( peopleDec ) peopleDec.addEventListener( 'click', () => updatePeopleFromIdx( peopleIndexOf( state.people ) - 1 ) );
	if ( peopleInc ) peopleInc.addEventListener( 'click', () => updatePeopleFromIdx( peopleIndexOf( state.people ) + 1 ) );

	// ── Room tile radios (WAI-ARIA pattern, see plan §7) ──────
	const roomTiles = Array.from( root.querySelectorAll( '[data-calc-room-group] [data-calc-room]' ) );

	const recPeopleEl = root.querySelector( '[data-calc-rec-people]' );
	const recRoomEl = root.querySelector( '[data-calc-rec-room]' );

	function refreshRecommendationLine() {
		const rec = root.querySelector( '[data-calc-room-group] [data-room-recommended]' )
			|| root.querySelector( '[data-calc-room-group] [data-recommended="true"]' );
		const recLine = root.querySelector( '[data-calc-room-rec]' );
		if ( recLine ) recLine.hidden = ! rec;
		if ( rec ) {
			if ( recPeopleEl ) recPeopleEl.textContent = String( state.people );
			if ( recRoomEl ) recRoomEl.textContent = rec.querySelector( '.meet-pricing__room-name' )?.textContent || '';
		}
	}

	roomTiles.forEach( ( tile ) => {
		tile.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			selectRoom( root, state, ssot, tile );
			rerender();
		} );
	} );

	bindRovingRadio( roomTiles, ( tile ) => {
		selectRoom( root, state, ssot, tile );
		rerender();
	} );

	// ── Duration radios (WAI-ARIA pattern) ────────────────────
	const durPills = Array.from( root.querySelectorAll( '[data-calc-duration-group] [data-calc-duration]' ) );

	function selectDuration( pill ) {
		if ( ! pill || pill.getAttribute( 'aria-disabled' ) === 'true' ) return;
		selectDurationPill( root, pill );
		state.duration = pill.getAttribute( 'data-calc-duration' );
	}

	durPills.forEach( ( pill ) => {
		pill.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			selectDuration( pill );
			rerender();
		} );
	} );
	bindRovingRadio( durPills, ( pill ) => {
		selectDuration( pill );
		rerender();
	} );

	// ── Add-day button ───────────────────────────────────────
	const addDayBtn = root.querySelector( '[data-calc-add-day]' );
	if ( addDayBtn ) {
		addDayBtn.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			const last = state.days[ state.days.length - 1 ];
			const next = new Date( last.date );
			next.setDate( next.getDate() + 1 );
			state.days.push( {
				date: next.toISOString().slice( 0, 10 ),
				start: last.start,
				end: last.end,
			} );
			writeURL( state );
			renderDaysList( root, state );
			rerender();
		} );
	}

	// ── Addon checkboxes (native change bubbles; label toggles) ─
	root.querySelectorAll( '[data-calc-addon]' ).forEach( ( addon ) => {
		const slug = addon.getAttribute( 'data-calc-addon' );
		const checkbox = addon.querySelector( '[data-calc-addon-checkbox]' );
		if ( ! checkbox ) return;

		// Mirror checkbox state -> data-on for the CSS :has-driven extra panel + card glow
		// (the shared .calc__check already paints the swatch via :checked; this is for any
		//  per-calc visualisation that keys off the data-attr).
		const syncDataOn = () => addon.setAttribute( 'data-on', checkbox.checked ? 'true' : 'false' );

		checkbox.checked = !! ( state.addons[ slug ] );
		syncDataOn();

		// Whole-card click target: the label head only covers the title row, so
		// clicks on the card body/copy/padding would do nothing. Toggle from
		// anywhere except the nested controls — the head label (native label
		// behaviour already toggles), the checkbox itself, the tea-type select,
		// the catering per-head field, and the extra panel (showing its gap
		// shouldn't switch the addon off).
		addon.addEventListener( 'click', ( e ) => {
			if ( e.target.closest( 'input, select, label, .meet-pricing__addon-extra' ) ) return;
			e.preventDefault();
			checkbox.checked = ! checkbox.checked;
			checkbox.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );

		checkbox.addEventListener( 'change', () => {
			state.addons[ slug ] = checkbox.checked;
			syncDataOn();
			writeURL( state );
			rerender();
		} );

		// Keyboard: normalise Enter + Space to a single toggle (native behaviour
		// varies by browser for visually-hidden checkboxes — Enter doesn't
		// toggle a native checkbox in any browser; Space does but the keydown
		// capture lets us stop Locomotive Scroll intercepting it).
		checkbox.addEventListener( 'keydown', ( e ) => {
			if ( ( e.key === 'Enter' || e.key === ' ' ) && e.target === checkbox ) {
				e.preventDefault();
				e.stopPropagation();
				checkbox.checked = ! checkbox.checked;
				checkbox.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		}, { capture: true } );

		// Tea-type select
		const teaSelect = addon.querySelector( '[data-calc-addon-tea-type]' );
		if ( teaSelect ) {
			teaSelect.value = String( state.addons.teaType );
			teaSelect.addEventListener( 'change', () => {
				state.addons.teaType = parseInt( teaSelect.value, 10 ) || 5;
				writeURL( state );
				rerender();
			} );
			// Keyboard: open the dropdown on Enter/Space — native behaviour varies
			// by browser (Safari won't open on Space/Enter), so normalise it.
			// Matches the workspace-pricing roster select pattern.
			teaSelect.addEventListener( 'keydown', ( e ) => {
				if ( ( e.key === 'Enter' || e.key === ' ' ) && e.target === teaSelect ) {
					if ( teaSelect.showPicker ) {
						try {
							teaSelect.showPicker();
							e.preventDefault();
						} catch { /* picker already open or unavailable */ }
					}
				}
			}, { capture: true } );
		}

		// Catering per-head input
		const cateringInput = addon.querySelector( '[data-calc-addon-catering-perhead]' );
		if ( cateringInput ) {
			cateringInput.value = String( state.addons.cateringPerHead );
			cateringInput.addEventListener( 'input', () => {
				const v = parseFloat( cateringInput.value );
				state.addons.cateringPerHead = isNaN( v ) ? 0 : Math.max( M.MIN_CATERING_PER_HEAD, Math.min( M.MAX_CATERING_PER_HEAD, v ) );
				writeURL( state );
				rerender();
			} );
		}
	} );

	// ── Slider firing on people change → refresh room availability + recompute
	function rerender() {
		// Refresh room availability based on the new people count.
		updateRoomAvailability( root, state, ssot );

		// If the currently-selected room is now too small, snap to the recommended.
		const currentTile = root.querySelector( '[data-calc-room="' + state.room + '"]' );
		if ( currentTile && currentTile.getAttribute( 'aria-disabled' ) === 'true' ) {
			const rec = pickRoomForPeople( root, state, ssot );
			if ( rec ) {
				selectRoom( root, state, ssot, root.querySelector( '[data-calc-room="' + rec.slug + '"]' ) );
			} else {
				state.room = null;
				selectRoomTile( root, null );
			}
		} else if ( ! state.room ) {
			const rec = pickRoomForPeople( root, state, ssot );
			if ( rec ) {
				selectRoom( root, state, ssot, root.querySelector( '[data-calc-room="' + rec.slug + '"]' ) );
			}
		}

		// Refresh the recommendation line (recommended room name + N people).
		const recTile = root.querySelector( '[data-calc-room-group] [data-recommended="true"]' );
		const recLine = root.querySelector( '[data-calc-room-rec]' );
		if ( recLine ) recLine.hidden = ! recTile;
		if ( recTile ) {
			if ( recPeopleEl ) recPeopleEl.textContent = String( state.people );
			if ( recRoomEl ) recRoomEl.textContent = recTile.querySelector( '.meet-pricing__room-name' )?.textContent || '';
		}

		// Recompute + render results.
		const computed = compute( state, ssot );
		renderResults( root, state, computed, ssot );
		writeURL( state );
	}

	return rerender;
}

// --- Init ---
function initCalc( root ) {
	if ( ! root ) return;

	const ssot = getSSOT();

	// If SSOT rooms aren't injected, the calc is unusable; bail silently so
	// the surrounding page doesn't throw (no-JS / preview fallback).
	if ( ! ssot.rooms || Object.keys( ssot.rooms ).length === 0 ) return;

	const state = readURL( ssot );

	// Hydrate the people slider to match state.people.
	const peopleRange = root.querySelector( '[data-calc-people-range]' );
	const peopleSlider = root.querySelector( '[data-calc-people-slider]' );
	const peopleOut = root.querySelector( '[data-calc-people-out]' );
	const idx = peopleIndexOf( state.people );
	if ( peopleRange ) peopleRange.value = String( idx );
	if ( peopleSlider ) peopleSlider.style.setProperty( '--pct', ( idx / MAX_IDX ) * 100 + '%' );
	if ( peopleOut ) peopleOut.value = String( state.people );
	const peopleDec = root.querySelector( '[data-calc-people-dec]' );
	const peopleInc = root.querySelector( '[data-calc-people-inc]' );
	if ( peopleDec ) peopleDec.disabled = idx <= 0;
	if ( peopleInc ) peopleInc.disabled = idx >= MAX_IDX;

	// Mark room availability + auto-pick the recommended room if none selected.
	// Calls the top-level selectRoom() so state.room, state.roomName, and the
	// evening-duration pill's aria-disabled state are all hydrated together.
	updateRoomAvailability( root, state, ssot );
	if ( ! state.room ) {
		const rec = pickRoomForPeople( root, state, ssot );
		if ( rec ) {
			selectRoom( root, state, ssot, rec.tile );
		}
	} else {
		const tile = root.querySelector( '[data-calc-room="' + state.room + '"]' );
		if ( tile ) {
			selectRoom( root, state, ssot, tile );
		}
	}

	// Hydrate the duration pill from state.duration (may be null on cold load).
	// A room with no evening rate disables the evening pill (handled above by
	// selectRoom); fall back to null if the prior selection no longer applies.
	if ( state.duration ) {
		const pill = root.querySelector( '[data-calc-duration="' + state.duration + '"]' );
		if ( pill && pill.getAttribute( 'aria-disabled' ) !== 'true' ) {
			selectDurationPill( root, pill );
		} else {
			state.duration = null;
		}
	}

	// Render the days list (also seeds the per-day hours indicators).
	renderDaysList( root, state );

	// Recommendation line + initial results.
	const recTile = root.querySelector( '[data-calc-room-group] [data-recommended="true"]' );
	const recLine = root.querySelector( '[data-calc-room-rec]' );
	if ( recLine ) recLine.hidden = ! recTile;
	if ( recTile ) {
		const recPeopleEl = root.querySelector( '[data-calc-rec-people]' );
		const recRoomEl = root.querySelector( '[data-calc-rec-room]' );
		if ( recPeopleEl ) recPeopleEl.textContent = String( state.people );
		if ( recRoomEl ) recRoomEl.textContent = recTile.querySelector( '.meet-pricing__room-name' )?.textContent || '';
	}

	const rerender = bindEvents( root, state, ssot );
	rerender();

	// Share row (email + copy link) — shared handler module. The share section
	// lives OUTSIDE the calc body grid (it has its own container so the sticky
	// quote aside scrolls past it), so look it up from the wrapper parent.
	initCalcShare( root.parentElement, {
		slug: 'meet-pricing',
		getState: () => ( {
			people:     state.people,
			room:       state.room,
			duration:   state.duration,
			days:       state.days.map( ( d ) => ( { date: d.date, start: d.start, end: d.end } ) ),
			addons:     {
				impact:             state.addons.impact,
				tea:                state.addons.tea,
				teaType:            state.addons.teaType,
				catering:           state.addons.catering,
				cateringPerHead:    state.addons.cateringPerHead,
				projector:          state.addons.projector,
				sound:              state.addons.sound,
			},
			impact:      state.addons.impact,
		} ),
	} );
}

export function initMeetPricing() {
	document.querySelectorAll( '[data-js="calc-meet-pricing"]' ).forEach( initCalc );
}