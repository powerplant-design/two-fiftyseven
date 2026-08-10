/**
 * two/fiftyseven · Phase 2.2 · Calc 4 — Meeting / workshop / away-day / event cost
 * ----------------------------------------------------------------------------
 * Compares running a meeting, workshop, away-day or event at a Wellington
 * industry-standard venue against running it at two/fiftyseven.
 *
 * Live, client-side, vanilla JS. Used only on /calculator/meeting-costs/.
 *
 * Markup contract: calc root has [data-js="calc-meeting-costs"]. Inputs use
 * [data-mc-*] attributes. Engine writes into hidden sinks inside the root —
 * a small page coordinator script reads sinks and routes values into the
 * visible result figures + breakdown rows.
 *
 * Pricing: MEETING_PRICES (below) is the canonical source of truth for 2/57
 * meeting / event space rates. Sibling to PRICES in calc-office-costs.js.
 *
 * Honest framing — DO NOT change:
 *   - Projector + sound system are SEPARATE small add-ons at maintenance-
 *     replacement cost. NOT "all-in", NOT "no AV surcharge".
 *   - Catering is free when the customer arranges it directly, charged at
 *     cost when we arrange it.
 *   - Climate-positive venue = 200% verified offset (not a fee item).
 * ============================================================================
 */

// --- 2/57 MEETING + EVENT SPACE PRICES (ex GST, single source of truth) ----
const MEETING_PRICES = {
  'meeting-room':        { name: 'Meeting room',                 capacity: 6,   day: 250,  evening: null, hour: 60  },
  'silver-linings':      { name: 'Silver Linings',               capacity: 12,  day: 590,  evening: 360,  hour: 120 },
  'studios-whataitai-ngake': { name: 'Studios Whātaitai + Ngake', capacity: 12, day: 490,  evening: 290,  hour: 90  },
  'workshop':            { name: 'Workshop space',               capacity: 36,  day: 690,  evening: 440,  hour: 180 },
  'event':               { name: 'Event space',                  capacity: 80,  day: 990,  evening: 590,  hour: 240 },
  'entire':              { name: 'Entire two/fiftyseven',        capacity: 200, day: 1490, evening: 790,  hour: 300 }
};

// 2/57 AV add-on rates (maintenance-replacement level, ex GST).
// Confirmed by Ash 2026-05-18: flat $50 per booking for projector and sound.
const MEETING_AV = {
  projector:  { name: 'Projector + screen', perDay: 50,  perHalfDay: 50, perHour: 50 },
  sound:      { name: 'Sound system + mic', perDay: 50,  perHalfDay: 50, perHour: 50 }
};

// 2/57 catering rate for tea + coffee (continuous, ex GST).
// Confirmed by Ash 2026-05-18: $5 per head.
const MEETING_TEA_PER_HEAD = 5;

// Expose globally for inject-prices.js / inline coordinator.
if (typeof window !== 'undefined') {
  window.twofiftyseven = window.twofiftyseven || {};
  window.twofiftyseven.meetingPrices = MEETING_PRICES;
  window.twofiftyseven.meetingAV = MEETING_AV;
}

// --- Industry-standard bands (Wellington / Auckland) ----------------------
// All low/high per the unit indicated. Conservative midmarket bands drawn
// from publicly listed Wellington venue hire pages (Te Papa events,
// Te Auaha, Generator, hotel conference facilities) + NZ corporate-catering
// market norms. FLAGGED — every band needs Ash sign-off before publish.
const IND = {
  // Room hire — per duration, scaled by group size band.
  ROOM: {
    'half-day': { small: [350, 650],  mid: [550, 1100], large: [900, 2200] },
    'full-day': { small: [550, 1100], mid: [850, 1900], large: [1500, 3800] },
    'multi-day':{ small: [550, 1100], mid: [850, 1900], large: [1500, 3800] }, // PER DAY — engine multiplies by day count below
    'evening':  { small: [350, 750],  mid: [600, 1300], large: [1100, 2600] },
    'hourly':   { small: [90, 180],   mid: [140, 280],  large: [220, 480] }
  },
  // Catering — per head bands (NZ).
  TEA:         [4, 8],         // per head (continuous tea + coffee, half-day basis)
  BREAKFAST:   [12, 22],       // light breakfast / pastries
  LUNCH_LIGHT: [20, 25],
  LUNCH_HEARTY:[30, 40],
  AFTERNOON:   [8, 15],
  DRINKS:      [25, 55],       // 2-3 drinks + light bites
  // AV — per booking (small event = half-day, full-day +30%, multi-day +120%)
  AV_PROJECTOR: [180, 350],
  AV_SOUND:     [280, 550],
  // Facilitation — flat bands. Updated 2026-05-18 from PayScale NZ +
  // NZ Facilitators + The Facilitators Network market data.
  FAC_HALF: [1500, 4500],
  FAC_FULL: [3500, 8000],
  FAC_SENIOR: [5000, 9000],
  // Materials
  MAT_WHITEBOARDS: [40, 120],
  MAT_POSTITS:     [25, 60],
  MAT_PRINTING:    [40, 150],
  // Setup
  SETUP_STD:     [80, 180],
  SETUP_COMPLEX: [250, 600]
};

function sizeBand(n) {
  if (n <= 10) return 'small';
  if (n <= 36) return 'mid';
  return 'large';
}

// Pick the 2/57 space for a given size + duration.
function pickSpace(size, duration) {
  if (size <= 6 && duration !== 'evening') return 'meeting-room';
  if (size <= 12 && duration === 'evening') return 'studios-whataitai-ngake';
  if (size <= 12) return 'studios-whataitai-ngake';
  if (size <= 36) return 'workshop';
  if (size <= 80) return 'event';
  return 'entire';
}

function durationFactor(duration, multiDayCount) {
  // Multiplier on AV / catering for full-day, multi-day vs half-day base.
  if (duration === 'hourly') return 0.4;
  if (duration === 'evening') return 0.7;
  if (duration === 'half-day') return 1.0;
  if (duration === 'full-day') return 1.3;
  if (duration === 'multi-day') return 1.3 * (multiDayCount || 2);
  return 1.0;
}

function roomDurationKey(duration) {
  if (duration === 'multi-day') return 'multi-day';
  if (duration === 'evening') return 'evening';
  if (duration === 'hourly') return 'hourly';
  if (duration === 'full-day') return 'full-day';
  return 'half-day';
}

function fmtBand(low, high) {
  if (Math.round(low) === Math.round(high)) return '$' + Math.round(low).toLocaleString('en-NZ');
  return '$' + Math.round(low).toLocaleString('en-NZ') + '–$' + Math.round(high).toLocaleString('en-NZ');
}
function fmtMoney(n) {
  return '$' + Math.round(n).toLocaleString('en-NZ');
}

function compute(state) {
  const size = state.size;
  // Job 11 — calc starts at zero. If the user hasn't entered a group size
  // or hasn't populated a single day's start+end time, return an all-zero
  // result and empty breakdown so nothing renders until inputs exist.
  if (!size || size <= 0 || !state.duration) {
    return {
      industry: { low: 0, high: 0, lines: [] },
      ours: { total: 0, lines: [], spaceKey: null },
      saving: { low: 0, high: 0 },
      spaceName: ''
    };
  }
  const band = sizeBand(size);
  const dur = state.duration;
  const daysCount = state.multiDayCount || 1;
  const durFactor = durationFactor(dur, daysCount);

  // --- INDUSTRY-STANDARD LINES (low + high) ---
  const lines = [];
  let indLow = 0, indHigh = 0;

  // Room
  const roomBandBase = IND.ROOM[roomDurationKey(dur)][band];
  const roomMultiplier = (dur === 'multi-day') ? daysCount : 1;
  const roomBand = [roomBandBase[0] * roomMultiplier, roomBandBase[1] * roomMultiplier];
  const roomNoteSuffix = (dur === 'multi-day') ? (' · ' + daysCount + ' days') : '';
  lines.push({ key: 'room', label: 'Room hire', note: 'Wellington venue · ' + dur + roomNoteSuffix + ' · ' + size + ' people', low: roomBand[0], high: roomBand[1] });
  indLow += roomBand[0]; indHigh += roomBand[1];

  // Catering
  let catLow = 0, catHigh = 0;
  const catNotes = [];
  if (state.tea)         { catLow += IND.TEA[0]*size; catHigh += IND.TEA[1]*size; catNotes.push('tea+coffee'); }
  if (state.breakfast)   { catLow += IND.BREAKFAST[0]*size; catHigh += IND.BREAKFAST[1]*size; catNotes.push('breakfast'); }
  if (state.lunchLight)  { catLow += IND.LUNCH_LIGHT[0]*size; catHigh += IND.LUNCH_LIGHT[1]*size; catNotes.push('light lunch'); }
  if (state.lunchHearty) { catLow += IND.LUNCH_HEARTY[0]*size; catHigh += IND.LUNCH_HEARTY[1]*size; catNotes.push('hearty lunch'); }
  if (state.afternoon)   { catLow += IND.AFTERNOON[0]*size; catHigh += IND.AFTERNOON[1]*size; catNotes.push('afternoon tea'); }
  if (state.drinks)      { catLow += IND.DRINKS[0]*size; catHigh += IND.DRINKS[1]*size; catNotes.push('drinks'); }
  // Multi-day repeats catering per day
  if (dur === 'multi-day') { catLow *= daysCount; catHigh *= daysCount; }
  if (catLow + catHigh > 0) {
    lines.push({ key: 'catering', label: 'Catering', note: catNotes.join(' · ') + ' × ' + size, low: catLow, high: catHigh });
    indLow += catLow; indHigh += catHigh;
  }

  // AV
  let avLow = 0, avHigh = 0;
  const avNotes = [];
  if (state.avProjector) { avLow += IND.AV_PROJECTOR[0]*durFactor; avHigh += IND.AV_PROJECTOR[1]*durFactor; avNotes.push('projector'); }
  if (state.avSound)     { avLow += IND.AV_SOUND[0]*durFactor;     avHigh += IND.AV_SOUND[1]*durFactor;     avNotes.push('sound'); }
  if (avLow + avHigh > 0) {
    lines.push({ key: 'av', label: 'AV', note: avNotes.join(' + '), low: avLow, high: avHigh });
    indLow += avLow; indHigh += avHigh;
  }

  // Facilitation
  if (state.fac === 'half')   { lines.push({ key:'facilitation', label:'Facilitation', note:'External facilitator · half-day', low: IND.FAC_HALF[0], high: IND.FAC_HALF[1] }); indLow += IND.FAC_HALF[0]; indHigh += IND.FAC_HALF[1]; }
  if (state.fac === 'full')   { lines.push({ key:'facilitation', label:'Facilitation', note:'External facilitator · full-day', low: IND.FAC_FULL[0], high: IND.FAC_FULL[1] }); indLow += IND.FAC_FULL[0]; indHigh += IND.FAC_FULL[1]; }
  if (state.fac === 'senior') { lines.push({ key:'facilitation', label:'Facilitation', note:'Senior / multi-day facilitator', low: IND.FAC_SENIOR[0], high: IND.FAC_SENIOR[1] }); indLow += IND.FAC_SENIOR[0]; indHigh += IND.FAC_SENIOR[1]; }

  // Materials
  let matLow = 0, matHigh = 0;
  const matNotes = [];
  if (state.matBoards)   { matLow += IND.MAT_WHITEBOARDS[0]; matHigh += IND.MAT_WHITEBOARDS[1]; matNotes.push('whiteboards'); }
  if (state.matPostits)  { matLow += IND.MAT_POSTITS[0];     matHigh += IND.MAT_POSTITS[1];     matNotes.push('post-its+pens'); }
  if (state.matPrinting) { matLow += IND.MAT_PRINTING[0];    matHigh += IND.MAT_PRINTING[1];    matNotes.push('printing'); }
  if (matLow + matHigh > 0) {
    lines.push({ key: 'materials', label: 'Materials', note: matNotes.join(' · '), low: matLow, high: matHigh });
    indLow += matLow; indHigh += matHigh;
  }

  // Setup
  if (state.setup === 'standard') { lines.push({ key:'setup', label:'Setup + pack-down', note:'Standard room reset', low: IND.SETUP_STD[0], high: IND.SETUP_STD[1] }); indLow += IND.SETUP_STD[0]; indHigh += IND.SETUP_STD[1]; }
  if (state.setup === 'complex')  { lines.push({ key:'setup', label:'Setup + pack-down', note:'Complex reset / multi-room', low: IND.SETUP_COMPLEX[0], high: IND.SETUP_COMPLEX[1] }); indLow += IND.SETUP_COMPLEX[0]; indHigh += IND.SETUP_COMPLEX[1]; }

  // Custom — repeating lines (new) + legacy single fallback
  if (Array.isArray(state.customLines) && state.customLines.length) {
    state.customLines.forEach(function (cl) {
      if (cl && cl.label && cl.value > 0) {
        lines.push({ key:'custom', label: cl.label, note:'Custom line you added', low: cl.value, high: cl.value });
        indLow += cl.value; indHigh += cl.value;
      }
    });
  } else if (state.customLabel && state.customValue > 0) {
    lines.push({ key:'custom', label: state.customLabel, note:'Custom line you added', low: state.customValue, high: state.customValue });
    indLow += state.customValue; indHigh += state.customValue;
  }

  // --- 2/57 LINES ---
  const ourLines = [];
  const spaceKey = pickSpace(size, dur);
  const space = MEETING_PRICES[spaceKey];
  let roomRate = 0, roomNote = '';
  if (dur === 'hourly') {
    roomRate = (space.hour || space.day) * 3; // assume 3hr typical hourly booking
    roomNote = space.name + ' · $' + (space.hour || 0) + '/hr × 3 hr';
  } else if (dur === 'evening' && space.evening) {
    roomRate = space.evening;
    roomNote = space.name + ' · evening rate';
  } else if (dur === 'multi-day') {
    roomRate = space.day * daysCount;
    roomNote = space.name + ' · day rate × ' + daysCount;
  } else if (dur === 'half-day') {
    roomRate = Math.round(space.day * 0.6);
    roomNote = space.name + ' · half-day (60% of day)';
  } else {
    roomRate = space.day;
    roomNote = space.name + ' · day rate';
  }
  ourLines.push({ key:'room', label:'Room', note: roomNote, value: roomRate });
  let oursTotal = roomRate;

  // Tea + coffee — $5/pp continuous (ex GST)
  if (state.tea) {
    var teaCost = MEETING_TEA_PER_HEAD * size;
    ourLines.push({ key:'tea', label:'Tea + coffee', note:'$5/head · continuous', value: teaCost });
    oursTotal += teaCost;
  }

  // Catering — free when arranged directly by customer; charged at cost when 2/57 arranges.
  // Engine uses industry midpoint for the comparison figure.
  let oursCatering = 0;
  const catBits = [];
  function add(perHead, label) { const v = Math.round(perHead * size); oursCatering += v; catBits.push(label); }
  if (state.breakfast)   add((IND.BREAKFAST[0]+IND.BREAKFAST[1])/2, 'breakfast');
  if (state.lunchLight)  add((IND.LUNCH_LIGHT[0]+IND.LUNCH_LIGHT[1])/2, 'light lunch');
  if (state.lunchHearty) add((IND.LUNCH_HEARTY[0]+IND.LUNCH_HEARTY[1])/2, 'hearty lunch');
  if (state.afternoon)   add((IND.AFTERNOON[0]+IND.AFTERNOON[1])/2, 'afternoon tea');
  if (state.drinks)      add((IND.DRINKS[0]+IND.DRINKS[1])/2, 'drinks');
  if (dur === 'multi-day') oursCatering *= daysCount;
  if (oursCatering > 0) {
    ourLines.push({ key:'catering', label:'Catering', note:'Free when you arrange it directly, charged at cost when we arrange it.', value: oursCatering });
    oursTotal += oursCatering;
  }

  // AV add-ons at maintenance rate
  function avRate(item) {
    if (dur === 'hourly')  return item.perHour * 3;
    if (dur === 'half-day' || dur === 'evening') return item.perHalfDay;
    if (dur === 'multi-day') return item.perDay * daysCount;
    return item.perDay;
  }
  if (state.avProjector) { const v = avRate(MEETING_AV.projector); ourLines.push({ key:'av-proj', label:'Projector + screen', note:'Maintenance-replacement rate · separate add-on', value: v }); oursTotal += v; }
  if (state.avSound)     { const v = avRate(MEETING_AV.sound);     ourLines.push({ key:'av-sound', label:'Sound system + mic', note:'Maintenance-replacement rate · separate add-on', value: v }); oursTotal += v; }

  // Facilitation, materials, custom — pass through at industry mid (not a 2/57 service)
  function midOf(b) { return Math.round((b[0]+b[1])/2); }
  if (state.fac === 'half')   { const v = midOf(IND.FAC_HALF);   ourLines.push({ key:'fac', label:'Facilitation', note:'Bring your own facilitator (industry mid shown)', value: v }); oursTotal += v; }
  if (state.fac === 'full')   { const v = midOf(IND.FAC_FULL);   ourLines.push({ key:'fac', label:'Facilitation', note:'Bring your own facilitator (industry mid shown)', value: v }); oursTotal += v; }
  if (state.fac === 'senior') { const v = midOf(IND.FAC_SENIOR); ourLines.push({ key:'fac', label:'Facilitation', note:'Bring your own facilitator (industry mid shown)', value: v }); oursTotal += v; }

  // Materials (provided in-house mostly free, but printing/post-its passed through)
  if (state.matBoards || state.matPostits || state.matPrinting) {
    let v = 0; const bits = [];
    if (state.matBoards)   { bits.push('whiteboards + flipcharts (included)'); }
    if (state.matPostits)  { v += 30; bits.push('post-its + pens'); }
    if (state.matPrinting) { v += 60; bits.push('printing'); }
    ourLines.push({ key:'materials', label:'Materials', note: bits.join(' · '), value: v });
    oursTotal += v;
  }

  if (state.setup === 'complex') { ourLines.push({ key:'setup', label:'Complex setup', note:'Multi-room or non-standard reset', value: 200 }); oursTotal += 200; }

  if (Array.isArray(state.customLines) && state.customLines.length) {
    state.customLines.forEach(function (cl) {
      if (cl && cl.label && cl.value > 0) {
        ourLines.push({ key:'custom', label: cl.label, note:'Custom line you added', value: cl.value });
        oursTotal += cl.value;
      }
    });
  } else if (state.customLabel && state.customValue > 0) {
    ourLines.push({ key:'custom', label: state.customLabel, note:'Custom line you added', value: state.customValue });
    oursTotal += state.customValue;
  }

  // Impact Discount — 50% off the 2/57 total only (not the industry band)
  let impact = 0;
  if (state.impact) {
    impact = Math.round(oursTotal * 0.5);
    ourLines.push({ key:'impact', label:'Impact Discount', note:'50% off for charities, NGOs, indigenous-led, community, social enterprise', value: -impact });
    oursTotal -= impact;
  }

  const savingLow = Math.max(0, indLow - oursTotal);
  const savingHigh = Math.max(0, indHigh - oursTotal);

  return {
    spaceKey, spaceName: space.name,
    industry: { low: Math.round(indLow), high: Math.round(indHigh), lines },
    ours: { total: Math.round(oursTotal), lines: ourLines },
    saving: { low: Math.round(savingLow), high: Math.round(savingHigh) }
  };
}

// --- DOM wiring ------------------------------------------------------------

/**
 * Derive the duration key from explicit start + end times (+ optional
 * multi-day flag). Returns { duration, hours } where duration ∈
 * 'half-day' | 'full-day' | 'multi-day' | 'evening' | 'hourly'.
 *
 * Rules:
 *   - multi-day flag wins
 *   - start >= 17:00 → 'evening'
 *   - duration >= 6 hours AND start before noon → 'full-day'
 *   - 3 <= duration < 6 → 'half-day'
 *   - duration < 3 → 'hourly'
 *   - duration >= 6 hours but start ≥ noon → still 'full-day' (catch-all)
 */
function deriveDuration(startStr, endStr, multiDay) {
  function toMin(s) {
    if (!s || typeof s !== 'string' || s.indexOf(':') < 0) return null;
    var p = s.split(':');
    return (parseInt(p[0], 10) || 0) * 60 + (parseInt(p[1], 10) || 0);
  }
  var s = toMin(startStr); var e = toMin(endStr);
  if (s === null || e === null) return { duration: 'full-day', hours: 8 };
  var hours = Math.max(0, (e - s) / 60);
  if (multiDay) return { duration: 'multi-day', hours: hours };
  if (s >= 17 * 60) return { duration: 'evening', hours: hours };
  if (hours < 3)   return { duration: 'hourly', hours: hours };
  if (hours < 6)   return { duration: 'half-day', hours: hours };
  return { duration: 'full-day', hours: hours };
}

if (typeof window !== 'undefined') {
  window.twofiftyseven.deriveDuration = deriveDuration;
}

function readState(root) {
  function val(sel) { const el = root.querySelector(sel); return el ? el.value : ''; }
  function checked(sel) { const el = root.querySelector(sel); return !!(el && el.checked); }
  function num(sel, d) { const v = parseFloat(val(sel)); return isNaN(v) ? d : v; }

  // Jobs 6–9 — read repeating day-rows + derive duration from total hours +
  // populated day count. No more multi-day toggle, no more days numeric input.
  function toMin(s) {
    if (!s || typeof s !== 'string' || s.indexOf(':') < 0) return null;
    var p = s.split(':'); return (parseInt(p[0], 10) || 0) * 60 + (parseInt(p[1], 10) || 0);
  }
  // Convert visible 12h "HH:MM" + AM/PM badge into 24h "HH:MM" for the engine.
  // Returns null if the user hasn't filled both digits or the value is invalid.
  function to24h(rawValue, period) {
    if (!rawValue || typeof rawValue !== 'string') return null;
    var m = rawValue.match(/^(\d{1,2}):(\d{2})$/);
    if (!m) return null;
    var hh = parseInt(m[1], 10);
    var mm = parseInt(m[2], 10);
    if (isNaN(hh) || isNaN(mm) || mm < 0 || mm > 59 || hh < 1 || hh > 12) return null;
    if (period === 'pm' && hh !== 12) hh += 12;
    else if (period === 'am' && hh === 12) hh = 0;
    return (hh < 10 ? '0' : '') + hh + ':' + (mm < 10 ? '0' : '') + mm;
  }
  var dayRows = root.querySelectorAll('[data-mc-day-row]');
  var days = [];
  dayRows.forEach(function (row) {
    var sIn = row.querySelector('[data-mc-day-start]');
    var eIn = row.querySelector('[data-mc-day-end]');
    var sPer = row.querySelector('[data-mc-day-start-period]');
    var ePer = row.querySelector('[data-mc-day-end-period]');
    var startStr = sIn ? to24h(sIn.value, sPer ? sPer.getAttribute('data-period') : 'am') : null;
    var endStr   = eIn ? to24h(eIn.value, ePer ? ePer.getAttribute('data-period') : 'am') : null;
    days.push({ start: startStr, end: endStr });
  });
  if (days.length === 0) days.push({ start: null, end: null });

  var rowsParent = root.querySelector('[data-mc-day-rows]');
  if (rowsParent) rowsParent.setAttribute('data-count', String(days.length));

  // Hours per populated day; total across all populated days.
  var perDayHours = days.map(function (d) {
    var s = toMin(d.start), e = toMin(d.end);
    if (s === null || e === null || e <= s) return 0;
    return (e - s) / 60;
  });
  var populatedCount = perDayHours.filter(function (h) { return h > 0; }).length;
  var totalHours = perDayHours.reduce(function (a, b) { return a + b; }, 0);

  // Duration key: multi-day if 2+ populated; otherwise derive from the one
  // populated day's hours using existing thresholds.
  var duration = null;
  var daysCountForMath = populatedCount || 1;
  if (populatedCount >= 2) {
    duration = 'multi-day';
  } else if (populatedCount === 1) {
    var firstIdx = perDayHours.findIndex(function (h) { return h > 0; });
    var s = toMin(days[firstIdx].start);
    if (s !== null && s >= 17 * 60) duration = 'evening';
    else if (totalHours < 3) duration = 'hourly';
    else if (totalHours < 6) duration = 'half-day';
    else duration = 'full-day';
  }

  // Mirror derived duration into the hidden input so the coordinator's
  // share-URL logic can include it.
  var hiddenDur = root.querySelector('input[data-mc-duration]');
  if (hiddenDur) hiddenDur.value = duration || '';

  // Inline duration label — empty when no day is populated.
  var label = root.querySelector('[data-mc-duration-label]');
  if (label) {
    if (duration === null) {
      label.textContent = '';
    } else {
      var rh = Math.round(totalHours * 10) / 10;
      var labelMap = {
        'full-day': 'full-day rate',
        'half-day': 'half-day rate',
        'evening':  'evening rate',
        'multi-day':'multi-day rate (' + daysCountForMath + ' days)',
        'hourly':   'hourly rate'
      };
      label.textContent = rh + ' hour' + (rh === 1 ? '' : 's') + ' · ' + labelMap[duration];
    }
  }

  return {
    size: Math.max(0, Math.min(200, num('[data-mc-size]', 0))),
    duration: duration,
    durationHours: totalHours,
    multiDayCount: daysCountForMath,
    days: days,
    tea: checked('[data-mc-tea]'),
    breakfast: checked('[data-mc-breakfast]'),
    lunchLight: checked('[data-mc-lunch-light]'),
    lunchHearty: checked('[data-mc-lunch-hearty]'),
    afternoon: checked('[data-mc-afternoon]'),
    drinks: checked('[data-mc-drinks]'),
    avProjector: checked('[data-mc-av-projector]'),
    avSound: checked('[data-mc-av-sound]'),
    fac: val('[data-mc-fac]:checked') || 'none',
    matBoards: checked('[data-mc-mat-boards]'),
    matPostits: checked('[data-mc-mat-postits]'),
    matPrinting: checked('[data-mc-mat-printing]'),
    setup: val('[data-mc-setup]:checked') || 'included',
    customLabel: val('[data-mc-custom-label]').trim(),
    customValue: num('[data-mc-custom-value]', 0),
    customLines: (function () {
      var out = [];
      root.querySelectorAll('[data-mc-custom-row]').forEach(function (row) {
        var lbl = row.querySelector('[data-mc-custom-label]');
        var v   = row.querySelector('[data-mc-custom-value]');
        var label = (lbl && lbl.value || '').trim();
        var value = v ? parseFloat(v.value) : NaN;
        if (label && !isNaN(value) && value > 0) out.push({ label: label, value: value });
      });
      return out;
    })(),
    impact: checked('[data-mc-impact]')
  };
}

function render(root, result) {
  // Hidden sinks inside the root (coordinator reads these).
  function set(sel, v) { const el = root.querySelector(sel); if (el) el.textContent = v; }
  set('[data-mc-sink-industry-low]',  fmtMoney(result.industry.low));
  set('[data-mc-sink-industry-high]', fmtMoney(result.industry.high));
  set('[data-mc-sink-industry-band]', fmtBand(result.industry.low, result.industry.high));
  set('[data-mc-sink-ours-total]',    fmtMoney(result.ours.total));
  set('[data-mc-sink-saving-low]',    fmtMoney(result.saving.low));
  set('[data-mc-sink-saving-high]',   fmtMoney(result.saving.high));
  set('[data-mc-sink-saving-band]',   fmtBand(result.saving.low, result.saving.high));
  set('[data-mc-sink-space-name]',    result.spaceName);

  // Industry lines (JSON)
  const indEl = root.querySelector('[data-mc-sink-industry-lines]');
  if (indEl) indEl.textContent = JSON.stringify(result.industry.lines);
  const oursEl = root.querySelector('[data-mc-sink-ours-lines]');
  if (oursEl) oursEl.textContent = JSON.stringify(result.ours.lines);

  // Dispatch event so coordinator can re-render breakdown rows.
  root.dispatchEvent(new CustomEvent('mc:rendered', { detail: result }));
}

function init() {
  const root = document.querySelector('[data-js="calc-meeting-costs"]');
  if (!root) return;

  function tick() { render(root, compute(readState(root))); }

  root.addEventListener('input', tick);
  root.addEventListener('change', tick);
  // Stepper buttons
  root.querySelectorAll('[data-mc-size-dec]').forEach(b => b.addEventListener('click', () => {
    const out = root.querySelector('[data-mc-size]');
    if (!out) return;
    // Job 11 — minimum group size is 0 (was 2), so calc starts empty.
    const v = Math.max(0, parseInt(out.value, 10) - 1);
    out.value = v;
    const display = root.querySelector('[data-mc-size-display]');
    if (display) display.textContent = v;
    tick();
  }));
  root.querySelectorAll('[data-mc-size-inc]').forEach(b => b.addEventListener('click', () => {
    const out = root.querySelector('[data-mc-size]');
    if (!out) return;
    const v = Math.min(200, parseInt(out.value, 10) + 1);
    out.value = v;
    const display = root.querySelector('[data-mc-size-display]');
    if (display) display.textContent = v;
    tick();
  }));

  tick();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
