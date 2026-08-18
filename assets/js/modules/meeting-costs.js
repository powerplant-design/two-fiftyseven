/**
 * 257 Meeting Costs Calculator — engine
 * ----------------------------------------------------------------------------
 * Compares running a meeting, workshop, away-day or event at a Wellington
 * industry-standard venue against running it at two/fiftyseven. Group size +
 * day times drive a mid-market industry benchmark band (from the IND constants,
 * cited research — stay in code); catering, AV, facilitation, materials and
 * custom lines are added as selected. The 2/57 space is picked automatically
 * from the group size (pickSpace).
 *
 * Reads from window.twofiftyseven:
 *   - rooms   (6 × {capacity, day, hour, evening} — slugs match two57_meet_rooms())
 *   - addons  ({ av.{projector,sound}.flat, tea.singlePerHead,
 *                materials.{postits,printing}, setup.complex })
 *   - impact  ({ discountPct })
 *
 * Markup contract: root has [data-js="calc-meeting-costs"].
 * Inputs:
 *   [data-calc-size-*]                      — group-size slider/stepper/readout
 *   [data-calc-days-list] [data-calc-add-day] + JS-rendered rows
 *     (native date + start/end time pickers, [data-calc-day-index] hours
 *      readout, per-row remove button)
 *   [data-calc-check="<key>"]               — catering/AV/materials checkboxes
 *   [data-calc-fac-group] [data-calc-fac]   — facilitation radios
 *   [data-calc-setup-group] [data-calc-setup] — setup radios
 *   [data-calc-custom-list] [data-calc-custom-add] + [data-calc-custom-label|value]
 *   [data-calc-addon="impact"]              — impact discount option card
 * Outputs:
 *   [data-calc-result-ours] [data-calc-result-space] [data-calc-result-prompt]
 *   [data-calc-duration-label]
 *   [data-calc-industry-lines|total] [data-calc-ours-lines|total]
 *
 * URL sync: ?size&days&extras&fac&setup&custom&impact — share-link reproduces state.
 * ============================================================================
 */

import { initCalcShare } from './calc-share.js';
import { bindRovingRadio, bindStepper, bindBreakdownTrigger, bindSourceTooltips, fmt$ } from './calc-utils.js';

// --- Methodology constants (cited, stay in code) ---
const M = {
	MAX_SIZE: 200,
	DEFAULT_START: '09:00',
	DEFAULT_END: '17:00',
};

// Industry-standard bands (Wellington / Auckland) — midmarket ranges drawn from
// published venue hire pages + NZ catering/AV/facilitation market norms. See
// SOURCES. Every band needs Ash sign-off before publish (source FLAG).
const IND = {
	// Room hire — per duration, scaled by group size band.
	ROOM: {
		'half-day': { small: [350, 650],  mid: [550, 1100], large: [900, 2200] },
		'full-day': { small: [550, 1100], mid: [850, 1900], large: [1500, 3800] },
		'multi-day':{ small: [550, 1100], mid: [850, 1900], large: [1500, 3800] }, // PER DAY — engine multiplies by day count
		'evening':  { small: [350, 750],  mid: [600, 1300], large: [1100, 2600] },
		'hourly':   { small: [90, 180],   mid: [140, 280],  large: [220, 480] },
	},
	// Catering — per head bands (NZ).
	TEA:         [4, 8],
	BREAKFAST:   [12, 22],
	LUNCH_LIGHT: [20, 25],
	LUNCH_HEARTY:[30, 40],
	AFTERNOON:   [8, 15],
	DRINKS:      [25, 55],
	// AV — per booking (small event = half-day, full-day +30%, multi-day +120%)
	AV_PROJECTOR: [180, 350],
	AV_SOUND:     [280, 550],
	// Facilitation — flat bands (PayScale NZ / NZ Facilitators market data).
	FAC_HALF:   [1500, 4500],
	FAC_FULL:   [3500, 8000],
	FAC_SENIOR: [5000, 9000],
	// Materials
	MAT_WHITEBOARDS: [40, 120],
	MAT_POSTITS:     [25, 60],
	MAT_PRINTING:    [40, 150],
	// Setup
	SETUP_STD:     [80, 180],
	SETUP_COMPLEX: [250, 600],
};

// Breakdown source attributions (cited research — links stay in code).
const SOURCES = {
	room:     { label: 'Wellington venue rate cards',   name: 'Willeston',           url: 'https://willeston.co.nz/our-rooms/' },
	catering: { label: 'NZ catering market rates',      name: 'MoneyHub',            url: 'https://www.moneyhub.co.nz/best-caterers-wellington.html' },
	av:       { label: 'Wellington AV hire rates',      name: 'Edwards Sound',        url: 'https://www.edwardsnz.co.nz/' },
	facilitation: { label: 'NZ facilitation rates',     name: 'PayScale NZ',          url: 'https://www.payscale.com/research/NZ/Skill=Facilitator/Hourly_Rate' },
};

// --- Read SSOT ---
function getSSOT() {
	const tfs = window.twofiftyseven || {};
	return {
		rooms:   tfs.rooms  || {},
		addons:  tfs.addons || {},
		impact:  tfs.impact || {},
	};
}

// --- Size band + duration helpers (source methodology) ---
function sizeBand( n ) {
	if ( n <= 10 ) return 'small';
	if ( n <= 36 ) return 'mid';
	return 'large';
}

// Pick the 2/57 space for a given size + duration. Slugs match
// two57_meet_rooms() (the SSOT rooms object).
function pickSpace( size, duration ) {
	if ( size <= 6 && duration !== 'evening' ) return 'meeting-room';
	if ( size <= 12 ) return 'studio';
	if ( size <= 36 ) return 'workshop';
	if ( size <= 80 ) return 'event';
	return 'entire';
}

function durationFactor( duration, multiDayCount ) {
	if ( duration === 'hourly' )   return 0.4;
	if ( duration === 'evening' )  return 0.7;
	if ( duration === 'half-day' ) return 1.0;
	if ( duration === 'full-day' ) return 1.3;
	if ( duration === 'multi-day') return 1.3 * ( multiDayCount || 2 );
	return 1.0;
}

function roomDurationKey( duration ) {
	return duration === 'multi-day' ? 'multi-day'
		: duration === 'evening'    ? 'evening'
		: duration === 'hourly'     ? 'hourly'
		: duration === 'full-day'   ? 'full-day'
		: 'half-day';
}

// --- Time helpers (native date/time pickers, 24h "HH:MM" engine values) ---
function toMin( t ) {
	if ( ! t || typeof t !== 'string' ) return null;
	const p = t.split( ':' );
	if ( p.length < 2 ) return null;
	const h = parseInt( p[ 0 ], 10 );
	const m = parseInt( p[ 1 ], 10 );
	if ( isNaN( h ) || isNaN( m ) ) return null;
	return h * 60 + m;
}

// Local-timezone YYYY-MM-DD for a Date (toISOString would shift the day in
// UTC+x timezones, showing "yesterday" to NZ visitors in the morning).
function isoFormat( d ) {
	const m = String( d.getMonth() + 1 ).padStart( 2, '0' );
	const day = String( d.getDate() ).padStart( 2, '0' );
	return d.getFullYear() + '-' + m + '-' + day;
}

function todayISO() {
	return isoFormat( new Date() );
}

function hoursForDay( day ) {
	const s = toMin( day.start );
	const e = toMin( day.end );
	if ( s === null || e === null || e <= s ) return 0;
	return ( e - s ) / 60;
}

// Derive the duration key + hours from populated day rows.
function deriveDuration( days ) {
	const perDayHours = ( days || [] ).map( hoursForDay );
	const populated = perDayHours.map( ( h, i ) => ( h > 0 ? i : -1 ) ).filter( ( i ) => i >= 0 );
	const totalHours = perDayHours.reduce( ( a, b ) => a + b, 0 );

	if ( populated.length === 0 ) {
		return { duration: null, hours: 0, multiDayCount: 1 };
	}
	if ( populated.length >= 2 ) {
		return { duration: 'multi-day', hours: totalHours, multiDayCount: populated.length };
	}
	const first = days[ populated[ 0 ] ];
	const start = toMin( first.start );
	let duration;
	if ( start !== null && start >= 17 * 60 ) duration = 'evening';
	else if ( totalHours < 3 ) duration = 'hourly';
	else if ( totalHours < 6 ) duration = 'half-day';
	else duration = 'full-day';
	return { duration, hours: Math.round( totalHours * 10 ) / 10, multiDayCount: 1 };
}

// --- Formatters ---
function fmtBand( low, high ) {
	if ( Math.round( low ) === Math.round( high ) ) return fmt$( low );
	return `${ fmt$( low ) } – ${ fmt$( high ) }`;
}

// Per-head / per-unit rate — keeps fractional dollars where the midpoint
// bands aren't whole (light lunch $22.50, afternoon tea $11.50).
function fmtRate( n ) {
	return new Intl.NumberFormat( 'en-NZ', {
		style: 'currency',
		currency: 'NZD',
		maximumFractionDigits: 2,
	} ).format( n );
}

// --- Compute — mirror the source engine, reading rates from the SSOT ---
function compute( state, ssot ) {
	const size = state.size;
	const { duration, hours, multiDayCount } = deriveDuration( state.days );

	// Zero-start: no group size or no populated day → nothing rendered.
	if ( ! size || size <= 0 || ! duration ) {
		return {
			empty: true, duration, size,
			industry: { low: 0, high: 0, lines: [] },
			ours: { total: 0, lines: [], spaceKey: null },
			saving: { low: 0, high: 0 },
			spaceName: '',
		};
	}

	const band = sizeBand( size );
	const durFactor = durationFactor( duration, multiDayCount );

	// --- INDUSTRY-STANDARD LINES (low + high band) ---
	const lines = [];
	let indLow = 0;
	let indHigh = 0;

	const roomBandBase = IND.ROOM[ roomDurationKey( duration ) ][ band ];
	const roomMultiplier = duration === 'multi-day' ? multiDayCount : 1;
	const roomBandLow = roomBandBase[ 0 ] * roomMultiplier;
	const roomBandHigh = roomBandBase[ 1 ] * roomMultiplier;
	const roomNoteSuffix = duration === 'multi-day' ? ' · ' + multiDayCount + ' days' : '';
	lines.push( {
		key: 'room', label: 'Room hire',
		note: 'Wellington venue · ' + duration + roomNoteSuffix + ' · ' + size + ( size === 1 ? ' person' : ' people' ),
		low: roomBandLow, high: roomBandHigh, src: 'room',
	} );
	indLow += roomBandLow;
	indHigh += roomBandHigh;

	let catLow = 0;
	let catHigh = 0;
	const catNotes = [];
	const cat = state.cater;
	if ( cat.tea )          { catLow += IND.TEA[ 0 ] * size; catHigh += IND.TEA[ 1 ] * size; catNotes.push( 'tea+coffee' ); }
	if ( cat.breakfast )    { catLow += IND.BREAKFAST[ 0 ] * size; catHigh += IND.BREAKFAST[ 1 ] * size; catNotes.push( 'breakfast' ); }
	if ( cat.lunchLight )   { catLow += IND.LUNCH_LIGHT[ 0 ] * size; catHigh += IND.LUNCH_LIGHT[ 1 ] * size; catNotes.push( 'light lunch' ); }
	if ( cat.lunchHearty )  { catLow += IND.LUNCH_HEARTY[ 0 ] * size; catHigh += IND.LUNCH_HEARTY[ 1 ] * size; catNotes.push( 'hearty lunch' ); }
	if ( cat.afternoon )    { catLow += IND.AFTERNOON[ 0 ] * size; catHigh += IND.AFTERNOON[ 1 ] * size; catNotes.push( 'afternoon tea' ); }
	if ( cat.drinks )       { catLow += IND.DRINKS[ 0 ] * size; catHigh += IND.DRINKS[ 1 ] * size; catNotes.push( 'drinks' ); }
	if ( duration === 'multi-day' ) { catLow *= multiDayCount; catHigh *= multiDayCount; }
	if ( catLow + catHigh > 0 ) {
		lines.push( {
			key: 'catering', label: 'Catering',
			note: catNotes.join( ' · ' ) + ' × ' + size,
			low: catLow, high: catHigh, src: 'catering',
		} );
		indLow += catLow;
		indHigh += catHigh;
	}

	let avLow = 0;
	let avHigh = 0;
	const avNotes = [];
	const av = state.av;
	if ( av.projector ) { avLow += IND.AV_PROJECTOR[ 0 ] * durFactor; avHigh += IND.AV_PROJECTOR[ 1 ] * durFactor; avNotes.push( 'projector' ); }
	if ( av.sound )     { avLow += IND.AV_SOUND[ 0 ] * durFactor; avHigh += IND.AV_SOUND[ 1 ] * durFactor; avNotes.push( 'sound' ); }
	if ( avLow + avHigh > 0 ) {
		lines.push( { key: 'av', label: 'AV', note: avNotes.join( ' + ' ), low: avLow, high: avHigh, src: 'av' } );
		indLow += avLow;
		indHigh += avHigh;
	}

	const fac = state.fac;
	if ( fac === 'half' )   { lines.push( { key: 'facilitation', label: 'Facilitation', note: 'External facilitator · half-day', low: IND.FAC_HALF[ 0 ], high: IND.FAC_HALF[ 1 ], src: 'facilitation' } ); indLow += IND.FAC_HALF[ 0 ]; indHigh += IND.FAC_HALF[ 1 ]; }
	if ( fac === 'full' )   { lines.push( { key: 'facilitation', label: 'Facilitation', note: 'External facilitator · full-day', low: IND.FAC_FULL[ 0 ], high: IND.FAC_FULL[ 1 ], src: 'facilitation' } ); indLow += IND.FAC_FULL[ 0 ]; indHigh += IND.FAC_FULL[ 1 ]; }
	if ( fac === 'senior' ) { lines.push( { key: 'facilitation', label: 'Facilitation', note: 'Senior / multi-day facilitator', low: IND.FAC_SENIOR[ 0 ], high: IND.FAC_SENIOR[ 1 ], src: 'facilitation' } ); indLow += IND.FAC_SENIOR[ 0 ]; indHigh += IND.FAC_SENIOR[ 1 ]; }

	let matLow = 0;
	let matHigh = 0;
	const matNotes = [];
	const mat = state.mat;
	if ( mat.boards )   { matLow += IND.MAT_WHITEBOARDS[ 0 ]; matHigh += IND.MAT_WHITEBOARDS[ 1 ]; matNotes.push( 'whiteboards' ); }
	if ( mat.postits )  { matLow += IND.MAT_POSTITS[ 0 ];     matHigh += IND.MAT_POSTITS[ 1 ];     matNotes.push( 'post-its+pens' ); }
	if ( mat.printing ) { matLow += IND.MAT_PRINTING[ 0 ];    matHigh += IND.MAT_PRINTING[ 1 ];    matNotes.push( 'printing' ); }
	if ( matLow + matHigh > 0 ) {
		lines.push( { key: 'materials', label: 'Materials', note: matNotes.join( ' · ' ), low: matLow, high: matHigh } );
		indLow += matLow;
		indHigh += matHigh;
	}

	const setup = state.setup;
	if ( setup === 'standard' ) { lines.push( { key: 'setup', label: 'Setup + pack-down', note: 'Standard room reset', low: IND.SETUP_STD[ 0 ], high: IND.SETUP_STD[ 1 ] } ); indLow += IND.SETUP_STD[ 0 ]; indHigh += IND.SETUP_STD[ 1 ]; }
	if ( setup === 'complex' )  { lines.push( { key: 'setup', label: 'Setup + pack-down', note: 'Complex reset / multi-room', low: IND.SETUP_COMPLEX[ 0 ], high: IND.SETUP_COMPLEX[ 1 ] } ); indLow += IND.SETUP_COMPLEX[ 0 ]; indHigh += IND.SETUP_COMPLEX[ 1 ]; }

	state.custom.forEach( ( cl ) => {
		if ( cl && cl.label && cl.value > 0 ) {
			lines.push( { key: 'custom', label: cl.label, note: 'Custom line you added', low: cl.value, high: cl.value } );
			indLow += cl.value;
			indHigh += cl.value;
		}
	} );

	// --- TWO/FIFTYSEVEN LINES ---
	const ourLines = [];
	const spaceKey = pickSpace( size, duration );
	const space = ssot.rooms[ spaceKey ] || {};
	const spaceName = space.name || spaceKey;
	let roomRate = 0;
	let roomNote = '';
	if ( duration === 'hourly' ) {
		roomRate = ( space.hour || space.day || 0 ) * 3;
		roomNote = spaceName + ' · $' + ( space.hour || 0 ) + '/hr × 3 hr';
	} else if ( duration === 'evening' && space.evening ) {
		roomRate = space.evening;
		roomNote = spaceName + ' · evening rate';
	} else if ( duration === 'multi-day' ) {
		roomRate = ( space.day || 0 ) * multiDayCount;
		roomNote = spaceName + ' · day rate × ' + multiDayCount;
	} else if ( duration === 'half-day' ) {
		roomRate = Math.round( ( space.day || 0 ) * 0.6 );
		roomNote = spaceName + ' · half-day (60% of day)';
	} else {
		roomRate = space.day || 0;
		roomNote = spaceName + ' · day rate';
	}
	ourLines.push( { key: 'room', label: 'Room', note: roomNote, value: roomRate } );
	let oursTotal = roomRate;

	if ( cat.tea ) {
		const teaRate = ( ssot.addons.tea && ssot.addons.tea.singlePerHead ) || 5;
		const teaCost = teaRate * size;
		ourLines.push( { key: 'tea', label: 'Tea + coffee', note: fmt$( teaRate ) + '/head · continuous', value: teaCost } );
		oursTotal += teaCost;
	}

	// Catering — free when the customer arranges it directly, charged at cost
	// when 2/57 arranges. Engine uses the industry midpoint for the comparison
	// figure (NOT the ACF catering_organising_fee — that's the meet-pricing
	// quote model).
	const catMid = {
		BREAKFAST:   ( IND.BREAKFAST[ 0 ] + IND.BREAKFAST[ 1 ] ) / 2,
		LUNCH_LIGHT: ( IND.LUNCH_LIGHT[ 0 ] + IND.LUNCH_LIGHT[ 1 ] ) / 2,
		LUNCH_HEARTY:( IND.LUNCH_HEARTY[ 0 ] + IND.LUNCH_HEARTY[ 1 ] ) / 2,
		AFTERNOON:   ( IND.AFTERNOON[ 0 ] + IND.AFTERNOON[ 1 ] ) / 2,
		DRINKS:      ( IND.DRINKS[ 0 ] + IND.DRINKS[ 1 ] ) / 2,
	};
	let oursCatering = 0;
	const catBits = [];
	const addCat = ( perHead, label ) => {
		const v = Math.round( perHead * size );
		oursCatering += v;
		catBits.push( label + ' ' + fmtRate( perHead ) + '/head' );
	};
	if ( cat.breakfast )   addCat( catMid.BREAKFAST, 'breakfast' );
	if ( cat.lunchLight )  addCat( catMid.LUNCH_LIGHT, 'light lunch' );
	if ( cat.lunchHearty ) addCat( catMid.LUNCH_HEARTY, 'hearty lunch' );
	if ( cat.afternoon )   addCat( catMid.AFTERNOON, 'afternoon tea' );
	if ( cat.drinks )      addCat( catMid.DRINKS, 'drinks' );
	if ( duration === 'multi-day' ) oursCatering *= multiDayCount;
	if ( oursCatering > 0 ) {
		ourLines.push( {
			key: 'catering', label: 'Catering',
			note: catBits.join( ' + ' ) + ' × ' + size
				+ ( duration === 'multi-day' ? ' · ' + multiDayCount + ' days' : '' )
				+ ' — free when you arrange it directly, charged at cost when we arrange it.',
			value: oursCatering,
		} );
		oursTotal += oursCatering;
	}

	// AV add-ons at the ACF flat maintenance-replacement rate (per booking).
	const avRate = ( item ) => {
		const flat = ( item && item.flat ) || 50;
		if ( duration === 'hourly' ) return flat * 3; // typical 3hr hourly booking
		if ( duration === 'multi-day' ) return flat * multiDayCount;
		return flat;
	};
	const avProj = ( ssot.addons.av && ssot.addons.av.projector ) || null;
	const avSound = ( ssot.addons.av && ssot.addons.av.sound ) || null;
	if ( av.projector && avProj ) { const v = avRate( avProj ); ourLines.push( { key: 'av-proj', label: 'Projector + screen', note: 'Maintenance-replacement rate · separate add-on', value: v } ); oursTotal += v; }
	if ( av.sound && avSound )     { const v = avRate( avSound ); ourLines.push( { key: 'av-sound', label: 'Sound system + mic', note: 'Maintenance-replacement rate · separate add-on', value: v } ); oursTotal += v; }

	// Facilitation + materials + setup — pass through at industry mid / ACF charge.
	const midOf = ( b ) => Math.round( ( b[ 0 ] + b[ 1 ] ) / 2 );
	if ( fac === 'half' )   { const v = midOf( IND.FAC_HALF ); ourLines.push( { key: 'fac', label: 'Facilitation', note: 'Bring your own facilitator (industry mid shown)', value: v } ); oursTotal += v; }
	if ( fac === 'full' )   { const v = midOf( IND.FAC_FULL ); ourLines.push( { key: 'fac', label: 'Facilitation', note: 'Bring your own facilitator (industry mid shown)', value: v } ); oursTotal += v; }
	if ( fac === 'senior' ) { const v = midOf( IND.FAC_SENIOR ); ourLines.push( { key: 'fac', label: 'Facilitation', note: 'Bring your own facilitator (industry mid shown)', value: v } ); oursTotal += v; }

	const materials = ssot.addons.materials || {};
	if ( mat.boards || mat.postits || mat.printing ) {
		let v = 0;
		const bits = [];
		if ( mat.boards )   { bits.push( 'whiteboards + flipcharts (included)' ); }
		if ( mat.postits )  { v += materials.postits || 30; bits.push( 'post-its + pens' ); }
		if ( mat.printing ) { v += materials.printing || 60; bits.push( 'printing' ); }
		ourLines.push( { key: 'materials', label: 'Materials', note: bits.join( ' · ' ), value: v } );
		oursTotal += v;
	}

	if ( setup === 'complex' ) {
		const complex = ( ssot.addons.setup && ssot.addons.setup.complex ) || 200;
		ourLines.push( { key: 'setup', label: 'Complex setup', note: 'Multi-room or non-standard reset', value: complex } );
		oursTotal += complex;
	}

	state.custom.forEach( ( cl ) => {
		if ( cl && cl.label && cl.value > 0 ) {
			ourLines.push( { key: 'custom', label: cl.label, note: 'Custom line you added', value: cl.value } );
			oursTotal += cl.value;
		}
	} );

	// Impact Discount — % off the 2/57 total only (never the industry band).
	let impactAmt = 0;
	if ( state.impact ) {
		const discountPct = ssot.impact.discountPct || 0.5;
		impactAmt = Math.round( oursTotal * discountPct );
		ourLines.push( {
			key: 'impact', label: 'Impact Discount',
			note: ( discountPct * 100 ).toFixed( 0 ) + '% off the two/fiftyseven figure',
			value: -impactAmt,
		} );
		oursTotal -= impactAmt;
	}

	const savingLow = Math.max( 0, Math.round( indLow - oursTotal ) );
	const savingHigh = Math.max( 0, Math.round( indHigh - oursTotal ) );

	return {
		empty: false, duration, hours, multiDayCount, size,
		spaceKey, spaceName,
		industry: { low: Math.round( indLow ), high: Math.round( indHigh ), lines },
		ours: { total: Math.round( oursTotal ), lines: ourLines },
		saving: { low: savingLow, high: savingHigh },
	};
}

// --- State <-> URL ---
function readURL() {
	const params = new URLSearchParams( window.location.search );
	const state = {
		size: 0,
		days: [ { date: todayISO(), start: M.DEFAULT_START, end: M.DEFAULT_END } ],
		cater: { tea: false, breakfast: false, lunchLight: false, lunchHearty: false, afternoon: false, drinks: false },
		av: { projector: false, sound: false },
		mat: { boards: false, postits: false, printing: false },
		fac: 'none',
		setup: 'standard',
		// One blank row by default so the list isn't empty on first load;
		// additional lines are added with the "+ Add" trigger.
		custom: [ { label: '', value: 0 } ],
		impact: false,
	};

	const size = parseInt( params.get( 'size' ), 10 );
	if ( ! isNaN( size ) ) state.size = Math.max( 0, Math.min( M.MAX_SIZE, size ) );

	const daysRaw = params.get( 'days' );
	if ( daysRaw && daysRaw.trim() ) {
		const parsed = daysRaw.split( ',' ).map( ( s ) => {
			const m = s.trim().match( /^(\d{4}-\d{2}-\d{2})\|(\d{1,2}:\d{2})-(\d{1,2}:\d{2})$/ );
			if ( ! m ) return null;
			return { date: m[ 1 ], start: m[ 2 ], end: m[ 3 ] };
		} ).filter( Boolean );
		if ( parsed.length ) state.days = parsed;
	}

	const extras = params.get( 'extras' );
	if ( extras && extras.trim() ) {
		for ( const token of extras.split( ',' ) ) {
			const t = token.trim();
			if ( ! t ) continue;
			if ( t === 'tea' )         state.cater.tea = true;
			else if ( t === 'breakfast' ) state.cater.breakfast = true;
			else if ( t === 'lunch-light' ) state.cater.lunchLight = true;
			else if ( t === 'lunch-hearty' ) state.cater.lunchHearty = true;
			else if ( t === 'afternoon' ) state.cater.afternoon = true;
			else if ( t === 'drinks' ) state.cater.drinks = true;
			else if ( t === 'projector' ) state.av.projector = true;
			else if ( t === 'sound' ) state.av.sound = true;
			else if ( t === 'boards' ) state.mat.boards = true;
			else if ( t === 'postits' ) state.mat.postits = true;
			else if ( t === 'printing' ) state.mat.printing = true;
			else if ( t === 'impact' || t === 'impact-discount' ) state.impact = true;
		}
	}

	const fac = params.get( 'fac' );
	if ( fac === 'half' || fac === 'full' || fac === 'senior' ) state.fac = fac;

	const setup = params.get( 'setup' );
	if ( setup === 'standard' || setup === 'complex' ) state.setup = setup;

	const customRaw = params.get( 'custom' );
	if ( customRaw && customRaw.trim() ) {
		state.custom = customRaw.split( ',' ).map( ( pair ) => {
			const [ label, value ] = pair.split( '|' );
			const v = parseFloat( value );
			if ( ! label || isNaN( v ) || v <= 0 ) return null;
			return { label: decodeURIComponent( label ).trim(), value: v };
		} ).filter( Boolean );
	}

	return state;
}

function writeURL( state ) {
	const params = new URLSearchParams( window.location.search );
	params.set( 'size', String( state.size ) );

	if ( state.days.length ) {
		// Fall back on today's date + the default window if a picker was
		// cleared, so the encoded link always round-trips through readURL.
		params.set( 'days', state.days.map( ( d ) => {
			const date = /^\d{4}-\d{2}-\d{2}$/.test( d.date || '' ) ? d.date : todayISO();
			const start = d.start || M.DEFAULT_START;
			const end = d.end || M.DEFAULT_END;
			return `${ date }|${ start }-${ end }`;
		} ).join( ',' ) );
	} else {
		params.delete( 'days' );
	}

	const tokens = [];
	const cat = state.cater;
	if ( cat.tea ) tokens.push( 'tea' );
	if ( cat.breakfast ) tokens.push( 'breakfast' );
	if ( cat.lunchLight ) tokens.push( 'lunch-light' );
	if ( cat.lunchHearty ) tokens.push( 'lunch-hearty' );
	if ( cat.afternoon ) tokens.push( 'afternoon' );
	if ( cat.drinks ) tokens.push( 'drinks' );
	if ( state.av.projector ) tokens.push( 'projector' );
	if ( state.av.sound ) tokens.push( 'sound' );
	if ( state.mat.boards ) tokens.push( 'boards' );
	if ( state.mat.postits ) tokens.push( 'postits' );
	if ( state.mat.printing ) tokens.push( 'printing' );
	if ( state.impact ) tokens.push( 'impact-discount' );
	if ( tokens.length ) params.set( 'extras', tokens.join( ',' ) );
	else params.delete( 'extras' );

	if ( state.fac && state.fac !== 'none' ) params.set( 'fac', state.fac );
	else params.delete( 'fac' );
	if ( state.setup && state.setup !== 'standard' ) params.set( 'setup', state.setup );
	else params.delete( 'setup' );

	const custom = state.custom.filter( ( c ) => c.label && c.value > 0 );
	if ( custom.length ) {
		params.set( 'custom', custom.map( ( c ) => encodeURIComponent( c.label ) + '|' + c.value ).join( ',' ) );
	} else {
		params.delete( 'custom' );
	}

	const newURL = `${ window.location.pathname }?${ params.toString() }${ window.location.hash }`;
	window.history.replaceState( {}, '', newURL );
}

// --- Breakdown row renderers ---
function sourceTooltip( slug ) {
	const src = SOURCES[ slug ];
	if ( ! src ) return '';
	return `
		<span class="calc-source" tabindex="0">
			<button class="calc-source__trigger" type="button" aria-label="${ src.label }">i</button>
			<span class="calc-source__pop" role="tooltip">
				<span class="calc-source__pop-label">${ src.label }</span>
				<a href="${ src.url }" target="_blank" rel="noopener">${ src.name } ↗</a>
			</span>
		</span>`;
}

function compareRow( label, note, valueHtml, sourceSlug ) {
	return `
		<div class="calc__compare-row">
			<div class="calc__compare-row-label">
				<span>${ label }${ note ? `<span class="calc-source__note"> — ${ note }</span>` : '' }</span>
				${ sourceTooltip( sourceSlug ) }
			</div>
			<div class="calc__compare-row-value">${ valueHtml }</div>
		</div>`;
}

// --- Render ---
function renderResults( scope, root, state, computed ) {
	// Result card — headline figures + comparison bars (workspace-pricing
	// layout). Bars scale against the top of the industry band.
	const empty = computed.empty;
	// Anchor both the venue band and ours to a soft ceiling above whichever
	// is larger, so every bar stops short with visible headroom behind it —
	// the chart reads like the workspace one, whose anchor is the pricier
	// private office. Low keeps its stronger tint under the lighter high.
	const ceiling = Math.max( 1, computed.industry.high, computed.ours.total ) * 1.25;
	const clampPct = ( v ) => Math.max( 0, Math.min( 100, v ) );
	const pctVenLow = clampPct( ( computed.industry.low / ceiling ) * 100 );
	const pctVenHigh = clampPct( ( computed.industry.high / ceiling ) * 100 );
	const pctOurs = clampPct( ( computed.ours.total / ceiling ) * 100 );

	root.querySelectorAll( '[data-result-headline], [data-result-compare]' ).forEach( ( el ) => {
		el.hidden = empty;
	} );
	root.querySelectorAll( '[data-calc-result-ours]' ).forEach( ( el ) => {
		el.textContent = fmt$( computed.ours.total );
	} );
	root.querySelectorAll( '[data-calc-result-space]' ).forEach( ( el ) => {
		el.textContent = computed.spaceName ? computed.spaceName + ' · all-in' : '';
	} );

	root.querySelectorAll( '[data-calc-chart-venue-low]' ).forEach( ( el ) => {
		el.textContent = empty ? '$0' : fmt$( computed.industry.low );
	} );
	root.querySelectorAll( '[data-calc-chart-venue-high]' ).forEach( ( el ) => {
		el.textContent = empty ? '$0' : fmt$( computed.industry.high );
	} );
	root.querySelectorAll( '[data-calc-chart-ours]' ).forEach( ( el ) => {
		el.textContent = empty ? '$0' : fmt$( computed.ours.total );
	} );
	root.querySelectorAll( '[data-calc-chart-save-low]' ).forEach( ( el ) => {
		el.textContent = empty ? '$0' : fmt$( Math.max( 0, computed.saving.low ) );
	} );
	root.querySelectorAll( '[data-calc-chart-save-high]' ).forEach( ( el ) => {
		el.textContent = empty ? '$0' : fmt$( Math.max( 0, computed.saving.high ) );
	} );

	root.querySelectorAll( '.calc__chart-bar--low' ).forEach( ( bar ) => {
		bar.style.setProperty( '--bar-pct', ( empty ? 0 : pctVenLow ) + '%' );
	} );
	root.querySelectorAll( '.calc__chart-bar--high' ).forEach( ( bar ) => {
		bar.style.setProperty( '--bar-pct', ( empty ? 0 : pctVenHigh ) + '%' );
	} );
	root.querySelectorAll( '.calc__chart-bar--ours' ).forEach( ( bar ) => {
		bar.style.setProperty( '--bar-pct', ( empty ? 0 : pctOurs ) + '%' );
	} );

	// Duration label under the day rows
	const durLabel = root.querySelector( '[data-calc-duration-label]' );
	if ( durLabel ) {
		if ( computed.empty || ! computed.duration ) {
			durLabel.textContent = '';
		} else {
			const map = {
				'full-day': 'full-day rate',
				'half-day': 'half-day rate',
				'evening':  'evening rate',
				'multi-day':'multi-day rate (' + computed.multiDayCount + ' days)',
				'hourly':   'hourly rate',
			};
			durLabel.textContent = computed.duration === 'multi-day'
				? map[ computed.duration ]
				: computed.hours + ' hour' + ( computed.hours === 1 ? '' : 's' ) + ' · ' + map[ computed.duration ];
		}
	}

	// Empty-state prompt + breakdown clearing
	root.querySelectorAll( '[data-calc-result-prompt]' ).forEach( ( el ) => {
		el.hidden = ! computed.empty;
	} );

	// Breakdown — industry lines. The breakdown <details> sits outside the
	// [data-js] root (.calc__body), so query it from scope (the wrapper) —
	// the same pattern the office-carbon calc and the share row use.
	const indBody = scope.querySelector( '[data-calc-industry-lines]' );
	if ( indBody ) {
		indBody.innerHTML = computed.industry.lines.map( ( l ) => compareRow(
			l.label,
			l.note,
			fmtBand( l.low, l.high ),
			l.src
		) ).join( '' );
	}
	scope.querySelectorAll( '[data-calc-industry-total]' ).forEach( ( el ) => {
		el.textContent = computed.empty ? '$0' : fmtBand( computed.industry.low, computed.industry.high );
	} );

	// Breakdown — two/fiftyseven lines
	const oursBody = scope.querySelector( '[data-calc-ours-lines]' );
	if ( oursBody ) {
		oursBody.innerHTML = computed.ours.lines.map( ( l ) => compareRow(
			l.label,
			l.note,
			( l.value < 0 ? '−' : '' ) + fmt$( Math.abs( l.value ) ),
			null
		) ).join( '' );
	}
	scope.querySelectorAll( '[data-calc-ours-total]' ).forEach( ( el ) => {
		el.textContent = fmt$( computed.ours.total );
	} );
}

// --- Days list (JS-rendered repeating time rows) ---
function renderDaysList( root, state, onRerender ) {
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

		// Native date + time pickers — the exact same inputs + shared stamps
		// as the meet-pricing calculator.
		const dateInput = document.createElement( 'input' );
		dateInput.type = 'date';
		dateInput.value = day.date;
		dateInput.setAttribute( 'aria-label', `Day ${ i + 1 } date` );
		dateInput.addEventListener( 'input', () => {
			// A cleared picker reports "" — keep the last good date so the
			// share link never encodes an empty segment.
			if ( dateInput.value ) day.date = dateInput.value;
			onRerender();
		} );

		const startInput = makeTimeField( i, 'start', day, onRerender );
		const endInput = makeTimeField( i, 'end', day, onRerender );

		const arrow = document.createElement( 'span' );
		arrow.textContent = '→';
		arrow.setAttribute( 'aria-hidden', 'true' );

		inputs.appendChild( dateInput );
		inputs.appendChild( startInput );
		inputs.appendChild( arrow );
		inputs.appendChild( endInput );

		const hoursEl = document.createElement( 'span' );
		hoursEl.className = 'calc__day-row-hours';
		hoursEl.setAttribute( 'data-calc-day-index', String( i ) );
		hoursEl.textContent = fmtHrs( hoursForDay( day ) );
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
			renderDaysList( root, state, onRerender );
			onRerender();
		} );

		row.appendChild( label );
		row.appendChild( inputs );
		row.appendChild( remove );
		list.appendChild( row );
	} );
}

function fmtHrs( n ) {
	const v = Math.round( n * 2 ) / 2;
	return ( String( v ).replace( /\.0$/, '' ) ) + 'h';
}

// Native <input type="time">, values kept as 24h "HH:MM". Commits the
// change to the day's start/end and re-renders so the hours readout and
// duration label follow the pickers.
function makeTimeField( dayIdx, which, day, onRerender ) {
	const input = document.createElement( 'input' );
	input.type = 'time';
	input.value = day[ which ];
	input.setAttribute( 'data-calc-day-' + which, '' );
	input.setAttribute( 'aria-label', 'Day ' + ( dayIdx + 1 ) + ' ' + which + ' time' );
	input.addEventListener( 'input', () => {
		if ( day[ which ] === input.value ) return;
		day[ which ] = input.value;
		onRerender();
	} );
	return input;
}

// --- Custom expense rows (JS-rendered) ---
function renderCustomList( root, state, onRerender ) {
	const list = root.querySelector( '[data-calc-custom-list]' );
	if ( ! list ) return;
	list.innerHTML = '';

	state.custom.forEach( ( custom, i ) => {
		const row = document.createElement( 'li' );
		row.className = 'meeting-costs__custom-row';

		const labelInput = document.createElement( 'input' );
		labelInput.type = 'text';
		labelInput.className = 'calc__input meeting-costs__custom-label';
		labelInput.setAttribute( 'data-calc-custom-label', '' );
		labelInput.placeholder = 'e.g. Photographer';
		labelInput.setAttribute( 'aria-label', 'Custom expense name' );
		labelInput.value = custom.label;
		labelInput.addEventListener( 'input', () => {
			custom.label = labelInput.value.trim();
			onRerender();
		} );

		const valueInput = document.createElement( 'input' );
		valueInput.type = 'number';
		valueInput.className = 'calc__input meeting-costs__custom-value';
		valueInput.setAttribute( 'data-calc-custom-value', '' );
		valueInput.placeholder = '$ value';
		valueInput.min = '0';
		valueInput.step = '50';
		valueInput.setAttribute( 'aria-label', 'Custom expense value in dollars' );
		valueInput.value = custom.value ? String( custom.value ) : '';
		valueInput.addEventListener( 'change', () => {
			const v = parseFloat( valueInput.value.replace( /,/g, '' ) );
			custom.value = isNaN( v ) ? 0 : Math.max( 0, v );
			if ( valueInput.value !== String( custom.value ) ) valueInput.value = String( custom.value );
			onRerender();
		} );

		const remove = document.createElement( 'button' );
		remove.type = 'button';
		remove.className = 'calc__day-row-remove';
		remove.setAttribute( 'aria-label', 'Remove this line' );
		remove.textContent = '×';
		remove.disabled = state.custom.length <= 1;
		remove.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			state.custom.splice( i, 1 );
			renderCustomList( root, state, onRerender );
			onRerender();
		} );

		row.appendChild( labelInput );
		row.appendChild( valueInput );
		row.appendChild( remove );
		list.appendChild( row );
	} );
}

// --- Option price hints (2/57 side, shown before selection) ---
// Each priced option gets a monospace "· $X…" measure appended to its label
// so visitors see the cost before ticking it. Sources mirror compute():
// catering passes through the industry midpoint per head, AV at the ACF flat
// add-on, materials/setup at the ACF charge, facilitation at the midpoint.
function midOf( b ) {
	return Math.round( ( b[ 0 ] + b[ 1 ] ) / 2 );
}

function priceHintForKey( ssot, key ) {
	const addons = ssot.addons || {};
	switch ( key ) {
		case 'tea': {
			const t = ( addons.tea && addons.tea.singlePerHead ) || 5;
			return fmt$( t ) + '/head';
		}
		case 'breakfast':   return fmt$( midOf( IND.BREAKFAST ) ) + '/head';
		case 'lunch-light': return fmt$( midOf( IND.LUNCH_LIGHT ) ) + '/head';
		case 'lunch-hearty':return fmt$( midOf( IND.LUNCH_HEARTY ) ) + '/head';
		case 'afternoon':   return fmt$( midOf( IND.AFTERNOON ) ) + '/head';
		case 'drinks':      return fmt$( midOf( IND.DRINKS ) ) + '/head';
		case 'projector': {
			const f = ( addons.av && addons.av.projector && addons.av.projector.flat ) || 50;
			return fmt$( f ) + ' add-on';
		}
		case 'sound': {
			const f = ( addons.av && addons.av.sound && addons.av.sound.flat ) || 50;
			return fmt$( f ) + ' add-on';
		}
		case 'postits': {
			const v = ( addons.materials && addons.materials.postits ) || 30;
			return fmt$( v );
		}
		case 'printing': {
			const v = ( addons.materials && addons.materials.printing ) || 60;
			return fmt$( v );
		}
		default: return null;
	}
}

function paintPriceHints( root, ssot ) {
	// Checkbox cards — price sits on its own line under the card title,
	// mirroring the meet-pricing add-on tiles.
	root.querySelectorAll( '[data-calc-check]' ).forEach( ( input ) => {
		const hint = priceHintForKey( ssot, input.getAttribute( 'data-calc-check' ) );
		if ( ! hint ) return;
		const head = input.closest( '.calc__option-head' );
		if ( ! head ) return;
		head.appendChild( Object.assign( document.createElement( 'span' ), {
			className: 'meeting-costs__price-hint',
			textContent: hint,
		} ) );
	} );

	// Facilitation + setup radio cards carry flat pass-through charges; the
	// price lands on its own line under the card title.
	const facHints = {
		'half':   fmt$( midOf( IND.FAC_HALF ) ),
		'full':   fmt$( midOf( IND.FAC_FULL ) ),
		'senior': fmt$( midOf( IND.FAC_SENIOR ) ),
	};
	root.querySelectorAll( '[data-calc-fac]' ).forEach( ( btn ) => {
		const hint = facHints[ btn.getAttribute( 'data-calc-fac' ) ];
		if ( hint ) paintHintAfterTitle( btn, hint );
	} );

	root.querySelectorAll( '[data-calc-setup]' ).forEach( ( btn ) => {
		if ( btn.getAttribute( 'data-calc-setup' ) !== 'complex' ) return;
		const complex = ( ssot.addons && ssot.addons.setup && ssot.addons.setup.complex ) || 200;
		paintHintAfterTitle( btn, fmt$( complex ) + ' add-on' );
	} );
}

function paintHintAfterTitle( el, text ) {
	const title = el.querySelector( '.meeting-costs__card-title' );
	const span = Object.assign( document.createElement( 'span' ), {
		className: 'meeting-costs__price-hint',
		textContent: text,
	} );
	if ( title ) el.insertBefore( span, title.nextSibling );
	else el.appendChild( span );
}

// --- Event binding ---
function bindEvents( scope, root, state ) {
	function rerender() {
		renderResults( scope, root, state, compute( state, getSSOT() ) );
		writeURL( state );
		// Day-row hour readouts live in the DOM (renderDaysList) — a time edit
		// doesn't rerender the row, so sync them here after every render.
		state.days.forEach( ( day, i ) => {
			root.querySelectorAll( `[data-calc-day-index="${ i }"]` ).forEach( ( el ) => {
				el.textContent = fmtHrs( hoursForDay( day ) );
			} );
		} );
	}

	// Group size stepper (value-based, 0–200) — shared wiring.
	const stepper = bindStepper( root, {
		rangeSel: '[data-calc-size-range]',
		sliderSel: '[data-calc-size-slider]',
		outSel: '[data-calc-size-out]',
		decSel: '[data-calc-size-dec]',
		incSel: '[data-calc-size-inc]',
		max: M.MAX_SIZE,
		valueFor: ( i ) => i,
		current: () => state.size,
		onUpdate: ( n ) => {
			state.size = n;
			rerender();
		},
	} );
	stepper.paintCurrent();

	// ── Checkboxes (catering / AV / materials) — native change bubbles. ──
	const CHECK_MAP = {
		'tea':         ( v ) => state.cater.tea = v,
		'breakfast':   ( v ) => state.cater.breakfast = v,
		'lunch-light': ( v ) => state.cater.lunchLight = v,
		'lunch-hearty':( v ) => state.cater.lunchHearty = v,
		'afternoon':   ( v ) => state.cater.afternoon = v,
		'drinks':      ( v ) => state.cater.drinks = v,
		'projector':   ( v ) => state.av.projector = v,
		'sound':       ( v ) => state.av.sound = v,
		'boards':      ( v ) => state.mat.boards = v,
		'postits':     ( v ) => state.mat.postits = v,
		'printing':    ( v ) => state.mat.printing = v,
	};
	const CHECK_READ = {
		'tea':         () => state.cater.tea,
		'breakfast':   () => state.cater.breakfast,
		'lunch-light': () => state.cater.lunchLight,
		'lunch-hearty':() => state.cater.lunchHearty,
		'afternoon':   () => state.cater.afternoon,
		'drinks':      () => state.cater.drinks,
		'projector':   () => state.av.projector,
		'sound':       () => state.av.sound,
		'boards':      () => state.mat.boards,
		'postits':     () => state.mat.postits,
		'printing':    () => state.mat.printing,
	};
	root.querySelectorAll( '[data-calc-check]' ).forEach( ( input ) => {
		const key = input.getAttribute( 'data-calc-check' );
		const setter = CHECK_MAP[ key ];
		const getter = CHECK_READ[ key ];
		if ( ! setter ) return;
		if ( getter ) input.checked = ! ! getter();
		input.addEventListener( 'change', () => {
			setter( input.checked );
			rerender();
		} );

		// Whole card is the click target: toggling the swatch or title goes
		// through the <label>, but the body text sits outside it — catch any
		// click on the card that isn't already on an interactive element.
		const card = input.closest( '.meeting-costs__card' );
		if ( ! card ) return;
		card.addEventListener( 'click', ( e ) => {
			if ( e.target.closest( 'input, label, a' ) ) return;
			e.preventDefault();
			input.checked = ! input.checked;
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
	} );

	// ── Facilitation + setup radios (WAI-ARIA roving radio) ──
	function bindRadioGroup( groupSel, attr, current ) {
		const radios = Array.from( root.querySelectorAll( `${ groupSel } [data-calc-${ attr }]` ) );

		function setChecked( radio ) {
			radios.forEach( ( r ) => r.setAttribute( 'aria-checked', 'false' ) );
			if ( radio ) radio.setAttribute( 'aria-checked', 'true' );
		}

		function select( radio ) {
			if ( ! radio ) return;
			setChecked( radio );
			state[ attr ] = radio.getAttribute( 'data-calc-' + attr );
			rerender();
		}

		radios.forEach( ( radio ) => {
			radio.setAttribute( 'aria-checked', radio.getAttribute( 'data-calc-' + attr ) === current ? 'true' : 'false' );
			radio.addEventListener( 'click', () => select( radio ) );
		} );
		bindRovingRadio( radios, select );
	}
	bindRadioGroup( '[data-calc-fac-group]', 'fac', state.fac );
	bindRadioGroup( '[data-calc-setup-group]', 'setup', state.setup );

	// ── Impact discount option card ──
	const impactCard = root.querySelector( '[data-calc-addon="impact"]' );
	const impactCheckbox = impactCard ? impactCard.querySelector( '[data-calc-addon-checkbox]' ) : null;
	if ( impactCard && impactCheckbox ) {
		impactCheckbox.checked = state.impact;
		const syncDataOn = () => impactCard.setAttribute( 'data-on', impactCheckbox.checked ? 'true' : 'false' );
		syncDataOn();
		impactCard.addEventListener( 'click', ( e ) => {
			if ( e.target.closest( 'input, label, a' ) ) return;
			e.preventDefault();
			impactCheckbox.checked = ! impactCheckbox.checked;
			impactCheckbox.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
		impactCheckbox.addEventListener( 'change', () => {
			state.impact = impactCheckbox.checked;
			syncDataOn();
			rerender();
		} );
		impactCheckbox.addEventListener( 'keydown', ( e ) => {
			if ( ( e.key === 'Enter' || e.key === ' ' ) && e.target === impactCheckbox ) {
				e.preventDefault();
				e.stopPropagation();
				impactCheckbox.checked = ! impactCheckbox.checked;
				impactCheckbox.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		}, { capture: true } );
	}

	// ── Add a day ──
	const addDayBtn = root.querySelector( '[data-calc-add-day]' );
	if ( addDayBtn ) {
		addDayBtn.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			const last = state.days[ state.days.length - 1 ];
			const next = new Date( last.date );
			next.setDate( next.getDate() + 1 );
			state.days.push( {
				date: isoFormat( next ),
				start: last.start,
				end: last.end,
			} );
			renderDaysList( root, state, rerender );
			rerender();
		} );
	}

	// ── Add a custom line ──
	const addCustomBtn = root.querySelector( '[data-calc-custom-add]' );
	if ( addCustomBtn ) {
		addCustomBtn.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			state.custom.push( { label: '', value: 0 } );
			renderCustomList( root, state, rerender );
		} );
	}

	// ── Renderers + breakdown proxy + source tooltips ──
	renderDaysList( root, state, rerender );
	renderCustomList( root, state, rerender );

	bindBreakdownTrigger( root, 'methodology' );
	bindSourceTooltips( scope );

	return { rerender };
}

// --- Init ---
function initCalc( root ) {
	if ( ! root ) return;
	const scope = root.parentElement;
	const ssot = getSSOT();

	// If SSOT rooms aren't injected, the calc is unusable; bail silently.
	if ( ! ssot.rooms || Object.keys( ssot.rooms ).length === 0 ) return;

	const state = readURL();

	const bound = bindEvents( scope, root, state );
	bound.rerender();

	// One-time paint of monospace price hints onto each priced option label.
	paintPriceHints( root, ssot );

	// Share row — lives OUTSIDE the calc body grid (sticky aside), so look
	// it up from the wrapper parent.
	initCalcShare( scope, {
		slug: 'meeting-costs',
		getState: () => ( {
			size:     state.size,
			days:     state.days.map( ( d ) => ( { date: d.date, start: d.start, end: d.end } ) ),
			catering: {
				tea:          state.cater.tea,
				breakfast:    state.cater.breakfast,
				lunchLight:   state.cater.lunchLight,
				lunchHearty:  state.cater.lunchHearty,
				afternoon:    state.cater.afternoon,
				drinks:       state.cater.drinks,
			},
			av:        { projector: state.av.projector, sound: state.av.sound },
			materials: { boards: state.mat.boards, postits: state.mat.postits, printing: state.mat.printing },
			fac:       state.fac,
			setup:     state.setup,
			custom:    state.custom.map( ( c ) => ( { label: c.label, value: c.value } ) ),
			impact:    state.impact,
		} ),
	} );
}

export function initMeetingCosts() {
	document.querySelectorAll( '[data-js="calc-meeting-costs"]' ).forEach( initCalc );
}