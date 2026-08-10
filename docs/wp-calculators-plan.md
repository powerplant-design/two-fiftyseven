# Two-Fifty-Seven — Calculator Blocks Implementation Plan

## Overview

Port the standalone HTML/JS calculators (in `docs/257-calculators-wp/source-calculators/`) into WordPress ACF blocks that live inside the theme. Every calculator reads its prices and variable data from one ACF Options SSOT — nothing hardcoded. Researched/cited benchmarks stay in code.

**8 deliverables**: 6 big-calc blocks + 1 quote teaser block + 1 impact-stats partial.

**Build approach**: Foundation first, then one calculator at a time (demo links provided per calc).

---

## 1. Colour Space Strategy

**The repo's existing colour system is the source of truth.** Discard all colour space suggestions from the source README. No new `peach` theme.

The theme defines **8 themes** in `assets/css/02-tokens/_color-themes.scss` — `neutral`, `forest`, `purple`, `maroon`, each in light + dark. Blocks opt in by emitting `data-color-space="<space>"` on their root element. The theme JS (`assets/js/modules/color-theme.js`) resolves `data-theme="<space>-<mode>"` combining the space with the user's light/dark preference. No per-calc colour code.

**Each calculator block gets a `colour_space` ACF select field** with the same 4 choices every other block uses:

```json
{
  "key": "field_two57_calc_colour",
  "label": "Colour space",
  "name": "colour_space",
  "type": "select",
  "default_value": "forest",
  "choices": {
    "neutral": "Neutral",
    "forest": "Forest",
    "purple": "Purple",
    "maroon": "Maroon"
  }
}
```

The block template outputs:

```php
<section class="office-cost-calculator | block"
         data-color-space="<?php echo esc_attr( get_field('colour_space') ?: 'forest' ); ?>">
```

Calc SCSS references the theme's semantic tokens (never hardcoded hues):

| Calc role | Theme token |
|---|---|
| page ground | `--color-surface-primary` |
| config card | `--color-surface-secondary` |
| result panel (dark) | `--color-surface-inverse-primary` |
| result panel text | `--color-content-inverse` |
| body ink / headings | `--color-content-primary` |
| muted labels | `--color-content-secondary` |
| primary CTA bg / text | `--color-btn-primary-bg` / `--color-btn-primary-text` |
| hairlines | `--color-border-tertiary` |

This makes every calculator multi-purpose — admin picks the colour space per placement.

---

## 2. Single Source of Truth (SSOT)

### The store

`acf-json/group_two57_calculator_data.json` registers an ACF Options page **"Calculator data"** (slug `calculator-data-settings`) with **54 data fields across 6 tabs**:

| Tab | Fields | Contents |
|---|---|---|
| Memberships | 7 | Dedicated + Flexi 1-5 monthly prices, annual prepay discount % |
| Day passes | 4 | Single, 10-pack, 20-pack, 50-pack |
| Rooms | 24 | 6 rooms × {capacity, day, hour, evening} |
| Add-ons + catering | 8 | AV (projector, sound), tea (single/bottomless), catering fee, materials (post-its, printing), complex setup |
| Impact + kaupapa levers | 8 | Giving rate ($/person-hour), impact discount %, eligibility ceiling, paid-forward total + display, biodiversity contribution, carbon offset value, climate power premium % |
| Impact stats | 3 | Organisations count, donated total, hours worked |

### What goes in ACF vs. stays in code

**In ACF (257-commercial — admin-editable):**
- All membership prices (6) + annual discount %
- All day-pass prices (4)
- All room rates: 6 rooms × {capacity, day, hour, evening} = 24
- AV add-ons (projector, sound)
- Catering: tea (single/bottomless), organising fee, post-its, printing, complex setup
- Giving rate ($1/person-hour)
- Impact Discount (50%)
- Impact eligibility ceiling ($200k)
- Paid-forward total ($450k) + display string
- Biodiversity contribution ($2k/yr)
- Carbon offset value ($1.25/pp/yr)
- Climate power premium (5%)
- Impact stats (orgs, donated, hours)

**Stays in code (cited/researched — changing means changing the research):**
- Private-office $14,200/person/yr benchmark
- Competitor coworking ranges ($450-650 / $700-830)
- Rent $/m², grade/precinct modifiers
- Carbon emission factors (grid intensity, line loss, waste, commute)
- 200% offset ratio
- Industry-standard meeting bands (29 constants in calc-meeting-costs.js)
- Working-pattern assumptions (8h/day, 46 weeks, 230 days)
- MHFR costs ($445, ratio 1:12, cert 2.5yr)
- Admin loaded hourly ($70)
- Office costs v2 methodology constants (rent/sqm, outgoings %, furniture, cleaning, etc.)

### Exposing the store to JS

A `wp_head` helper in `functions.php` reads all ACF option fields and emits the `window.twofiftyseven` object so front-end engines read from it:

```php
add_action( 'wp_head', function () {
    if ( ! function_exists( 'get_field' ) ) return;
    $data = [
        'prices' => [
            'dedicated' => (int) get_field( 'membership_dedicated_monthly', 'option' ),
            'flexi-5'   => (int) get_field( 'membership_flexi_5_monthly', 'option' ),
            // ... flexi-4..1, day-pass, pass-10/20/50
        ],
        'rooms'   => [ /* room_* keys → { capacity, day, hour, evening } per room */ ],
        'addons'  => [ /* av_*, tea_*, catering_*, materials_*, setup_* */ ],
        'impact'  => [
            'givingRatePerPersonHour' => (float) get_field( 'giving_rate_per_person_hour', 'option' ),
            'discountPct'             => (float) get_field( 'impact_discount_pct', 'option' ) / 100,
            'paidForwardTotal'        => (int) get_field( 'paid_forward_total', 'option' ),
            'paidForwardDisplay'      => get_field( 'paid_forward_total_display', 'option' ),
        ],
        'stats'   => [
            'organisations' => (int) get_field( 'impact_organisations_count', 'option' ),
            'donated'       => (int) get_field( 'impact_donated_total', 'option' ),
            'hoursWorked'   => (int) get_field( 'impact_hours_worked', 'option' ),
        ],
    ];
    echo '<script>window.twofiftyseven = Object.assign(window.twofiftyseven||{}, ' . wp_json_encode( $data ) . ');</script>';
}, 1 );
```

---

## 3. The calculators

### Big calculators (6 blocks)

| # | Calc | Engine file | Root selector | Source |
|---|---|---|---|---|
| C1 | Workspace pricing | `calc-office-costs.js` (v1) | `[data-js="calc-office-costs"]` | `pricing/index.html` |
| C2 | Meet pricing (+ Host variant) | inline → new module | `.quote-calc` | `meetings/pricing/index.html` |
| C3 | Office costs (SEO/AEO) | `calc-office-costs-v2.js` | `[data-js="calc-office-costs-v2"]` | `calculator/office-costs/index.html` |
| C4 | Meeting costs | `calc-meeting-costs.js` | `[data-js="calc-meeting-costs"]` | `calculator/meeting-costs/index.html` |
| C5 | Office carbon | `calc-office-carbon.js` | `[data-js="calc-office-carbon"]` | `calculator/office-carbon/index.html` |
| C6 | Giving (hours→impact) | `calc-hours-to-impact.js` | `[data-js="calc-hours-to-impact"]` | `calculator/hours-to-impact/index.html` |

### Teaser + stats

| # | Item | Engine | Root selector | Source |
|---|---|---|---|---|
| T1 | Quick quote teaser | `quote-preview.js` | `.quick-quote` | `meetings/index.html` |
| T2 | Impact stats partial | reads `window.twofiftyseven.stats` | `[data-countup]` | homepage/impact/press |

### Host variant (C2)

Meet pricing reuses the same block with a `room_set` ACF select:
- `all` — all rooms (default, purple)
- `host` — large rooms only (Workshop, Event, Entire — drops Meeting Room, Silver Linings, Studio)

`block.php` renders only rooms in the chosen set. Every room reads rates from the SSOT regardless.

---

## 4. Per-block file checklist

Every calculator block creates these files (following existing theme conventions):

- [ ] `blocks/<calc>/block.php` — ported markup with `data-color-space` + all `data-*` engine hooks preserved, `$allowed_spaces` whitelist, `$is_preview` fallback
- [ ] `acf-json/group_two57_block_<calc>.json` — field group with `colour_space` select (+ `room_set` for meet pricing)
- [ ] `assets/js/modules/<calc>.js` — ported engine as ES module exporting `init<Calc>()`, hardcoded constants swapped for `window.twofiftyseven.*`
- [ ] `assets/css/06-components/_<calc>.scss` — styles using theme semantic tokens, no hardcoded hues
- [ ] `@forward '<calc>';` added to `assets/css/06-components/_index.scss`
- [ ] `import` + `init<Calc>()` added to `assets/js/main.js`
- [ ] Re-init hook added to `assets/js/modules/transitions.js` (Swup lifecycle)
- [ ] `acf_register_block_type()` added to `functions.php` `acf/init` handler

---

## 5. Engine porting details

### What each engine reads/writes to `window.twofiftyseven`

| Engine | Currently | After port |
|---|---|---|
| `calc-office-costs.js` (v1) | **Writes** `window.twofiftyseven.prices` | Reads from ACF-injected object (stop writing) |
| `calc-meeting-costs.js` | **Writes** `meetingPrices`, `meetingAV`, `deriveDuration` | Reads rooms/add-ons from ACF, stops writing prices |
| `calc-office-costs-v2.js` | Uses own `window.occv2` namespace | Reads prices from `window.twofiftyseven`, keeps `occv2` for compute functions |
| `calc-office-carbon.js` | Self-contained | Stays mostly self-contained (cited constants in code); reads `givingRate` from `window.twofiftyseven.impact` |
| `calc-hours-to-impact.js` | Self-contained | Reads `givingRatePerPersonHour` from `window.twofiftyseven.impact` |
| `inject-prices.js` | Reads `window.twofiftyseven.prices` | Unchanged (already reads from the global) |
| `quote-preview.js` | Reads room rates from HTML `data-*` attrs | Reads from `window.twofiftyseven.rooms` (reconciles the duplicate source) |

### SSOT gaps to fix during porting

1. **Room rates duplicated** between `calc-meeting-costs.js` (`MEETING_PRICES`) and `quote-preview.js` (HTML `data-day`/`data-hour`/`data-evening` attrs) — unify under ACF `rooms` object
2. **`PRICES` load-order coupling** — `inject-prices.js` silently no-ops if `calc-office-costs.js` doesn't load first. ACF-driven `window.twofiftyseven.prices` removes this dependency
3. **Duplicated constants across engines** — `WEEKS_PER_YEAR=46`, `HOURS_PER_DAY=8`, `SQM_PER_PERSON`, `POWER_W_PER_SQM=50` appear in 4 files with slight discrepancies (sqm/person is 10 in 3 files, 9 in v2). Reconcile.
4. **`SOURCES` objects** (citation URLs/labels) defined per-file with overlapping entries — keep in code per engine (they're cited), but deduplicate shared ones

---

## 6. Quote email backend (deferred to first calc that needs it)

Workspace pricing (C1) and Meet pricing (C2) have quote email forms. Build a WP REST endpoint:

1. `register_rest_route('two57/v1', '/quote-email')` in `functions.php`
2. Receives: name, email, comments (optional), calc type, calc state (JSON)
3. **Recomputes the quote server-side** from `get_field(..., 'option')` — the emailed figure can't be spoofed
4. Sends via MailPoet (theme already has `two57_mailpoet_form()` helper) or `wp_mail()` as fallback
5. Returns success/error JSON to the block's JS

Form fields per calculator:
- **Workspace pricing**: name (required), email (required)
- **Meet pricing**: name (required), email (required), comments (textarea, optional), honeypot `website` (hidden)

---

## 7. Build order

### Phase 1 — Foundation (this pass)

| Step | Task | Description |
|---|---|---|
| F1 | ACF Options SSOT | Move field group JSON to `acf-json/`, register Options page in `functions.php`, import/sync |
| F2 | `window.twofiftyseven` injector | `wp_head` callback that reads all 54 ACF fields and emits the JS object |
| P0 | Port `inject-prices.js` | Copy to `assets/js/modules/`, wrap as ES module, import in `main.js`, add Swup re-init |

**Review checkpoint**: verify `window.twofiftyseven` is populated, `inject-prices.js` reads from it, a test `data-price` element renders correctly.

### Phase 2 — Calculators (one at a time, demo links per calc)

Suggested order (simplest → most complex):

1. **C6 — Giving (hours→impact)** — simplest: 4 inputs, 1 ratio, no email, no comparison table
2. **C1 — Workspace pricing** — most finished, proves quote email backend
3. **C2 — Meet pricing** — proves colour swap (same block, different `colour_space` + `room_set`)
4. **C5 — Office carbon** — medium, emission factors stay in code
5. **C4 — Meeting costs** — high complexity, industry bands comparison
6. **C3 — Office costs v2** — highest complexity, 7 config cards, scenario slots

### Phase 3 — Teaser + stats

7. **T1 — Quick quote teaser** — small widget, hands off to meet pricing
8. **T2 — Impact stats partial** — `[data-countup]` reads `window.twofiftyseven.stats`

---

## 8. Integration patterns (follow existing theme conventions)

### Block registration (`functions.php`)

Add one `acf_register_block_type()` call per calc to the existing `acf/init` handler (around line 584):

```php
acf_register_block_type( [
    'name'            => 'office-cost-calculator',
    'title'           => __( '257 Office Cost Calculator', 'two-fiftyseven' ),
    'description'     => __( ' Wellington office cost comparison calculator.', 'two-fiftyseven' ),
    'render_template' => get_template_directory() . '/blocks/office-cost-calculator/block.php',
    'category'        => 'layout',
    'icon'            => 'calculator',
    'keywords'        => [ 'calculator', 'office', 'cost', 'pricing' ],
    'mode'            => 'edit',
    'supports'        => [
        'innerBlocks' => false,
        'align'       => false,
    ],
] );
```

### JS module pattern (`assets/js/modules/` + `main.js`)

```js
// assets/js/modules/office-cost-calculator.js
export function initOfficeCostCalculator() {
    const root = document.querySelector('[data-js="calc-office-costs-v2"]');
    if (!root) return;
    // ... ported engine logic
}
```

```js
// assets/js/main.js (add one import + one init call)
import { initOfficeCostCalculator } from './modules/office-cost-calculator.js';
// ...
initOfficeCostCalculator();
```

### Swup re-init (`transitions.js`)

Add to the content-replaced hook so calculators re-init after page swaps:

```js
import { initOfficeCostCalculator } from './modules/office-cost-calculator.js';
// ... in the content-replaced callback:
initOfficeCostCalculator();
```

### SCSS (`assets/css/06-components/`)

Create `_<calc>.scss` using theme semantic tokens only. Add `@forward` to `_index.scss`:

```scss
// assets/css/06-components/_index.scss
@forward 'office-cost-calculator';
```

### ACF field group (`acf-json/`)

One JSON file per block with the `colour_space` select + any block-specific fields (e.g. `room_set` for meet pricing). Location rule: `block == acf/<calc-slug>`.

---

## 9. Design guardrails (from source spec)

- No outlines on cards/buttons/toggles/thumbs
- Accent colour used as cards, never as full background
- Titles have no full stops and are never sentences
- Lists are bullets

---

## 10. Source file inventory

```
docs/257-calculators-wp/
├── README-CALCULATORS-WP.md              ← original spec (colour info discarded, see §1)
├── acf-json/
│   └── group_two57_calculator_data.json  ← SSOT Options field group (54 fields / 6 tabs)
└── source-calculators/
    ├── calculator/
    │   ├── hours-to-impact/index.html    ← C6 source
    │   ├── meeting-costs/index.html      ← C4 source
    │   ├── office-carbon/index.html      ← C5 source
    │   └── office-costs/index.html       ← C3 source (v2 engine)
    ├── pricing/index.html                ← C1 source (v1 engine)
    ├── meetings/
    │   ├── pricing/index.html            ← C2 source
    │   └── index.html                    ← T1 source (.quick-quote teaser)
    ├── shared-js/
    │   ├── calc-hours-to-impact.js       ← C6 engine
    │   ├── calc-meeting-costs.js         ← C4 engine
    │   ├── calc-office-carbon.js         ← C5 engine
    │   ├── calc-office-costs-v2.js       ← C3 engine
    │   ├── calc-office-costs.js          ← C1 engine (v1)
    │   ├── inject-prices.js              ← P0 (price injector)
    │   └── quote-preview.js              ← T1 engine
    └── design-system/
        ├── calculator.css                ← calc styles (port to _<calc>.scss)
        ├── components.css                ← shared component styles
        ├── fonts.css                     ← (use theme fonts instead)
        ├── impact.css                    ← T2 impact stats styles
        └── tokens.css                    ← (use theme tokens instead)
```

---

## Status

- [ ] **F1** — ACF Options SSOT
- [ ] **F2** — `window.twofiftyseven` injector
- [ ] **P0** — Port `inject-prices.js`
- [ ] **Review checkpoint**
- [ ] **C6** — Giving (hours→impact)
- [ ] **C1** — Workspace pricing
- [ ] **C2** — Meet pricing (+ Host variant)
- [ ] **C5** — Office carbon
- [ ] **C4** — Meeting costs
- [ ] **C3** — Office costs v2
- [ ] **T1** — Quick quote teaser
- [ ] **T2** — Impact stats partial
