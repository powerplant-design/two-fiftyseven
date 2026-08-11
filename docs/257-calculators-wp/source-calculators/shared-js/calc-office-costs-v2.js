/**
 * two/fiftyseven · Phase 2.3 · Wellington Office Cost Calculator (v2)
 * ----------------------------------------------------------------------------
 * Industry-leading public utility tool. Configures a Wellington office cost
 * top-to-bottom: rent, outgoings, utilities, cleaning, consumables,
 * compliance, insurance, furniture amortisation, admin overhead, lease
 * legals, optional booking software, plus an arbitrary list of custom lines.
 *
 * Live, client-side, vanilla JS. Used only on /calculator/office-costs/.
 *
 * Markup contract:
 *   Root: [data-js="calc-office-costs-v2"]
 *   Inputs: [data-occv2-*] attributes (see VARIABLE LIST in the page brief)
 *   Hidden sinks (descendants of root) receive computed values:
 *     [data-occv2-sink-annual-total]
 *     [data-occv2-sink-monthly-total]
 *     [data-occv2-sink-per-person-month]
 *     [data-occv2-sink-per-person-day]
 *     [data-occv2-sink-per-sqm-yr]
 *     [data-occv2-sink-line-{key}]  (rent, outgoings, furniture, internet,
 *       power, cleaning, kb, insurance, firstAid, fireWarden, admin,
 *       legals, booking, custom)
 *     [data-occv2-sink-cat-{key}]    (cat-rent-opex, cat-utilities,
 *       cat-cleaning-kb, cat-compliance-insurance, cat-furniture-admin-legals,
 *       cat-addons-custom)
 *     [data-occv2-sink-cat-pct-{key}]  (same keys, percentage values)
 *     [data-occv2-sink-line-note-{key}] (short derivation string)
 *     [data-occv2-sink-line-source-{key}] (primary source URL)
 *     [data-occv2-sink-line-source-label-{key}] (short source label)
 *     [data-occv2-sink-value-add-{key}] (living-wage, carbon, climate-power,
 *       giving, mhfr, total)
 *
 * Pure DOM-attached engine. Dispatches `occv2:rendered` on the root after
 * each tick so the page coordinator can re-paint the breakdown / value-add
 * sections from the sinks.
 *
 * Scenario API (window.occv2):
 *   saveScenario(slot, name)
 *   loadScenario(slot)
 *   clearScenario(slot)
 *   getScenarioSnapshot(slot) → {name, annualTotal, perPerson, state}
 *
 * Pattern mirrors calc-meeting-costs.js for engine consistency.
 * ============================================================================
 */

(function () {
  'use strict';

  // ---------------------------------------------------------------------------
  // CONSTANTS · grade + precinct modifiers
  // ---------------------------------------------------------------------------
  var GRADE_MODIFIER = {
    'A-grade':           1.35,
    'B-grade fitted':    1.00,
    'B-grade unfitted':  0.78,
    'C-grade':           0.62
  };
  var PRECINCT_MODIFIER = {
    'CBD core':   1.15,
    'CBD fringe': 1.00,
    'Te Aro':     0.92,
    'Thorndon':   1.05,
    'Lambton':    1.20,
    'Kelburn':    0.85,
    'Mt Vic':     0.95
  };

  // ---------------------------------------------------------------------------
  // DEFAULTS · authoritative defaults for tooltip use + initial state
  // (Used by the engine when an input is empty; also exported on window.occv2.)
  // ---------------------------------------------------------------------------
  var DEFAULTS = {
    teamSize: 0,
    daysPerWeek: 5,
    grade: 'B-grade fitted',
    precinct: 'CBD core',
    rentPerSqmPerYr: 310,
    sqmPerPerson: 9,
    outgoingsPctOfRent: 0.27,
    furniturePerPerson: 2000,
    furnitureAmortYrs: 5,
    internetPerMo: 200,
    powerWattsPerSqm: 50,
    powerHoursPerYear: 1840,
    powerPricePerKwh: 0.30,
    cleaningHoursPerSqmPerYr: 1.2,
    cleaningPerHour: 45,
    kbPerPersonPerYr: 300,
    insurancePerPersonPerYr: 200,
    firstAidPerPersonPerYr: 28,
    fireWardenPerPersonPerYr: 18,
    adminPctOfHours: 0.06,
    adminLoadedHourly: 70,
    leaseLegalsOneOff: 3500,
    leaseTermYears: 3,
    bookingSoftwareCost: 8,
    bookingSoftware: false
  };

  // ---------------------------------------------------------------------------
  // SOURCE links per line · used to populate the breakdown rows + tooltips
  // ---------------------------------------------------------------------------
  var SOURCES = {
    rent:       { label: 'Colliers NZ',     href: 'https://www.colliers.co.nz/en-nz/research' },
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
    custom:     { label: 'Your line',           href: '#methodology' }
  };

  // ---------------------------------------------------------------------------
  // HELPERS · number formatting + rounding
  // ---------------------------------------------------------------------------
  function fmtMoney(n) {
    if (!isFinite(n)) n = 0;
    return '$' + Math.round(n).toLocaleString('en-NZ');
  }
  function fmtPct(n) {
    if (!isFinite(n)) n = 0;
    return Math.round(n * 100) + '%';
  }
  function toNum(v, d) {
    var n = parseFloat(v);
    return isNaN(n) ? d : n;
  }
  function clamp(n, lo, hi) {
    if (n < lo) return lo;
    if (n > hi) return hi;
    return n;
  }

  // ---------------------------------------------------------------------------
  // STATE READER · pulls live values out of the DOM
  // ---------------------------------------------------------------------------
  function readState(root) {
    function val(sel)     { var el = root.querySelector(sel); return el ? el.value : ''; }
    function num(sel, d)  { return toNum(val(sel), d); }
    function checked(sel) { var el = root.querySelector(sel); return !!(el && el.checked); }

    // Team size is the spine · read from data-occv2-team-size input.
    var team = Math.max(0, Math.min(500, num('[data-occv2-team-size]', DEFAULTS.teamSize)));

    // Per-person days/week (array). If no per-person inputs exist, fall back
    // to a single days-per-week input or the default.
    var perPersonDays = [];
    root.querySelectorAll('[data-occv2-days-per-week]').forEach(function (el) {
      perPersonDays.push(clamp(toNum(el.value, DEFAULTS.daysPerWeek), 1, 7));
    });
    // If no per-person inputs but a team is set, treat every member as the
    // default days-per-week so engine math still works.
    while (perPersonDays.length < team) perPersonDays.push(DEFAULTS.daysPerWeek);

    var avgDaysPerWeek = team > 0
      ? perPersonDays.slice(0, team).reduce(function (a, b) { return a + b; }, 0) / team
      : DEFAULTS.daysPerWeek;

    var grade    = val('[data-occv2-grade]:checked') || val('[data-occv2-grade]') || DEFAULTS.grade;
    var precinct = val('[data-occv2-precinct]') || DEFAULTS.precinct;

    var bookingToggle = checked('[data-occv2-booking-toggle]');

    // Custom rows · repeating
    var customLines = [];
    root.querySelectorAll('[data-occv2-custom-row]').forEach(function (row) {
      var lbl = row.querySelector('[data-occv2-custom-label]');
      var v   = row.querySelector('[data-occv2-custom-value]');
      var label = (lbl && lbl.value || '').trim();
      var value = v ? parseFloat(v.value) : NaN;
      if (label && !isNaN(value) && value > 0) {
        customLines.push({ label: label, value: value });
      }
    });

    return {
      teamSize: team,
      perPersonDays: perPersonDays,
      avgDaysPerWeek: avgDaysPerWeek,
      grade: grade,
      precinct: precinct,
      rentPerSqmPerYr:         num('[data-occv2-rent-sqm]',       DEFAULTS.rentPerSqmPerYr),
      sqmPerPerson:            num('[data-occv2-sqm-pp]',         DEFAULTS.sqmPerPerson),
      outgoingsPctOfRent:      num('[data-occv2-outgoings-pct]',  DEFAULTS.outgoingsPctOfRent),
      furniturePerPerson:      num('[data-occv2-furniture-pp]',   DEFAULTS.furniturePerPerson),
      furnitureAmortYrs:       num('[data-occv2-furniture-yrs]',  DEFAULTS.furnitureAmortYrs),
      internetPerMo:           num('[data-occv2-internet-mo]',    DEFAULTS.internetPerMo),
      powerWattsPerSqm:        num('[data-occv2-power-w-sqm]',    DEFAULTS.powerWattsPerSqm),
      powerHoursPerYear:       num('[data-occv2-power-hrs]',      DEFAULTS.powerHoursPerYear),
      powerPricePerKwh:        num('[data-occv2-power-kwh]',      DEFAULTS.powerPricePerKwh),
      cleaningHoursPerSqmPerYr:num('[data-occv2-cleaning-hr-sqm]',DEFAULTS.cleaningHoursPerSqmPerYr),
      cleaningPerHour:         num('[data-occv2-cleaning-hr]',    DEFAULTS.cleaningPerHour),
      kbPerPersonPerYr:        num('[data-occv2-kb-pp]',          DEFAULTS.kbPerPersonPerYr),
      insurancePerPersonPerYr: num('[data-occv2-insurance-pp]',   DEFAULTS.insurancePerPersonPerYr),
      firstAidPerPersonPerYr:  num('[data-occv2-first-aid-pp]',   DEFAULTS.firstAidPerPersonPerYr),
      fireWardenPerPersonPerYr:num('[data-occv2-fire-warden-pp]', DEFAULTS.fireWardenPerPersonPerYr),
      adminPctOfHours:         num('[data-occv2-admin-pct]',      DEFAULTS.adminPctOfHours),
      adminLoadedHourly:       num('[data-occv2-admin-rate]',     DEFAULTS.adminLoadedHourly),
      leaseLegalsOneOff:       num('[data-occv2-legals]',         DEFAULTS.leaseLegalsOneOff),
      leaseTermYears:          num('[data-occv2-lease-yrs]',      DEFAULTS.leaseTermYears),
      bookingSoftware:         bookingToggle,
      bookingSoftwareCost:     num('[data-occv2-booking-cost]',   DEFAULTS.bookingSoftwareCost),
      customLines:             customLines
    };
  }

  // ---------------------------------------------------------------------------
  // COMPUTE · pure function over state → result lines + totals
  // ---------------------------------------------------------------------------
  function compute(s) {
    // Zero state · no team, no figures.
    if (!s.teamSize || s.teamSize <= 0) {
      return zeroResult();
    }

    var sqmTotal = s.teamSize * s.sqmPerPerson;

    var gradeMod = GRADE_MODIFIER[s.grade]    || 1.0;
    var precMod  = PRECINCT_MODIFIER[s.precinct] || 1.0;

    var rent       = sqmTotal * s.rentPerSqmPerYr * gradeMod * precMod;
    var outgoings  = rent * s.outgoingsPctOfRent;
    var furniture  = s.furnitureAmortYrs > 0
      ? (s.teamSize * s.furniturePerPerson) / s.furnitureAmortYrs : 0;
    var internet   = s.internetPerMo * 12;
    var power      = (sqmTotal * s.powerWattsPerSqm * s.powerHoursPerYear * s.powerPricePerKwh) / 1000;
    var cleaning   = sqmTotal * s.cleaningHoursPerSqmPerYr * s.cleaningPerHour;
    var kb         = s.teamSize * s.kbPerPersonPerYr;
    var insurance  = s.teamSize * s.insurancePerPersonPerYr;
    var firstAid   = s.teamSize * s.firstAidPerPersonPerYr;
    var fireWarden = s.teamSize * s.fireWardenPerPersonPerYr;

    var adminHours = s.teamSize * s.powerHoursPerYear * s.adminPctOfHours;
    var admin      = adminHours * s.adminLoadedHourly;
    var legals     = s.leaseTermYears > 0 ? s.leaseLegalsOneOff / s.leaseTermYears : 0;

    var bookingActive = s.bookingSoftware || s.teamSize >= 10;
    var booking = bookingActive ? s.teamSize * s.bookingSoftwareCost * 12 : 0;

    var customSum = 0;
    s.customLines.forEach(function (cl) { customSum += cl.value; });

    var annualTotal = rent + outgoings + furniture + internet + power + cleaning
                    + kb + insurance + firstAid + fireWarden + admin + legals
                    + booking + customSum;

    // Per-person-day uses the team-average days-per-week.
    var workingDaysPerYear = (s.avgDaysPerWeek || 5) * 46; // 46 weeks net of leave/holidays
    var perPersonDay = (s.teamSize > 0 && workingDaysPerYear > 0)
      ? annualTotal / s.teamSize / workingDaysPerYear : 0;

    // Lines · each with derivation note + source key.
    var lines = [
      { key: 'rent',       label: 'Rent',                    value: rent,
        note: '$' + Math.round(s.rentPerSqmPerYr) + '/m²/yr × ' + s.sqmPerPerson + ' m²/pp × ' + s.teamSize + ' people × ' + gradeMod.toFixed(2) + ' (' + s.grade + ') × ' + precMod.toFixed(2) + ' (' + s.precinct + ')' },
      { key: 'outgoings',  label: 'Outgoings',               value: outgoings,
        note: Math.round(s.outgoingsPctOfRent * 100) + '% of rent' },
      { key: 'furniture',  label: 'Furniture (amortised)',   value: furniture,
        note: '$' + Math.round(s.furniturePerPerson) + '/pp × ' + s.teamSize + ' ÷ ' + s.furnitureAmortYrs + ' yrs' },
      { key: 'internet',   label: 'Internet',                value: internet,
        note: '$' + Math.round(s.internetPerMo) + '/mo business fibre × 12' },
      { key: 'power',      label: 'Power',                   value: power,
        note: s.powerWattsPerSqm + ' W/m² × ' + sqmTotal + ' m² × ' + s.powerHoursPerYear + ' hrs × $' + s.powerPricePerKwh.toFixed(2) + '/kWh' },
      { key: 'cleaning',   label: 'Cleaning',                value: cleaning,
        note: s.cleaningHoursPerSqmPerYr + ' hr/m²/yr × $' + Math.round(s.cleaningPerHour) + '/hr × ' + sqmTotal + ' m²' },
      { key: 'kb',         label: 'Kitchen + bathroom',      value: kb,
        note: '$' + Math.round(s.kbPerPersonPerYr) + '/pp/yr consumables' },
      { key: 'insurance',  label: 'Insurance',               value: insurance,
        note: '$' + Math.round(s.insurancePerPersonPerYr) + '/pp/yr combined' },
      { key: 'firstAid',   label: 'First aid training',      value: firstAid,
        note: '$' + Math.round(s.firstAidPerPersonPerYr) + '/pp/yr (H&S Act 2015 compliance)' },
      { key: 'fireWarden', label: 'Fire warden training',    value: fireWarden,
        note: '$' + Math.round(s.fireWardenPerPersonPerYr) + '/pp/yr (FENZ requirement)' },
      { key: 'admin',      label: 'Admin time',              value: admin,
        note: Math.round(s.adminPctOfHours * 100) + '% of team hours × $' + Math.round(s.adminLoadedHourly) + '/hr loaded' },
      { key: 'legals',     label: 'Lease legals (amortised)', value: legals,
        note: '$' + Math.round(s.leaseLegalsOneOff).toLocaleString('en-NZ') + ' one-off ÷ ' + s.leaseTermYears + ' yr term' }
    ];
    if (bookingActive) {
      lines.push({ key: 'booking', label: 'Booking software', value: booking,
        note: '$' + s.bookingSoftwareCost + '/pp/mo × ' + s.teamSize + ' × 12 (auto-on at team ≥ 10)' });
    }
    s.customLines.forEach(function (cl, i) {
      lines.push({ key: 'custom-' + i, baseKey: 'custom', label: cl.label,
        value: cl.value, note: 'Custom line you added' });
    });

    // Category breakdown
    var categories = {
      'rent-opex':                rent + outgoings,
      'utilities':                internet + power,
      'cleaning-kb':              cleaning + kb,
      'compliance-insurance':     insurance + firstAid + fireWarden,
      'furniture-admin-legals':   furniture + admin + legals,
      'addons-custom':            booking + customSum
    };
    var categoryPct = {};
    Object.keys(categories).forEach(function (k) {
      categoryPct[k] = annualTotal > 0 ? categories[k] / annualTotal : 0;
    });

    // Value-add quantification (Job 11)
    var livingWageDelta   = 7.92 * sqmTotal;               // $7.92/m²/yr
    var carbonOffset      = 1.25 * s.teamSize;             // $1.25/pp/yr
    var climatePowerPrem  = power * 0.05;                  // 5% on power
    var giving            = 1 * s.teamSize * s.powerHoursPerYear; // $1/hr
    var mhfr              = (445 * Math.ceil(s.teamSize / 12)) / 2.5;
    var valueAddTotal     = livingWageDelta + carbonOffset + climatePowerPrem + giving + mhfr;

    return {
      sqmTotal: sqmTotal,
      annualTotal: annualTotal,
      monthlyTotal: annualTotal / 12,
      perPersonMonth: s.teamSize > 0 ? (annualTotal / s.teamSize / 12) : 0,
      perPersonDay: perPersonDay,
      perSqmYr: sqmTotal > 0 ? annualTotal / sqmTotal : 0,
      bookingActive: bookingActive,
      lines: lines,
      categories: categories,
      categoryPct: categoryPct,
      valueAdd: {
        livingWage:    livingWageDelta,
        carbon:        carbonOffset,
        climatePower:  climatePowerPrem,
        giving:        giving,
        mhfr:          mhfr,
        total:         valueAddTotal
      }
    };
  }

  function zeroResult() {
    return {
      sqmTotal: 0,
      annualTotal: 0,
      monthlyTotal: 0,
      perPersonMonth: 0,
      perPersonDay: 0,
      perSqmYr: 0,
      bookingActive: false,
      lines: [],
      categories: {
        'rent-opex': 0, 'utilities': 0, 'cleaning-kb': 0,
        'compliance-insurance': 0, 'furniture-admin-legals': 0, 'addons-custom': 0
      },
      categoryPct: {
        'rent-opex': 0, 'utilities': 0, 'cleaning-kb': 0,
        'compliance-insurance': 0, 'furniture-admin-legals': 0, 'addons-custom': 0
      },
      valueAdd: { livingWage: 0, carbon: 0, climatePower: 0, giving: 0, mhfr: 0, total: 0 }
    };
  }

  // ---------------------------------------------------------------------------
  // RENDER · writes computed values into hidden sinks; emits an event for the
  // coordinator to paint visible breakdown rows + category grid.
  // ---------------------------------------------------------------------------
  function render(root, result) {
    // Sinks live both inside the calc root (hidden, for coordinator JSON
    // reads) AND scattered across the page (visible cells in the value-add
    // table, breakdown headers, etc.). Always write to document scope so
    // every consumer updates.
    function set(sel, v) {
      document.querySelectorAll(sel).forEach(function (el) { el.textContent = v; });
    }
    set('[data-occv2-sink-annual-total]',     fmtMoney(result.annualTotal));
    set('[data-occv2-sink-monthly-total]',    fmtMoney(result.monthlyTotal));
    set('[data-occv2-sink-per-person-month]', fmtMoney(result.perPersonMonth));
    set('[data-occv2-sink-per-person-day]',   fmtMoney(result.perPersonDay));
    set('[data-occv2-sink-per-sqm-yr]',       fmtMoney(result.perSqmYr));
    set('[data-occv2-sink-sqm-total]',        Math.round(result.sqmTotal).toLocaleString('en-NZ') + ' m²');

    // Per-line sinks (engine still writes these for any consumers; coordinator
    // also reads the JSON dump for full row context including notes/sources).
    result.lines.forEach(function (l) {
      var sinkKey = l.baseKey || l.key;
      set('[data-occv2-sink-line-' + sinkKey + ']', fmtMoney(l.value));
    });

    // Category breakdown
    Object.keys(result.categories).forEach(function (k) {
      set('[data-occv2-sink-cat-' + k + ']',     fmtMoney(result.categories[k]));
      set('[data-occv2-sink-cat-pct-' + k + ']', fmtPct(result.categoryPct[k]));
    });

    // Value-add sinks
    set('[data-occv2-sink-value-add-living-wage]',   fmtMoney(result.valueAdd.livingWage));
    set('[data-occv2-sink-value-add-carbon]',        fmtMoney(result.valueAdd.carbon));
    set('[data-occv2-sink-value-add-climate-power]', fmtMoney(result.valueAdd.climatePower));
    set('[data-occv2-sink-value-add-giving]',        fmtMoney(result.valueAdd.giving));
    set('[data-occv2-sink-value-add-mhfr]',          fmtMoney(result.valueAdd.mhfr));
    set('[data-occv2-sink-value-add-total]',         fmtMoney(result.valueAdd.total));

    // JSON dump of lines so coordinator can render the breakdown table with
    // notes + source labels in one go (mirrors the MC pattern).
    var linesJson = JSON.stringify(result.lines.map(function (l) {
      var srcKey = l.baseKey || l.key;
      var src = SOURCES[srcKey] || SOURCES.custom;
      return {
        key: l.key, baseKey: l.baseKey || l.key,
        label: l.label, note: l.note, value: l.value,
        sourceLabel: src.label, sourceHref: src.href
      };
    }));
    var jsonEl = root.querySelector('[data-occv2-sink-lines-json]');
    if (jsonEl) jsonEl.textContent = linesJson;

    var catJson = JSON.stringify({
      'rent-opex':              { value: result.categories['rent-opex'],              pct: result.categoryPct['rent-opex'],              label: 'Rent + outgoings' },
      'utilities':              { value: result.categories['utilities'],              pct: result.categoryPct['utilities'],              label: 'Utilities' },
      'cleaning-kb':            { value: result.categories['cleaning-kb'],            pct: result.categoryPct['cleaning-kb'],            label: 'Cleaning + consumables' },
      'compliance-insurance':   { value: result.categories['compliance-insurance'],   pct: result.categoryPct['compliance-insurance'],   label: 'Compliance + insurance' },
      'furniture-admin-legals': { value: result.categories['furniture-admin-legals'], pct: result.categoryPct['furniture-admin-legals'], label: 'Furniture + admin + legals' },
      'addons-custom':          { value: result.categories['addons-custom'],          pct: result.categoryPct['addons-custom'],          label: 'Add-ons + custom' }
    });
    var catEl = root.querySelector('[data-occv2-sink-categories-json]');
    if (catEl) catEl.textContent = catJson;

    root.dispatchEvent(new CustomEvent('occv2:rendered', { detail: result }));
  }

  // ---------------------------------------------------------------------------
  // SCENARIO STORAGE · 3 named slots backed by localStorage
  // ---------------------------------------------------------------------------
  var SCENARIO_STORAGE_KEY = 'occv2-scenarios';

  function readScenarios() {
    try {
      var raw = window.localStorage.getItem(SCENARIO_STORAGE_KEY);
      if (!raw) return [null, null, null];
      var parsed = JSON.parse(raw);
      if (!Array.isArray(parsed)) return [null, null, null];
      while (parsed.length < 3) parsed.push(null);
      return parsed.slice(0, 3);
    } catch (e) {
      return [null, null, null];
    }
  }
  function writeScenarios(arr) {
    try {
      window.localStorage.setItem(SCENARIO_STORAGE_KEY, JSON.stringify(arr));
    } catch (e) { /* quota / privacy mode */ }
  }

  // Capture the full DOM-input state as a snapshot object.
  function snapshotState(root) {
    var snap = { v: 1, inputs: {} };
    root.querySelectorAll('input, select').forEach(function (el) {
      // Find any data-occv2-* attribute on this input.
      for (var i = 0; i < el.attributes.length; i++) {
        var a = el.attributes[i];
        if (a.name.indexOf('data-occv2-') === 0) {
          var key = a.name;
          if (el.type === 'checkbox') {
            snap.inputs[key] = !!el.checked;
          } else if (el.type === 'radio') {
            if (el.checked) snap.inputs[key] = el.value;
          } else {
            snap.inputs[key] = el.value;
          }
        }
      }
    });
    // Custom rows are repeating · capture them separately.
    snap.customLines = [];
    root.querySelectorAll('[data-occv2-custom-row]').forEach(function (row) {
      var lbl = row.querySelector('[data-occv2-custom-label]');
      var v   = row.querySelector('[data-occv2-custom-value]');
      snap.customLines.push({
        label: lbl ? lbl.value : '',
        value: v   ? v.value   : ''
      });
    });
    return snap;
  }

  function applyState(root, snap) {
    if (!snap || !snap.inputs) return;
    // Reset checkboxes first (so omitted ones become false).
    root.querySelectorAll('input[type="checkbox"][data-occv2-booking-toggle]').forEach(function (el) {
      el.checked = false;
    });
    Object.keys(snap.inputs).forEach(function (attr) {
      var els = root.querySelectorAll('[' + attr + ']');
      var val = snap.inputs[attr];
      els.forEach(function (el) {
        if (el.type === 'checkbox') {
          el.checked = !!val;
        } else if (el.type === 'radio') {
          el.checked = (el.value === val);
        } else {
          el.value = val;
        }
      });
    });
    // Restore custom-line rows (delegate to coordinator if available).
    if (typeof window.occv2.restoreCustomLines === 'function') {
      window.occv2.restoreCustomLines(snap.customLines || []);
    }
  }

  // ---------------------------------------------------------------------------
  // INIT · wire input + change events; expose scenario API on window.occv2
  // ---------------------------------------------------------------------------
  function init() {
    var root = document.querySelector('[data-js="calc-office-costs-v2"]');
    if (!root) return;

    function tick() {
      var state  = readState(root);
      var result = compute(state);
      render(root, result);
    }

    root.addEventListener('input',  tick);
    root.addEventListener('change', tick);

    // Stepper buttons for team-size (per-MC pattern).
    root.querySelectorAll('[data-occv2-team-dec]').forEach(function (b) {
      b.addEventListener('click', function () {
        var inp = root.querySelector('[data-occv2-team-size]');
        if (!inp) return;
        var v = Math.max(0, parseInt(inp.value, 10) - 1);
        inp.value = v;
        var disp = root.querySelector('[data-occv2-team-display]');
        if (disp) disp.textContent = v;
        tick();
        root.dispatchEvent(new CustomEvent('occv2:team-changed', { detail: { team: v } }));
      });
    });
    root.querySelectorAll('[data-occv2-team-inc]').forEach(function (b) {
      b.addEventListener('click', function () {
        var inp = root.querySelector('[data-occv2-team-size]');
        if (!inp) return;
        var v = Math.min(500, parseInt(inp.value, 10) + 1);
        inp.value = v;
        var disp = root.querySelector('[data-occv2-team-display]');
        if (disp) disp.textContent = v;
        tick();
        root.dispatchEvent(new CustomEvent('occv2:team-changed', { detail: { team: v } }));
      });
    });

    // Expose scenario API on window.occv2.
    window.occv2 = window.occv2 || {};
    window.occv2.defaults  = DEFAULTS;
    window.occv2.sources   = SOURCES;
    window.occv2.compute   = compute;
    window.occv2.readState = function () { return readState(root); };
    window.occv2.tick      = tick;

    window.occv2.saveScenario = function (slot, name) {
      var i = clamp(parseInt(slot, 10) - 1, 0, 2);
      var all = readScenarios();
      var state = snapshotState(root);
      var result = compute(readState(root));
      all[i] = {
        name: (name || ('Scenario ' + (i + 1))).slice(0, 40),
        savedAt: Date.now(),
        state: state,
        annualTotal: result.annualTotal,
        perPersonMonth: result.perPersonMonth,
        teamSize: readState(root).teamSize
      };
      writeScenarios(all);
      return all[i];
    };
    window.occv2.loadScenario = function (slot) {
      var i = clamp(parseInt(slot, 10) - 1, 0, 2);
      var all = readScenarios();
      var s = all[i];
      if (!s) return null;
      applyState(root, s.state);
      tick();
      return s;
    };
    window.occv2.clearScenario = function (slot) {
      var i = clamp(parseInt(slot, 10) - 1, 0, 2);
      var all = readScenarios();
      all[i] = null;
      writeScenarios(all);
    };
    window.occv2.getScenarioSnapshot = function (slot) {
      var i = clamp(parseInt(slot, 10) - 1, 0, 2);
      var all = readScenarios();
      return all[i] || null;
    };
    window.occv2.listScenarios = function () { return readScenarios(); };

    tick();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
