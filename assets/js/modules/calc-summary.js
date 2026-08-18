/**
 * 257 Calc Summary — engine
 * ----------------------------------------------------------------------------
 * Compact dashboard widget for workspace product pages (Hub / Base / Desk).
 * Two sliders (team size + avg days/week) feed four linked cards:
 *   - At 257          annual membership cost (Flexi-N per person × team × 12)
 *   - You'd save      savings vs a private office (scaled by days/week)
 *   - Carbon position net 257 position after 200% verified offset
 *   - Hours to impact $ of subsidised space funded by the team's hours
 * Each card is a whole-card link to the detailed calculator that owns it.
 *
 * Reads from window.twofiftyseven:
 *   - prices['flexi-N'].price  (Flexi membership monthly rate; falls back to
 *                               the canonical 9 + N×100 formula)
 *   - impact.givingRatePerPersonHour  ($ value of each person-hour funded)
 *
 * Simplified from the full calc engines, keeping their verified constants:
 *   - private-office benchmark $14,200/person/yr (workspace-pricing.js)
 *   - 257 building footprint → per-person-day share + 200% offset
 *     (office-carbon.js PERSON_DAY_257 / OFFSET_RATIO_PUBLIC)
 *   - giving = person-days × 8h × 46wk × $1/hr (hours-to-impact.js)
 *
 * Markup contract: root has [data-js="calc-summary"].
 * Inputs:  [data-cs-team-range|slider|out]
 *          [data-cs-days-range|slider|out]
 * Outputs: [data-cs-cost] [data-cs-save] [data-cs-private]
 *          [data-cs-carbon] [data-cs-giving]
 *
 * No URL sync, no share/email — this is a summary that hands off to the full
 * calculators via the whole-card links.
 * ============================================================================
 */

import { bindStepper, fmt$ } from './calc-utils.js';

// --- Methodology constants (stay in code, cited in the calc engines) ---
const M = {
	DEFAULT_TEAM: 1,
	DEFAULT_DAYS: 3,
	TEAM_MAX: 15,
	DAYS_MAX: 5,
	// Private central-Wellington office benchmark (workspace-pricing.js)
	PRIVATE_OFFICE_PER_PERSON_YR: 14200,
	// Working pattern (hours-to-impact.js)
	GIVING_HOURS_DAY: 8,
	GIVING_WEEKS: 46,
	// 257 measured footprint (office-carbon.js — Tadpole ACE 2025)
	BUILDING_FOOTPRINT_TCO2E_YR: 6.5,
	BUILDING_CAPACITY: 80,
	OFFICE_DAYS_YR: 230,
	// Public-facing offset claim (office-carbon.js)
	OFFSET_RATIO_PUBLIC: 2.0,
};

// Per-person-day share of the 257 building footprint (kgCO2e/person-day)
const PERSON_DAY_257 = ( M.BUILDING_FOOTPRINT_TCO2E_YR * 1000 ) / ( M.BUILDING_CAPACITY * M.OFFICE_DAYS_YR );

export function initCalcSummary() {
	const root = document.querySelector( '[data-js="calc-summary"]' );
	if ( ! root ) return;

	const ssot = window.twofiftyseven || {};
	const givingRate = ( ssot.impact && ssot.impact.givingRatePerPersonHour ) || 1;

	function getFlexiPrice( days ) {
		const p = ( ssot.prices && ssot.prices[ 'flexi-' + days ] ) || {};
		return p.price || 9 + days * 100;
	}

	// --- Element refs ---
	const costEl = root.querySelector( '[data-cs-cost]' );
	const saveEl = root.querySelector( '[data-cs-save]' );
	const privateEl = root.querySelector( '[data-cs-private]' );
	const carbonEl = root.querySelector( '[data-cs-carbon]' );
	const givingEl = root.querySelector( '[data-cs-giving]' );

	// --- State ---
	const state = {
		team: M.DEFAULT_TEAM,
		days: M.DEFAULT_DAYS,
	};

	// --- Compute ---
	function compute() {
		const t = state.team;
		const d = state.days;

		const flexiMonth = getFlexiPrice( d );
		const oursAnnual = flexiMonth * t * 12;
		const daysFraction = d / M.DAYS_MAX;
		const privateAnnual = t * M.PRIVATE_OFFICE_PER_PERSON_YR * daysFraction;
		const saving = privateAnnual - oursAnnual;

		const personDays = t * d * M.GIVING_WEEKS;
		const carbonKg = personDays * PERSON_DAY_257 * ( 1 - M.OFFSET_RATIO_PUBLIC );
		const giving = personDays * M.GIVING_HOURS_DAY * givingRate;

		return { oursAnnual, privateAnnual, saving, carbonKg, giving };
	}

	// --- Render ---
	function render() {
		const c = compute();

		if ( costEl ) {
			costEl.innerHTML = fmt$( c.oursAnnual ) + '<span class="calc-summary__figure-unit">/yr</span>';
		}
		if ( saveEl ) saveEl.textContent = fmt$( c.saving );
		if ( privateEl ) privateEl.textContent = fmt$( c.privateAnnual );
		if ( carbonEl ) {
			const tonnes = Math.abs( c.carbonKg ) / 1000;
			carbonEl.innerHTML = '\u2212' + tonnes.toFixed( 2 ) + '<span class="calc-summary__figure-unit"> t CO\u2082e/yr</span>';
		}
		if ( givingEl ) givingEl.textContent = fmt$( c.giving );
	}

	function rerender() {
		render();
	}

	// --- Steppers ---
	const teamStepper = bindStepper( root, {
		rangeSel: '[data-cs-team-range]',
		sliderSel: '[data-cs-team-slider]',
		outSel: '[data-cs-team-out]',
		max: M.TEAM_MAX - 1,
		valueFor: ( i ) => i + 1,
		current: () => state.team - 1,
		onUpdate: ( idx ) => {
			state.team = idx + 1;
			rerender();
		},
	} );

	const daysStepper = bindStepper( root, {
		rangeSel: '[data-cs-days-range]',
		sliderSel: '[data-cs-days-slider]',
		outSel: '[data-cs-days-out]',
		max: M.DAYS_MAX - 1,
		valueFor: ( i ) => i + 1,
		current: () => state.days - 1,
		onUpdate: ( idx ) => {
			state.days = idx + 1;
			rerender();
		},
	} );

	// --- Bootstrap ---
	teamStepper.paintCurrent();
	daysStepper.paintCurrent();
	rerender();
}