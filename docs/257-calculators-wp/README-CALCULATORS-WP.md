# two/fiftyseven Calculators → WordPress — packaging spec

**For:** the dev who owns the `two-fiftyseven` theme + Kinsta deploys.
**Goal:** every calculator becomes an ACF block that (a) rides the **theme's existing colour system per placement** (incl. light/dark), and (b) reads all prices + variable data from **one source of truth** — nothing hardcoded. Researched/cited benchmarks stay in code.

This bundle: the master spec (this file), the ready ACF Options field group (`acf-json/`), and every calculator's current source (`source-calculators/`). Written to drop into a Claude Code / opencode session on the theme repo.

---

## 1. The calculators (what "all of them" is)

**Big calcs** — full standalone tools, each its own block:

| # | Calc | Source | Engine | Root selector | Colour space |
|---|---|---|---|---|---|
| 1 | Office costs (SEO/AEO) | `calculator/office-costs/` | `calc-office-costs-v2.js` | `[data-js="calc-office-costs-v2"]` | **forest** (grey + lime, restrained) |
| 2 | Meeting costs | `calculator/meeting-costs/` | `calc-meeting-costs.js` | `[data-js="calc-meeting-costs"]` | forest |
| 3 | Office carbon | `calculator/office-carbon/` | `calc-office-carbon.js` | `[data-js="calc-office-carbon"]` | forest |
| 4 | Giving (hours→impact) | `calculator/hours-to-impact/` | `calc-hours-to-impact.js` | `[data-js="calc-hours-to-impact"]` | forest |
| 5 | Workspace pricing | `pricing/` | `calc-office-costs.js` (v1) | `[data-js="calc-office-costs"]` | forest (fuller lime cards) |
| 6 | Meet pricing (venue quote) | `meetings/pricing/` | inline `<script>` | `.quote-calc` | **purple** — and **peach** for Host |

**In-page teasers** — small "quick quote" widgets embedded on landing pages, each linking to a big calc. One block, placed many times:

| Teaser | Instance source | Engine | Root selector |
|---|---|---|---|
| Quick quote | `meetings/index.html` (`.quick-quote`) | `quote-preview.js` | `.quick-quote` |

**Impact stats teaser** — the "900+ orgs / $500k donated / 415k hours" count-up (homepage `impact-tease`, also on Impact + Press pages). Not a calculator, but its numbers are now in the SSOT (§2) so a small `[data-countup]` block/partial can render them anywhere.

So: **6 big-calc blocks + 1 quote teaser + 1 stats partial**, all fed by the one options store.

---

## 2. Single source of truth (SSOT)

### What's already there
Your SSOT is half-built: `calc-office-costs.js` exposes a `PRICES` object on `window.twofiftyseven.prices`, `inject-prices.js` writes those into every `[data-price]` element sitewide, and `tokens.css` holds `--impact-total: 450000`. The WP job: **move the source into ACF Options**, keep the same `window.twofiftyseven` shape so the front-end barely changes.

### The store
`acf-json/group_two57_calculator_data.json` (included) registers an Options page **Calculator data** — **54 fields in 6 tabs**: Memberships, Day passes, Rooms, Add-ons + catering, Impact + kaupapa levers, Impact stats. Every value two/fiftyseven sets at will lives here; **nothing is hardcoded**.

### The line we're holding
Only **257-commercial** numbers go in ACF. **Researched/cited** numbers stay as code constants next to their sources, because changing one means changing the research.
- **In the store:** membership prices, day-pass prices, room/event rates, AV + catering add-ons, giving rate ($1/person-hour), Impact Discount (50%), impact-eligibility ceiling ($200k), paid-forward total ($450k), biodiversity contribution, the office-costs value-add levers (carbon-offset value, climate-power premium), and the impact stats (orgs, donated, hours). *(Per your call: these all stay variable, sourced from here, never hardcoded.)*
- **Stays in code (cited):** the private-office $14,200/person/yr benchmark, competitor coworking ranges ($450–650 / $700–830), rent $/m², grade/precinct modifiers, carbon emission factors, the 200% offset ratio, meeting industry-standard bands, working-pattern assumptions (8h/day, 46 weeks, 230 days).

### Room rates — reconciled
Room rates were duplicated and conflicting across three files. Per your answer the store uses: **Meeting Room day $290**, **Entire day $1,900**, **Studio capacity 16** (the meetings/pricing set). The SSOT is where you verify the whole set in-admin before go-live — that's the point of it.

### Exposing the store to JS
One theme helper so the front-end keeps reading `window.twofiftyseven.*` (drop-in, `functions.php` or a small mu-plugin):

```php
add_action( 'wp_head', function () {
    if ( ! function_exists( 'get_field' ) ) return;
    $data = [
        'prices' => [
            'dedicated' => (int) get_field( 'membership_dedicated_monthly', 'option' ),
            'flexi-5'   => (int) get_field( 'membership_flexi_5_monthly', 'option' ),
            // … flexi-4…1, day-pass, pass-10/20/50 …
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

Then: `inject-prices.js` works unchanged; each engine swaps its hardcoded constants for `window.twofiftyseven.*` reads (the engines already centralise these at the top of each file — a small, contained edit each). For the quote email, the server recomputes from the same `get_field(...,'option')` values so the emailed figure can't be spoofed. The impact-stats `[data-countup]` targets read `window.twofiftyseven.stats`.

---

## 3. Colour per placement — ride the theme's own system

The theme already does exactly what you asked. `02-tokens/_color-themes.scss` defines **8 themes** — `neutral`, `forest`, `purple`, `maroon`, each **light + dark**. You apply one by putting `data-color-space="…"` on an element; the theme JS combines it with the light/dark mode and writes `data-theme="[space]-[mode]"`. So calc colour is **not a new system to build** — each block opts into the existing one and gets light/dark for free.

Mapping to your calcs (your answers):
- **Forest** = grey-green ground (`#b0bca6`) + lime/forest accents → this *is* "grey with lime". Office costs, meeting costs, carbon, giving, **and** workspace pricing all sit here (office-costs restrained; workspace-pricing fuller lime cards — same space, different intensity).
- **Purple** → Meet pricing.
- **Peach** → the **Host** variant of Meet pricing. Peach isn't in the theme yet — add `peach-light` / `peach-dark` to `_color-themes.scss` following the forest/purple pattern, then it's selectable like the rest.
- **Neutral / Maroon** available for any placement.

### The field (per block)
```json
{ "key": "field_two57_calc_colour", "label": "Colour space", "name": "colour_space",
  "type": "select", "default_value": "forest",
  "choices": { "forest": "Forest (grey + lime)", "purple": "Purple", "peach": "Peach (Host)", "maroon": "Maroon", "neutral": "Neutral" } }
```
Optionally add `colour_mode` (auto / light / dark) → `data-color-mode`; default auto so it follows the site toggle.

### The block outputs the attributes; the theme does the rest
```php
<section class="office-cost-calculator | block"
         data-color-space="<?php echo esc_attr( get_field('colour_space') ?: 'forest' ); ?>"
         <?php if ( $m = get_field('colour_mode') ) echo 'data-color-mode="' . esc_attr($m) . '"'; ?>>
```

### Calc SCSS references the theme's semantic tokens (never hardcoded hues)
This is the whole trick — map each structural role to a theme token and the calc recolours itself for any space + mode:

| Calc role | Theme token |
|---|---|
| page ground | `--color-surface-primary` |
| config card (the "lime card") | `--color-surface-secondary` |
| result panel (the dark one) | `--color-surface-inverse-primary` |
| result panel text | `--color-content-inverse` |
| body ink / headings | `--color-content-primary` |
| muted labels | `--color-content-secondary` |
| primary CTA bg / text | `--color-btn-primary-bg` / `--color-btn-primary-text` |
| hairlines | `--color-border-tertiary` |

So the workspace-pricing calc I just restyled (lime cards + ink panel + lime CTA) becomes: cards `var(--color-surface-secondary)`, panel `var(--color-surface-inverse-primary)`, CTA `var(--color-btn-primary-bg)`. Flip the block to `purple` → the Meet look; to `peach` → Host — **no per-calc colour code**, and light/dark handled by the theme.

Design guardrails that must survive the port (from Ash): no outlines on cards/buttons/toggles/thumbs; the accent is used as **cards, never a full background**; titles have no full stops and are never sentences; lists are bullets.

### Host = Meet pricing, peach, fewer rooms
Host reuses the meet-pricing calc in peach with only the **larger-capacity rooms** (Workshop, Event, Entire — drop Meeting Room / Silver Linings / Studio). Give the meet/quote block a room-set field:
```json
{ "key":"field_two57_quote_roomset", "name":"room_set", "type":"select", "default_value":"all",
  "choices": { "all":"All rooms", "host":"Host — large rooms only" } }
```
`block.php` renders only the rooms in the chosen set; every room reads its rates from the SSOT `room_*` fields regardless.

---

## 4. Per-calc conversion plan

Same five moves per block:

1. **Register the block** (`acf_register_block_type` in `functions.php` `acf/init`) + a field group with the `colour_space` (and, for the quote block, `room_set`) select.
2. **Port the markup** from `source-calculators/<calc>/index.html` into `blocks/<calc>/block.php` — the calc body only (drop the page `<header>`/`<footer>`/JSON-LD). Keep every `data-*` engine hook. Add `data-color-space` (+ optional `data-color-mode`).
3. **Port the engine** to `assets/js/modules/<calc>.js`, swapping hardcoded constants for `window.twofiftyseven.*`. Import + init in `main.js` (and re-init after Swup).
4. **Port the styles** to `assets/css/06-components/_<calc>.scss`, referencing the theme's semantic colour tokens (table above).
5. **Wire quote email** (workspace + meet only) — REST endpoint that recomputes from ACF options + sends via MailPoet.

Build order: **workspace pricing first** (most finished, already this structure), then **meet-pricing** (proves the colour swap: same block, purple → peach), then the four `calculator/*` tools, then the quote teaser + stats partial.

---

## 5. What's in this bundle

```
README-CALCULATORS-WP.md              ← this file
acf-json/
  group_two57_calculator_data.json    ← SSOT Options field group, 54 fields / 6 tabs (import / wp acf json sync)
source-calculators/
  calculator/{office-costs,meeting-costs,office-carbon,hours-to-impact}/index.html
  pricing/index.html                  ← workspace pricing (restyled reference)
  meetings/pricing/index.html         ← meet pricing (purple reference)
  meetings/index.html                 ← contains a .quick-quote teaser instance
  shared-js/*.js                      ← 6 engines + quote-preview + inject-prices
  design-system/*.css                 ← tokens, components, calculator, impact, fonts
```

## 6. Status of the open questions
1. **Room rates** — resolved to $290 / $1,900 / cap 16; verify the full set in the SSOT admin before go-live. ✅
2. **Value-add + biodiversity levers** — kept in the store as variables, never hardcoded. ✅
3. **Impact stats** — added to the SSOT (orgs / donated / hours) so homepage, Impact and Press pages all read from one place. ✅
4. **Colour** — mapped to the theme's own spaces (forest / purple / maroon / neutral) + light-dark; **peach still needs adding** to `_color-themes.scss` before Host uses it. ⚠️ one small theme addition.
