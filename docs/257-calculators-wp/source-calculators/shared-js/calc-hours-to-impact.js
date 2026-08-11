/**
 * two/fiftyseven · Phase 2.2 · Calc 3 — Hours to Impact
 * ----------------------------------------------------------------------------
 * Translates a team's hours at 257 into the $1-per-person-hour Impact Discount
 * contribution. Live, client-side, vanilla JS. Used inline on /impact/ and
 * on the standalone /calculator/hours-to-impact/ page.
 *
 * Mechanism (verified across 257's history): every person-hour spent at 257
 * by a paying member funds approximately $1 of subsidised space for an
 * organisation receiving the Impact Discount. The ratio is conservative —
 * the actual redistribution has consistently outpaced the ratio.
 *
 * Markup contract: the calc root has [data-js="calc-hours-to-impact"]. Inputs
 * and result slots use [data-calc-*] attributes.
 *
 * Locked content + voice rules apply to all DOM-rendered strings.
 * ============================================================================
 */

// --- Methodology constants (verified, see /phase-1/source-verification.md +
//     calc redesign brief)

const M = {
  // The locked mechanism: $1 per person-hour
  RATIO_NZD_PER_HOUR:   1.0,

  // Working pattern defaults
  HOURS_PER_DAY:        8,
  WEEKS_PER_YEAR:       46,         // 52 − 4 leave − 11 stat = 46 NZ standard
  DAYS_PER_WEEK:        5,

  // Bounds for inputs
  MAX_TEAM:             30,
  MAX_DAYS:             5,
  MAX_HOURS:            24,
  MAX_WEEKS:            52,
};

// --- Compute

function fmt$(n) {
  return new Intl.NumberFormat("en-NZ", { style: "currency", currency: "NZD", maximumFractionDigits: 0 }).format(Math.round(n));
}
function fmtN(n) {
  return new Intl.NumberFormat("en-NZ").format(Math.round(n));
}
// Placeholder shapes (per Job 9):
//   currency: '$0' on zero
//   hours:    '0 hrs' (with space)
//   bare int: '0'
function fmtHrs(n) {
  return fmtN(n) + ' hrs';
}

function compute(state) {
  // Total person-hours across the year
  const hours_yr = state.team * state.daysPerWeek * state.weeksPerYear * state.hoursPerDay;
  // Giving figure — directly proportional, $1/hour
  const giving_yr = hours_yr * M.RATIO_NZD_PER_HOUR;
  // Per-person figures for human-scale framing
  const hours_per_person_yr = state.daysPerWeek * state.weeksPerYear * state.hoursPerDay;
  // 1840 hours per person at standard NZ working year (46 × 5 × 8)

  return {
    hours_yr,
    giving_yr,
    hours_per_person_yr,
  };
}

// --- State <-> URL

function readURL() {
  // Job 8 · zero-start. With no URL state, every input is zero so the page
  // paints empty fields and $0 / 0 hrs results. URL state still hydrates the
  // calc from a shared/configured link.
  const params = new URLSearchParams(window.location.search);
  const state = {
    team:          parseInt(params.get("team") || "0", 10),
    daysPerWeek:   parseInt(params.get("days") || "0", 10),
    weeksPerYear:  parseInt(params.get("weeks") || "0", 10),
    hoursPerDay:   parseInt(params.get("hours") || "0", 10),
  };
  state.team         = Math.max(0, Math.min(M.MAX_TEAM, state.team));
  state.daysPerWeek  = Math.max(0, Math.min(M.MAX_DAYS, state.daysPerWeek));
  state.weeksPerYear = Math.max(0, Math.min(M.MAX_WEEKS, state.weeksPerYear));
  state.hoursPerDay  = Math.max(0, Math.min(M.MAX_HOURS, state.hoursPerDay));
  return state;
}

function writeURL(state) {
  const params = new URLSearchParams(window.location.search);
  params.set("team",  state.team);
  params.set("days",  state.daysPerWeek);
  params.set("weeks", state.weeksPerYear);
  params.set("hours", state.hoursPerDay);
  const newURL = `${window.location.pathname}?${params.toString()}${window.location.hash}`;
  window.history.replaceState({}, "", newURL);
}

// --- Render

function renderResults(root, state, computed) {
  // Headline equation cells — write the result figure to all output slots.
  // Note: we deliberately use [data-calc-result-hours] for the output cell to
  // avoid colliding with the [data-calc-hours] input control (hours-per-day).
  // Job 9 · '0 hrs' (with space) on zero, never '—'.
  root.querySelectorAll('[data-calc-result-hours]').forEach(el => el.textContent = fmtHrs(computed.hours_yr));
  root.querySelectorAll('[data-calc-giving]').forEach(el => el.textContent = fmt$(computed.giving_yr));

  // Per-person framing (shown in "at this rate, one person funds..." copy)
  root.querySelectorAll('[data-calc-per-person-hours]').forEach(el => el.textContent = fmtN(computed.hours_per_person_yr));
  root.querySelectorAll('[data-calc-per-person-giving]').forEach(el => el.textContent = fmt$(computed.hours_per_person_yr * M.RATIO_NZD_PER_HOUR));

  // Input echo (used in copy moments)
  root.querySelectorAll('[data-calc-echo-team]').forEach(el => el.textContent = state.team);
  root.querySelectorAll('[data-calc-echo-days]').forEach(el => el.textContent = state.daysPerWeek);
  root.querySelectorAll('[data-calc-echo-weeks]').forEach(el => el.textContent = state.weeksPerYear);
  root.querySelectorAll('[data-calc-echo-hours]').forEach(el => el.textContent = state.hoursPerDay);
}

// --- Event binding + init

function bindEvents(root, state) {
  function rerender() {
    const computed = compute(state);
    renderResults(root, state, computed);
    writeURL(state);
  }

  // Team stepper · Job 8: minimum is 0 so the calc starts empty.
  const teamDec = root.querySelector('[data-calc-team-dec]');
  const teamInc = root.querySelector('[data-calc-team-inc]');
  const teamOut = root.querySelector('[data-calc-team-out]');
  function updateTeam(n) {
    state.team = Math.max(0, Math.min(M.MAX_TEAM, n));
    if (teamOut) teamOut.value = state.team;
    if (teamDec) teamDec.disabled = state.team <= 0;
    if (teamInc) teamInc.disabled = state.team >= M.MAX_TEAM;
    rerender();
  }
  teamDec?.addEventListener("click", () => updateTeam(state.team - 1));
  teamInc?.addEventListener("click", () => updateTeam(state.team + 1));

  // Delegated inputs · Job 8: empty input falls back to 0, not to a default.
  root.addEventListener("change", (e) => {
    if (e.target.matches("[data-calc-days]")) {
      state.daysPerWeek = Math.max(0, Math.min(M.MAX_DAYS, parseInt(e.target.value, 10) || 0));
      rerender();
      return;
    }
    if (e.target.matches("[data-calc-weeks]")) {
      state.weeksPerYear = Math.max(0, Math.min(M.MAX_WEEKS, parseInt(e.target.value, 10) || 0));
      rerender();
      return;
    }
    if (e.target.matches("[data-calc-hours]")) {
      state.hoursPerDay = Math.max(0, Math.min(M.MAX_HOURS, parseInt(e.target.value, 10) || 0));
      rerender();
      return;
    }
  });

  return rerender;
}

function initCalcHoursToImpact(root) {
  if (!root) return;
  const state = readURL();

  const teamOut = root.querySelector('[data-calc-team-out]');
  if (teamOut) teamOut.value = state.team;
  // Job 8 · only pre-check a days radio if URL state explicitly set one (> 0).
  if (state.daysPerWeek > 0) {
    root.querySelectorAll('[data-calc-days]').forEach(input => { input.checked = (parseInt(input.value, 10) === state.daysPerWeek); });
  } else {
    root.querySelectorAll('[data-calc-days]').forEach(input => { input.checked = false; });
  }
  const weeksInput = root.querySelector('[data-calc-weeks]');
  // Empty string in the input so placeholder "0" shows; engine state stays 0.
  if (weeksInput) weeksInput.value = state.weeksPerYear > 0 ? state.weeksPerYear : '';
  const hoursInput = root.querySelector('[data-calc-hours]');
  if (hoursInput) hoursInput.value = state.hoursPerDay > 0 ? state.hoursPerDay : '';

  const rerender = bindEvents(root, state);
  rerender();
}

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll('[data-js="calc-hours-to-impact"]').forEach(initCalcHoursToImpact);
});
