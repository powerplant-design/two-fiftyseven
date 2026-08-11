/**
 * 257 Workspace Pricing Calculator — engine
 * ----------------------------------------------------------------------------
 * Compares running a private central-Wellington office against being at 257
 * for a 1–15 person team. Every team member picks their own membership tier
 * (Dedicated or Flexi 5…1); the total is the sum of their rates.
 *
 * Membership prices + the annual prepay discount read from
 * window.twofiftyseven.prices / .annualDiscountPct (the ACF Options SSOT).
 *
 * Private-office methodology constants (rent/sqm, opex %, power W/m², MHFR,
 * admin load, etc.) and the comparison benchmarks ($14,200/person/yr private
 * office, $450–650 / $700–830 peer coworking) stay in code — they're cited
 * NZ methodology, not admin-editable.
 *
 * Markup contract: root has [data-js="calc-office-costs"].
 * Inputs:  [data-calc-team-*] stepper, [data-calc-commitment] radios (1/3/5),
 *          [data-calc-annual] checkbox, [data-calc-roster] member selects
 * Outputs: [data-calc-*-total], [data-result-*] comparison panel + chart,
 *          [data-calc-private-lines], [data-calc-ours-lines],
 *          [data-calc-bridge-figure]
 * ============================================================================
 */

import { initCalcShare } from './calc-share.js';

// --- Cited methodology + benchmarks (stay in code) ---
const M = {
	RENT_PER_SQM_YR:       420,
	SQM_PER_PERSON:        10,
	OPEX_PCT:              0.27,
	FURNITURE_PER_PERSON:  2000,
	INTERNET_YR:           2400,
	POWER_W_PER_SQM:       50,
	POWER_HRS_DAY:         8,
	POWER_DAYS_YR:         230,
	POWER_NZD_PER_KWH:     0.30,
	CLEANING_HR_NZD:       45,
	CLEANING_HRS_PER_M_YR: 1.2,
	CONSUMABLES_YR:        300,
	INSURANCE_YR:          200,
	MHFR_PER_PERSON:       445,
	MHFR_RATIO:            12,
	MHFR_CERT_YEARS:       2.5,
	ADMIN_PCT_OF_HOURS:    0.06,
	ADMIN_HR_LOADED:       70,
	LEGAL_ONE_OFF:         3500,
	BOOKING_SW_MO:         75,
	BOOKING_SW_THRESHOLD:  10,
	HOURS_PER_DAY:         8,
	WEEKS_PER_YEAR:        46,
	TEAM_MAX:              15,
	// Comparison benchmarks (pricing coordinator · verified NZ 2026)
	PRIVATE_OFFICE_PER_PERSON_YR: 14200,
	COMPETITOR_FLEXI_LOW_MO:      450,
	COMPETITOR_FLEXI_HIGH_MO:     650,
	COMPETITOR_DEDICATED_LOW_MO:  700,
	COMPETITOR_DEDICATED_HIGH_MO: 830,
	GIVING_HOURS_DAY:             8,
	GIVING_WEEKS:                 46,
	GIVING_RATE:                  1,
};

// --- Sources (citation URLs, stay in code) ---
const SOURCES = {
	rent:        { label: 'Wellington B-grade rent', name: 'Colliers · Bayleys 2026', url: 'https://www.colliers.co.nz/en-nz/countries/new-zealand/cities/wellington/office-leasing' },
	opex:        { label: 'Operating expenses', name: 'Property Council NZ', url: 'https://www.propertynz.co.nz/' },
	space:       { label: '10 m² per person', name: 'Government Property Group · British Council for Offices', url: 'https://www.gpg.govt.nz/workplace-design/' },
	furniture:   { label: 'Bring-in furniture', name: 'NZ commercial market mid', url: 'https://commercialtraders.co.nz/' },
	internet:    { label: 'Business fibre', name: '2degrees Business', url: 'https://www.2degrees.nz/business/broadband/plans' },
	power:       { label: 'Office power', name: 'BRANZ · EECA', url: 'https://www.eeca.govt.nz/co-funding-and-support/products/commercial-buildings-decarbonisation-pathway/' },
	cleaning:    { label: 'Cleaning rate', name: 'Clean Planet Wellington', url: 'https://www.cleanplanetwellington.co.nz/commercial-cleaning-prices-wellington-2026' },
	consumables: { label: 'Kitchen + bathroom', name: 'Bottom-up calc · NZ supplier benchmarks' },
	insurance:   { label: 'Insurance', name: 'Insurance Council of NZ', url: 'https://www.icnz.org.nz/individuals/commercial/' },
	mhfr:        { label: 'MHFR training', name: 'Stepping Stone Trust · CoLiberate', url: 'https://stepstone.org.nz/education/mhfaaotearoa/' },
	admin:       { label: 'Admin time + rate', name: 'Hays · Robert Walters · PayScale 2026 + 40% on-cost', url: 'https://www.payscale.com/research/NZ/Job=Office_Administrator/Hourly_Rate' },
	legal:       { label: 'Lease legal setup', name: 'LawyerFinder NZ 2026', url: 'https://lawyerfinder.co.nz/resources/costs/lawyer-fees/' },
	booking:     { label: 'Booking software', name: 'Skedda · Officely 2026', url: 'https://www.skedda.com/' },
};

const TIER_LABELS = {
	rent:        'Rent',
	opex:        'Outgoings',
	furniture:   'Furniture (amortised)',
	internet:    'Internet',
	power:       'Power',
	cleaning:    'Cleaning',
	consumables: 'Kitchen + bathroom',
	insurance:   'Insurance',
	mhfr:        'MHFR training',
	admin:       'Admin time',
	legal:       'Lease legals (amortised)',
	booking:     'Booking software',
};

// --- Formatters ---
function fmt$( n ) {
	return new Intl.NumberFormat( 'en-NZ', {
		style: 'currency',
		currency: 'NZD',
		maximumFractionDigits: 0,
	} ).format( Math.round( n ) );
}

function fmtN( n ) {
	return new Intl.NumberFormat( 'en-NZ' ).format( Math.round( n ) );
}

function round100( n ) {
	return Math.round( n / 100 ) * 100;
}

// --- Read SSOT ---
function getPrices() {
	const p = ( window.twofiftyseven && window.twofiftyseven.prices ) || {};
	const mk = ( key, fallback ) => ( p[ key ] && p[ key ].price ) || fallback;
	return {
		dedicated:  mk( 'dedicated', 659 ),
		flexi5:     mk( 'flexi-5', 509 ),
		flexi4:     mk( 'flexi-4', 409 ),
		flexi3:     mk( 'flexi-3', 309 ),
		flexi2:     mk( 'flexi-2', 209 ),
		flexi1:     mk( 'flexi-1', 109 ),
		labels:     p,
	};
}

function getAnnualDiscount() {
	const v = window.twofiftyseven && window.twofiftyseven.annualDiscountPct;
	return typeof v === 'number' && v > 0 ? v / 100 : 0.10;
}

const FLEXI_PRICE = { 1: 'flexi1', 2: 'flexi2', 3: 'flexi3', 4: 'flexi4', 5: 'flexi5' };

// --- Compute ---
function compute( state, prices, annualDiscount ) {
	const t = state.team;
	const c = state.commitment;
	const sqm = t * M.SQM_PER_PERSON;

	if ( ! t || t <= 0 ) {
		return {
			privateLines: [], privateTotalYr: 0,
			oursLines: [],    oursTotalYr: 0,
			annualSaving: 0, commitmentSaving: 0, capitalTiedUp: 0,
			teamHoursAt257: 0, givingDollars: 0,
		};
	}

	const rent        = sqm * M.RENT_PER_SQM_YR;
	const opex        = rent * M.OPEX_PCT;
	const furniture   = ( t * M.FURNITURE_PER_PERSON ) / c;
	const internet    = M.INTERNET_YR;
	const power       = ( ( M.POWER_W_PER_SQM * M.POWER_HRS_DAY * M.POWER_DAYS_YR * sqm ) / 1000 ) * M.POWER_NZD_PER_KWH;
	const cleaning    = M.CLEANING_HR_NZD * M.CLEANING_HRS_PER_M_YR * sqm;
	const consumables = t * M.CONSUMABLES_YR;
	const insurance   = t * M.INSURANCE_YR;
	const mhfrCount   = Math.ceil( t / M.MHFR_RATIO );
	const mhfr        = ( mhfrCount * M.MHFR_PER_PERSON ) / M.MHFR_CERT_YEARS;
	const teamHours   = t * M.HOURS_PER_DAY * M.WEEKS_PER_YEAR * 5;
	const admin       = teamHours * M.ADMIN_PCT_OF_HOURS * M.ADMIN_HR_LOADED;
	const legal       = M.LEGAL_ONE_OFF / c;
	const booking     = ( t >= M.BOOKING_SW_THRESHOLD ) ? ( M.BOOKING_SW_MO * 12 ) : 0;

	const privateLines = [
		{ key: 'rent', amount: rent, source: 'rent' },
		{ key: 'opex', amount: opex, source: 'opex' },
		{ key: 'furniture', amount: furniture, source: 'furniture', note: `amortised over ${ c } yr` },
		{ key: 'internet', amount: internet, source: 'internet' },
		{ key: 'power', amount: power, source: 'power' },
		{ key: 'cleaning', amount: cleaning, source: 'cleaning' },
		{ key: 'consumables', amount: consumables, source: 'consumables' },
		{ key: 'insurance', amount: insurance, source: 'insurance' },
		{ key: 'mhfr', amount: mhfr, source: 'mhfr', note: `${ mhfrCount } trained @ ${ M.MHFR_RATIO }:1, amortised` },
		{ key: 'admin', amount: admin, source: 'admin', note: `${ ( M.ADMIN_PCT_OF_HOURS * 100 ).toFixed( 0 ) }% of team hours @ $${ M.ADMIN_HR_LOADED }/hr loaded` },
		{ key: 'legal', amount: legal, source: 'legal', note: `lease setup amortised over ${ c } yr` },
	];
	if ( booking ) privateLines.push( { key: 'booking', amount: booking, source: 'booking' } );
	const privateTotalYr = privateLines.reduce( ( s, l ) => s + l.amount, 0 );

	let oursTotalYr = 0;
	const oursLines = [];
	for ( const m of state.members ) {
		if ( ! m.tier ) continue;
		if ( m.tier === 'dedicated' ) {
			const month = state.annualDiscount ? prices.dedicated * ( 1 - annualDiscount ) : prices.dedicated;
			oursTotalYr += month * 12;
			oursLines.push( { tier: 'Dedicated', monthly: month, annual: month * 12 } );
		} else if ( m.tier.indexOf( 'flexi' ) === 0 ) {
			const days = parseInt( m.tier.split( '-' )[ 1 ], 10 );
			const month = prices[ FLEXI_PRICE[ days ] ];
			oursTotalYr += month * 12;
			oursLines.push( { tier: `Flexi ${ days }`, monthly: month, annual: month * 12 } );
		}
	}

	const annualSaving     = privateTotalYr - oursTotalYr;
	const commitmentSaving = annualSaving * c;
	const capitalTiedUp    = ( t * M.FURNITURE_PER_PERSON ) + M.LEGAL_ONE_OFF;

	const teamHoursAt257 = t * M.HOURS_PER_DAY * M.WEEKS_PER_YEAR * 5;

	// Giving dollars — hours in the space (Dedicated counts 5 days) valued at $1/hr.
	const tierTiers = state.members.map( ( m ) => m.tier ).filter( Boolean );
	let givingHours = 0;
	for ( const tier of tierTiers ) {
		const days = tier === 'dedicated' ? 5 : ( parseInt( tier.split( '-' )[ 1 ], 10 ) || 0 );
		givingHours += days * M.GIVING_HOURS_DAY * M.GIVING_WEEKS;
	}
	const givingDollars = givingHours * M.GIVING_RATE;

	return {
		privateLines, privateTotalYr,
		oursLines,    oursTotalYr,
		annualSaving, commitmentSaving, capitalTiedUp,
		teamHoursAt257, givingDollars,
	};
}

// --- Comparison (proposal chart mirrors the pricing coordinator's private
// --- methodology, so the 1/3/5yr commitment reshapes the private-office cost
// --- and therefore the savings) ---
function computeComparison( computed, tierTiers ) {
	const teamSize = tierTiers.length;
	const privateAnnual = computed.privateTotalYr || ( teamSize * M.PRIVATE_OFFICE_PER_PERSON_YR );

	let cwLow = 0;
	let cwHigh = 0;
	for ( const tier of tierTiers ) {
		if ( tier === 'dedicated' ) {
			cwLow  += M.COMPETITOR_DEDICATED_LOW_MO * 12;
			cwHigh += M.COMPETITOR_DEDICATED_HIGH_MO * 12;
		} else {
			cwLow  += M.COMPETITOR_FLEXI_LOW_MO * 12;
			cwHigh += M.COMPETITOR_FLEXI_HIGH_MO * 12;
		}
	}

	const oursAnnual = computed.oursTotalYr;

	return {
		privateAnnual,
		cwLow, cwHigh,
		oursAnnual,
		savePrivate: Math.max( 0, privateAnnual - oursAnnual ),
		saveCwLow:   Math.max( 0, cwLow - oursAnnual ),
		saveCwHigh:  Math.max( 0, cwHigh - oursAnnual ),
		pctPrivate:  100,
		pctCwLow:    privateAnnual > 0 ? Math.max( 0, Math.min( 100, ( cwLow / privateAnnual ) * 100 ) ) : 0,
		pctCwHigh:   privateAnnual > 0 ? Math.max( 0, Math.min( 100, ( cwHigh / privateAnnual ) * 100 ) ) : 0,
		pctOurs:     privateAnnual > 0 ? Math.max( 0, Math.min( 100, ( oursAnnual / privateAnnual ) * 100 ) ) : 0,
	};
}

// --- State <-> URL ---
function readURL() {
	const params = new URLSearchParams( window.location.search );
	const state = {
		team:         parseInt( params.get( 'team' ) || '0', 10 ),
		commitment:   parseInt( params.get( 'commitment' ) || '1', 10 ),
		annualDiscount: params.get( 'annual' ) === 'true',
		members:      [],
	};
	state.team = Math.max( 0, Math.min( M.TEAM_MAX, state.team ) );
	if ( [ 1, 3, 5 ].indexOf( state.commitment ) === -1 ) state.commitment = 1;

	const desks = params.get( 'desks' );
	if ( desks && desks.length === state.team ) {
		for ( const ch of desks ) {
			if ( ch === 'd' ) state.members.push( { tier: 'dedicated' } );
			else if ( /[1-5]/.test( ch ) ) state.members.push( { tier: `flexi-${ ch }` } );
			else state.members.push( { tier: 'flexi-1' } );
		}
	} else if ( state.team > 0 ) {
		state.members = Array.from( { length: state.team }, () => ( { tier: 'flexi-1' } ) );
	}
	return state;
}

function writeURL( state ) {
	const params = new URLSearchParams( window.location.search );
	params.set( 'team', state.team );
	params.set( 'commitment', state.commitment );
	params.set( 'annual', state.annualDiscount ? 'true' : 'false' );
	const desks = state.members.map( ( m ) => {
		if ( ! m.tier ) return 'x';
		if ( m.tier === 'dedicated' ) return 'd';
		return m.tier.split( '-' )[ 1 ];
	} ).join( '' );
	params.set( 'desks', desks );
	const newURL = `${ window.location.pathname }?${ params.toString() }${ window.location.hash }`;
	window.history.replaceState( {}, '', newURL );
}

// --- Render ---
function updateAnnualWrap( root, state ) {
	const wrap = root.querySelector( '[data-calc-annual-wrap]' );
	if ( ! wrap ) return;
	const hasDedicated = state.members.some( ( m ) => m.tier && m.tier === 'dedicated' );
	wrap.hidden = ! hasDedicated;
	if ( ! hasDedicated ) {
		const input = root.querySelector( '[data-calc-annual]' );
		if ( input ) input.checked = false;
		state.annualDiscount = false;
	}
}

function renderRoster( root, state, prices ) {
	const list = root.querySelector( '[data-calc-roster]' );
	if ( ! list ) return;
	list.innerHTML = '';

	const order = [ 'dedicated', 'flexi-5', 'flexi-4', 'flexi-3', 'flexi-2', 'flexi-1' ];
	const options = order.map( ( slug ) => {
		const info = prices.labels[ slug ] || { name: slug, price: null };
		const price = info.price !== null ? ` · $${ info.price }/mo` : '';
		return `<option value="${ slug }">${ info.name }${ price }</option>`;
	} ).join( '' );

	for ( let i = 0; i < state.team; i++ ) {
		const row = document.createElement( 'li' );
		row.className = 'calc__roster-row';
		row.innerHTML = `
			<span class="calc__roster-label | text-l font-bold">Member ${ i + 1 }</span>
			<select class="calc__roster-select" aria-label="Member ${ i + 1 } membership type" data-calc-member="${ i }">
				${ options }
			</select>
		`;
		const tier = state.members[ i ] && state.members[ i ].tier;
		if ( tier ) row.querySelector( 'select' ).value = tier;

		// Keyboard: open the dropdown on Enter/Space — native behaviour varies
		// by browser (Safari won't open on Space/Enter), so normalise it.
		const select = row.querySelector( 'select' );
		select.addEventListener( 'keydown', ( e ) => {
			if ( ( e.key === 'Enter' || e.key === ' ' ) && e.target === select ) {
				if ( select.showPicker ) {
					try {
						select.showPicker();
						e.preventDefault();
					} catch { /* picker already open or unavailable — let the browser handle it */ }
				}
			}
		}, { capture: true } );

		list.appendChild( row );
	}

	updateAnnualWrap( root, state );
}

function renderSourceRow( line ) {
	const source = SOURCES[ line.source ] || { label: line.source, name: '' };
	return `
		<div class="compare__row">
			<div class="compare__row-label">
				<span class="calc-source">
					<span>${ TIER_LABELS[ line.key ] }${ line.note ? ` <span class="text-s">&middot; ${ line.note }</span>` : '' }</span>
					<button class="calc-source__trigger" type="button" aria-label="${ source.label } source">i</button>
					<span class="calc-source__pop" role="tooltip">
						<span class="calc-source__pop-label">${ source.label }</span>
						${ source.url
							? `<a href="${ source.url }" target="_blank" rel="noopener">${ source.name } &#8599;</a>`
							: source.name }
					</span>
				</span>
			</div>
			<div class="compare__row-value">${ fmt$( round100( line.amount ) ) }</div>
		</div>
	`;
}

function renderResults( root, state, computed, prices, annualDiscount ) {
	root.querySelectorAll( '[data-calc-private-lines]' ).forEach( ( el ) => {
		el.innerHTML = computed.privateLines.map( renderSourceRow ).join( '' );
	} );
	root.querySelectorAll( '[data-calc-private-total]' ).forEach( ( el ) => {
		el.textContent = fmt$( round100( computed.privateTotalYr ) );
	} );
	root.querySelectorAll( '[data-calc-ours-lines]' ).forEach( ( el ) => {
		el.innerHTML = computed.oursLines.map( ( l ) => `
			<div class="compare__row">
				<div class="compare__row-label">${ l.tier }</div>
				<div class="compare__row-value">${ fmt$( l.annual ) }/yr</div>
			</div>
		` ).join( '' );
	} );
	root.querySelectorAll( '[data-calc-ours-total]' ).forEach( ( el ) => {
		el.textContent = fmt$( round100( computed.oursTotalYr ) );
	} );

	root.querySelectorAll( '[data-calc-saving-figure]' ).forEach( ( el ) => {
		el.textContent = fmt$( round100( computed.commitmentSaving ) );
	} );
	root.querySelectorAll( '[data-calc-saving-annual]' ).forEach( ( el ) => {
		el.textContent = fmt$( round100( computed.annualSaving ) );
	} );
	root.querySelectorAll( '[data-calc-saving-capital]' ).forEach( ( el ) => {
		el.textContent = fmt$( round100( computed.capitalTiedUp ) );
	} );
	root.querySelectorAll( '[data-calc-period]' ).forEach( ( el ) => {
		el.textContent = `${ state.commitment } year${ state.commitment === 1 ? '' : 's' }`;
	} );
	// Exact monthly team total — the real product price, not round100.
	root.querySelectorAll( '[data-calc-mini-total]' ).forEach( ( el ) => {
		el.textContent = fmt$( Math.round( computed.oursTotalYr / 12 ) );
	} );

	// Dedicated annual-prepay discount — per-member $/yr saved (only shown when
	// the checkbox is ticked).
	root.querySelectorAll( '[data-calc-dedicated-save]' ).forEach( ( el ) => {
		if ( state.annualDiscount ) {
			const dedicatedCount = state.members.filter( ( m ) => m.tier === 'dedicated' ).length;
			const saved = Math.round( prices.dedicated * annualDiscount * dedicatedCount * 12 );
			el.textContent = saved > 0 ? `-${ fmt$( saved ) }/yr` : fmt$( 0 );
		} else {
			el.textContent = fmt$( 0 );
		}
	} );

	// Comparison panel + chart
	const tiers = state.members.map( ( m ) => m.tier ).filter( Boolean );
	const cmp = computeComparison( computed, tiers );
	const empty = state.team <= 0;
	root.querySelectorAll( '[data-result-headline]' ).forEach( ( el ) => { el.hidden = empty; } );
	root.querySelectorAll( '[data-result-empty]' ).forEach( ( el ) => { el.hidden = ! empty; } );
	root.querySelectorAll( '[data-result-compare]' ).forEach( ( el ) => { el.hidden = empty; } );

	root.querySelectorAll( '[data-result-ours-annual]' ).forEach( ( el ) => {
		el.textContent = fmt$( cmp.oursAnnual );
	} );
	root.querySelectorAll( '[data-result-ours-monthly]' ).forEach( ( el ) => {
		el.textContent = fmt$( Math.round( cmp.oursAnnual / 12 ) );
	} );
	root.querySelectorAll( '[data-result-team-size]' ).forEach( ( el ) => {
		el.textContent = String( state.team );
	} );
	root.querySelectorAll( '[data-result-team-suffix]' ).forEach( ( el ) => {
		el.textContent = state.team === 1 ? 'member' : 'members';
	} );
	root.querySelectorAll( '[data-result-private]' ).forEach( ( el ) => {
		el.textContent = fmt$( cmp.privateAnnual );
	} );
	root.querySelectorAll( '[data-result-other-coworking-low]' ).forEach( ( el ) => {
		el.textContent = fmt$( cmp.cwLow );
	} );
	root.querySelectorAll( '[data-result-other-coworking-high]' ).forEach( ( el ) => {
		el.textContent = fmt$( cmp.cwHigh );
	} );
	root.querySelectorAll( '[data-result-save-private]' ).forEach( ( el ) => {
		el.textContent = fmt$( cmp.savePrivate );
	} );
	root.querySelectorAll( '[data-result-save-coworking-low]' ).forEach( ( el ) => {
		el.textContent = fmt$( cmp.saveCwLow );
	} );
	root.querySelectorAll( '[data-result-save-coworking-high]' ).forEach( ( el ) => {
		el.textContent = fmt$( cmp.saveCwHigh );
	} );
	root.querySelectorAll( '[data-result-giving]' ).forEach( ( el ) => {
		el.textContent = fmt$( computed.givingDollars );
	} );

	root.querySelectorAll( '.workspace-pricing__bar--private' ).forEach( ( bar ) => {
		bar.style.setProperty( '--bar-pct', cmp.pctPrivate + '%' );
	} );
	root.querySelectorAll( '.workspace-pricing__bar--coworking-low' ).forEach( ( bar ) => {
		bar.style.setProperty( '--bar-pct', cmp.pctCwLow + '%' );
	} );
	root.querySelectorAll( '.workspace-pricing__bar--coworking-high' ).forEach( ( bar ) => {
		bar.style.setProperty( '--bar-pct', cmp.pctCwHigh + '%' );
	} );
	root.querySelectorAll( '.workspace-pricing__bar--ours' ).forEach( ( bar ) => {
		bar.style.setProperty( '--bar-pct', cmp.pctOurs + '%' );
	} );

	// Kaupapa bridge figure — scoped to the root here (unlike the source's
	// document-wide query) so the block stays self-contained.
	root.querySelectorAll( '[data-calc-bridge-figure]' ).forEach( ( el ) => {
		el.textContent = fmt$( round100( computed.givingDollars ) );
	} );
	root.querySelectorAll( '[data-calc-bridge-hours]' ).forEach( ( el ) => {
		el.textContent = fmtN( computed.teamHoursAt257 );
	} );
}

// --- Tooltip handling ---
function bindSourceTooltips( root ) {
	root.addEventListener( 'click', ( e ) => {
		const trigger = e.target.closest( '.calc-source__trigger' );
		if ( trigger ) {
			const wrap = trigger.closest( '.calc-source' );
			const isOpen = wrap.dataset.open === 'true';
			root.querySelectorAll( '.calc-source[data-open="true"]' ).forEach( ( el ) => { el.dataset.open = 'false'; } );
			wrap.dataset.open = isOpen ? 'false' : 'true';
			e.stopPropagation();
		} else {
			root.querySelectorAll( '.calc-source[data-open="true"]' ).forEach( ( el ) => { el.dataset.open = 'false'; } );
		}
	} );
}

// --- Events ---
function bindEvents( root, state, prices, annualDiscount ) {
	function rerender() {
		const computed = compute( state, prices, annualDiscount );
		renderResults( root, state, computed, prices, annualDiscount );
		writeURL( state );
	}

	const teamDec = root.querySelector( '[data-calc-team-dec]' );
	const teamInc = root.querySelector( '[data-calc-team-inc]' );
	const teamOut = root.querySelector( '[data-calc-team-out]' );

	function updateTeam( n ) {
		state.team = Math.max( 0, Math.min( M.TEAM_MAX, n ) );
		if ( state.members.length < state.team ) {
			while ( state.members.length < state.team ) state.members.push( { tier: 'flexi-1' } );
		} else if ( state.members.length > state.team ) {
			state.members = state.members.slice( 0, state.team );
		}
		if ( teamOut ) teamOut.value = state.team;
		if ( teamDec ) teamDec.disabled = state.team <= 0;
		if ( teamInc ) teamInc.disabled = state.team >= M.TEAM_MAX;
		renderRoster( root, state, prices );
		rerender();
	}

	if ( teamDec ) teamDec.addEventListener( 'click', () => updateTeam( state.team - 1 ) );
	if ( teamInc ) teamInc.addEventListener( 'click', () => updateTeam( state.team + 1 ) );

	// Commitment radio group (WAI-ARIA pattern, see §7)
	const commitRadios = Array.from( root.querySelectorAll( '[data-calc-commitment-group] [data-calc-commitment]' ) );
	const commitGroup = root.querySelector( '[data-calc-commitment-group]' );

	function setCommitChecked( radio ) {
		commitRadios.forEach( ( r ) => r.setAttribute( 'aria-checked', 'false' ) );
		if ( radio ) radio.setAttribute( 'aria-checked', 'true' );
	}

	function selectCommit( radio ) {
		setCommitChecked( radio );
		state.commitment = parseInt( radio.getAttribute( 'data-calc-commitment' ), 10 );
		rerender();
	}

	if ( commitGroup && commitRadios.length ) {
		commitRadios.forEach( ( radio ) => {
			radio.addEventListener( 'click', () => selectCommit( radio ) );
		} );
		const navKeys = [ 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown' ];
		commitRadios.forEach( ( radio, idx ) => {
			radio.addEventListener( 'keydown', ( e ) => {
				if ( navKeys.includes( e.key ) ) {
					e.preventDefault();
					e.stopPropagation();
					let nextIdx;
					if ( e.key === 'ArrowLeft' || e.key === 'ArrowUp' ) {
						nextIdx = idx <= 0 ? commitRadios.length - 1 : idx - 1;
					} else {
						nextIdx = idx >= commitRadios.length - 1 ? 0 : idx + 1;
					}
					commitRadios[ nextIdx ].focus();
					selectCommit( commitRadios[ nextIdx ] );
				} else if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					e.stopPropagation();
					selectCommit( radio );
				}
			}, { capture: true } );
		} );
	}

	// Annual checkbox
	const annualInput = root.querySelector( '[data-calc-annual]' );
	if ( annualInput ) {
		annualInput.checked = state.annualDiscount;
		annualInput.addEventListener( 'change', () => {
			state.annualDiscount = annualInput.checked;
			rerender();
		} );
		// Keyboard: normalise Space/Enter to a single toggle (native behaviour
		// varies by browser for visually-hidden checkboxes).
		annualInput.addEventListener( 'keydown', ( e ) => {
			if ( ( e.key === 'Enter' || e.key === ' ' ) && e.target === annualInput ) {
				e.preventDefault();
				e.stopPropagation();
				annualInput.checked = ! annualInput.checked;
				annualInput.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		}, { capture: true } );
	}

	// Roster + commitment delegate (change bubbles from the select)
	root.addEventListener( 'change', ( e ) => {
		if ( e.target.matches( '[data-calc-member]' ) ) {
			const idx = parseInt( e.target.getAttribute( 'data-calc-member' ), 10 );
			state.members[ idx ] = { tier: e.target.value };
			updateAnnualWrap( root, state );
			rerender();
			return;
		}
	} );

	// Breakdown trigger proxies into the full-width <details>
	const breakdownTrigger = root.querySelector( '[data-breakdown-trigger]' );
	const breakdownDetails = document.getElementById( 'workspace-pricing-methodology' );
	if ( breakdownTrigger && breakdownDetails ) {
		breakdownTrigger.addEventListener( 'click', () => {
			const wasOpen = breakdownDetails.open;
			breakdownDetails.open = ! wasOpen;
			breakdownTrigger.setAttribute( 'aria-expanded', String( ! wasOpen ) );
			if ( ! wasOpen ) {
				breakdownDetails.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		} );
		breakdownDetails.addEventListener( 'toggle', () => {
			breakdownTrigger.setAttribute( 'aria-expanded', String( breakdownDetails.open ) );
		} );
	}

	return rerender;
}

// --- Init ---
function initCalc( root ) {
	if ( ! root ) return;

	const prices = getPrices();
	const annualDiscount = getAnnualDiscount();

	const state = readURL();

	const teamOut = root.querySelector( '[data-calc-team-out]' );
	if ( teamOut ) teamOut.value = state.team;
	const teamDec = root.querySelector( '[data-calc-team-dec]' );
	const teamInc = root.querySelector( '[data-calc-team-inc]' );
	if ( teamDec ) teamDec.disabled = state.team <= 0;
	if ( teamInc ) teamInc.disabled = state.team >= M.TEAM_MAX;

	root.querySelectorAll( '[data-calc-commitment-group] [data-calc-commitment]' ).forEach( ( btn ) => {
		btn.setAttribute( 'aria-checked', parseInt( btn.getAttribute( 'data-calc-commitment' ), 10 ) === state.commitment ? 'true' : 'false' );
	} );

	const annualInput = root.querySelector( '[data-calc-annual]' );
	if ( annualInput ) annualInput.checked = state.annualDiscount;

	renderRoster( root, state, prices );
	bindSourceTooltips( root );
	const rerender = bindEvents( root, state, prices, annualDiscount );
	rerender();

	// Share row (email + copy link) — shared handler module.
	initCalcShare( root, {
		slug: 'workspace-pricing',
		getState: () => ( {
			team:       state.team,
			commitment: state.commitment,
			annual:     state.annualDiscount,
			members:    state.members.map( ( m ) => ( { tier: m.tier } ) ),
		} ),
	} );
}

export function initWorkspacePricing() {
	document.querySelectorAll( '[data-js="calc-office-costs"]' ).forEach( initCalc );
}