/**
 * two/fiftyseven · Phase 2.2 · Calc 2 — Office carbon position
 * ----------------------------------------------------------------------------
 * Compares the operational carbon footprint of a private central-Wellington
 * office against being at two/fiftyseven, for an audience of ESG / procurement
 * / sustainability leads. Live, client-side, vanilla JS. Used on the standalone
 * /calculator/office-carbon/ page.
 *
 * Methodology values are LOCKED per the calc redesign brief, with the
 * Tadpole ACE 2025 emission factors as verified in
 * /phase-1/source-verification.md (items 15-21).
 *
 * Public-facing offset claim is 200% (per Ash directive). The verified
 * combined ~238% headline (24 Ekos NZUs at 126% + Ecotricity 125% Toitū at
 * 112% of total) is methodology-page detail only and never the user-facing
 * hero figure.
 *
 * Biodiversity credits via Sanctuary Mountain Maungatautari are NOT carbon
 * offsets (per Sean Weaver, Ekos founder). They are a separate stream and
 * never aggregated into the offset percentage.
 *
 * Markup contract: the calc root has [data-js="calc-office-carbon"]. Inputs
 * and result slots use [data-calc-*] attributes.
 *
 * Locked content + voice rules apply to all DOM-rendered strings.
 * ============================================================================
 */

// --- Methodology constants (verified, see ../phase-1/source-verification.md) -

const M = {
  // Tadpole ACE 2025 emission factors — verified, item 15
  GRID_KGCO2E_PER_KWH:   0.1011,     // NZ grid electricity, Tadpole ACE 2025
  LINE_LOSS_KGCO2E_KWH:  0.0077,     // line losses, Tadpole ACE 2025
  // Combined: 0.1088 kgCO2e/kWh — the canonical figure on the methodology page

  // Office energy load (BRANZ + EECA, item 6 + item 20)
  POWER_W_PER_SQM:       50,         // BRANZ commercial office benchmark
  SQM_PER_PERSON:        10,         // GPG + BCO standard
  OFFICE_DAYS_YR:        230,        // standard NZ working year

  // Office waste (Tadpole ACE 2025, item 15)
  WASTE_KG_PER_DAY:      0.5,        // kg/day/person, Wellington office mid
  WASTE_KGCO2E_PER_KG:   0.584,      // landfill w/ gas recovery, Tadpole ACE 2025

  // Commute baseline (item 21, Wellington 15km RT mixed EV/ICE)
  COMMUTE_KGCO2E_DAY:    0.3,        // per-person/workday mid

  // 257 measured footprint (verified Tadpole ACE measurement, calc brief)
  BUILDING_FOOTPRINT_TCO2E_YR: 6.5,  // 257 in-office annual measured (Tadpole ACE 2025)
  BUILDING_CAPACITY:           80,   // approx daily occupancy across Base + Hub + Desk + meetings
  // Per-person-day share derived = (6.5 × 1000) / (80 × 230) ≈ 0.353 kgCO2e/person-day

  // Offset (public-facing claim)
  OFFSET_RATIO_PUBLIC:   2.0,        // 200% — the locked user-facing figure

  // Working pattern defaults
  HOURS_PER_DAY:         8,
  WEEKS_PER_YEAR:        46,         // 52 − 4 leave − 11 stat = 46 NZ standard

  // Reference: per-person-day at 257 (derived from building footprint)
  // ≈ 6500 kg / (80 × 230) = 0.353 kgCO2e/person-day
};

// Per-person-day share of the 257 building footprint
const PERSON_DAY_257 = (M.BUILDING_FOOTPRINT_TCO2E_YR * 1000) / (M.BUILDING_CAPACITY * M.OFFICE_DAYS_YR);

// --- Sources (URL-linked per calc redesign brief)

const SOURCES = {
  tadpole:    { label: "Tadpole ACE 2025 emission factors", name: "Tadpole ACE Carbon Calculator", url: "https://www.tadpole.co.nz/ace-carbon-calculator/" },
  mfe:        { label: "NZ grid 2025 baseline",             name: "MfE Measuring Emissions Guide 2025", url: "https://environment.govt.nz/publications/measuring-emissions-guide-2025/" },
  branz:      { label: "Office energy benchmark",           name: "BRANZ + EECA",                       url: "https://www.eeca.govt.nz/co-funding-and-support/products/commercial-buildings-decarbonisation-pathway/" },
  ecotricity: { label: "Toitū climate positive electricity at 125%", name: "Ecotricity",                 url: "https://ecotricity.co.nz/why-ecotricity/climate-positive" },
  ekos_nzu:   { label: "Ekos NZU carbon credits",           name: "Ekos · Our Projects",                url: "https://www.ekos.co.nz/our-projects" },
  ekos_bio:   { label: "Ekos BioCredita (biodiversity, NOT carbon offsets)", name: "Ekos BioCredita",   url: "https://www.ekos.co.nz/sdu-1" },
  maungatautari: { label: "Sanctuary Mountain Maungatautari", name: "Sanctuary Mountain",                url: "https://www.sanctuarymountain.co.nz/support/biodiversity-credits" },
};

// --- Compute

function fmtKg(n) {
  // Always render kgCO2e. Switch to t at ≥10,000 kg for readability, with one decimal.
  // Job 9 · '0 t' (with space) on zero — same shape the user sees pre-input.
  const rounded = Math.round(n);
  if (rounded === 0) return '0 t';
  if (Math.abs(rounded) >= 10000) {
    return `${(rounded / 1000).toFixed(1)} tCO₂e`;
  }
  return `${new Intl.NumberFormat("en-NZ").format(rounded)} kgCO₂e`;
}
function fmtKgSigned(n) {
  const sign = n < 0 ? "−" : "";
  return sign + fmtKg(Math.abs(n));
}

function compute(state) {
  const t = state.team;
  const d = state.daysPerWeek;
  const w = state.weeksPerYear;
  const hpd = state.hoursPerDay;
  const sqm = t * M.SQM_PER_PERSON;

  // Person-days at the office across the year (own-office side)
  const person_days_private = t * d * w;
  // Person-hours at the office across the year (used for derived metrics)
  const person_hours        = person_days_private * hpd;

  // PRIVATE OFFICE — operational carbon (kgCO2e/yr)
  // a. Electricity = W/m² × hours × days × m² → kWh × factor (incl. line losses)
  const power_kwh_yr      = (M.POWER_W_PER_SQM * hpd * w * d * sqm) / 1000;
  const private_power_kg  = power_kwh_yr * (M.GRID_KGCO2E_PER_KWH + M.LINE_LOSS_KGCO2E_KWH);
  // b. Waste = 0.5 kg/day/person × person-days × 0.584 kg CO2e/kg
  const private_waste_kg  = person_days_private * M.WASTE_KG_PER_DAY * M.WASTE_KGCO2E_PER_KG;
  // c. Commute = 0.3 kgCO2e/workday × person-days
  const private_commute_kg = person_days_private * M.COMMUTE_KGCO2E_DAY;

  const private_total = private_power_kg + private_waste_kg + private_commute_kg;

  const private_lines = [
    { key: "power",   amount: private_power_kg,   source: "branz",   note: `${sqm} m² @ 50 W/m², ${hpd}h × ${d} days × ${w} weeks` },
    { key: "waste",   amount: private_waste_kg,   source: "tadpole", note: `0.5 kg/person-day at 0.584 kgCO₂e/kg` },
    { key: "commute", amount: private_commute_kg, source: "tadpole", note: `Wellington 15km RT mixed EV/ICE mid` },
  ];

  // AT 257 — measured per-person-day × person-days
  const ours_total_kg = person_days_private * PERSON_DAY_257;
  // After 200% offset → NET = measured × (1 − 2.0) = − measured
  // (200% means we offset twice what we emit; net is the negative of the measured.)
  const ours_offset_kg = ours_total_kg * (1 - M.OFFSET_RATIO_PUBLIC);
  // "Carbon-positive" = the absolute magnitude of the negative
  const ours_positive_kg = Math.abs(ours_offset_kg);

  // Saving vs private office
  const saved_vs_private_kg = private_total - ours_total_kg;
  // Total avoided + offset (private − after-offset, signed)
  const net_avoided_kg = private_total - ours_offset_kg;

  return {
    private_lines, private_total,
    ours_total_kg,
    ours_offset_kg,        // signed, negative
    ours_positive_kg,      // absolute magnitude
    saved_vs_private_kg,
    net_avoided_kg,
    person_days_private, person_hours,
    inputs: { t, d, w, hpd, sqm },
  };
}

// --- State <-> URL

function readURL() {
  // Job 8 · zero-start. Cold load = all-zero state, all-zero result figures.
  const params = new URLSearchParams(window.location.search);
  const state = {
    team:          parseInt(params.get("team") || "0", 10),
    daysPerWeek:   parseInt(params.get("days") || "0", 10),
    weeksPerYear:  parseInt(params.get("weeks") || "0", 10),
    hoursPerDay:   parseInt(params.get("hours") || "0", 10),
  };
  state.team         = Math.max(0, Math.min(15, state.team));
  state.daysPerWeek  = Math.max(0, Math.min(5, state.daysPerWeek));
  state.weeksPerYear = Math.max(0, Math.min(52, state.weeksPerYear));
  state.hoursPerDay  = Math.max(0, Math.min(24, state.hoursPerDay));
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

const ROW_LABELS = {
  power:   "Office electricity",
  waste:   "Office waste to landfill",
  commute: "Team commute",
};

function renderResults(root, state, computed) {
  // private lines table
  const privateBody = root.querySelector('[data-calc-private-lines]');
  if (privateBody) {
    privateBody.innerHTML = computed.private_lines.map(l => `
      <div class="compare__row">
        <div class="compare__row-label">
          <span class="calc-source" tabindex="0">
            <span>${ROW_LABELS[l.key]}${l.note ? ` <span class="small" style="color: var(--colour-text-3);">— ${l.note}</span>` : ""}</span>
            <button class="info-trigger" aria-label="${SOURCES[l.source].label} source" type="button">i</button>
            <span class="calc-source__pop" role="tooltip">
              <span class="calc-source__pop-label">${SOURCES[l.source].label}</span>
              <a href="${SOURCES[l.source].url}" target="_blank" rel="noopener">${SOURCES[l.source].name} ↗</a>
            </span>
          </span>
        </div>
        <div class="compare__row-value">${fmtKg(l.amount)}</div>
      </div>
    `).join("");
  }

  const privateTotal = root.querySelector('[data-calc-private-total]');
  if (privateTotal) privateTotal.textContent = fmtKg(computed.private_total);

  // 257 side — measured row + offset row
  const oursBody = root.querySelector('[data-calc-ours-lines]');
  if (oursBody) {
    oursBody.innerHTML = `
      <div class="compare__row">
        <div class="compare__row-label">
          <span class="calc-source" tabindex="0">
            <span>Measured at 257 <span class="small" style="color: var(--colour-text-3);">— per-person-day share of 6.5 tCO₂e/yr building footprint</span></span>
            <button class="info-trigger" aria-label="Tadpole ACE 2025 measurement" type="button">i</button>
            <span class="calc-source__pop" role="tooltip">
              <span class="calc-source__pop-label">${SOURCES.tadpole.label}</span>
              <a href="${SOURCES.tadpole.url}" target="_blank" rel="noopener">${SOURCES.tadpole.name} ↗</a>
            </span>
          </span>
        </div>
        <div class="compare__row-value">${fmtKg(computed.ours_total_kg)}</div>
      </div>
      <div class="compare__row">
        <div class="compare__row-label">
          <span class="calc-source" tabindex="0">
            <span>Offset at 200% <span class="small" style="color: var(--colour-text-3);">— Ekos NZUs + Ecotricity Toitū climate positive electricity</span></span>
            <button class="info-trigger" aria-label="Carbon offsets — Ekos + Ecotricity" type="button">i</button>
            <span class="calc-source__pop" role="tooltip">
              <span class="calc-source__pop-label">${SOURCES.ecotricity.label}</span>
              <a href="${SOURCES.ecotricity.url}" target="_blank" rel="noopener">${SOURCES.ecotricity.name} ↗</a>
            </span>
          </span>
        </div>
        <div class="compare__row-value">−${fmtKg(computed.ours_total_kg * M.OFFSET_RATIO_PUBLIC)}</div>
      </div>
    `;
  }

  const oursTotal = root.querySelector('[data-calc-ours-total]');
  if (oursTotal) oursTotal.textContent = fmtKgSigned(computed.ours_offset_kg);

  // Headline carbon-positive figure — may appear in multiple slots
  // (sidebar mini-total + headline strip). Update every matching slot.
  root.querySelectorAll('[data-calc-positive-figure]').forEach(el => el.textContent = fmtKg(computed.ours_positive_kg));
  root.querySelectorAll('[data-calc-saved-figure]').forEach(el => el.textContent = fmtKg(computed.saved_vs_private_kg));
  root.querySelectorAll('[data-calc-net-figure]').forEach(el => el.textContent = fmtKg(computed.net_avoided_kg));

  // ESG-export panel mirror values
  document.querySelectorAll('[data-calc-export-private]').forEach(el => el.textContent = fmtKg(computed.private_total));
  document.querySelectorAll('[data-calc-export-ours]').forEach(el => el.textContent = fmtKg(computed.ours_total_kg));
  document.querySelectorAll('[data-calc-export-offset]').forEach(el => el.textContent = fmtKgSigned(computed.ours_offset_kg));
  document.querySelectorAll('[data-calc-export-team]').forEach(el => el.textContent = state.team);
  document.querySelectorAll('[data-calc-export-days]').forEach(el => el.textContent = state.daysPerWeek);
  document.querySelectorAll('[data-calc-export-weeks]').forEach(el => el.textContent = state.weeksPerYear);
}

// --- Tooltip click handler

function bindSourceTooltips(root) {
  root.addEventListener("click", (e) => {
    const trigger = e.target.closest(".info-trigger");
    if (trigger) {
      const wrap = trigger.closest(".calc-source");
      const isOpen = wrap.dataset.open === "true";
      root.querySelectorAll(".calc-source[data-open='true']").forEach(el => el.dataset.open = "false");
      wrap.dataset.open = isOpen ? "false" : "true";
      e.stopPropagation();
    } else {
      root.querySelectorAll(".calc-source[data-open='true']").forEach(el => el.dataset.open = "false");
    }
  });
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
    state.team = Math.max(0, Math.min(15, n));
    if (teamOut) teamOut.value = state.team;
    if (teamDec) teamDec.disabled = state.team <= 0;
    if (teamInc) teamInc.disabled = state.team >= 15;
    rerender();
  }
  teamDec?.addEventListener("click", () => updateTeam(state.team - 1));
  teamInc?.addEventListener("click", () => updateTeam(state.team + 1));

  // Days/week segmented · Job 8: empty input falls back to 0.
  root.addEventListener("change", (e) => {
    if (e.target.matches("[data-calc-days]")) {
      state.daysPerWeek = parseInt(e.target.value, 10);
      rerender();
      return;
    }
    if (e.target.matches("[data-calc-weeks]")) {
      state.weeksPerYear = Math.max(0, Math.min(52, parseInt(e.target.value, 10) || 0));
      rerender();
      return;
    }
    if (e.target.matches("[data-calc-hours]")) {
      state.hoursPerDay = Math.max(0, Math.min(24, parseInt(e.target.value, 10) || 0));
      rerender();
      return;
    }
  });

  // "Copy citation block" placeholder for Phase 4
  const copyBtn = root.querySelector('[data-calc-copy-citation]');
  if (copyBtn) {
    copyBtn.addEventListener("click", () => {
      const block = root.querySelector('[data-calc-citation-block]');
      if (!block || !navigator.clipboard) return;
      navigator.clipboard.writeText(block.innerText.replace(/\s+/g, " ").trim()).then(() => {
        const original = copyBtn.textContent;
        copyBtn.textContent = "Copied";
        setTimeout(() => { copyBtn.textContent = original; }, 1600);
      });
    });
  }

  return rerender;
}

function initCalcOfficeCarbon(root) {
  if (!root) return;
  const state = readURL();

  // sync initial UI · Job 8: pre-select / pre-fill only when URL state non-zero.
  const teamOut = root.querySelector('[data-calc-team-out]');
  if (teamOut) teamOut.value = state.team;
  if (state.daysPerWeek > 0) {
    root.querySelectorAll('[data-calc-days]').forEach(input => { input.checked = (parseInt(input.value, 10) === state.daysPerWeek); });
  } else {
    root.querySelectorAll('[data-calc-days]').forEach(input => { input.checked = false; });
  }
  const weeksInput = root.querySelector('[data-calc-weeks]');
  if (weeksInput) weeksInput.value = state.weeksPerYear > 0 ? state.weeksPerYear : '';
  const hoursInput = root.querySelector('[data-calc-hours]');
  if (hoursInput) hoursInput.value = state.hoursPerDay > 0 ? state.hoursPerDay : '';

  bindSourceTooltips(root);
  const rerender = bindEvents(root, state);
  rerender();
}

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll('[data-js="calc-office-carbon"]').forEach(initCalcOfficeCarbon);
});
