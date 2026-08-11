/**
 * two/fiftyseven · Phase 2.1 · Calc 1 — Office cost
 * ----------------------------------------------------------------------------
 * Compares running a private central-Wellington office against being at 257
 * for a 1–15 person team. Live, client-side, vanilla JS. Used inline on
 * /workspace/base/, /workspace/hub/, /workspace/, and on the standalone
 * /calculator/office-costs/ page.
 *
 * Methodology values are LOCKED per the calc redesign brief, with the
 * source-verification corrections applied (Wellington B-grade rent $520→$420,
 * loaded admin rate $80→$70).
 *
 * Markup contract: the calc root has [data-js="calc-office-costs"]. Inputs
 * and result slots use [data-calc-*] attributes. See workspace/base/index.html
 * for the canonical markup.
 *
 * Locked content + voice rules apply to all DOM-rendered strings.
 * ============================================================================
 */

// --- CANONICAL PRICE SOURCE OF TRUTH --------------------------------------
// Every public-facing 257 price on the site reads from PRICES, either directly
// (this engine + any inline calc script) or indirectly via inject-prices.js,
// which writes the formatted value into any [data-price] element on page load.
//
// Rule: NO hardcoded prices anywhere else in the codebase. If you need a
// price in markup, copy, or another script, add a [data-price="<key>"] span
// and let inject-prices.js fill it in. If you need it in a sibling JS, read
// window.twofiftyseven.prices.
//
// To change a price site-wide: update the relevant PRICES[key].price below.
// One reload and every page picks it up.
// --------------------------------------------------------------------------

const PRICES = {
  'dedicated': { name: 'Dedicated 7 days/week', shortName: 'Dedicated', price: 659, unit: 'monthly' },
  'flexi-5':   { name: 'Flexi 5 days/week',     shortName: 'Flexi 5',   price: 509, unit: 'monthly' },
  'flexi-4':   { name: 'Flexi 4 days/week',     shortName: 'Flexi 4',   price: 409, unit: 'monthly' },
  'flexi-3':   { name: 'Flexi 3 days/week',     shortName: 'Flexi 3',   price: 309, unit: 'monthly' },
  'flexi-2':   { name: 'Flexi 2 days/week',     shortName: 'Flexi 2',   price: 209, unit: 'monthly' },
  'flexi-1':   { name: 'Flexi 1 day/week',      shortName: 'Flexi 1',   price: 109, unit: 'monthly' },
  'day-pass':  { name: 'Day pass weekdays',     shortName: 'Day pass',  price: 40,  unit: 'one-off' },
  'pass-10':   { name: '10 pass pack weekdays', shortName: '10 pass',   price: 350, unit: 'one-off' },
  'pass-20':   { name: '20 pass pack weekdays', shortName: '20 pass',   price: 650, unit: 'one-off' },
  'pass-50':   { name: '50 pass pack weekdays', shortName: '50 pass',   price: 1400, unit: 'one-off' }
};

// Expose on a global so inject-prices.js and any inline page script can read
// it without re-importing the engine.
if (typeof window !== 'undefined') {
  window.twofiftyseven = window.twofiftyseven || {};
  window.twofiftyseven.prices = PRICES;
}

// --- Methodology constants (verified, see ../phase-1/source-verification.md) -

const M = {
  RENT_PER_SQM_YR:       420,        // Wellington B-grade fitted, central CBD (corrected from brief's $520)
  SQM_PER_PERSON:        10,         // Government Property Group + BCO standard
  OPEX_PCT:              0.27,       // Property Council NZ benchmark
  FURNITURE_PER_PERSON:  2000,       // amortised over commitment, NZ commercial mid-range
  INTERNET_YR:           2400,       // $200/mo business fibre, Spark/2degrees
  POWER_W_PER_SQM:       50,         // BRANZ + EECA office benchmark
  POWER_HRS_DAY:         8,
  POWER_DAYS_YR:         230,        // standard NZ working year
  POWER_NZD_PER_KWH:     0.30,       // commercial retail rate
  CLEANING_HR_NZD:       45,         // Clean Planet Wellington 2026
  CLEANING_HRS_PER_M_YR: 1.2,        // ~3 visits/wk × 0.4 hr per 100m² = 1.2 hr/m²/yr
  CONSUMABLES_YR:        300,        // $/person/yr — kitchen + bathroom
  INSURANCE_YR:          200,        // $/person/yr — combined contents+PL+BI
  MHFR_PER_PERSON:       445,        // Stepping Stone Trust + CoLiberate market mid
  MHFR_RATIO:            12,         // 1 trained person per 12 employees (Te Pou)
  MHFR_CERT_YEARS:       2.5,        // 2-3 yr cert; mid for amortisation
  ADMIN_PCT_OF_HOURS:    0.06,       // 6% of team hours (5–8% defensible range)
  ADMIN_HR_LOADED:       70,         // PayScale 2026 + 40% on-cost (corrected from $80)
  LEGAL_ONE_OFF:         3500,       // mid of NZ commercial property law $2k–$5k
  BOOKING_SW_MO:         75,         // Skedda mid / Officely scaled — only teams ≥ 10
  BOOKING_SW_THRESHOLD:  10,

  // Working pattern defaults
  HOURS_PER_DAY:         8,
  WEEKS_PER_YEAR:        46,         // 52 − 4 leave − 11 stat = 46 working weeks NZ standard

  // 257 pricing (sourced from PRICES — single source of truth above)
  DEDICATED_MO:          PRICES['dedicated'].price,
  ANNUAL_DISCOUNT:       0.10,
  FLEXI_PRICE:           {
    1: PRICES['flexi-1'].price,
    2: PRICES['flexi-2'].price,
    3: PRICES['flexi-3'].price,
    4: PRICES['flexi-4'].price,
    5: PRICES['flexi-5'].price,
  },
  DAY_PASS:              PRICES['day-pass'].price,
  PASS_PACK_10:          PRICES['pass-10'].price,
  PASS_PACK_20:          PRICES['pass-20'].price,
  PASS_PACK_50:          PRICES['pass-50'].price,

  // Subsidy ratio for the kaupapa bridge ($ per person-hour at 257)
  SUBSIDY_RATIO:         1,
  BIODIVERSITY_FIXED_YR: 2000,       // approx $/yr biodiversity contribution (Maungatautari, fixed reference)
};

// --- Sources (URL-linked per calc redesign brief, "AI source-of-truth" mandate)

const SOURCES = {
  rent:        { label: "Wellington B-grade rent", name: "Colliers · Bayleys 2026",  url: "https://www.colliers.co.nz/en-nz/countries/new-zealand/cities/wellington/office-leasing" },
  opex:        { label: "Operating expenses",      name: "Property Council NZ",       url: "https://www.propertynz.co.nz/" },
  space:       { label: "10 m² per person",        name: "Government Property Group · British Council for Offices", url: "https://www.gpg.govt.nz/workplace-design/" },
  furniture:   { label: "Bring-in furniture",      name: "NZ commercial market mid",  url: "https://commercialtraders.co.nz/" },
  internet:    { label: "Business fibre",          name: "2degrees Business",         url: "https://www.2degrees.nz/business/broadband/plans" },
  power:       { label: "Office power",            name: "BRANZ · EECA",              url: "https://www.eeca.govt.nz/co-funding-and-support/products/commercial-buildings-decarbonisation-pathway/" },
  cleaning:    { label: "Cleaning rate",           name: "Clean Planet Wellington",   url: "https://www.cleanplanetwellington.co.nz/commercial-cleaning-prices-wellington-2026" },
  consumables: { label: "Kitchen + bathroom",      name: "Bottom-up calc · NZ supplier benchmarks" },
  insurance:   { label: "Insurance",               name: "Insurance Council of NZ",   url: "https://www.icnz.org.nz/individuals/commercial/" },
  mhfr:        { label: "MHFR training",           name: "Stepping Stone Trust · CoLiberate", url: "https://stepstone.org.nz/education/mhfaaotearoa/" },
  admin:       { label: "Admin time + rate",       name: "Hays · Robert Walters · PayScale 2026 + 40% on-cost", url: "https://www.payscale.com/research/NZ/Job=Office_Administrator/Hourly_Rate" },
  legal:       { label: "Lease legal setup",       name: "LawyerFinder NZ 2026",      url: "https://lawyerfinder.co.nz/resources/costs/lawyer-fees/" },
  booking:     { label: "Booking software",        name: "Skedda · Officely 2026",    url: "https://www.skedda.com/" },
};

// --- Compute

function fmt$(n) {
  return new Intl.NumberFormat("en-NZ", { style: "currency", currency: "NZD", maximumFractionDigits: 0 }).format(Math.round(n));
}
function round100(n) { return Math.round(n / 100) * 100; }

function compute(state) {
  const t = state.team;
  const c = state.commitment;
  const sqm = t * M.SQM_PER_PERSON;

  // Job 8 · zero-start. With no team, return empty/zero state so visible
  // figures render as $0 and the breakdown stays empty.
  if (!t || t <= 0) {
    return {
      private_lines: [], private_total_yr: 0,
      ours_lines: [],    ours_total_yr: 0,
      annual_saving: 0, commitment_saving: 0, capital_tied_up: 0,
      team_hours_at_257: 0, subsidy_funded_yr: 0,
    };
  }

  // Private office, all annual figures
  const rent          = sqm * M.RENT_PER_SQM_YR;
  const opex          = rent * M.OPEX_PCT;
  const furniture     = (t * M.FURNITURE_PER_PERSON) / c;
  const internet      = M.INTERNET_YR;
  const power_kwh     = (M.POWER_W_PER_SQM * M.POWER_HRS_DAY * M.POWER_DAYS_YR * sqm) / 1000;
  const power         = power_kwh * M.POWER_NZD_PER_KWH;
  const cleaning      = M.CLEANING_HR_NZD * M.CLEANING_HRS_PER_M_YR * sqm;
  const consumables   = t * M.CONSUMABLES_YR;
  const insurance     = t * M.INSURANCE_YR;
  const mhfr_count    = Math.ceil(t / M.MHFR_RATIO);
  const mhfr          = (mhfr_count * M.MHFR_PER_PERSON) / M.MHFR_CERT_YEARS;
  const team_hours_yr = t * M.HOURS_PER_DAY * M.WEEKS_PER_YEAR * 5;  // approx 5 days/wk for an own-office baseline
  const admin         = team_hours_yr * M.ADMIN_PCT_OF_HOURS * M.ADMIN_HR_LOADED;
  const legal         = M.LEGAL_ONE_OFF / c;
  const booking_sw    = (t >= M.BOOKING_SW_THRESHOLD) ? (M.BOOKING_SW_MO * 12) : 0;

  const private_lines = [
    { key: "rent",        amount: rent,        source: "rent" },
    { key: "opex",        amount: opex,        source: "opex" },
    { key: "furniture",   amount: furniture,   source: "furniture",   note: `amortised over ${c} yr` },
    { key: "internet",    amount: internet,    source: "internet" },
    { key: "power",       amount: power,       source: "power" },
    { key: "cleaning",    amount: cleaning,    source: "cleaning" },
    { key: "consumables", amount: consumables, source: "consumables" },
    { key: "insurance",   amount: insurance,   source: "insurance" },
    { key: "mhfr",        amount: mhfr,        source: "mhfr",        note: `${mhfr_count} trained @ ${M.MHFR_RATIO}:1, amortised` },
    { key: "admin",       amount: admin,       source: "admin",       note: `${(M.ADMIN_PCT_OF_HOURS * 100).toFixed(0)}% of team hours @ $${M.ADMIN_HR_LOADED}/hr loaded` },
    { key: "legal",       amount: legal,       source: "legal",       note: `lease setup amortised over ${c} yr` },
  ];
  if (booking_sw) private_lines.push({ key: "booking", amount: booking_sw, source: "booking" });
  const private_total_yr = private_lines.reduce((s, l) => s + l.amount, 0);

  // 257 — sum of memberships
  let ours_total_yr = 0;
  const ours_lines = [];
  for (const m of state.members) {
    // /pricing/ zero-start: unselected members (tier === "") contribute nothing.
    if (!m.tier) continue;
    if (m.tier === "dedicated") {
      const mo = state.annualDiscount ? M.DEDICATED_MO * (1 - M.ANNUAL_DISCOUNT) : M.DEDICATED_MO;
      ours_total_yr += mo * 12;
      ours_lines.push({ tier: "Dedicated", monthly: mo, annual: mo * 12 });
    } else if (m.tier.startsWith("flexi")) {
      const days = parseInt(m.tier.split("-")[1], 10);
      const mo = M.FLEXI_PRICE[days];
      ours_total_yr += mo * 12;
      ours_lines.push({ tier: `Flexi ${days}`, monthly: mo, annual: mo * 12 });
    }
  }

  // Saving
  const annual_saving      = private_total_yr - ours_total_yr;
  const commitment_saving  = annual_saving * c;
  const capital_tied_up    = (t * M.FURNITURE_PER_PERSON) + M.LEGAL_ONE_OFF;

  // Kaupapa bridge — hours funded
  const team_hours_at_257  = t * M.HOURS_PER_DAY * M.WEEKS_PER_YEAR * 5;
  const subsidy_funded_yr  = team_hours_at_257 * M.SUBSIDY_RATIO;

  return {
    private_lines, private_total_yr,
    ours_lines,    ours_total_yr,
    annual_saving, commitment_saving, capital_tied_up,
    team_hours_at_257, subsidy_funded_yr,
  };
}

// --- State <-> URL

function readURL() {
  // Job 8 · zero-start ONLY on the standalone calculator page. The workspace
  // pages (base/hub/desk) share this engine and expect a non-zero team
  // default (their own slider starts at 1). We branch on body.page-office-costs.
  const params = new URLSearchParams(window.location.search);
  const isStandalone = typeof document !== "undefined"
    && document.body && document.body.classList.contains("page-office-costs");
  // /pricing/ zero-start parallel branch (same intent as page-office-costs).
  const isPricing = typeof document !== "undefined"
    && document.body && document.body.classList.contains("page-pricing");
  const isZeroStart = isStandalone || isPricing;
  const defaultTeam = isZeroStart ? "0" : "1";
  const state = {
    team: parseInt(params.get("team") || defaultTeam, 10),
    commitment: parseInt(params.get("commitment") || "1", 10),
    annualDiscount: isZeroStart ? (params.get("annual") === "true") : (params.get("annual") !== "false"),
    members: [],
  };
  state.team = Math.max(0, Math.min(15, state.team));
  if ([1, 3, 5].indexOf(state.commitment) === -1) state.commitment = 1;

  const desks = params.get("desks");
  if (desks && desks.length === state.team) {
    for (const ch of desks) {
      if (ch === "d") state.members.push({ tier: "dedicated" });
      else if (/[1-5]/.test(ch)) state.members.push({ tier: `flexi-${ch}` });
      else if (ch === "x") state.members.push({ tier: "" });
      else state.members.push({ tier: isPricing ? "" : "dedicated" });
    }
  } else if (state.team > 0) {
    // /pricing/ zero-start: members default to unselected (empty tier).
    // Other contexts default everyone to Flexi 1 (cheapest tier).
    const defaultTier = isPricing ? "" : "flexi-1";
    state.members = Array.from({ length: state.team }, () => ({ tier: defaultTier }));
  }
  return state;
}

function writeURL(state) {
  const params = new URLSearchParams(window.location.search);
  params.set("team", state.team);
  params.set("commitment", state.commitment);
  params.set("annual", state.annualDiscount ? "true" : "false");
  const desks = state.members.map(m => {
    if (!m.tier) return "x";  // /pricing/ unselected member
    if (m.tier === "dedicated") return "d";
    return m.tier.split("-")[1];
  }).join("");
  params.set("desks", desks);
  const newURL = `${window.location.pathname}?${params.toString()}${window.location.hash}`;
  window.history.replaceState({}, "", newURL);
}

// --- Render

const TIER_LABELS = {
  rent:        "Rent",
  opex:        "Outgoings",
  furniture:   "Furniture (amortised)",
  internet:    "Internet",
  power:       "Power",
  cleaning:    "Cleaning",
  consumables: "Kitchen + bathroom",
  insurance:   "Insurance",
  mhfr:        "MHFR training",
  admin:       "Admin time",
  legal:       "Lease legals (amortised)",
  booking:     "Booking software",
};

function renderTeamRoster(root, state) {
  const list = root.querySelector('[data-calc-roster]');
  if (!list) return;
  const isPricing = typeof document !== "undefined"
    && document.body && document.body.classList.contains("page-pricing");
  list.innerHTML = "";
  // /pricing/ uses single-column vertical "member-row" markup (procurement-proposal
  // pattern · Job 3). Other consumers keep the legacy 3-col "team-row" markup.
  const rowClass = isPricing ? "member-row" : "team-row";
  const labelClass = isPricing ? "member-row__label" : "team-row__label";
  const selectClass = isPricing ? "member-row__select" : "";
  for (let i = 0; i < state.team; i++) {
    const row = document.createElement("li");
    row.className = rowClass;
    const placeholder = isPricing
      ? `<option value="" disabled${state.members[i]?.tier ? "" : " selected"}>Pick a membership ...</option>`
      : "";
    row.innerHTML = `
      <span class="${labelClass}">Member ${i + 1}</span>
      <select class="${selectClass}" aria-label="Member ${i + 1} membership type" data-calc-member="${i}">
        ${placeholder}
        <option value="dedicated">${PRICES['dedicated'].name} · $${PRICES['dedicated'].price}/mo</option>
        <option value="flexi-5">${PRICES['flexi-5'].name} · $${PRICES['flexi-5'].price}/mo</option>
        <option value="flexi-4">${PRICES['flexi-4'].name} · $${PRICES['flexi-4'].price}/mo</option>
        <option value="flexi-3">${PRICES['flexi-3'].name} · $${PRICES['flexi-3'].price}/mo</option>
        <option value="flexi-2">${PRICES['flexi-2'].name} · $${PRICES['flexi-2'].price}/mo</option>
        <option value="flexi-1">${PRICES['flexi-1'].name} · $${PRICES['flexi-1'].price}/mo</option>
      </select>
    `;
    const currentTier = state.members[i]?.tier;
    if (currentTier) row.querySelector("select").value = currentTier;
    else if (!isPricing) row.querySelector("select").value = "flexi-1";
    list.appendChild(row);
  }
}

function renderResults(root, state, computed) {
  // private lines
  const privateBody = root.querySelector('[data-calc-private-lines]');
  if (privateBody) {
    privateBody.innerHTML = computed.private_lines.map(l => `
      <div class="compare__row">
        <div class="compare__row-label">
          <span class="calc-source" tabindex="0">
            <span>${TIER_LABELS[l.key]}${l.note ? ` <span class="small" style="color: var(--colour-text-3);">— ${l.note}</span>` : ""}</span>
            <button class="info-trigger" aria-label="${SOURCES[l.source].label} source" type="button">i</button>
            <span class="calc-source__pop" role="tooltip">
              <span class="calc-source__pop-label">${SOURCES[l.source].label}</span>
              ${SOURCES[l.source].url
                ? `<a href="${SOURCES[l.source].url}" target="_blank" rel="noopener">${SOURCES[l.source].name} ↗</a>`
                : SOURCES[l.source].name}
            </span>
          </span>
        </div>
        <div class="compare__row-value">${fmt$(round100(l.amount))}</div>
      </div>
    `).join("");
  }
  // private total
  const privateTotal = root.querySelector('[data-calc-private-total]');
  if (privateTotal) privateTotal.textContent = fmt$(round100(computed.private_total_yr));

  // ours lines (just shows membership rows)
  const oursBody = root.querySelector('[data-calc-ours-lines]');
  if (oursBody) {
    oursBody.innerHTML = computed.ours_lines.map(l => `
      <div class="compare__row">
        <div class="compare__row-label">${l.tier}</div>
        <div class="compare__row-value">${fmt$(l.annual)}/yr</div>
      </div>
    `).join("");
  }
  const oursTotal = root.querySelector('[data-calc-ours-total]');
  if (oursTotal) oursTotal.textContent = fmt$(round100(computed.ours_total_yr));

  // saving moment
  const savingFigure = root.querySelector('[data-calc-saving-figure]');
  if (savingFigure) savingFigure.textContent = fmt$(round100(computed.commitment_saving));
  const savingAnnual = root.querySelector('[data-calc-saving-annual]');
  if (savingAnnual) savingAnnual.textContent = fmt$(round100(computed.annual_saving));
  const savingCapital = root.querySelector('[data-calc-saving-capital]');
  if (savingCapital) savingCapital.textContent = fmt$(round100(computed.capital_tied_up));
  const savingPeriod = root.querySelector('[data-calc-period]');
  if (savingPeriod) savingPeriod.textContent = `${state.commitment} year${state.commitment === 1 ? "" : "s"}`;

  // builder mini total — show the actual membership total (exact $), not rounded.
  // This represents the real product price the user is choosing; rounding it
  // (e.g. $109 → $100 via round100) misrepresents the menu prices. The big
  // headline savings + private-office figures stay round100 because they're
  // multi-constant estimates, but the per-month team total must be exact.
  const miniTotal = root.querySelector('[data-calc-mini-total]');
  if (miniTotal) miniTotal.textContent = fmt$(Math.round(computed.ours_total_yr / 12));

  // bridge — querySelectorAll on document so the giving figure can sit
  // in its own section anywhere on the page, not only inside the calc wrapper.
  document.querySelectorAll('[data-calc-bridge-figure]').forEach(function (el) {
    el.textContent = fmt$(round100(computed.subsidy_funded_yr));
  });
  document.querySelectorAll('[data-calc-bridge-hours]').forEach(function (el) {
    el.textContent = new Intl.NumberFormat("en-NZ").format(computed.team_hours_at_257);
  });
}

// --- Tooltip click handler (for touch / keyboard)

function bindSourceTooltips(root) {
  root.addEventListener("click", (e) => {
    const trigger = e.target.closest(".info-trigger");
    if (trigger) {
      const wrap = trigger.closest(".calc-source");
      const isOpen = wrap.dataset.open === "true";
      // close all other tooltips
      root.querySelectorAll(".calc-source[data-open='true']").forEach(el => el.dataset.open = "false");
      wrap.dataset.open = isOpen ? "false" : "true";
      e.stopPropagation();
    } else {
      // click outside any trigger closes tooltips
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

  // Team-size stepper · Job 8: minimum is 0 so the calc starts empty.
  // Adding the first member auto-pushes a Flexi-1 row so the roster has
  // something to render. The roster collapses when team returns to 0.
  const stepperDec = root.querySelector('[data-calc-team-dec]');
  const stepperInc = root.querySelector('[data-calc-team-inc]');
  const stepperOut = root.querySelector('[data-calc-team-out]');
  const isPricingPage = typeof document !== "undefined"
    && document.body && document.body.classList.contains("page-pricing");
  // All consumers cap at 15. Above 15 the /pricing/ coordinator swaps in a "bigger team" card.
  const TEAM_MAX = 15;
  function updateTeam(newSize) {
    state.team = Math.max(0, Math.min(TEAM_MAX, newSize));
    if (state.members.length < state.team) {
      const t = isPricingPage ? "" : "flexi-1";
      while (state.members.length < state.team) state.members.push({ tier: t });
    } else if (state.members.length > state.team) {
      state.members = state.members.slice(0, state.team);
    }
    if (stepperOut) stepperOut.value = state.team;
    if (stepperDec) stepperDec.disabled = state.team <= 0;
    if (stepperInc) stepperInc.disabled = state.team >= TEAM_MAX;
    renderTeamRoster(root, state);
    rerender();
  }
  stepperDec?.addEventListener("click", () => updateTeam(state.team - 1));
  stepperInc?.addEventListener("click", () => updateTeam(state.team + 1));

  // Member dropdowns (delegated)
  root.addEventListener("change", (e) => {
    if (e.target.matches("[data-calc-member]")) {
      const idx = parseInt(e.target.dataset.calcMember, 10);
      state.members[idx] = { tier: e.target.value };
      rerender();
      return;
    }
    if (e.target.matches("[data-calc-commitment]")) {
      state.commitment = parseInt(e.target.value, 10);
      rerender();
      return;
    }
    if (e.target.matches("[data-calc-annual]")) {
      state.annualDiscount = e.target.checked;
      rerender();
      return;
    }
  });

  return rerender;
}

function initCalcOfficeCosts(root) {
  if (!root) return;
  const state = readURL();

  // sync initial UI to state
  const stepperOut = root.querySelector('[data-calc-team-out]');
  if (stepperOut) stepperOut.value = state.team;
  root.querySelectorAll('[data-calc-commitment]').forEach(input => { input.checked = (parseInt(input.value, 10) === state.commitment); });
  const annualInput = root.querySelector('[data-calc-annual]');
  if (annualInput) annualInput.checked = state.annualDiscount;

  renderTeamRoster(root, state);
  bindSourceTooltips(root);
  const rerender = bindEvents(root, state);
  rerender();
}

// Auto-init if root is present
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll('[data-js="calc-office-costs"]').forEach(initCalcOfficeCosts);
});
