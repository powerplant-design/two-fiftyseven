/**
 * 257 Office Costs Calculator — engine
 * ----------------------------------------------------------------------------
 * Configures a Wellington office cost top-to-bottom: rent (grade + precinct
 * modifiers), outgoings, utilities, cleaning, consumables, compliance +
 * insurance, furniture amortisation, admin overhead, lease legals, booking
 * software and custom lines. The total is recomputed live and compared against
 * what the same team costs at two/fiftyseven (per-member Flexi/Dedicated
 * memberships) in a savings band.
 *
 * Ported from the source engine `calc-office-costs-v2.js` + its page
 * coordinator + the four standalone inline scripts (team roster, grade→rent
 * sync, savings band, take-it) into ONE module — no window.occv2 global.
 * Scenarios stay module-local (localStorage `occv2-scenarios` only).
 *
 * Markup contract: root has [data-js="calc-office-costs-v2"] (the legacy
 * engine hook — the only "v2" remnant; C1 workspace-pricing owns the
 * [data-js="calc-office-costs"] hook, so the blocks can't share it).
 *
 * Inputs:  [data-occv2-*] inputs/selects, grade radios [data-occv2-grade],
 *          team slider [data-oc-team-*], per-member days roster rebuilt into
 *          [data-oc-days-roster], custom rows [data-occv2-custom-rows]
 * Outputs: [data-result-*] figure panel, [data-oc-vs257] savings band,
 *          scenario slots, breakdown [data-occv2-lines-slot] /
 *          [data-occv2-category-slot] / [data-occv2-value-add].
 *
 * SSOT reads (window.twofiftyseven): prices.{dedicated,flexi-5..1} for the
 * savings band — NOT the source's hardcoded FLEXI{109..509}/DEDICATED 659.
 *
 * URL sync: readURL/writeURL keep the source's compact keys
 * team/days/pre/sqm/rent/opex/net/pw/phr/pkw/chrs/crt/kb/ins/fa/fw/adp/adr/
 * leg/lty/fpp/fy/bc + grade/bt + cNl cNv custom rows. days is a
 * comma-separated list, one value per team member (the source only captured
 * the first member's slider — a port fix).
 * ============================================================================
 */

import { initCalcShare } from './calc-share.js';
import { bindRovingRadio, bindStepper, bindBreakdownTrigger, bindSourceTooltips, fmt$, fmtN } from './calc-utils.js';
import { getScrollInstance } from './scroll.js';

// --- Grade + precinct rent modifiers (cited, stay in code) ---
const GRADE_MODIFIER = {
	'A-grade':         1.35,
	'B-grade fitted':  1.00,
	'B-grade unfitted': 0.78,
	'C-grade':         0.62,
};

const PRECINCT_MODIFIER = {
	'CBD core':   1.15,
	'CBD fringe': 1.00,
	'Te Aro':     0.92,
	'Thorndon':   1.05,
	'Lambton':    1.20,
	'Kelburn':    0.85,
	'Mt Vic':     0.95,
};

// --- Authoritative defaults (used when an input is empty; cited, stay in code) ---
const DEFAULTS = {
	teamSize:              0,
	daysPerWeek:           5,
	grade:                 'B-grade fitted',
	precinct:              'CBD core',
	rentPerSqmPerYr:       310,
	sqmPerPerson:          9,
	outgoingsPctOfRent:    0.27,
	furniturePerPerson:    2000,
	furnitureAmortYrs:     5,
	internetPerMo:         200,
	powerWattsPerSqm:      50,
	powerHoursPerYear:     1840,
	powerPricePerKwh:      0.30,
	cleaningHoursPerSqmYr: 1.2,
	cleaningPerHour:       45,
	kbPerPersonPerYr:      300,
	insurancePerPersonYr:  200,
	firstAidPerPersonYr:   28,
	fireWardenPerPersonYr: 18,
	adminPctOfHours:       0.06,
	adminLoadedHourly:     70,
	leaseLegalsOneOff:     3500,
	leaseTermYears:        3,
	bookingSoftwareCost:   8,
	bookingSoftware:       false,
};

const M = {
	TEAM_MAX:     15,
	DAYS_MIN:     1,
	DAYS_MAX:     5,
	WEEKS_PER_YR: 46,
};

// --- Source links per line (cited research — stay in code) ---
const SOURCES = {
	rent:       { label: 'Colliers NZ',        href: 'https://www.colliers.co.nz/en-nz/research' },
	outgoings:  { label: 'Property Council NZ', href: 'https://www.propertycouncil.co.nz/' },
	furniture:  { label: 'Govt Property Group', href: 'https://www.gpg.govt.nz/workplace-design/' },
	internet:   { label: '2degrees Business',   href: 'https://www.2degrees.nz/business/broadband/plans' },
	power:      { label: 'EECA + BRANZ',        href: 'https://www.eeca.govt.nz/co-funding-and-support/products/commercial-buildings-decarbonisation-pathway/' },
	cleaning:   { label: 'Clean Planet Welly',  href: 'https://www.cleanplanetwellington.co.nz/commercial-cleaning-prices-wellington-2026' },
	kb:         { label: 'Officemax NZ',        href: 'https://www.officemax.co.nz/' },
	insurance:  { label: 'ICNZ',                href: 'https://www.icnz.org.nz/individuals/commercial/' },
	firstAid:   { label: 'St John NZ',          href: 'https://www.stjohn.org.nz/first-aid/training/' },
	fireWarden: { label: 'FENZ',                href: 'https://www.fireandemergency.nz/businesses-and-landlords/' },
	admin:      { label: 'PayScale NZ',         href: 'https://www.payscale.com/research/NZ/Job=Office_Administrator/Hourly_Rate' },
	legals:     { label: 'LawyerFinder NZ',     href: 'https://lawyerfinder.co.nz/resources/costs/lawyer-fees/' },
	booking:    { label: 'Skedda',              href: 'https://www.skedda.com/' },
	custom:     { label: 'Your line',           href: '#methodology' },
};

// Value-add citations (Job 11 — the "quietly funds" table).
const VALUE_ADD = {
	livingWage:   { label: 'Living-wage cleaners',   sub: 'Difference vs minimum-wage contractors · $7.92/m²/yr',                    href: 'https://www.livingwage.org.nz/' },
	carbon:       { label: 'Verified carbon offset', sub: '200% NZ-EU carbon offset · $1.25/pp/yr',                                 href: 'https://www.epa.govt.nz/industry-areas/emissions-trading-scheme/' },
	climatePower: { label: 'Climate-positive power', sub: '~5% premium on a standard grid supply',                                 href: 'https://www.ecotricity.co.nz/' },
	giving:       { label: 'Giving contribution',    sub: '$1 per team-hour, given forward',                                        href: null },
	mhfr:         { label: 'MHFR-trained team',      sub: '$445 certification ÷ 12 people ÷ 2.5 yr · on-site',                      href: 'https://stepstone.org.nz/education/mhfaaotearoa/' },
};

// --- Helpers ---
function toNum( v, d ) {
	const n = parseFloat( v );
	return isNaN( n ) ? d : n;
}

function clamp( n, lo, hi ) {
	return Math.max( lo, Math.min( hi, n ) );
}

function escapeHTML( s ) {
	return String( s ).replace( /[&<>"']/g, ( c ) => (
		{ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ]
	) );
}

// --- Read SSOT ---
// Member prices for the savings band (same shape as workspace-pricing).
const FLEXI_PRICE = { 1: 'flexi1', 2: 'flexi2', 3: 'flexi3', 4: 'flexi4', 5: 'flexi5' };

function getPrices() {
	const p = ( window.twofiftyseven && window.twofiftyseven.prices ) || {};
	const mk = ( key, fallback ) => ( p[ key ] && p[ key ].price ) || fallback;
	return {
		dedicated: mk( 'dedicated', 659 ),
		flexi1:    mk( 'flexi-1', 109 ),
		flexi2:    mk( 'flexi-2', 209 ),
		flexi3:    mk( 'flexi-3', 309 ),
		flexi4:    mk( 'flexi-4', 409 ),
		flexi5:    mk( 'flexi-5', 509 ),
	};
}

// --- State reader — pulls live values out of the DOM (C3's inputs are all
// --- DOM-carried, so every tick re-reads; no separate state object). ---
function readState( root ) {
	const val = ( sel ) => { const el = root.querySelector( sel ); return el ? el.value : ''; };
	const num = ( sel, d ) => toNum( val( sel ), d );
	const checked = ( sel ) => { const el = root.querySelector( sel ); return ! ! ( el && el.checked ); };

	const team = Math.round( clamp( num( '[data-occv2-team-size]', DEFAULTS.teamSize ), 0, M.TEAM_MAX ) );

	// Per-person days/week — the roster rows. If a team is set but rows are
	// missing (no-JS markup), every member is treated as the default.
	const perPersonDays = [];
	root.querySelectorAll( '[data-occv2-days-per-week]' ).forEach( ( el ) => {
		perPersonDays.push( clamp( toNum( el.value, DEFAULTS.daysPerWeek ), M.DAYS_MIN, M.DAYS_MAX ) );
	} );
	while ( perPersonDays.length < team ) perPersonDays.push( DEFAULTS.daysPerWeek );

	const avgDaysPerWeek = team > 0
		? perPersonDays.slice( 0, team ).reduce( ( a, b ) => a + b, 0 ) / team
		: DEFAULTS.daysPerWeek;

	const gradeEl = root.querySelector( '[data-occv2-grade][aria-checked="true"]' );
	const grade = gradeEl ? gradeEl.getAttribute( 'data-occv2-grade' ) || DEFAULTS.grade : DEFAULTS.grade;
	const precinct = val( '[data-occv2-precinct]' ) || DEFAULTS.precinct;

	const customLines = [];
	root.querySelectorAll( '[data-occv2-custom-row]' ).forEach( ( row ) => {
		const lbl = row.querySelector( '[data-occv2-custom-label]' );
		const v = row.querySelector( '[data-occv2-custom-value]' );
		const label = ( lbl && lbl.value || '' ).trim();
		const value = v ? parseFloat( v.value ) : NaN;
		if ( label && ! isNaN( value ) && value > 0 ) {
			customLines.push( { label, value } );
		}
	} );

	return {
		teamSize:                team,
		perPersonDays,
		avgDaysPerWeek,
		grade,
		precinct,
		rentPerSqmPerYr:         num( '[data-occv2-rent-sqm]',       DEFAULTS.rentPerSqmPerYr ),
		sqmPerPerson:            num( '[data-occv2-sqm-pp]',         DEFAULTS.sqmPerPerson ),
		outgoingsPctOfRent:      num( '[data-occv2-outgoings-pct]',  DEFAULTS.outgoingsPctOfRent * 100 ) / 100,
		furniturePerPerson:      num( '[data-occv2-furniture-pp]',   DEFAULTS.furniturePerPerson ),
		furnitureAmortYrs:       num( '[data-occv2-furniture-yrs]',  DEFAULTS.furnitureAmortYrs ),
		internetPerMo:           num( '[data-occv2-internet-mo]',    DEFAULTS.internetPerMo ),
		powerWattsPerSqm:        num( '[data-occv2-power-w-sqm]',    DEFAULTS.powerWattsPerSqm ),
		powerHoursPerYear:       num( '[data-occv2-power-hrs]',      DEFAULTS.powerHoursPerYear ),
		powerPricePerKwh:        num( '[data-occv2-power-kwh]',      DEFAULTS.powerPricePerKwh ),
		cleaningHoursPerSqmYr:   num( '[data-occv2-cleaning-hr-sqm]', DEFAULTS.cleaningHoursPerSqmYr ),
		cleaningPerHour:         num( '[data-occv2-cleaning-hr]',    DEFAULTS.cleaningPerHour ),
		kbPerPersonPerYr:        num( '[data-occv2-kb-pp]',          DEFAULTS.kbPerPersonPerYr ),
		insurancePerPersonPerYr: num( '[data-occv2-insurance-pp]',   DEFAULTS.insurancePerPersonPerYr ),
		firstAidPerPersonPerYr:  num( '[data-occv2-first-aid-pp]',   DEFAULTS.firstAidPerPersonPerYr ),
		fireWardenPerPersonPerYr:num( '[data-occv2-fire-warden-pp]', DEFAULTS.fireWardenPerPersonPerYr ),
		adminPctOfHours:          num( '[data-occv2-admin-pct]',   DEFAULTS.adminPctOfHours * 100 ) / 100,
		adminLoadedHourly:       num( '[data-occv2-admin-rate]',     DEFAULTS.adminLoadedHourly ),
		leaseLegalsOneOff:       num( '[data-occv2-legals]',         DEFAULTS.leaseLegalsOneOff ),
		leaseTermYears:          num( '[data-occv2-lease-yrs]',      DEFAULTS.leaseTermYears ),
		bookingSoftware:         checked( '[data-occv2-booking-toggle]' ),
		bookingSoftwareCost:     num( '[data-occv2-booking-cost]',   DEFAULTS.bookingSoftwareCost ),
		customLines,
	};
}

// --- Compute — pure over state → result lines + totals (mirrors the source) ---
function compute( s ) {
	if ( ! s.teamSize || s.teamSize <= 0 ) return zeroResult();

	const sqmTotal = s.teamSize * s.sqmPerPerson;
	const gradeMod = GRADE_MODIFIER[ s.grade ] || 1.0;
	const precMod = PRECINCT_MODIFIER[ s.precinct ] || 1.0;

	const rent       = sqmTotal * s.rentPerSqmPerYr * gradeMod * precMod;
	const outgoings  = rent * s.outgoingsPctOfRent;
	const furniture  = s.furnitureAmortYrs > 0
		? ( s.teamSize * s.furniturePerPerson ) / s.furnitureAmortYrs : 0;
	const internet   = s.internetPerMo * 12;
	const power      = ( sqmTotal * s.powerWattsPerSqm * s.powerHoursPerYear * s.powerPricePerKwh ) / 1000;
	const cleaning   = sqmTotal * s.cleaningHoursPerSqmYr * s.cleaningPerHour;
	const kb         = s.teamSize * s.kbPerPersonPerYr;
	const insurance  = s.teamSize * s.insurancePerPersonPerYr;
	const firstAid   = s.teamSize * s.firstAidPerPersonPerYr;
	const fireWarden = s.teamSize * s.fireWardenPerPersonPerYr;

	const adminHours = s.teamSize * s.powerHoursPerYear * s.adminPctOfHours;
	const admin      = adminHours * s.adminLoadedHourly;
	const legals     = s.leaseTermYears > 0 ? s.leaseLegalsOneOff / s.leaseTermYears : 0;

	const bookingActive = s.bookingSoftware || s.teamSize >= 10;
	const booking = bookingActive ? s.teamSize * s.bookingSoftwareCost * 12 : 0;

	let customSum = 0;
	s.customLines.forEach( ( cl ) => { customSum += cl.value; } );

	const annualTotal = rent + outgoings + furniture + internet + power + cleaning
		+ kb + insurance + firstAid + fireWarden + admin + legals
		+ booking + customSum;

	// Per-person-day uses the team-average days-per-week.
	const workingDaysPerYear = ( s.avgDaysPerWeek || DEFAULTS.daysPerWeek ) * M.WEEKS_PER_YR;
	const perPersonDay = ( s.teamSize > 0 && workingDaysPerYear > 0 )
		? annualTotal / s.teamSize / workingDaysPerYear : 0;

	const lines = [
		{
			key: 'rent', baseKey: 'rent', label: 'Rent', value: rent,
			note: '$' + Math.round( s.rentPerSqmPerYr ) + '/m²/yr × ' + s.sqmPerPerson + ' m²/pp × ' + s.teamSize + ' people × ' + gradeMod.toFixed( 2 ) + ' (' + s.grade + ') × ' + precMod.toFixed( 2 ) + ' (' + s.precinct + ')',
		},
		{ key: 'outgoings', baseKey: 'outgoings', label: 'Outgoings', value: outgoings, note: Math.round( s.outgoingsPctOfRent * 100 ) + '% of rent' },
		{ key: 'furniture', baseKey: 'furniture', label: 'Furniture (amortised)', value: furniture, note: '$' + Math.round( s.furniturePerPerson ) + '/pp × ' + s.teamSize + ' ÷ ' + s.furnitureAmortYrs + ' yrs' },
		{ key: 'internet', baseKey: 'internet', label: 'Internet', value: internet, note: '$' + Math.round( s.internetPerMo ) + '/mo business fibre × 12' },
		{
			key: 'power', baseKey: 'power', label: 'Power', value: power,
			note: s.powerWattsPerSqm + ' W/m² × ' + sqmTotal + ' m² × ' + s.powerHoursPerYear + ' hrs × $' + s.powerPricePerKwh.toFixed( 2 ) + '/kWh',
		},
		{ key: 'cleaning', baseKey: 'cleaning', label: 'Cleaning', value: cleaning, note: s.cleaningHoursPerSqmYr + ' hr/m²/yr × $' + Math.round( s.cleaningPerHour ) + '/hr × ' + sqmTotal + ' m²' },
		{ key: 'kb', baseKey: 'kb', label: 'Kitchen + bathroom', value: kb, note: '$' + Math.round( s.kbPerPersonPerYr ) + '/pp/yr consumables' },
		{ key: 'insurance', baseKey: 'insurance', label: 'Insurance', value: insurance, note: '$' + Math.round( s.insurancePerPersonPerYr ) + '/pp/yr combined' },
		{ key: 'firstAid', baseKey: 'firstAid', label: 'First aid training', value: firstAid, note: '$' + Math.round( s.firstAidPerPersonPerYr ) + '/pp/yr (H&S Act 2015 compliance)' },
		{ key: 'fireWarden', baseKey: 'fireWarden', label: 'Fire warden training', value: fireWarden, note: '$' + Math.round( s.fireWardenPerPersonPerYr ) + '/pp/yr (FENZ requirement)' },
		{
			key: 'admin', baseKey: 'admin', label: 'Admin time', value: admin,
			note: Math.round( s.adminPctOfHours * 100 ) + '% of team hours × $' + Math.round( s.adminLoadedHourly ) + '/hr loaded',
		},
		{
			key: 'legals', baseKey: 'legals', label: 'Lease legals (amortised)', value: legals,
			note: '$' + Math.round( s.leaseLegalsOneOff ).toLocaleString( 'en-NZ' ) + ' one-off ÷ ' + s.leaseTermYears + ' yr term',
		},
	];
	if ( bookingActive ) {
		lines.push( {
			key: 'booking', baseKey: 'booking', label: 'Booking software', value: booking,
			note: '$' + s.bookingSoftwareCost + '/pp/mo × ' + s.teamSize + ' × 12 (auto-on at team ≥ 10)',
		} );
	}
	s.customLines.forEach( ( cl, i ) => {
		lines.push( { key: 'custom-' + i, baseKey: 'custom', label: cl.label, value: cl.value, note: 'Custom line you added' } );
	} );

	const categories = {
		'rent-opex':              rent + outgoings,
		'utilities':              internet + power,
		'cleaning-kb':            cleaning + kb,
		'compliance-insurance':   insurance + firstAid + fireWarden,
		'furniture-admin-legals': furniture + admin + legals,
		'addons-custom':          booking + customSum,
	};
	const categoryPct = {};
	Object.keys( categories ).forEach( ( k ) => {
		categoryPct[ k ] = annualTotal > 0 ? categories[ k ] / annualTotal : 0;
	} );

	// Value-add quantification (Job 11)
	const livingWage  = 7.92 * sqmTotal;
	const carbon      = 1.25 * s.teamSize;
	const climatePre  = power * 0.05;
	const giving      = 1 * s.teamSize * s.powerHoursPerYear;
	const mhfr        = ( 445 * Math.ceil( s.teamSize / 12 ) ) / 2.5;
	const valueAdd    = livingWage + carbon + climatePre + giving + mhfr;

	return {
		sqmTotal,
		annualTotal,
		monthlyTotal: annualTotal / 12,
		perPersonMonth: s.teamSize > 0 ? ( annualTotal / s.teamSize / 12 ) : 0,
		perPersonDay,
		perSqmYr: sqmTotal > 0 ? annualTotal / sqmTotal : 0,
		bookingActive,
		lines,
		categories,
		categoryPct,
		valueAdd: { livingWage, carbon, climatePower: climatePre, giving, mhfr, total: valueAdd },
	};
}

function zeroResult() {
	return {
		sqmTotal: 0, annualTotal: 0, monthlyTotal: 0, perPersonMonth: 0,
		perPersonDay: 0, perSqmYr: 0, bookingActive: false, lines: [],
		categories: {
			'rent-opex': 0, 'utilities': 0, 'cleaning-kb': 0,
			'compliance-insurance': 0, 'furniture-admin-legals': 0, 'addons-custom': 0,
		},
		categoryPct: {
			'rent-opex': 0, 'utilities': 0, 'cleaning-kb': 0,
			'compliance-insurance': 0, 'furniture-admin-legals': 0, 'addons-custom': 0,
		},
		valueAdd: { livingWage: 0, carbon: 0, climatePower: 0, giving: 0, mhfr: 0, total: 0 },
	};
}

// --- URL state sync (source's compact keys) ---
const URL_FIELDS = [
	[ 'data-occv2-team-size',        'team' ],
	[ 'data-occv2-precinct',         'pre' ],
	[ 'data-occv2-sqm-pp',           'sqm' ],
	[ 'data-occv2-rent-sqm',         'rent' ],
	[ 'data-occv2-outgoings-pct',    'opex' ],
	[ 'data-occv2-internet-mo',      'net' ],
	[ 'data-occv2-power-w-sqm',      'pw' ],
	[ 'data-occv2-power-hrs',        'phr' ],
	[ 'data-occv2-power-kwh',        'pkw' ],
	[ 'data-occv2-cleaning-hr-sqm',  'chrs' ],
	[ 'data-occv2-cleaning-hr',      'crt' ],
	[ 'data-occv2-kb-pp',            'kb' ],
	[ 'data-occv2-insurance-pp',     'ins' ],
	[ 'data-occv2-first-aid-pp',     'fa' ],
	[ 'data-occv2-fire-warden-pp',   'fw' ],
	[ 'data-occv2-admin-pct',        'adp' ],
	[ 'data-occv2-admin-rate',       'adr' ],
	[ 'data-occv2-legals',           'leg' ],
	[ 'data-occv2-lease-yrs',        'lty' ],
	[ 'data-occv2-furniture-pp',     'fpp' ],
	[ 'data-occv2-furniture-yrs',    'fy' ],
	[ 'data-occv2-booking-cost',     'bc' ],
];

function currentTeam( root ) {
	const el = root.querySelector( '[data-occv2-team-size]' );
	return parseInt( ( el && el.value ) || '0', 10 ) || 0;
}

function readDaysFromURL() {
	const raw = new URLSearchParams( window.location.search ).get( 'days' );
	if ( ! raw || ! raw.trim() ) return null;
	return raw.split( ',' ).map( ( s ) => clamp( parseInt( s, 10 ) || M.DAYS_MAX, M.DAYS_MIN, M.DAYS_MAX ) );
}

// ── Scenario storage · 3 named slots backed by localStorage ──
const SCENARIO_STORAGE_KEY = 'occv2-scenarios';

function readScenarios() {
	try {
		const raw = window.localStorage.getItem( SCENARIO_STORAGE_KEY );
		if ( ! raw ) return [ null, null, null ];
		const parsed = JSON.parse( raw );
		if ( ! Array.isArray( parsed ) ) return [ null, null, null ];
		while ( parsed.length < 3 ) parsed.push( null );
		return parsed.slice( 0, 3 );
	} catch ( e ) {
		return [ null, null, null ];
	}
}

function writeScenarios( arr ) {
	try {
		window.localStorage.setItem( SCENARIO_STORAGE_KEY, JSON.stringify( arr ) );
	} catch ( e ) { /* quota / privacy mode */ }
}

// Capture the full DOM-input state as a snapshot (same shape as the source
// engine, but grade is a button radio so it's stored separately).
function snapshotState( root ) {
	const snap = { v: 1, inputs: {} };
	root.querySelectorAll( 'input, select, textarea' ).forEach( ( el ) => {
		for ( let i = 0; i < el.attributes.length; i++ ) {
			const a = el.attributes[ i ];
			if ( a.name.indexOf( 'data-occv2-' ) !== 0 ) continue;
			const key = a.name;
			if ( el.type === 'checkbox' ) snap.inputs[ key ] = !! el.checked;
			else snap.inputs[ key ] = el.value;
		}
	} );
	const gradeEl = root.querySelector( '[data-occv2-grade][aria-checked="true"]' );
	snap.grade = gradeEl ? gradeEl.getAttribute( 'data-occv2-grade' ) || DEFAULTS.grade : DEFAULTS.grade;
	snap.customLines = [];
	root.querySelectorAll( '[data-occv2-custom-row]' ).forEach( ( row ) => {
		const lbl = row.querySelector( '[data-occv2-custom-label]' );
		const v = row.querySelector( '[data-occv2-custom-value]' );
		snap.customLines.push( {
			label: lbl ? lbl.value : '',
			value: v ? v.value : '',
		} );
	} );
	return snap;
}

// --- Rendering ---
function renderBreakdownRows( scope, result ) {
	const linesSlot = scope.querySelector( '[data-occv2-lines-slot]' );
	const totalSlot = scope.querySelector( '[data-occv2-lines-total]' );
	if ( linesSlot ) {
		linesSlot.innerHTML = result.lines.map( ( l ) => {
			const src = SOURCES[ l.baseKey || l.key ] || SOURCES.custom;
			const isExt = src.href && src.href.charAt( 0 ) !== '#';
			return `
				<div class="calc__compare-row office-costs__breakdown-row">
					<div class="calc__compare-row-label">
						<span>${ escapeHTML( l.label ) }<span class="calc-source__note"> — ${ escapeHTML( l.note ) }</span></span>
						${ src.href ? `<a class="office-costs__row-source | text-s" href="${ src.href }"${ isExt ? ' target="_blank" rel="noopener"' : '' }>${ escapeHTML( src.label ) }${ isExt ? ' &#8599;' : '' }</a>` : '' }
					</div>
					<div class="calc__compare-row-value">${ fmt$( l.value ) }</div>
				</div>`;
		} ).join( '' );
	}
	if ( totalSlot ) totalSlot.textContent = fmt$( result.annualTotal );
}

function renderCategoryGrid( scope, result ) {
	const catSlot = scope.querySelector( '[data-occv2-category-slot]' );
	if ( ! catSlot ) return;
	const labels = {
		'rent-opex':              'Rent + outgoings',
		'utilities':              'Utilities',
		'cleaning-kb':            'Cleaning + consumables',
		'compliance-insurance':   'Compliance + insurance',
		'furniture-admin-legals': 'Furniture + admin + legals',
		'addons-custom':          'Add-ons + custom',
	};
	catSlot.innerHTML = Object.keys( labels ).map( ( k ) => {
		const pct = Math.round( ( result.categoryPct[ k ] || 0 ) * 100 );
		return `
			<div class="office-costs__category">
				<span class="office-costs__category-label | text-s">${ escapeHTML( labels[ k ] ) }</span>
				<span class="office-costs__category-value | font-bold">${ fmt$( result.categories[ k ] || 0 ) }</span>
				<span class="office-costs__category-pct | text-s text-monospace">${ pct }% of total</span>
			</div>`;
	} ).join( '' );
}

function renderValueAdd( scope, result ) {
	const slot = scope.querySelector( '[data-occv2-value-add]' );
	if ( ! slot ) return;
	const rows = [
		[ 'livingWage',   result.valueAdd.livingWage ],
		[ 'carbon',       result.valueAdd.carbon ],
		[ 'climatePower', result.valueAdd.climatePower ],
		[ 'giving',       result.valueAdd.giving ],
		[ 'mhfr',         result.valueAdd.mhfr ],
	];
	slot.innerHTML = rows.map( ( [ key, value ] ) => {
		const v = VALUE_ADD[ key ];
		return `
			<div class="calc__compare-row office-costs__value-row">
				<div class="calc__compare-row-label">
					<span>${ escapeHTML( v.label ) }<span class="calc-source__note"> — ${ escapeHTML( v.sub ) }</span></span>
					${ v.href ? `<a class="office-costs__row-source | text-s" href="${ v.href }" target="_blank" rel="noopener">Source &#8599;</a>` : '' }
				</div>
				<div class="calc__compare-row-value">${ fmt$( value ) }</div>
			</div>`;
	} ).join( '' ) + `
		<div class="calc__compare-row calc__compare-row--total">
			<div class="calc__compare-row-label">Equivalent procured-separately value</div>
			<div class="calc__compare-row-value">${ fmt$( result.valueAdd.total ) }</div>
		</div>`;
}

function renderResults( scope, root, state, result, prices ) {
	// Result panel — headline + stat rows
	const empty = ! state.teamSize || state.teamSize <= 0;
	root.querySelectorAll( '[data-result-content]' ).forEach( ( el ) => { el.hidden = empty; } );
	root.querySelectorAll( '[data-result-empty]' ).forEach( ( el ) => { el.hidden = ! empty; } );

	root.querySelectorAll( '[data-result-annual]' ).forEach( ( el ) => {
		el.textContent = empty ? '$0' : fmt$( result.annualTotal );
	} );
	root.querySelectorAll( '[data-result-monthly]' ).forEach( ( el ) => {
		el.textContent = empty ? '$0' : fmt$( result.monthlyTotal );
	} );
	root.querySelectorAll( '[data-result-pp-month]' ).forEach( ( el ) => {
		el.textContent = empty ? '$0' : fmt$( result.perPersonMonth );
	} );
	root.querySelectorAll( '[data-result-pp-day]' ).forEach( ( el ) => {
		el.textContent = empty ? '$0' : fmt$( result.perPersonDay );
	} );
	root.querySelectorAll( '[data-result-per-sqm]' ).forEach( ( el ) => {
		el.textContent = empty ? '$0' : fmt$( result.perSqmYr );
	} );

	// Savings band — per-member days → Flexi tier, 5 days = Dedicated high.
	const box = scope.querySelector( '[data-oc-vs257]' );
	const saveEl = box ? box.querySelector( '[data-oc-save-figure]' ) : null;
	if ( box && saveEl ) {
		const days = Array.from( root.querySelectorAll( '[data-oc-day-slider]' ) ).map( ( sl ) =>
			clamp( parseInt( sl.value, 10 ) || M.DAYS_MAX, M.DAYS_MIN, M.DAYS_MAX ) );
		let lo = 0;
		let hi = 0;
		days.forEach( ( d ) => {
			lo += prices[ FLEXI_PRICE[ d ] ];
			hi += ( d === M.DAYS_MAX ) ? prices.dedicated : prices[ FLEXI_PRICE[ d ] ];
		} );
		lo *= 12;
		hi *= 12;
		const saveFloor = result.annualTotal - hi;
		const saveCeil = result.annualTotal - lo;
		if ( ! days.length || result.annualTotal <= 0 || saveFloor <= 0 ) {
			box.hidden = true;
		} else {
			box.hidden = false;
			saveEl.textContent = saveFloor === saveCeil
				? fmt$( saveFloor )
				: fmt$( saveFloor ) + '–' + fmt$( saveCeil );
		}
	}

	renderBreakdownRows( scope, result );
	renderCategoryGrid( scope, result );
	renderValueAdd( scope, result );
}

function writeURL( root ) {
	const params = new URLSearchParams();
	URL_FIELDS.forEach( ( [ attr, key ] ) => {
		if ( attr === 'data-occv2-team-size' ) return;
		const el = root.querySelector( '[' + attr + ']' );
		if ( el && el.value !== '' ) {
			if ( attr === 'data-occv2-outgoings-pct' || attr === 'data-occv2-admin-pct' ) {
				params.set( key, String( parseFloat( el.value ) / 100 ) );
			} else {
				params.set( key, el.value );
			}
		}
	} );

	// Team — always encoded (0 keeps the empty card shareable).
	params.set( 'team', String( currentTeam( root ) ) );

	// Days — one value per member, comma-separated.
	const days = Array.from( root.querySelectorAll( '[data-occv2-days-per-week]' ) ).map( ( el ) => el.value );
	if ( days.length ) {
		params.set( 'days', days.join( ',' ) );
	} else {
		params.delete( 'days' );
	}

	const g = root.querySelector( '[data-occv2-grade][aria-checked="true"]' );
	if ( g ) params.set( 'grade', g.getAttribute( 'data-occv2-grade' ) );

	const bt = root.querySelector( '[data-occv2-booking-toggle]' );
	if ( bt && bt.checked ) params.set( 'bt', '1' );

	root.querySelectorAll( '[data-occv2-custom-row]' ).forEach( ( row, i ) => {
		const lbl = row.querySelector( '[data-occv2-custom-label]' );
		const val = row.querySelector( '[data-occv2-custom-value]' );
		if ( lbl && lbl.value ) params.set( 'c' + i + 'l', lbl.value );
		if ( val && val.value ) params.set( 'c' + i + 'v', val.value );
	} );

	const qs = params.toString();
	window.history.replaceState( {}, '', window.location.pathname + ( qs ? '?' + qs : '' ) + window.location.hash );
}

// --- Roster rebuild (per-member days sliders) ---
function paintSlider( sl ) {
	if ( ! sl ) return;
	const min = parseFloat( sl.min ) || 0;
	const max = parseFloat( sl.max ) || 1;
	const v = isNaN( parseFloat( sl.value ) ) ? min : parseFloat( sl.value );
	const pct = max > min ? ( ( v - min ) / ( max - min ) ) * 100 : 0;
	const wrap = sl.closest( '.calc__slider' );
	if ( wrap ) wrap.style.setProperty( '--pct', pct + '%' );
}

function rebuildRoster( root, team, afterInput ) {
	const roster = root.querySelector( '[data-oc-days-roster]' );
	if ( ! roster ) return;
	const prev = Array.from( roster.querySelectorAll( '[data-oc-day-slider]' ) ).map( ( sl ) =>
		clamp( parseInt( sl.value, 10 ) || M.DAYS_MAX, M.DAYS_MIN, M.DAYS_MAX ) );

	roster.innerHTML = '';
	for ( let i = 0; i < team; i++ ) {
		const val = prev[ i ] != null ? prev[ i ] : M.DAYS_MAX;

		const li = document.createElement( 'li' );
		li.className = 'office-costs__roster-row';

		const label = document.createElement( 'span' );
		label.className = 'office-costs__roster-label | text-s';
		label.textContent = 'Member ' + ( i + 1 );

		const wrap = document.createElement( 'div' );
		wrap.className = 'calc__slider office-costs__roster-slider';

		const range = document.createElement( 'input' );
		range.type = 'range';
		range.className = 'calc__slider-input';
		range.min = String( M.DAYS_MIN );
		range.max = String( M.DAYS_MAX );
		range.step = '1';
		range.value = String( val );
		range.setAttribute( 'data-occv2-days-per-week', '' );
		range.setAttribute( 'data-oc-day-slider', '' );
		range.setAttribute( 'aria-label', 'Member ' + ( i + 1 ) + ' days per week' );

		const out = document.createElement( 'span' );
		out.className = 'office-costs__roster-out | text-s';
		const outVal = document.createElement( 'output' );
		outVal.textContent = val + ( val === 1 ? ' day' : ' days' );
		out.appendChild( outVal );

		range.addEventListener( 'input', () => {
			paintSlider( range );
			outVal.textContent = range.value + ( range.value === '1' ? ' day' : ' days' );
			if ( afterInput ) afterInput();
		} );

		wrap.appendChild( range );
		li.appendChild( label );
		li.appendChild( wrap );
		li.appendChild( out );
		roster.appendChild( li );
	}
	roster.querySelectorAll( '[data-oc-day-slider]' ).forEach( paintSlider );
}

// --- Custom rows (repeating, JS-rendered) ---
function renderCustomList( root, afterInput ) {
	const list = root.querySelector( '[data-occv2-custom-rows]' );
	if ( ! list ) return;
	const active = Array.from( list.querySelectorAll( '[data-occv2-custom-row]' ) ).map( ( row ) => ( {
		label: ( row.querySelector( '[data-occv2-custom-label]' ) || {} ).value || '',
		value: ( row.querySelector( '[data-occv2-custom-value]' ) || {} ).value || '',
	} ) );
	if ( ! active.length ) active.push( { label: '', value: '' } );

	list.innerHTML = '';
	active.forEach( ( custom, i ) => {
		const row = document.createElement( 'li' );
		row.className = 'office-costs__custom-row';
		row.setAttribute( 'data-occv2-custom-row', '' );

		const labelInput = document.createElement( 'input' );
		labelInput.type = 'text';
		labelInput.className = 'calc__input office-costs__custom-label';
		labelInput.setAttribute( 'data-occv2-custom-label', '' );
		labelInput.placeholder = 'e.g. Reception';
		labelInput.setAttribute( 'aria-label', 'Custom expense name' );
		labelInput.value = custom.label;
		labelInput.addEventListener( 'input', () => { if ( afterInput ) afterInput(); } );

		const valueInput = document.createElement( 'input' );
		valueInput.type = 'number';
		valueInput.className = 'calc__input office-costs__custom-value';
		valueInput.setAttribute( 'data-occv2-custom-value', '' );
		valueInput.placeholder = '$ value/yr';
		valueInput.min = '0';
		valueInput.step = '100';
		valueInput.setAttribute( 'aria-label', 'Custom expense value in dollars per year' );
		valueInput.value = custom.value;
		valueInput.addEventListener( 'change', () => {
			const v = parseFloat( valueInput.value.replace( /,/g, '' ) );
			valueInput.value = isNaN( v ) ? '' : String( Math.max( 0, v ) );
			if ( afterInput ) afterInput();
		} );

		const remove = document.createElement( 'button' );
		remove.type = 'button';
		remove.className = 'office-costs__custom-remove | calc__day-row-remove';
		remove.setAttribute( 'aria-label', 'Remove this line' );
		remove.textContent = '×';
		remove.disabled = active.length <= 1;
		remove.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			if ( active.length <= 1 ) {
				labelInput.value = '';
				valueInput.value = '';
			} else {
				row.remove();
			}
			if ( afterInput ) afterInput();
		} );

		row.appendChild( labelInput );
		row.appendChild( valueInput );
		row.appendChild( remove );
		list.appendChild( row );
	} );
}

// --- Scenario restore — rebuilds roster + custom rows to match a snapshot ---
function restoreSaved( root, snap, afterInput ) {
	if ( ! snap || ! snap.inputs ) return;
	// Reset checkboxes first (omitted ones become false).
	root.querySelectorAll( 'input[type="checkbox"]' ).forEach( ( el ) => { el.checked = false; } );

	const setVal = ( el, val ) => {
		if ( el.type === 'checkbox' ) { el.checked = !! val; return; }
		// Outgoings and admin-pct were stored as decimals (0.27, 0.06) in old
		// snapshots; the inputs now show whole-number percents (27, 6).
		if ( attr === 'data-occv2-outgoings-pct' || attr === 'data-occv2-admin-pct' ) {
			const n = parseFloat( val );
			el.value = ( n > 0 && n < 1 ) ? String( n * 100 ) : val;
		} else {
			el.value = val;
		}
	};
	Object.keys( snap.inputs ).forEach( ( attr ) => {
		const val = snap.inputs[ attr ];
		root.querySelectorAll( '[' + attr + ']' ).forEach( ( el ) => setVal( el, val ) );
	} );
	root.querySelectorAll( '[data-occv2-grade]' ).forEach( ( btn ) => {
		btn.setAttribute( 'aria-checked', btn.getAttribute( 'data-occv2-grade' ) === snap.grade ? 'true' : 'false' );
	} );
	// The grade radio + rent base come from the snapshot — repaint the visible
	// grade-adjusted rent box to match (bindGradeRent's refresh only fires on
	// a real radio click).
	const baseEl = root.querySelector( '[data-occv2-rent-sqm]' );
	const dispEl = root.querySelector( '[data-oc-rent-display]' );
	const labelEl = root.querySelector( '[data-oc-grade-label]' );
	if ( baseEl && dispEl ) {
		const mod = GRADE_MODIFIER[ snap.grade ] || 1;
		dispEl.value = Math.round( ( parseFloat( baseEl.value ) || 0 ) * mod );
		if ( labelEl && snap.grade ) labelEl.textContent = snap.grade;
	}
	// Booking cost row visibility follows the restored toggle.
	syncBookingVisibility( root );
	// Rebuild the roster to the restored team width.
	rebuildRoster( root, currentTeam( root ), afterInput );
	if ( afterInput ) afterInput();
}

function syncBookingVisibility( root ) {
	const toggle = root.querySelector( '[data-occv2-booking-toggle]' );
	const costWrap = root.querySelector( '[data-oc-booking-cost-wrap]' );
	if ( toggle && costWrap ) costWrap.hidden = ! toggle.checked;
}

// --- Scenario slots + compare dialog ---
function renderScenarioRow( scope ) {
	const all = readScenarios();
	for ( let i = 1; i <= 3; i++ ) {
		const btn = scope.querySelector( '[data-scenario-slot="' + i + '"]' );
		if ( ! btn ) continue;
		const s = all[ i - 1 ];
		const nameEl = btn.querySelector( '[data-scenario-slot-name]' );
		const valEl = btn.querySelector( '[data-scenario-slot-value]' );
		if ( s ) {
			btn.classList.add( 'office-costs__scenario-slot--filled' );
			if ( nameEl ) nameEl.textContent = s.name || ( 'Scenario ' + i );
			if ( valEl ) valEl.textContent = fmt$( s.annualTotal );
		} else {
			btn.classList.remove( 'office-costs__scenario-slot--filled' );
			if ( nameEl ) nameEl.textContent = 'Empty';
			if ( valEl ) valEl.textContent = '·';
		}
	}
}

function bindScenarios( scope, root, afterInput, onReset ) {
	renderScenarioRow( scope );

	scope.querySelectorAll( '[data-scenario-slot]' ).forEach( ( btn ) => {
		const slot = parseInt( btn.getAttribute( 'data-scenario-slot' ), 10 );
		btn.addEventListener( 'click', () => {
			const existing = readScenarios()[ slot - 1 ];
			if ( existing ) {
				restoreSaved( root, existing.state, afterInput );
			} else {
				const name = window.prompt( 'Name this scenario:', 'Scenario ' + slot );
				if ( name === null ) return;
				const snap = snapshotState( root );
				const result = compute( readState( root ) );
				const all = readScenarios();
				all[ slot - 1 ] = {
					name: ( name || ( 'Scenario ' + slot ) ).slice( 0, 40 ),
					savedAt: Date.now(),
					state: snap,
					annualTotal: result.annualTotal,
					perPersonMonth: result.perPersonMonth,
					teamSize: readState( root ).teamSize,
				};
				writeScenarios( all );
				renderScenarioRow( scope );
			}
		} );
		// Right-click clears a filled slot.
		btn.addEventListener( 'contextmenu', ( e ) => {
			e.preventDefault();
			const existing = readScenarios()[ slot - 1 ];
			if ( ! existing ) return;
			if ( window.confirm( 'Clear scenario "' + ( existing.name || slot ) + '"?' ) ) {
				const all = readScenarios();
				all[ slot - 1 ] = null;
				writeScenarios( all );
				renderScenarioRow( scope );
			}
		} );
	} );

	const saveBtn = scope.querySelector( '[data-scenario-save]' );
	if ( saveBtn ) {
		saveBtn.addEventListener( 'click', () => {
			const all = readScenarios();
			let nextEmpty = all.findIndex( ( s ) => ! s ) + 1;
			if ( ! nextEmpty ) {
				if ( ! window.confirm( 'All three slots are full. Replace slot 1?' ) ) return;
				nextEmpty = 1;
			}
			const name = window.prompt( 'Name this scenario:', 'Scenario ' + nextEmpty );
			if ( name === null ) return;
			const snap = snapshotState( root );
			const result = compute( readState( root ) );
			const next = readScenarios();
			next[ nextEmpty - 1 ] = {
				name: ( name || ( 'Scenario ' + nextEmpty ) ).slice( 0, 40 ),
				savedAt: Date.now(),
				state: snap,
				annualTotal: result.annualTotal,
				perPersonMonth: result.perPersonMonth,
				teamSize: readState( root ).teamSize,
			};
			writeScenarios( next );
			renderScenarioRow( scope );
		} );
	}

	const compareBtn = scope.querySelector( '[data-scenario-compare]' );
	const compareDialog = scope.querySelector( '[data-scenario-compare-dialog]' );
	const compareClose = scope.querySelector( '[data-scenario-compare-close]' );
	const compareGrid = scope.querySelector( '[data-scenario-compare-grid]' );
	if ( compareBtn && compareDialog && compareGrid ) {
		compareBtn.addEventListener( 'click', () => {
			renderScenarioRow( scope ); // slot labels stay fresh in the dialog
			const all = readScenarios();
			let html = '';
			for ( let i = 0; i < 3; i++ ) {
				const s = all[ i ];
				if ( s ) {
					const perPpYr = s.perPersonMonth ? ( s.perPersonMonth * 12 ) : 0;
					html += `
						<div class="office-costs__compare-col">
							<span class="office-costs__compare-col-name | font-bold">${ escapeHTML( s.name ) }</span>
							<span class="office-costs__compare-col-value | text-xl">${ fmt$( s.annualTotal ) }</span>
							<span class="office-costs__compare-col-meta | text-s">${ s.teamSize } people · ${ fmt$( perPpYr ) }/pp/yr</span>
						</div>`;
				} else {
					html += `
						<div class="office-costs__compare-col">
							<span class="office-costs__compare-col-name | font-bold">Slot ${ i + 1 }</span>
							<span class="office-costs__compare-empty | text-s">Empty</span>
						</div>`;
				}
			}
			compareGrid.innerHTML = html;
			if ( typeof compareDialog.showModal === 'function' ) compareDialog.showModal();
			else compareDialog.setAttribute( 'open', 'open' );
			getScrollInstance()?.lenisInstance?.stop();
		} );
	}
	if ( compareClose && compareDialog ) {
		compareClose.addEventListener( 'click', () => {
			if ( typeof compareDialog.close === 'function' ) compareDialog.close();
			else compareDialog.removeAttribute( 'open' );
			getScrollInstance()?.lenisInstance?.start();
		} );
		// Native dialog close (Esc key) — resume scroll.
		compareDialog.addEventListener( 'close', () => {
			getScrollInstance()?.lenisInstance?.start();
		} );
	}

	const resetBtn = scope.querySelector( '[data-scenario-reset]' );
	if ( resetBtn ) {
		resetBtn.addEventListener( 'click', () => {
			if ( ! window.confirm( 'Reset all inputs to defaults and clear scenarios?' ) ) return;
			// Restore every input to its default value.
			root.querySelectorAll( '[data-occv2-sqm-pp]' ).forEach( ( el ) => { el.value = DEFAULTS.sqmPerPerson; } );
			root.querySelectorAll( '[data-occv2-rent-sqm]' ).forEach( ( el ) => { el.value = DEFAULTS.rentPerSqmPerYr; } );
			root.querySelectorAll( '[data-oc-rent-display]' ).forEach( ( el ) => { el.value = DEFAULTS.rentPerSqmPerYr; } );
			root.querySelectorAll( '[data-occv2-outgoings-pct]' ).forEach( ( el ) => { el.value = DEFAULTS.outgoingsPctOfRent * 100; } );
			root.querySelectorAll( '[data-occv2-internet-mo]' ).forEach( ( el ) => { el.value = DEFAULTS.internetPerMo; } );
			root.querySelectorAll( '[data-occv2-power-w-sqm]' ).forEach( ( el ) => { el.value = DEFAULTS.powerWattsPerSqm; } );
			root.querySelectorAll( '[data-occv2-power-hrs]' ).forEach( ( el ) => { el.value = DEFAULTS.powerHoursPerYear; } );
			root.querySelectorAll( '[data-occv2-power-kwh]' ).forEach( ( el ) => { el.value = DEFAULTS.powerPricePerKwh; } );
			root.querySelectorAll( '[data-occv2-cleaning-hr-sqm]' ).forEach( ( el ) => { el.value = DEFAULTS.cleaningHoursPerSqmYr; } );
			root.querySelectorAll( '[data-occv2-cleaning-hr]' ).forEach( ( el ) => { el.value = DEFAULTS.cleaningPerHour; } );
			root.querySelectorAll( '[data-occv2-kb-pp]' ).forEach( ( el ) => { el.value = DEFAULTS.kbPerPersonPerYr; } );
			root.querySelectorAll( '[data-occv2-insurance-pp]' ).forEach( ( el ) => { el.value = DEFAULTS.insurancePerPersonYr; } );
			root.querySelectorAll( '[data-occv2-first-aid-pp]' ).forEach( ( el ) => { el.value = DEFAULTS.firstAidPerPersonYr; } );
			root.querySelectorAll( '[data-occv2-fire-warden-pp]' ).forEach( ( el ) => { el.value = DEFAULTS.fireWardenPerPersonYr; } );
			root.querySelectorAll( '[data-occv2-admin-pct]' ).forEach( ( el ) => { el.value = DEFAULTS.adminPctOfHours * 100; } );
			root.querySelectorAll( '[data-occv2-admin-rate]' ).forEach( ( el ) => { el.value = DEFAULTS.adminLoadedHourly; } );
			root.querySelectorAll( '[data-occv2-legals]' ).forEach( ( el ) => { el.value = DEFAULTS.leaseLegalsOneOff; } );
			root.querySelectorAll( '[data-occv2-lease-yrs]' ).forEach( ( el ) => { el.value = DEFAULTS.leaseTermYears; } );
			root.querySelectorAll( '[data-occv2-furniture-pp]' ).forEach( ( el ) => { el.value = DEFAULTS.furniturePerPerson; } );
			root.querySelectorAll( '[data-occv2-furniture-yrs]' ).forEach( ( el ) => { el.value = DEFAULTS.furnitureAmortYrs; } );
			root.querySelectorAll( '[data-occv2-booking-cost]' ).forEach( ( el ) => { el.value = DEFAULTS.bookingSoftwareCost; } );
			root.querySelectorAll( '[data-occv2-booking-toggle]' ).forEach( ( el ) => { el.checked = DEFAULTS.bookingSoftware; } );
			root.querySelectorAll( '[data-occv2-precinct]' ).forEach( ( el ) => { el.value = DEFAULTS.precinct; } );
			// Grade radios.
			root.querySelectorAll( '[data-occv2-grade]' ).forEach( ( btn ) => {
				btn.setAttribute( 'aria-checked', btn.getAttribute( 'data-occv2-grade' ) === DEFAULTS.grade ? 'true' : 'false' );
			} );
			// Team slider to 0.
			root.querySelectorAll( '[data-occv2-team-size]' ).forEach( ( el ) => { el.value = '0'; } );
			// Custom rows back to one empty row.
			const customList = root.querySelector( '[data-occv2-custom-rows]' );
			if ( customList ) customList.innerHTML = '';
			renderCustomList( root, afterInput );
			// Clear scenarios from localStorage.
			writeScenarios( [ null, null, null ] );
			renderScenarioRow( scope );
			// Sync booking visibility + roster + retick.
			syncBookingVisibility( root );
			rebuildRoster( root, 0, afterInput );
			if ( onReset ) onReset();
			afterInput();
		} );
	}
}

// --- Grade → rent display sync (visible box shows the adjusted rate; the
// --- hidden [data-occv2-rent-sqm] holds the base the engine reads). ---
function bindGradeRent( root, gradeRadios, afterInput ) {
	const baseEl = root.querySelector( '[data-occv2-rent-sqm]' );
	const dispEl = root.querySelector( '[data-oc-rent-display]' );
	const label = root.querySelector( '[data-oc-grade-label]' );
	if ( ! baseEl || ! dispEl ) return;

	const gradeName = () => {
		const g = root.querySelector( '[data-occv2-grade][aria-checked="true"]' );
		return g ? g.getAttribute( 'data-occv2-grade' ) : DEFAULTS.grade;
	};
	const mod = () => GRADE_MODIFIER[ gradeName() ] || 1;

	const refreshDisplay = () => {
		dispEl.value = Math.round( ( parseFloat( baseEl.value ) || 0 ) * mod() );
		if ( label ) label.textContent = gradeName();
	};

	dispEl.addEventListener( 'input', () => {
		const eff = parseFloat( dispEl.value ) || 0;
		baseEl.value = Math.round( eff / ( mod() || 1 ) );
		if ( label ) label.textContent = gradeName();
		if ( afterInput ) afterInput();
	} );

	// Grade radio select refreshes the display box.
	gradeRadios.forEach( ( radio ) => {
		radio.addEventListener( 'click', () => { refreshDisplay(); if ( afterInput ) afterInput(); } );
	} );

	refreshDisplay();
}

// --- Init ---
function initCalc( root ) {
	if ( ! root ) return;
	const scope = root.parentElement;
	const prices = getPrices();

	// ── Tick pipeline — defined before any render so roster/custom/URL all
	// ── wire through the same retick once they exist in the DOM. ──
	const states = { team: currentTeam( root ) };

	function syncTeam( n ) {
		states.team = n;
		root.querySelectorAll( '[data-occv2-team-size]' ).forEach( ( el ) => { el.value = String( n ); } );
		rebuildRoster( root, n, retick );
		retick();
	}
	function retick() {
		const state = readState( root );
		states.team = state.teamSize;
		renderResults( scope, root, state, compute( state ), prices );
		writeURL( root );
	}

	// ── Initial DOM renders (rows need live listeners, so after retick) ──
	renderCustomList( root, retick );
	rebuildRoster( root, states.team, retick );

	// ── URL → DOM before the first tick ──
	function applyURL() {
		const params = new URLSearchParams( window.location.search );
		URL_FIELDS.forEach( ( [ attr, key ] ) => {
			if ( ! params.has( key ) ) return;
			if ( attr === 'data-occv2-team-size' ) return; // handled below
			const el = root.querySelector( '[' + attr + ']' );
			if ( el ) {
				if ( attr === 'data-occv2-outgoings-pct' || attr === 'data-occv2-admin-pct' ) {
					el.value = String( parseFloat( params.get( key ) ) * 100 );
				} else {
					el.value = params.get( key );
				}
			}
		} );
		// Team — clamp into the block range; keep 0 shareable (empty card).
		if ( params.has( 'team' ) ) {
			const team = clamp( parseInt( params.get( 'team' ), 10 ) || 0, 0, M.TEAM_MAX );
			root.querySelectorAll( '[data-occv2-team-size]' ).forEach( ( el ) => { el.value = String( team ); } );
			rebuildRoster( root, team, retick );
			states.team = team;
		}
		// Days — per-member list, applied after the roster exists.
		const days = readDaysFromURL();
		if ( days && days.length ) {
			root.querySelectorAll( '[data-oc-day-slider]' ).forEach( ( sl, i ) => {
				if ( days[ i ] ) sl.value = String( days[ i ] );
			} );
			root.querySelectorAll( '[data-oc-day-slider]' ).forEach( ( sl ) => {
				paintSlider( sl );
				const box = sl.closest( '.office-costs__roster-row' );
				const out = box && box.querySelector( 'output' );
				if ( out ) out.textContent = sl.value + ( sl.value === '1' ? ' day' : ' days' );
			} );
		}
		// Grade radios.
		if ( params.has( 'grade' ) ) {
			root.querySelectorAll( '[data-occv2-grade]' ).forEach( ( btn ) => {
				btn.setAttribute( 'aria-checked', btn.getAttribute( 'data-occv2-grade' ) === params.get( 'grade' ) ? 'true' : 'false' );
			} );
		}
		// Booking toggle.
		if ( params.has( 'bt' ) ) {
			root.querySelectorAll( '[data-occv2-booking-toggle]' ).forEach( ( el ) => { el.checked = true; } );
		}
		syncBookingVisibility( root );
		// Custom rows — grow to the URL count, fill, then re-render so the new
		// rows carry live listeners (values survive the rebuild).
		let maxIdx = -1;
		params.forEach( ( _v, k ) => {
			const m = k.match( /^c(\d+)[lv]$/ );
			if ( m ) maxIdx = Math.max( maxIdx, parseInt( m[ 1 ], 10 ) );
		} );
		if ( maxIdx >= 0 ) {
			while ( root.querySelectorAll( '[data-occv2-custom-row]' ).length <= maxIdx ) {
				const li = document.createElement( 'li' );
				li.className = 'office-costs__custom-row';
				li.innerHTML = '<input type="text" class="calc__input office-costs__custom-label" data-occv2-custom-label aria-label="Custom expense name" placeholder="e.g. Reception">'
					+ '<input type="number" class="calc__input office-costs__custom-value" data-occv2-custom-value aria-label="Custom expense value in dollars per year" placeholder="$ value/yr" min="0" step="100">'
					+ '<button type="button" class="office-costs__custom-remove | calc__day-row-remove" aria-label="Remove this line">×</button>';
				root.querySelector( '[data-occv2-custom-rows]' ).appendChild( li );
			}
			const rows = root.querySelectorAll( '[data-occv2-custom-row]' );
			rows.forEach( ( row, i ) => {
				const lbl = row.querySelector( '[data-occv2-custom-label]' );
				const val = row.querySelector( '[data-occv2-custom-value]' );
				if ( lbl && params.has( 'c' + i + 'l' ) ) lbl.value = params.get( 'c' + i + 'l' );
				if ( val && params.has( 'c' + i + 'v' ) ) val.value = params.get( 'c' + i + 'v' );
			} );
			renderCustomList( root, retick );
		}
	}

	// ── Team stepper (value-based, mirrors C1/C5/C6) ──
	const stepper = bindStepper( root, {
		rangeSel: '[data-oc-team-range]',
		sliderSel: '[data-oc-team-slider]',
		outSel: '[data-oc-team-out]',
		decSel: '[data-oc-team-dec]',
		incSel: '[data-oc-team-inc]',
		max: M.TEAM_MAX,
		valueFor: ( i ) => i,
		current: () => states.team,
		onUpdate: syncTeam,
	} );

	// ── Grade radios (WAI-ARIA roving) ──
	const gradeRadios = Array.from( root.querySelectorAll( '[data-oc-grade-group] [data-occv2-grade]' ) );
	function setGradeChecked( radio ) {
		gradeRadios.forEach( ( r ) => r.setAttribute( 'aria-checked', 'false' ) );
		if ( radio ) radio.setAttribute( 'aria-checked', 'true' );
	}
	function selectGrade( radio ) {
		if ( ! radio ) return;
		setGradeChecked( radio );
		retick();
	}
	gradeRadios.forEach( ( radio ) => {
		radio.addEventListener( 'click', () => selectGrade( radio ) );
	} );
	bindRovingRadio( gradeRadios, selectGrade );

	// ── Booking toggle visibility ──
	const bookingToggle = root.querySelector( '[data-occv2-booking-toggle]' );
	if ( bookingToggle ) {
		bookingToggle.addEventListener( 'change', () => { syncBookingVisibility( root ); retick(); } );
		// The whole option card is the click target (the checkbox is
		// visually-hidden with pointer-events:none).
		const bookingCard = root.querySelector( '[data-oc-booking-card]' );
		if ( bookingCard ) {
			bookingCard.addEventListener( 'click', ( e ) => {
				if ( e.target.closest( 'label' ) ) return;
				if ( e.target.closest( 'input' ) ) return;
				bookingToggle.checked = ! bookingToggle.checked;
				syncBookingVisibility( root );
				retick();
			} );
		}
		const bookingLabel = bookingToggle.closest( 'label' );
		if ( bookingLabel ) {
			bookingLabel.addEventListener( 'click', ( e ) => {
				e.preventDefault();
				bookingToggle.checked = ! bookingToggle.checked;
				syncBookingVisibility( root );
				retick();
			} );
		}
	}

	// ── URL → DOM before the grade→rent display bind (so the visible rent box
	// ── paints the URL-applied base × modifier) ──
	applyURL();
	stepper.paintCurrent();
	root.querySelectorAll( '[data-oc-day-slider]' ).forEach( paintSlider );

	// ── Grade→rent display sync + native input/change retick ──
	bindGradeRent( root, gradeRadios, retick );
	root.addEventListener( 'input', retick );
	root.addEventListener( 'change', retick );

	// ── Share row (lives outside the calc root, in the wrapper) ──
	initCalcShare( scope, {
		slug: 'office-costs',
		getState: () => {
			const s = readState( root );
			return {
				team: s.teamSize,
				days: s.perPersonDays.slice( 0, s.teamSize ),
				precinct: s.precinct,
				grade: s.grade,
				sqmPerPerson: s.sqmPerPerson,
				rentPerSqmPerYr: s.rentPerSqmPerYr,
				outgoingsPctOfRent: s.outgoingsPctOfRent,
				internetPerMo: s.internetPerMo,
				powerWattsPerSqm: s.powerWattsPerSqm,
				powerHoursPerYear: s.powerHoursPerYear,
				powerPricePerKwh: s.powerPricePerKwh,
				cleaningHoursPerSqmYr: s.cleaningHoursPerSqmYr,
				cleaningPerHour: s.cleaningPerHour,
				kbPerPersonPerYr: s.kbPerPersonPerYr,
				insurancePerPersonPerYr: s.insurancePerPersonPerYr,
				firstAidPerPersonPerYr: s.firstAidPerPersonPerYr,
				fireWardenPerPersonPerYr: s.fireWardenPerPersonPerYr,
				adminPctOfHours: s.adminPctOfHours,
				adminLoadedHourly: s.adminLoadedHourly,
				leaseLegalsOneOff: s.leaseLegalsOneOff,
				leaseTermYears: s.leaseTermYears,
				furniturePerPerson: s.furniturePerPerson,
				furnitureAmortYrs: s.furnitureAmortYrs,
				bookingSoftware: s.bookingSoftware,
				bookingSoftwareCost: s.bookingSoftwareCost,
				customLines: s.customLines,
			};
		},
	} );

	// ── Breakdown + tooltips + scenarios (after retick exists) ──
	bindBreakdownTrigger( root, 'methodology' );
	bindSourceTooltips( scope );

	const customAdd = root.querySelector( '[data-occv2-custom-add]' );
	if ( customAdd ) {
		customAdd.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			const list = root.querySelector( '[data-occv2-custom-rows]' );
			if ( list ) {
				const row = document.createElement( 'li' );
				row.className = 'office-costs__custom-row';
				row.setAttribute( 'data-occv2-custom-row', '' );
				row.innerHTML = '<input type="text" class="calc__input office-costs__custom-label" data-occv2-custom-label aria-label="Custom expense name" placeholder="e.g. Reception">'
					+ '<input type="number" class="calc__input office-costs__custom-value" data-occv2-custom-value aria-label="Custom expense value in dollars per year" placeholder="$ value/yr" min="0" step="100">'
					+ '<button type="button" class="office-costs__custom-remove | calc__day-row-remove" aria-label="Remove this line">×</button>';
				list.appendChild( row );
			}
			renderCustomList( root, retick );
		} );
	}
	// Custom rows carry their own input/change listeners for label/value edits;
	// a full re-render after adding/removing is handled by renderCustomList.

	bindScenarios( scope, root, retick, () => {
		states.team = 0;
		stepper.paintCurrent();
	} );

	retick();
}

export function initOfficeCosts() {
	document.querySelectorAll( '[data-js="calc-office-costs-v2"]' ).forEach( initCalc );
}