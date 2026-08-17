/**
 * 257 Office Carbon Calculator — engine
 * ----------------------------------------------------------------------------
 * Compares the operational carbon footprint of a private central-Wellington
 * office against being at two/fiftyseven, for sustainability / ESG /
 * procurement leads.
 *
 * Methodology values are LOCKED per the calc redesign brief, using the
 * Tadpole ACE 2025 emission factors. Public-facing offset claim is 200%.
 * Biodiversity credits (Sanctuary Mountain Maungatautari via Ekos BioCredita)
 * are NOT carbon offsets and are never aggregated into the offset percentage.
 *
 * Working-pattern constants (46 weeks, 8 hours/day, etc.) stay in code —
 * they're cited NZ standards, not admin-editable.
 *
 * Markup contract: root has [data-js="calc-office-carbon"]. The inputs live
 * inside the root; the share + export/citation sections live in the wrapper,
 * so the shared root element for side-effects is the wrapper (scope).
 *
 * Inputs:  [data-calc-team-dec/inc/out], [data-calc-days], [data-calc-weeks],
 *          [data-calc-hours]
 * Outputs: [data-calc-result-{private,ours,positive}] (sticky aside, HTML
 *          number+unit split), [data-calc-export-{private,ours,offset,team,
 *          days,weeks}] (citation rows), [data-calc-breakdown-{total,net}],
 *          [data-calc-saved-figure], [data-calc-net-figure],
 *          [data-calc-private-lines], [data-calc-ours-lines]
 * ============================================================================
 */

import { initCalcShare } from './calc-share.js';
import { bindRovingRadio, bindStepper, restrictStepperInputs, bindBreakdownTrigger, bindSourceTooltips } from './calc-utils.js';

// --- Methodology constants (verified, stay in code) ---
const M = {
	// Tadpole ACE 2025 emission factors
	GRID_KGCO2E_PER_KWH: 0.1011,
	LINE_LOSS_KGCO2E_KWH: 0.0077,
	// Combined: 0.1088 kgCO2e/kWh — the canonical methodology-page figure

	// Office energy load (BRANZ + EECA)
	POWER_W_PER_SQM: 50,
	SQM_PER_PERSON: 10,
	OFFICE_DAYS_YR: 230,

	// Office waste (Tadpole ACE 2025)
	WASTE_KG_PER_DAY: 0.5,
	WASTE_KGCO2E_PER_KG: 0.584,

	// Commute baseline (Wellington 15km RT mixed EV/ICE)
	COMMUTE_KGCO2E_DAY: 0.3,

	// 257 measured footprint (verified Tadpole ACE measurement)
	BUILDING_FOOTPRINT_TCO2E_YR: 6.5,
	BUILDING_CAPACITY: 80,
	// Per-person-day share = (6.5 × 1000) / (80 × 230) ≈ 0.353 kgCO2e/person-day

	// Offset (public-facing claim)
	OFFSET_RATIO_PUBLIC: 2.0,

	// Working-pattern defaults
	HOURS_PER_DAY: 8,
	WEEKS_PER_YEAR: 46,
	DAYS_PER_WEEK: 5,

	// Input bounds
	MAX_TEAM: 15,
	MAX_DAYS: 5,
	MAX_HOURS: 24,
	MAX_WEEKS: 52,
};

// Per-person-day share of the 257 building footprint
const PERSON_DAY_257 = ( M.BUILDING_FOOTPRINT_TCO2E_YR * 1000 ) / ( M.BUILDING_CAPACITY * M.OFFICE_DAYS_YR );

// --- Sources (URL-linked, as in the source calculator) ---
const SOURCES = {
	tadpole: { label: 'Tadpole ACE 2025 emission factors', name: 'Tadpole ACE Carbon Calculator', url: 'https://www.tadpole.co.nz/ace-carbon-calculator/' },
	mfe: { label: 'NZ grid 2025 baseline', name: 'MfE Measuring Emissions Guide 2025', url: 'https://environment.govt.nz/publications/measuring-emissions-guide-2025/' },
	branz: { label: 'Office energy benchmark', name: 'BRANZ + EECA', url: 'https://www.eeca.govt.nz/co-funding-and-support/products/commercial-buildings-decarbonisation-pathway/' },
	ecotricity: { label: 'Toitū climate positive electricity at 125%', name: 'Ecotricity', url: 'https://ecotricity.co.nz/why-ecotricity/climate-positive' },
	ekos_nzu: { label: 'Ekos NZU carbon credits', name: 'Ekos · Our Projects', url: 'https://www.ekos.co.nz/our-projects' },
	ekos_bio: { label: 'Ekos BioCredita (biodiversity, NOT carbon offsets)', name: 'Ekos BioCredita', url: 'https://www.ekos.co.nz/sdu-1' },
	maungatautari: { label: 'Sanctuary Mountain Maungatautari', name: 'Sanctuary Mountain', url: 'https://www.sanctuarymountain.co.nz/support/biodiversity-credits' },
};

// --- Formatters ---
function fmtKg( n ) {
	// Always render kgCO2e. Switch to t at >=10,000 kg for readability.
	// '0 t' on zero — same shape the user sees pre-input.
	const rounded = Math.round( n );
	if ( rounded === 0 ) return '0 t';
	if ( Math.abs( rounded ) >= 10000 ) {
		return `${ ( rounded / 1000 ).toFixed( 1 ) } tCO₂e`;
	}
	return `${ new Intl.NumberFormat( 'en-NZ' ).format( rounded ) } kgCO₂e`;
}

function fmtKgSigned( n ) {
	const sign = n < 0 ? '−' : '';
	return sign + fmtKg( Math.abs( n ) );
}

// HTML variant: number + unit split so the unit can be styled smaller and
// baseline-aligned (see .calc__kg-unit in _calc-office-carbon.scss).
function kgHtml( n ) {
	const s = fmtKg( n );
	const sp = s.indexOf( ' ' );
	if ( sp < 0 ) return s;
	return `${ s.slice( 0, sp ) }<span class="calc__kg-unit">${ s.slice( sp + 1 ) }</span>`;
}

function kgHtmlSigned( n ) {
	return ( n < 0 ? '−' : '' ) + kgHtml( Math.abs( n ) );
}

// --- Compute ---
function compute( state ) {
	const t = state.team;
	const d = state.daysPerWeek;
	const w = state.weeksPerYear;
	const hpd = state.hoursPerDay;
	const sqm = t * M.SQM_PER_PERSON;

	const person_days_private = t * d * w;
	const person_hours = person_days_private * hpd;

	// Private NZ office — operational carbon (kgCO2e/yr)
	const power_kwh_yr = ( M.POWER_W_PER_SQM * hpd * w * d * sqm ) / 1000;
	const private_power_kg = power_kwh_yr * ( M.GRID_KGCO2E_PER_KWH + M.LINE_LOSS_KGCO2E_KWH );
	const private_waste_kg = person_days_private * M.WASTE_KG_PER_DAY * M.WASTE_KGCO2E_PER_KG;
	const private_commute_kg = person_days_private * M.COMMUTE_KGCO2E_DAY;
	const private_total = private_power_kg + private_waste_kg + private_commute_kg;

	const private_lines = [
		{ key: 'power', amount: private_power_kg, source: 'branz', note: `${ sqm } m² @ 50 W/m², ${ hpd }h × ${ d } days × ${ w } weeks` },
		{ key: 'waste', amount: private_waste_kg, source: 'tadpole', note: '0.5 kg/person-day at 0.584 kgCO₂e/kg' },
		{ key: 'commute', amount: private_commute_kg, source: 'tadpole', note: 'Wellington 15km RT mixed EV/ICE mid' },
	];

	const ours_total_kg = person_days_private * PERSON_DAY_257;
	const ours_offset_kg = ours_total_kg * ( 1 - M.OFFSET_RATIO_PUBLIC );
	const ours_positive_kg = Math.abs( ours_offset_kg );

	const saved_vs_private_kg = private_total - ours_total_kg;
	const net_avoided_kg = private_total - ours_offset_kg;

	return {
		private_lines, private_total,
		ours_total_kg,
		ours_offset_kg,      // signed, negative
		ours_positive_kg,    // absolute magnitude
		saved_vs_private_kg,
		net_avoided_kg,
		person_days_private, person_hours,
	};
}

// --- State <-> URL ---
function readURL() {
	const params = new URLSearchParams( window.location.search );
	const hasParams = params.has( 'team' ) || params.has( 'days' ) || params.has( 'weeks' ) || params.has( 'hours' );
	const state = {
		team: parseInt( params.get( 'team' ) || '0', 10 ),
		daysPerWeek: parseInt( params.get( 'days' ) || '0', 10 ),
		weeksPerYear: parseInt( params.get( 'weeks' ) || '0', 10 ),
		hoursPerDay: parseFloat( params.get( 'hours' ) || '0' ),
	};
	// On a cold load (no URL params), pre-fill the NZ working-year defaults.
	if ( ! hasParams ) {
		state.team = 1;
		state.daysPerWeek = M.DAYS_PER_WEEK;
		state.weeksPerYear = M.WEEKS_PER_YEAR;
		state.hoursPerDay = M.HOURS_PER_DAY;
	}
	state.team = Math.max( 0, Math.min( M.MAX_TEAM, state.team ) );
	state.daysPerWeek = Math.max( 0, Math.min( M.MAX_DAYS, state.daysPerWeek ) );
	state.weeksPerYear = Math.max( 0, Math.min( M.MAX_WEEKS, state.weeksPerYear ) );
	state.hoursPerDay = Math.max( 0, Math.min( M.MAX_HOURS, state.hoursPerDay ) );
	return state;
}

function writeURL( state ) {
	const params = new URLSearchParams( window.location.search );
	params.set( 'team', state.team );
	params.set( 'days', state.daysPerWeek );
	params.set( 'weeks', state.weeksPerYear );
	params.set( 'hours', state.hoursPerDay );
	const newURL = `${ window.location.pathname }?${ params.toString() }${ window.location.hash }`;
	window.history.replaceState( {}, '', newURL );
}

// --- Render ---
const ROW_LABELS = {
	power: 'Office electricity',
	waste: 'Office waste to landfill',
	commute: 'Team commute',
};

function sourceTooltip( slug ) {
	const src = SOURCES[ slug ];
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

function renderResults( scope, state, computed ) {
	const privateBody = scope.querySelector( '[data-calc-private-lines]' );
	if ( privateBody ) {
		privateBody.innerHTML = computed.private_lines.map( ( l ) => compareRow(
			ROW_LABELS[ l.key ],
			l.note,
			kgHtml( l.amount ),
			l.source
		) ).join( '' );
	}

	const oursBody = scope.querySelector( '[data-calc-ours-lines]' );
	if ( oursBody ) {
		oursBody.innerHTML = [
			compareRow( 'Measured at 257', 'per-person-day share of 6.5 tCO₂e/yr building footprint', kgHtml( computed.ours_total_kg ), 'tadpole' ),
			compareRow( 'Offset at 200%', 'Ekos NZUs + Ecotricity Toitū climate positive electricity', `−${ kgHtml( computed.ours_total_kg * M.OFFSET_RATIO_PUBLIC ) }`, 'ecotricity' ),
		].join( '' );
	}

	// Shared slots — every matching element, anywhere in the wrapper.
	scope.querySelectorAll( '[data-calc-export-private]' ).forEach( ( el ) => el.textContent = fmtKg( computed.private_total ) );
	scope.querySelectorAll( '[data-calc-export-ours]' ).forEach( ( el ) => el.textContent = fmtKg( computed.ours_total_kg ) );
	scope.querySelectorAll( '[data-calc-export-offset]' ).forEach( ( el ) => el.textContent = fmtKgSigned( computed.ours_offset_kg ) );

	// Sticky aside headline figures — number + unit split so the unit can sit
	// smaller (xl) on the same baseline as the 3xl number.
	scope.querySelectorAll( '[data-calc-result-private]' ).forEach( ( el ) => el.innerHTML = kgHtml( computed.private_total ) );
	scope.querySelectorAll( '[data-calc-result-ours]' ).forEach( ( el ) => el.innerHTML = kgHtml( computed.ours_total_kg ) );
	scope.querySelectorAll( '[data-calc-result-positive]' ).forEach( ( el ) => el.innerHTML = kgHtmlSigned( computed.ours_offset_kg ) );

	// Breakdown totals + figures — rendered as HTML so the unit can sit
	// smaller on the same baseline as the number.
	scope.querySelectorAll( '[data-calc-breakdown-total]' ).forEach( ( el ) => el.innerHTML = kgHtml( computed.private_total ) );
	scope.querySelectorAll( '[data-calc-breakdown-net]' ).forEach( ( el ) => el.innerHTML = kgHtmlSigned( computed.ours_offset_kg ) );
	scope.querySelectorAll( '[data-calc-saved-figure]' ).forEach( ( el ) => el.innerHTML = kgHtml( computed.saved_vs_private_kg ) );
	scope.querySelectorAll( '[data-calc-net-figure]' ).forEach( ( el ) => el.innerHTML = kgHtml( computed.net_avoided_kg ) );
	scope.querySelectorAll( '[data-calc-export-team]' ).forEach( ( el ) => el.textContent = state.team );
	scope.querySelectorAll( '[data-calc-export-days]' ).forEach( ( el ) => el.textContent = state.daysPerWeek );
	scope.querySelectorAll( '[data-calc-export-weeks]' ).forEach( ( el ) => el.textContent = state.weeksPerYear );
}

// --- Event binding ---
function bindEvents( scope, root, state ) {
	function rerender() {
		const computed = compute( state );
		renderResults( scope, state, computed );
		writeURL( state );
	}

	// Team stepper · min 0 so the calc starts empty — shared wiring.
	const stepper = bindStepper( root, {
		rangeSel: '[data-calc-team-range]',
		sliderSel: '[data-calc-team-slider]',
		outSel: '[data-calc-team-out]',
		decSel: '[data-calc-team-dec]',
		incSel: '[data-calc-team-inc]',
		max: M.MAX_TEAM,
		valueFor: ( i ) => i,
		current: () => state.team,
		onUpdate: ( n ) => {
			state.team = n;
			rerender();
		},
	} );
	stepper.paintCurrent();

	// ── Days radius (WAI-ARIA roving radio) ──────────────
	const dayRadios = Array.from( root.querySelectorAll( '[data-calc-days-group] [data-calc-days]' ) );

	function setRadioChecked( radio ) {
		dayRadios.forEach( ( r ) => r.setAttribute( 'aria-checked', 'false' ) );
		if ( radio ) radio.setAttribute( 'aria-checked', 'true' );
	}

	function updateRadioTabindex() {
		const checked = dayRadios.find( ( r ) => r.getAttribute( 'aria-checked' ) === 'true' ) || dayRadios[ 0 ];
		dayRadios.forEach( ( r ) => {
			r.tabIndex = 0;
		} );
		if ( checked ) checked.tabIndex = 0;
		dayRadios.forEach( ( r ) => {
			if ( r !== checked ) r.tabIndex = -1;
		} );
	}

	function selectDay( radio ) {
		setRadioChecked( radio );
		state.daysPerWeek = Math.max( 0, Math.min( M.MAX_DAYS, parseInt( radio.getAttribute( 'data-calc-days' ), 10 ) || 0 ) );
		updateRadioTabindex();
		rerender();
	}

	if ( dayRadios.length ) {
		dayRadios.forEach( ( radio ) => radio.addEventListener( 'click', () => selectDay( radio ) ) );
		bindRovingRadio( dayRadios, selectDay );
	}
	updateRadioTabindex();

	// Weeks + hours number inputs · clamped, stepper-only.
	root.addEventListener( 'change', ( e ) => {
		if ( e.target.matches( '[data-calc-weeks]' ) ) {
			const v = parseInt( e.target.value, 10 ) || 0;
			state.weeksPerYear = Math.max( 0, Math.min( M.MAX_WEEKS, v ) );
			if ( v !== state.weeksPerYear ) e.target.value = state.weeksPerYear;
			rerender();
			return;
		}
		if ( e.target.matches( '[data-calc-hours]' ) ) {
			const v = parseFloat( e.target.value ) || 0;
			state.hoursPerDay = Math.max( 0, Math.min( M.MAX_HOURS, v ) );
			if ( v !== state.hoursPerDay ) e.target.value = state.hoursPerDay;
			rerender();
			return;
		}
	} );

	restrictStepperInputs( root.querySelectorAll( '[data-calc-weeks], [data-calc-hours]' ) );

	// Breakdown trigger proxies into the full-width <details>
	bindBreakdownTrigger( root, 'methodology' );

	// "Copy citation block" — plain clipboard copy of the export panel text.
	const copyBtn = scope.querySelector( '[data-calc-copy-citation]' );
	if ( copyBtn ) {
		copyBtn.addEventListener( 'click', () => {
			const block = scope.querySelector( '[data-calc-citation-block]' );
			if ( ! block || ! navigator.clipboard ) return;
			navigator.clipboard.writeText( block.innerText.replace( /\s+/g, ' ' ).trim() ).then( () => {
				const original = copyBtn.textContent;
				copyBtn.textContent = 'Copied';
				setTimeout( () => { copyBtn.textContent = original; }, 1600 );
			} );
		} );
	}

	return rerender;
}

// --- Init ---
function initCalc( root ) {
	if ( ! root ) return;
	const scope = root.parentElement;
	const state = readURL();

	// Sync initial UI from state (defaults pre-filled on cold load).
	root.querySelectorAll( '[data-calc-days-group] [data-calc-days]' ).forEach( ( btn ) => {
		btn.setAttribute( 'aria-checked', parseInt( btn.getAttribute( 'data-calc-days' ), 10 ) === state.daysPerWeek ? 'true' : 'false' );
	} );

	const weeksInput = root.querySelector( '[data-calc-weeks]' );
	if ( weeksInput ) weeksInput.value = state.weeksPerYear > 0 ? state.weeksPerYear : '';

	const hoursInput = root.querySelector( '[data-calc-hours]' );
	if ( hoursInput ) hoursInput.value = state.hoursPerDay > 0 ? state.hoursPerDay : '';

	bindSourceTooltips( scope );
	const rerender = bindEvents( scope, root, state );
	rerender();

	// Share row (email + copy link) — lives OUTSIDE the calc body grid (this
	// calc uses a sticky aside), so look it up from the wrapper parent.
	initCalcShare( scope, {
		slug: 'office-carbon',
		getState: () => ( {
			team: state.team,
			daysPerWeek: state.daysPerWeek,
			weeksPerYear: state.weeksPerYear,
			hoursPerDay: state.hoursPerDay,
		} ),
	} );
}

export function initOfficeCarbon() {
	document.querySelectorAll( '[data-js="calc-office-carbon"]' ).forEach( initCalc );
}