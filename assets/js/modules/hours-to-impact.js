/**
 * 257 Hours to Impact Calculator — engine
 * ----------------------------------------------------------------------------
 * Translates a team's hours at two/fiftyseven into the dollar value of
 * subsidised space funded via the Impact Discount.
 *
 * Reads the giving rate from window.twofiftyseven.impact.givingRatePerPersonHour
 * (populated by the wp_head SSOT injector from ACF Options).
 *
 * Working-pattern constants (46 weeks, 8 hours/day, etc.) stay in code —
 * they're cited NZ standards, not admin-editable.
 *
 * Markup contract: root has [data-js="calc-hours-to-impact"].
 * Inputs: [data-calc-team-*], [data-calc-days], [data-calc-weeks], [data-calc-hours]
 * Outputs: [data-calc-result-hours], [data-calc-giving],
 *          [data-calc-per-person-hours], [data-calc-per-person-giving]
 * ============================================================================
 */

// --- Methodology constants (cited, stay in code) ---
const M = {
	HOURS_PER_DAY:  8,
	WEEKS_PER_YEAR: 46,
	DAYS_PER_WEEK:  5,
	MAX_TEAM:       30,
	MAX_DAYS:       5,
	MAX_HOURS:      24,
	MAX_WEEKS:      52,
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

function fmtHrs( n ) {
	return fmtN( n ) + ' hrs';
}

// --- Compute ---
function compute( state, givingRate ) {
	const hours_yr = state.team * state.daysPerWeek * state.weeksPerYear * state.hoursPerDay;
	const giving_yr = hours_yr * givingRate;
	const hours_per_person_yr = state.daysPerWeek * state.weeksPerYear * state.hoursPerDay;

	return { hours_yr, giving_yr, hours_per_person_yr };
}

// --- State <-> URL ---
function readURL() {
	const params = new URLSearchParams( window.location.search );
	const hasParams = params.has( 'team' ) || params.has( 'days' ) || params.has( 'weeks' ) || params.has( 'hours' );
	const state = {
		team:         parseInt( params.get( 'team' ) || '0', 10 ),
		daysPerWeek:  parseInt( params.get( 'days' ) || '0', 10 ),
		weeksPerYear: parseInt( params.get( 'weeks' ) || '0', 10 ),
		hoursPerDay:  parseFloat( params.get( 'hours' ) || '0' ),
	};
	// On a cold load (no URL params), pre-fill the NZ working-year defaults
	if ( ! hasParams ) {
		state.team         = 1;
		state.daysPerWeek  = M.DAYS_PER_WEEK;
		state.weeksPerYear = M.WEEKS_PER_YEAR;
		state.hoursPerDay  = M.HOURS_PER_DAY;
	}
	state.team         = Math.max( 0, Math.min( M.MAX_TEAM, state.team ) );
	state.daysPerWeek  = Math.max( 0, Math.min( M.MAX_DAYS, state.daysPerWeek ) );
	state.weeksPerYear = Math.max( 0, Math.min( M.MAX_WEEKS, state.weeksPerYear ) );
	state.hoursPerDay  = Math.max( 0, Math.min( M.MAX_HOURS, state.hoursPerDay ) );
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
function renderResults( root, state, computed, givingRate ) {
	root.querySelectorAll( '[data-calc-result-hours]' ).forEach( ( el ) => {
		el.textContent = fmtHrs( computed.hours_yr );
	} );
	root.querySelectorAll( '[data-calc-giving]' ).forEach( ( el ) => {
		el.textContent = fmt$( computed.giving_yr );
	} );
	root.querySelectorAll( '[data-calc-per-person-hours]' ).forEach( ( el ) => {
		el.textContent = fmtN( computed.hours_per_person_yr );
	} );
	root.querySelectorAll( '[data-calc-per-person-giving]' ).forEach( ( el ) => {
		el.textContent = fmt$( computed.hours_per_person_yr * givingRate );
	} );
}

// --- Event binding ---
function bindEvents( root, state, givingRate ) {
	function rerender() {
		const computed = compute( state, givingRate );
		renderResults( root, state, computed, givingRate );
		writeURL( state );
	}

	const teamDec = root.querySelector( '[data-calc-team-dec]' );
	const teamInc = root.querySelector( '[data-calc-team-inc]' );
	const teamOut = root.querySelector( '[data-calc-team-out]' );

	function updateTeam( n ) {
		state.team = Math.max( 0, Math.min( M.MAX_TEAM, n ) );
		if ( teamOut ) teamOut.value = state.team;
		if ( teamDec ) teamDec.disabled = state.team <= 0;
		if ( teamInc ) teamInc.disabled = state.team >= M.MAX_TEAM;
		rerender();
	}

	if ( teamDec ) teamDec.addEventListener( 'click', () => updateTeam( state.team - 1 ) );
	if ( teamInc ) teamInc.addEventListener( 'click', () => updateTeam( state.team + 1 ) );

	// ── Radio group keyboard nav (WAI-ARIA pattern) ──────────
	// Buttons with role="radio". Tab into the group (checked or
	// first), arrow keys to move between, Enter/Space to select.
	const dayRadios = Array.from( root.querySelectorAll( '[data-calc-days-group] [data-calc-days]' ) );
	const dayGroup = root.querySelector( '[data-calc-days-group]' );

	function getCheckedRadio() {
		return dayRadios.find( ( r ) => r.getAttribute( 'aria-checked' ) === 'true' );
	}

	function setRadioChecked( radio ) {
		dayRadios.forEach( ( r ) => r.setAttribute( 'aria-checked', 'false' ) );
		if ( radio ) radio.setAttribute( 'aria-checked', 'true' );
	}

	function updateRadioTabindex() {
		dayRadios.forEach( ( r ) => {
			r.tabIndex = 0;
		} );
	}

	function selectDay( radio ) {
		setRadioChecked( radio );
		state.daysPerWeek = Math.max( 0, Math.min( M.MAX_DAYS, parseInt( radio.getAttribute( 'data-calc-days' ), 10 ) || 0 ) );
		updateRadioTabindex();
		rerender();
	}

	if ( dayGroup && dayRadios.length ) {
		// Click to select
		dayRadios.forEach( ( radio ) => {
			radio.addEventListener( 'click', () => selectDay( radio ) );
		} );

		// Arrow keys + Enter/Space — attached via capture on each
		// button so Locomotive Scroll can't intercept the arrows.
		const navKeys = [ 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown' ];
		dayRadios.forEach( ( radio, idx ) => {
			radio.addEventListener( 'keydown', ( e ) => {
				if ( navKeys.includes( e.key ) ) {
					e.preventDefault();
					e.stopPropagation();
					let nextIdx;
					if ( e.key === 'ArrowLeft' || e.key === 'ArrowUp' ) {
						nextIdx = idx <= 0 ? dayRadios.length - 1 : idx - 1;
					} else {
						nextIdx = idx >= dayRadios.length - 1 ? 0 : idx + 1;
					}
					dayRadios[ nextIdx ].focus();
					selectDay( dayRadios[ nextIdx ] );
				} else if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					e.stopPropagation();
					selectDay( radio );
				}
			}, { capture: true } );
		} );
	}

	// Call once on init to set initial tabindex
	updateRadioTabindex();

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

	// Restrict the bounded number inputs (weeks/hours) to stepper-only:
	// typed digits never enter, so values can't exceed min/max. Only the
	// up/down steppers (native keyboard arrows + spinner buttons) move them.
	// Capture phase + stopPropagation so Locomotive Scroll can't hijack arrows.
	const stepperOnly = [ 'ArrowUp', 'ArrowDown', 'Tab', 'Enter' ];
	root.querySelectorAll( '[data-calc-weeks], [data-calc-hours]' ).forEach( ( input ) => {
		input.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'ArrowUp' || e.key === 'ArrowDown' ) {
				e.stopPropagation();
				return;
			}
			if ( ! stepperOnly.includes( e.key ) ) {
				e.preventDefault();
			}
		}, { capture: true } );
	} );

	// Breakdown trigger proxies into the full-width <details>
	const breakdownTrigger = root.querySelector( '[data-breakdown-trigger]' );
	const breakdownDetails = document.getElementById( 'methodology' );
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

	const tfs = window.twofiftyseven || {};
	const givingRate = ( tfs.impact && tfs.impact.givingRatePerPersonHour ) || 1;

	const state = readURL();

	const teamOut = root.querySelector( '[data-calc-team-out]' );
	if ( teamOut ) teamOut.value = state.team;

	if ( state.daysPerWeek > 0 ) {
		root.querySelectorAll( '[data-calc-days-group] [data-calc-days]' ).forEach( ( btn ) => {
			btn.setAttribute( 'aria-checked', parseInt( btn.getAttribute( 'data-calc-days' ), 10 ) === state.daysPerWeek ? 'true' : 'false' );
		} );
	} else {
		root.querySelectorAll( '[data-calc-days-group] [data-calc-days]' ).forEach( ( btn ) => {
			btn.setAttribute( 'aria-checked', 'false' );
		} );
	}

	const weeksInput = root.querySelector( '[data-calc-weeks]' );
	if ( weeksInput ) weeksInput.value = state.weeksPerYear > 0 ? state.weeksPerYear : '';

	const hoursInput = root.querySelector( '[data-calc-hours]' );
	if ( hoursInput ) hoursInput.value = state.hoursPerDay > 0 ? state.hoursPerDay : '';

	const rerender = bindEvents( root, state, givingRate );
	rerender();
}

export function initHoursToImpact() {
	document.querySelectorAll( '[data-js="calc-hours-to-impact"]' ).forEach( initCalc );
}
