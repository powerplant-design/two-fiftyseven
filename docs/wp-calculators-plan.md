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

- [ ] `blocks/<calc>/block.php` — ported markup with `data-color-space` + all `data-*` engine hooks preserved, `$allowed_spaces` whitelist, `$is_preview` fallback. Uses shared `.calc__*` classes (see §11) for standard elements; adds a per-calc identity class (e.g. `hours-to-impact`) for block-specific overrides.
- [ ] `acf-json/group_two57_block_<calc>.json` — field group with `colour_space` select (+ `room_set` for meet pricing)
- [ ] `assets/js/modules/<calc>.js` — ported engine as ES module exporting `init<Calc>()`, hardcoded constants swapped for `window.twofiftyseven.*`
- [ ] `assets/css/06-components/_calc-<calc>.scss` — **per-calc overrides only**; shared base styling lives in `_calc-base.scss` (see §11). Uses theme semantic tokens, no hardcoded hues.
- [ ] `@forward 'calc-<calc>';` added to `assets/css/06-components/_index.scss` (after `@forward 'calc-base';` so calc files cluster together)
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

### Radio / segmented controls — keyboard pattern

Use `<button role="radio">` (not hidden native radios) for all segmented/radio-group controls in calculators. This pattern, established in the hours-to-impact calc, provides:

- All buttons `tabindex="0"` so users can **Tab** through each option
- **Arrow keys** (Left/Right/Up/Down) cycle between options and select
- **Enter or Space** selects the focused option
- **Click** works with mouse
- `aria-checked="true|false"` on each button for screen readers
- `role="radiogroup"` + `aria-label` on the container
- Keydown listener attached per-button with `{ capture: true }` + `stopPropagation()` so Locomotive Scroll can't intercept arrow keys
- Visible `:focus-visible` outline using `--color-border-primary`

Reference implementation: `assets/js/modules/hours-to-impact.js` + `blocks/hours-to-impact/block.php`

---

## 8. Integration patterns (follow existing theme conventions)

### Block registration (`functions.php`)

Add one `acf_register_block_type()` call per calc to the existing `acf/init` handler (around line 584). All calculator blocks use the **`257 Calc <Name>`** title convention so they cluster together in the block picker:

```php
acf_register_block_type( [
    'name'            => 'workspace-pricing',
    'title'           => __( '257 Calc Workspace Pricing', 'two-fiftyseven' ),
    'description'     => __( 'Wellington office cost comparison calculator.', 'two-fiftyseven' ),
    'render_template' => get_template_directory() . '/blocks/workspace-pricing/block.php',
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

Calculator SCSS uses a **shared base + per-calc override** pattern (see §11):

- `_calc-base.scss` — shared `.calc__*` classes used by all calculator blocks
- `_calc-<calc>.scss` — per-calc overrides only (block shell padding, unique elements)

Both are `@forward`ed in `_index.scss`, with `calc-base` before the per-calc files so they cluster together:

```scss
// assets/css/06-components/_index.scss
@forward 'calc-base';
@forward 'calc-hours-to-impact';
// @forward 'calc-workspace-pricing';  // future calcs
```

New calculators should **reuse `.calc__*` classes** for any standard element (stepper, radio group, number input, result panel, breakdown, stat row, etc.) and only add per-calc classes for genuinely unique elements (comparison tables, quote forms, scenario slots, etc.). Extend `_calc-base.scss` when a new shared element type is needed across multiple calculators.

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
        ├── calculator.css                ← calc styles (port to _calc-base.scss + _calc-<calc>.scss)
        ├── components.css                ← shared component styles
        ├── fonts.css                     ← (use theme fonts instead)
        ├── impact.css                    ← T2 impact stats styles
        └── tokens.css                    ← (use theme tokens instead)
```

---

## 11. Shared calculator styling system (`.calc__*`)

### Architecture

All calculator blocks share a common SCSS base, `_calc-base.scss`, which defines `.calc__*` classes for the standard calculator elements. Each calculator block uses these classes directly in its markup and adds only its unique extras in a per-calc `_calc-<calc>.scss` file.

**Why:** Avoids duplicating stepper/input/radio/result/breakdown styles across 6+ calculator SCSS files. The hours-to-impact calc (C6) was the reference implementation; its styles were lifted into `_calc-base.scss` and the per-calc file slimmed to ~18 lines of overrides.

### File layout

```
assets/css/06-components/
├── _calc-base.scss              ← shared .calc__* classes (all calcs)
├── _calc-hours-to-impact.scss    ← C6 overrides (shell padding, --accent col)
├── _calc-workspace-pricing.scss ← C1 overrides (future)
├── _calc-meet-pricing.scss      ← C2 overrides (future)
└── ...
```

`_index.scss` forwards them grouped:
```scss
@forward 'calc-base';
@forward 'calc-hours-to-impact';
// @forward 'calc-workspace-pricing';  // future
```

### Shared `.calc__*` class catalogue

| Class | Element | Used by |
|---|---|---|
| `.calc__intro` / `__eyebrow` / `__heading` / `__tagline` | Section intro (scroll-revealed) | All |
| `.calc__body` | 50/50 grid (inputs left, result right); stacks at `bp-lg` | All |
| `.calc__inputs` | Inputs card (`--color-surface-secondary` bg, scroll-revealed) | All |
| `.calc__fields-grid` | 2-col grid for paired inputs (stacks ≤600px) | C6, C5, others |
| `.calc__field` / `__field-label` | Field wrapper + label | All |
| `.calc__stepper` (+ `button` / `output`) | −/output/+ stepper | C1, C3, C4, C5, C6 |
| `.calc__radio-group` / `__radio-label` | Segmented radio buttons (`<button role="radio">`) | C3, C4, C5, C6 |
| `.calc__input` | Number/text input (stepper-only keyboard guard) | All |
| `.calc__microcopy` | Small helper text under inputs | C5, C6 |
| `.calc__result` / `__result-grid` / `__result-col` / `__result-label` / `__result-figure` / `__result-unit` | Result panel (dark `--color-surface-inverse-primary` bg) | All |
| `.calc__breakdown-trigger` / `__breakdown-caret` | Trigger button + CSS chevron (rotates when open) | C3, C4, C5, C6 |
| `.calc__breakdown` / `__breakdown-summary` / `__breakdown-body` / `__breakdown-grid` / `__breakdown-col` / `__breakdown-heading` / `__breakdown-prose` | Full-width disclosure panel | C3, C4, C5, C6 |
| `.calc__stat` / `__stat-label` / `__stat-value` / `__stat-unit` | Label/value/unit stat row | C6 (extensible) |

### How to use it in a new calculator

1. **Markup**: use `.calc__*` classes for any standard element in `block.php`. Add a per-calc identity class on `<section>` (e.g. `workspace-pricing`) for block-specific scoping.
2. **Unique elements**: add a new per-calc class (e.g. `workspace-pricing__chart`) and style it in `_calc-<calc>.scss`.
3. **New shared element type**: if a new element type appears in **2+ calculators**, add it to `_calc-base.scss` as `.calc__<element>` rather than duplicating it per-calc.
4. **Overrides**: if a calc needs to tweak a shared element (e.g. a bigger result figure, different gap), scope the override under the per-calc identity class in `_calc-<calc>.scss`:
   ```scss
   .workspace-pricing {
       .calc__result-figure { font-size: calc(var(--text-3xl-size) * 2); }
   }
   ```

### Reveal animation

`_calc-base.scss` exposes a `reveal` mixin (opacity + translateY) used by `.calc__intro` and `.calc__inputs`. Any new scroll-revealed element in a calc can `@include reveal;` if it imports the mixin, or reuse the `.is-inview` class pattern.

## Status

Last updated: 2026-08-11 (all work on `feature/calculators` branch)

- [x] **F1** — ACF Options SSOT ✅ committed (`2ee8788`)
- [x] **F2** — `window.twofiftyseven` injector ✅ committed (`2ee8788`)
- [x] **P0** — Port `inject-prices.js` ✅ committed (`2ee8788`)
- [x] **Review checkpoint** ✅ passed — `window.twofiftyseven` populated, `data-price="dedicated"` renders `$659`
- [x] **C6** — Giving (hours→impact) ✅ committed (`3331d48`) + refactored to shared `.calc__*` system (uncommitted)
- [ ] **C1** — Workspace pricing ← **next up**
- [ ] **C2** — Meet pricing (+ Host variant)
- [ ] **C5** — Office carbon
- [ ] **C4** — Meeting costs
- [ ] **C3** — Office costs v2
- [ ] **T1** — Quick quote teaser
- [ ] **T2** — Impact stats partial

### C6 details (committed `3331d48`, then refactored to shared system)

Demo: `https://twofiftyseven.pages.dev/calculator/hours-to-impact/`

All §4 checklist items done. The C6 SCSS was split into:
- `_calc-base.scss` — shared `.calc__*` classes (see §11), reusable by all future calculators
- `_calc-hours-to-impact.scss` — slimmed to ~18 lines (block shell padding + `--accent` result col placeholder)

Block markup (`block.php`) uses `.calc__*` classes throughout; the `hours-to-impact` identity class remains on `<section>` for block-specific scoping.

Behaviour added during build:
- `readURL()` cold-load defaults: team=1, days=5, weeks=46, hours=8 (immediate non-zero result)
- Radio-group keyboard pattern (§7) via `<button role="radio">`
- Breakdown `<details>` inside the `data-js` root so per-person stats update live
- `scroll-margin-top` on breakdown clears the fixed header when auto-scrolled
- BREAKDOWN heading only shown when open (`:not([open])` hides summary)
- Weeks/hours inputs in a 2-col grid; hours input increments by 0.5 (`step="0.5"`)
- Bounded inputs: stepper-only keyboard guard (typing blocked, only arrows), snap-back clamp on change
- Breakdown caret: CSS chevron matching mobile nav, rotates when open (`aria-expanded`)
- Body grid stacks at `bp-lg` (1024px) not `bp-md` — prevents 2x result figures overflowing at tablet widths

### Code-review notes (2026-08-10)

- Fixed invalid token `--layout-content-wide` → `--layout-wide-size` (token doesn't exist in theme).
- Re-indented `block.php` (fields-grid wrapper + `<details>` had broken leading whitespace). Tags verified balanced.
