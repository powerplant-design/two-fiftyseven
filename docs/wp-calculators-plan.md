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
- Office costs methodology constants (rent/sqm, outgoings %, furniture, cleaning, etc.)

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
| `calc-office-costs-v2.js` | Uses own `window.occv2` namespace | Reads prices from `window.twofiftyseven`; **drops `occv2` entirely** — one module, scenario API module-local (see C3 plan) |
| `calc-office-carbon.js` | Self-contained | Stays fully self-contained (all cited constants in code); reads nothing from `window.twofiftyseven` |
| `calc-hours-to-impact.js` | Self-contained | Reads `givingRatePerPersonHour` from `window.twofiftyseven.impact` |
| `inject-prices.js` | Reads `window.twofiftyseven.prices` | Unchanged (already reads from the global) |
| `quote-preview.js` | Reads room rates from HTML `data-*` attrs | Reads from `window.twofiftyseven.rooms` (reconciles the duplicate source) |

### SSOT gaps to fix during porting

1. **Room rates duplicated** between `calc-meeting-costs.js` (`MEETING_PRICES`) and `quote-preview.js` (HTML `data-day`/`data-hour`/`data-evening` attrs) — unify under ACF `rooms` object
2. **`PRICES` load-order coupling** — `inject-prices.js` silently no-ops if `calc-office-costs.js` doesn't load first. ACF-driven `window.twofiftyseven.prices` removes this dependency
3. **Duplicated constants across engines** — `WEEKS_PER_YEAR=46`, `HOURS_PER_DAY=8`, `SQM_PER_PERSON`, `POWER_W_PER_SQM=50` appear in 4 files with slight discrepancies (sqm/person is 10 in 3 files, 9 in v2). Reconcile.
4. **`SOURCES` objects** (citation URLs/labels) defined per-file with overlapping entries — keep in code per engine (they're cited), but deduplicate shared ones

---

## 6. Share + email backend (reusable across calculator blocks)

Several calculators have a "share your calculation" row (C6 hours-to-impact, C1 workspace pricing, C2 meet pricing, plus future C3–C5). Two actions are provided — **email the calculation** and **copy a shareable link**. PDF download is deliberately out of scope.

This section defines a single reusable backend + frontend pattern used by every calculator that needs it. Build it once (on whichever calc lands first that needs it), then each subsequent calc adds the markup + an engine call — no per-calc backend.

### 6.1 Share row markup (all calcs)

A section inside the calc's `[data-js="calc-<name>"]` root, after the breakdown `<details>`. Uses shared `.calc__share-*` classes (defined in `_calc-base.scss`, §11) so it's styled consistently across calculators:

```html
<div class="calc__share | stack" data-calc-share>
  <p class="calc__share-eyebrow | text-monospace text-s">Take this with you</p>
  <h2 class="calc__share-title | text-3xl text-wrap-balance">save your number, share it, send it on</h2>
  <div class="calc__share-row">

    <!-- Email card -->
    <div class="calc__share-card | stack">
      <h3 class="calc__share-card-title">Email me these numbers</h3>
      <p class="calc__share-card-body">Get the numbers and a one-line summary in your inbox, ready to forward to your team.</p>
      <form class="calc__share-form | cluster" data-calc-share-email novalidate>
        <input class="calc__share-input" type="email" name="email" placeholder="you@example.com" autocomplete="email" data-calc-share-email-input aria-label="Your email" required>
        <!-- honeypot — hidden from real users, rejected server-side -->
        <input class="calc__share-honeypot visually-hidden" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" data-calc-share-honeypot>
        <button class="btn" data-type="primary" type="submit" data-calc-share-submit>Send →</button>
        <p class="calc__share-consent | text-s">
          <label class="calc__share-check">
            <input type="checkbox" name="consent" checked data-calc-share-consent>
            By submitting I agree to the <a href="/contact-policy/">Contact policy</a>
          </label>
        </p>
      </form>
      <p class="calc__share-status | text-xs text-monospace" data-calc-share-status role="status" aria-live="polite"></p>
    </div>

    <!-- Copy link card -->
    <div class="calc__share-card | stack">
      <h3 class="calc__share-card-title">Share the numbers</h3>
      <p class="calc__share-card-body">Same numbers, any browser, your team clicks and sees the exact same numbers.</p>
      <button class="btn" data-type="secondary" type="button" data-calc-share-copy>Copy link →</button>
    </div>

  </div>
</div>
```

Data-attr contract (shared module `calc-share.js`): `data-calc-share` (section), `data-calc-share-email` (form), `-email-input`, `-honeypot`, `-consent` (checkbox), `-submit` (button), `-status` (email status), `-copy` (button). There is **no copy-status element** — copy feedback lives on the button label (see §6.2).

> **Gotchas learned in QA (2026-08-11):** the consent `<p>` must live **inside** the `<form>` — the module reads it via `form.querySelector('[data-calc-share-consent]')`; when it sat outside, consent gating silently broke. Buttons use the theme `.btn` system (`data-type="primary|secondary"`), **not** a dedicated `.calc__share-btn` class (removed during review). The `novalidate` form disables native constraint validation; JS gating does the job.

#### Consent + contact policy (required)

The submit button is gated by a **consent checkbox** with a link to a **contact policy page** — and, per the standard gating pattern implemented during review (2026-08-11), by the **email field being non-empty** too. The policy page (slug: `/contact-policy/`, page titled "Contact policy") must be created by the site admin once and exist in the WP install. The checkbox is **checked by default** — the lead is opt-out, not opt-in, because the lead is explicitly opting into a transactional send + potential follow-up from the client. If the consent checkbox is unchecked at submit (or the email is empty), the endpoint returns an error and no email/lead is created.

The contact policy link is a normal internal `<a href="/contact-policy/">`. Site admin creates the page once. If the page is missing, the link 404s visibly (don't silently swallow — the client must publish the policy before the share row goes live).

### 6.2 Copy link (client-side, no backend)

The "Copy link" button uses `navigator.clipboard.writeText(window.location.href)`. The calc engine already keeps the URL in sync via `writeURL(state)` (team/days/weeks/hours in the query string), so the copied link reproduces the exact numbers when opened in any browser. **Feedback lives entirely on the button label** — on success it flips to "Link copied ✓", on failure (fallback path) to "Copy your browser address bar to share.", then reverts to "Copy link →" after ~4 s (`REVERT_MS`). Falls back to a hidden input + `document.execCommand('copy')` when `navigator.clipboard` is unavailable. There is no `data-calc-share-copy-status` element — a separate copy status box was removed during review because the button already communicates the state (2026-08-11).

> **Built as a shared module** — `assets/js/modules/calc-share.js` exports `initCalcShare(root, { slug, getState })`. Each calc's engine imports it and calls it in `init<Calc>()`; there is no per-calc copy of the submit/copy/consent/honeypot logic (revised from an earlier draft that duplicated the handler in each engine). See §6.5.

### 6.3 Email backend — WP REST endpoint (reusable)

One endpoint serves all calculators. The `calc` field tells the server which recompute logic + email template to use.

```
POST /wp-json/two57/v1/calc-share-email

Body (JSON):
{
  "calc":     "hours-to-impact" | "workspace-pricing" | "meet-pricing" | ...,
  "email":    "you@example.com",
  "consent":  true,                  // must be true — server rejects if false/missing
  "website":  "",                    // honeypot — if non-empty, fake success
  "page":     "/calculator/hours-to-impact/",  // window.location.pathname
  "state":    { /* calc-specific params, e.g. team/daysPerWeek/weeksPerYear/hoursPerDay */ }
}
```

`page` is the pathname of the page the calc sits on. The server needs it to build the "open the calculation" link in the email (the endpoint is shared across pages/calcs and can't know its own URL).

Server flow (`functions.php`, `rest_api_init` → `register_rest_route('two57/v1', '/calc-share-email')`):

1. **Honeypot**: if `website` non-empty → respond `{success: true}` but do nothing (waste bots silently).
2. **Validate email** via `is_email()`. Reject if invalid.
3. **Consent**: if `consent !== true` → `{success: false, message: "You must accept the contact policy to send."}`. No email, no lead.
4. **Sanitise + bound-check state** per calc (integers/floats within the same min/max the engine uses; e.g. team 1–30, days 1–5, weeks 1–52, hours 1–24 for hours-to-impact). Server **clamps** out-of-range values to the engine bounds rather than rejecting (matches `Math.min/Math.max` clamping in the engines).
5. **Recompute the figures server-side** using `get_field(..., 'option')` for the SSOT values (giving rate for C6, room rates for C2, memberships for C1, etc.). The figure in the email is authoritative — never trust the client. A `switch ($calc)` dispatches to a per-calc recompute function (e.g. `two57_calc_figures_hours_to_impact($state)`).
6. **Compose the email** — subject + plain + HTML variants with a calc-specific summary block:
   - One-line summary ("A team of 2 at 5 days a week funds $3,680 of subsidised space a year.")
   - The figures (the bound input state + the computed outcomes)
   - A link back to the page with the same query strings so the recipient reproduces the numbers
   - Footer: contact policy link, the two/fiftyseven address
7. **Send**: prefer MailPoet's `MailerFactory` (uses the configured MailPoet sending method — keeps bounce/unsubscribe handling consistent). Fallback to `wp_mail()` if MailPoet isn't active.
8. **Lead capture** (always, regardless of which calc) — see §6.4.
9. **Respond** `{success: true}` or `{success: false, message}`.

### 6.4 Lead capture — MailPoet list "Calculator leads"

Every email-submit also writes a lead to a MailPoet list named **"Calculator leads"** so the client can follow up. This is a single list shared across all calculators — each lead is stamped with a `calc_source` custom field so the client can filter "leads from hours-to-impact" vs "leads from workspace pricing" etc.

#### Subscribers

MailPoet exposes the APIs to do this without a confirmation email:
- `MailPoet\Subscribers\SubscribersRepository` — find-or-create by email (WP users and existing subscribers are deduped automatically)
- `MailPoet\Segments\SegmentsRepository` — look up the "Calculator leads" list by name
- `MailPoet\Subscribers\SubscriberSegmentRepository` — attach the subscriber to the list
- `MailPoet\Subscribers\SubscriberSaveController` — wraps the whole create-or-update flow including custom fields

Implementation:
1. Find "Calculator leads" list by name. If it doesn't exist yet, **create it** (`SegmentsRepository::createOrUpdate('Calculator leads', '', SegmentEntity::TYPE_DEFAULT)` — note the **positional** signature `(string $name, string $description, string $type, …)`, not an array). This keeps demo/local working without manual setup, and on first production deploy the list appears automatically.
2. Find an existing subscriber by email, or create a new one (`SubscriberSaveController::createOrUpdate(['email' => $email, 'first_name' => '', 'last_name' => '', 'status' => SubscriberEntity::STATUS_SUBSCRIBED], $existing)`).
3. Attach to "Calculator leads" if not already attached.
4. Stamp `calc_source` custom field with the calc slug (`'hours-to-impact'`, `'workspace-pricing'`, etc.). The custom field is auto-created on first use. This is the analytics hook — "how many leads came from each calculator".
5. No double-opt-in confirmation email is sent — this is a transactional send to a lead who explicitly consented to the contact policy. The follow-up newsletter pipeline is up to the client's MailPoet automation on that list; we just deposit the lead.

#### Why one shared list

- Simpler for the client — one list to monitor/manage, one place to attach a "Welcome / follow-up" automation.
- The `calc_source` custom field gives per-calc segmentation when the client wants it (MailPoet supports filtering segments by custom field value).
- If the same email submits from two different calculators, both calc_source values accumulate (or overwrite — TBD by client preference; default: latest wins, but the lead's first submission date is preserved).

### 6.5 Calc adaptors

Each calculator that needs the share row implements:
- **Markup**: inserts the share section (above) inside its root, with calc-appropriate copy.
- **Engine**: imports `initCalcShare` from `assets/js/modules/calc-share.js` and calls `initCalcShare(root, { slug: '<slug>', getState: () => state })` once inside `init<Calc>()`. The shared module wires the email submit (POST), consent gating, honeypot, status UI, and copy button — no per-calc handler code.
- **Server recompute**: a `two57_calc_figures_<slug>($state): array` helper returning the **figures** (the one-line summary is composed separately in the `<slug>` compose function). Lives in `inc/calc-share-email.php`; a `switch ($calc)` in the endpoint dispatches to it.

Calcs that don't need email (none currently planned — every calc that has a share row wants email) simply don't insert the share markup.

### 6.6 Frontend behaviour (shared module `calc-share.js`)

Implemented once in `assets/js/modules/calc-share.js`; per-calc engines only call `initCalcShare(root, { slug, getState })`:
- **Email form submit**: `e.preventDefault()` (the form is `novalidate` — native validation is bypassed, JS gates). Honeypot filled → fake success. Else `fetch(POST)` with `{ calc, email, consent: true, website, page: location.pathname, state: getState() }`; on success status "Sent, check your inbox"; on error show the returned message. Inline loading state ("Sending…") then re-enable + revert after 4 s.
- **Copy button**: as per §6.2 — feedback on the button label, no status element.
- **Gating (consent + email)**: the submit button is `disabled` until **both** the consent checkbox is checked **and** the email field is non-empty (listeners on `input` + `change`). This is the standard gating pattern shared with other gates. If a submission somehow arrives with consent unchecked → "Tick the box to agree to the contact policy first."; with an empty email (button bypassed) → "Enter your email address to send." + focus the field. Either state also short-circuits before the `fetch`. The disabled visual comes from the `.btn` `:disabled` rule added to `_button.scss` (opacity 0.4, `not-allowed`, hover/active suppressed).
- **Honeypot**: hidden via `class="calc__share-honeypot visually-hidden"` (off-screen, not `display:none` — bots that respect `display:none` won't fill it). Module checks before submit; server double-checks.

### 6.7 SCSS — shared share row styling

Add to `_calc-base.scss` (so it's shared, not per-calc):

| Class | Element |
|---|---|
| `.calc__share` | Container `<div>` (the section) — `--stack-gap: var(--space-m)`, full width (`grid-column: 1 / -1`) so it spans the calc body grid |
| `.calc__share-eyebrow` | "Take this with you" |
| `.calc__share-title` | "save your number, share it, send it on" (no closing full stop) |
| `.calc__share-row` | 2-col grid (email card + copy card); stacks to 1-col ≤ `bp-lg` |
| `.calc__share-card` | Card — `--color-surface-secondary` bg, `--corner-radius-card`, `padding: var(--space-m)`, button pinned to bottom via `margin-block-start: auto` |
| `.calc__share-card-title` | Card title (h3) |
| `.calc__share-card-body` | Card body copy |
| `.calc__share-form` | `flex` + `gap: var(--space-xs)` + `flex-wrap` (a `.cluster`, not `.stack`) — input + send on one row, consent wraps beneath |
| `.calc__share-input` | Email input — NOT `.calc__input`; custom `.calc__share-form .calc__share-input` override of the theme-wide form input rule (`_forms.scss` `inline-size:100%` + `field-sizing:content`) → `inline-size:auto` + `field-sizing:fixed` so it sizes to its content inside the flex row |
| `.calc__share-honeypot` | Visually hidden — the markup applies the `visually-hidden` utility class directly (`class="calc__share-honeypot visually-hidden"`); no `@extend` across SCSS module scopes (utilities load after components) |
| *(buttons)* | Use the theme `.btn` system — `class="btn" data-type="primary"` (Send) / `data-type="secondary"` (Copy link). No `.calc__share-btn` class. Disabled gating visual from `.btn:disabled` in `_button.scss` |
| `.calc__share-consent` | Consent `<p>` — `flex: 1 1 100%` so it takes its own row under Send; colour `--color-content-secondary` |
| `.calc__share-check` | Checkbox + label styling; policy link underlined, `--color-content-primary` |
| `.calc__share-status` | Email status box — monospace, centred, `1lh` min-height, `--color-surface-inverse-primary` bg (error `--color-surface-inverse-secondary`), transparent while `:empty`; carries `data-calc-share-status="error|success"` |

**Responsive:** at ≤ `bp-sm` (640px) the Send and Copy link buttons go **full-width** across their card (`.calc__share-form .btn, .calc__share-card > .btn { inline-size: 100%; justify-content: center; }`).

### 6.8 Files to touch (when share is built)

| File | Change |
|---|---|
| `inc/calc-share-email.php` | **Backend lives here** (loaded from `functions.php`): `rest_api_init` endpoint + per-calc `two57_calc_figures_<slug>()` recompute helpers + MailPoet lead capture + rate limit + send (MailerFactory → `wp_mail`). Built once, shared by every calc. |
| `functions.php` | `wp_localize_script` adds `two57CalcShare.emailEndpoint` (the REST URL). No per-calc code. |
| `blocks/<calc>/block.php` | Insert the share markup inside the calc root, after the breakdown `<details>` |
| `assets/js/modules/calc-share.js` | **Shared handler module** (built once): email submit (POST + consent + honeypot + status), copy button (clipboard → `execCommand` fallback) |
| `assets/js/modules/<calc>.js` | `import { initCalcShare } from './calc-share.js'` + call `initCalcShare(root, { slug, getState })` in `init<Calc>()` |
| `assets/css/06-components/_calc-base.scss` | `.calc__share-*` shared classes (one addition, reused by every calc) |
| `assets/css/06-components/_button.scss` | `.btn:disabled` state (opacity 0.4, `not-allowed`, hover/active suppressed) — enables the gated-submit visual |
| `docs/wp-calculators-plan.md` | This section |
| WP admin (one-time) | Create the "Contact policy" page at `/contact-policy/`. The "Calculator leads" MailPoet list + `calc_source` custom field auto-create on first submit. |

MailPoet API notes verified against the installed plugin (v5.x):
- `SegmentsRepository::createOrUpdate()` takes **positional args** `(name, description, type, …)` — not an array.
- `SubscriberSaveController::createOrUpdate(array $data, ?SubscriberEntity)` upserts by email; pass `'status' => SubscriberEntity::STATUS_SUBSCRIBED` to skip double-opt-in.
- `SubscriberSegmentRepository::createOrUpdate(SubscriberEntity, SegmentEntity, status)` attaches to a list.
- `CustomFieldsRepository::createOrUpdate(['name' => 'calc_source', 'type' => 'text', 'params' => ['label' => ...]])` find-or-creates the custom field; then `SubscriberSaveController::updateCustomFields(['cf_<id>' => $calc], $subscriber)` stamps it.
- `MailerFactory::getDefaultMailer()->send($newsletter, $subscriber)` where `$newsletter = ['subject' => ..., 'body' => ['html' => ..., 'text' => ...]]`.

### 6.9 Anti-abuse + rate limiting

- Honeypot catches naive bots.
- Per-IP rate limit via transients: max 3 calc-share-email submits per IP per 10 minutes. Returns 429 + a polite "Try again in a few minutes" message.
- Email address is validated but no captcha (keeps UX light); the consent + honeypot + rate-limit combo is sufficient for a small business calc.

### 6.10 Build sequencing

The share row can be built on any of the calculators that need it. **Built on C6 (hours-to-impact)** because it was already live with the complete `.calc__*` system; the backend is generic so C1–C5 retrofit cleanly. Order of operations when a calc gets the share row:

1. `inc/calc-share-email.php` holds the `rest_api_init` endpoint + the per-calc recompute helpers (shared infrastructure **built once**; each calc adds its own sanitize + figures + compose `$calc` case to the existing switches)
2. Build the calc's share markup in `block.php` (calc-specific copy)
3. The `.calc__share-*` classes live in `_calc-base.scss` — **built once**, no per-calc SCSS
4. Wire the calc engine: `import { initCalcShare }` + `initCalcShare(root, { slug, getState })`
5. Test sends against a local mail trap (DevKinsta's Mailhog if available); otherwise define `TWO57_CALC_EMAIL_LOG` so composed emails are written to `error_log` for QA

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

1. **C6 — Giving (hours→impact)** — ✅ done. 4 inputs, 1 ratio, no comparison table. **Also ships the §6 share row + email backend + MailPoet lead capture as the reference implementation** (built on C6 rather than C1 — C6 already had the complete `.calc__*` system, §6.10). C1+ simply reuse it.
2. **C1 — Workspace pricing** — ✅ done (`b084d49`). Retrofit the §6 share row + prove a second calc's `two57_calc_figures_workspace_pricing()` + email template. Backend work needed: per-calc sanitize + figures + compose cases in `inc/calc-share-email.php` (the C6 backend is generic, but each calc adds its own `$calc` case).
3. **C2 — Meet pricing** — proves colour swap (same block, different `colour_space` + `room_set`). Reuses the §6 backend.
4. **C5 — Office carbon** — medium, emission factors stay in code
5. **C4 — Meeting costs** — high complexity, industry bands comparison
6. **C3 — Office costs** — highest complexity, 7 config cards, scenario slots

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
@forward 'calc-workspace-pricing';
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
├── _calc-workspace-pricing.scss ← C1 overrides (roster, annual toggle, chart)
├── _calc-meet-pricing.scss      ← C2 overrides (future)
└── ...
```

`_index.scss` forwards them grouped:
```scss
@forward 'calc-base';
@forward 'calc-hours-to-impact';
@forward 'calc-workspace-pricing';
```

### Shared `.calc__*` class catalogue

| Class | Element | Used by |
|---|---|---|
| `.calc__intro` / `__eyebrow` / `__heading` / `__tagline` | Section intro (scroll-revealed) | All |
| `.calc__body` | 50/50 grid (inputs left, result right); stacks at `bp-lg` | All |
| `.calc__inputs` | Inputs card (`--color-surface-secondary` bg, scroll-revealed) | All |
| `.calc__fields-grid` | 2-col grid for paired inputs (stacks ≤600px) | C6, C5, others |
| `.calc__field` / `__field-label` | Field wrapper + label | All |
| `.calc__slider-row` / `__slider-controls` / `__slider-value` | −/output/+ stepper row (value readout) | All |
| `.calc__stepper-btn` | The − / + buttons (shared `.calc-icon-btn` recipe) | All |
| `.calc__radio-group` / `__radio-label` | Segmented radio buttons (`<button role="radio">`) | C1, C3, C4, C5, C6 |
| `.calc__radio-group--days` | Grid variant of `.calc__radio-group` — 5 equal columns (one per day), labels stretch to fill (`inline-size: auto`) | C5, C6 |
| `.calc__input` | Number/text input (stepper-only keyboard guard) | All |
| `.calc__microcopy` | Small helper text under inputs | C5, C6 |
| `.calc__result` / `__result-grid` / `__result-col` / `__result-label` / `__result-figure` / `__result-unit` | Result panel (dark `--color-surface-inverse-primary` bg) | All |
| `.calc__breakdown-trigger` / `__breakdown-caret` | Trigger button + CSS chevron (rotates when open) | C1, C3, C4, C5, C6 |
| `.calc__breakdown` / `__breakdown-summary` / `__breakdown-body` / `__breakdown-grid` / `__breakdown-col` / `__breakdown-heading` / `__breakdown-prose` | Full-width disclosure panel | C1, C3, C4, C5, C6 |
| `.calc__stat` / `__stat-label` / `__stat-value` / `__stat-unit` | Label/value/unit stat row | C1, C6 (extensible) |
| `.calc__share` / `__share-eyebrow` / `__share-title` / `__share-row` | Share section (email + copy cards) | All with a share row |
| `.calc__share-card` / `__share-card-title` / `__share-card-body` | Share card | All with a share row |
| `.calc__share-form` / `__share-input` | Email form row (buttons use the theme `.btn` system — no `.calc__share-btn` class) | All with a share row |
| `.calc__share-honeypot` | Visually hidden honeypot (uses `visually-hidden` utility in markup) | All with a share row |
| `.calc__share-consent` / `__share-check` | Consent checkbox + label + policy link | All with a share row |
| `.calc__share-status` | Status output (`data-calc-share-status` error/success) | All with a share row |

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

Last updated: 2026-08-18 (T1 calc widget built on `feature/calculators-continued`)

- [x] **F1** — ACF Options SSOT ✅ committed (`2ee8788`)
- [x] **F2** — `window.twofiftyseven` injector ✅ committed (`2ee8788`)
- [x] **P0** — Port `inject-prices.js` ✅ committed (`2ee8788`)
- [x] **Review checkpoint** ✅ passed — `window.twofiftyseven` populated, `data-price="dedicated"` renders `$659`
- [x] **C6** — Giving (hours→impact) ✅ committed (`3331d48`) + refactored to shared `.calc__*` system (committed `ef0d2cb`)
- [x] **C6 share row + email/copy backend** — first calculator to ship the §6 reusable system (committed `5080103`)
- [x] **C1** — Workspace pricing ✅ built + committed (`b084d49`) — retrofits the §6 share row + proves `two57_calc_figures_workspace_pricing()`; breakdown + chart refinements landed after the commit (see "C1 — Workspace pricing implementation plan" + status notes below)
- [x] **Base-SCSS refactor** — shared primitives promoted to `_calc-base.scss` (see "Shared-SCSS refactor" below), ready for C2
- [x] **Shared team-size slider system** ✅ committed (`699d816`) — `.calc__slider*` primitives in `_calc-base.scss` (range input + stepper buttons + big readout); ported to C1 and C6. Mobile hides the slider (buttons + number only). See "Slider system" below.
- [x] **C1/C6 UX polish** ✅ committed (`699d816`) — commitment field reordered below memberships + relabelled "Private office lease term"; Dedicated annual-save breakdown row hidden unless annual is ticked **and** ≥1 Dedicated member; new memberships default to **Dedicated** tier (`M.DEFAULT_TIER`); `calc-source` tooltips capped to viewport + anchored right of the trigger below `bp-md` (fixes the mobile horizontal-overflow bug the slider work surfaced)
- [x] **C2** — Meet pricing (+ Host variant) ✅ built + committed (`1d36abb`) on `feature/calculators-continued` (see "C2 — Meet pricing implementation — built" below)
- [x] **DRY refactor** ✅ staged on `feature/calculators-continued` (see the "DRY refactor" note under C2 below) — shared `.calc__option-card`/`__head`, shared `calc-utils.js` (`fmt$`/`fmtN`/`bindRovingRadio`), single `two57_meet_rooms()` PHP helper
- [x] **C5** — Office carbon ✅ built on `feature/calculators-continued` (see "C5 — Office carbon implementation" below; pending commit)
- [x] **C4** — Meeting costs ✅ built + staged on `feature/calculators-continued` (see "C4 — Meeting costs implementation plan" + "C4 — Implementation state (2026-08-18)" below; pending commit — reverified green `2026-08-18`)
- [x] **C3** — Office costs ✅ built + staged on `feature/calculators-continued` (see "C3 — Office costs implementation plan" + "C3 — Implementation state (2026-08-18)" below; pending commit)
- [x] **T1** — Quick quote teaser ✅ built on `feature/calculators-continued` (see "T1 — Calc Widget implementation" below; pending commit)
- [ ] **T2** — Impact stats partial

> **Next action (2026-08-11):** merge `feature/calculators` → `main` and deploy so C1 + C6 (slider system, tooltip fix, default tier) can be tested on the live site. After deploy, verify on live: slider drag/stepper/readout on both calcs, roster defaulting to Dedicated, annual-prepay row visibility, and tooltip behaviour on mobile (no horizontal overflow).

### C5 — Office carbon (built 2026-08-17)

Port of the standalone `/calculator/office-carbon/` source using only the shared `.calc__*` catalogue (no new CSS classes except `.calc-source__note` for engine-rendered breakdown source notes). Locked constants (Tadpole ACE 2025) and the 200% public offset claim stay in code, mirroring the source engine's `compute()`. Biodiversity credits (Sanctuary Mountain) kept as a separate stream, never aggregated.

UX matches C6 (hours-to-impact): team size is the shared `.calc__slider-row` (range input + stepper buttons + big readout, **max 15** — same ceiling as C1 workspace-pricing), days/week a roving-radio segment on the shared `.calc__radio-group--days` grid (5 equal columns, labels stretch to fill — shared with C6, `2026-08-17`), weeks/hours number inputs. Cold load pre-fills NZ defaults like C6: **team 1 · 5 days · 46 weeks · 8 hours/day** (URL params always win). The sticky-aside net figure (and the email's HTML headline) render **signed** (−kgCO₂e, `kgHtmlSigned`) to make the net-negative position explicit.

**Files:**
- `blocks/office-carbon/block.php` — intro (ACF eyebrow/heading/tagline + colour_space), inputs card (team slider row, days roving-radio grid, weeks/hours number inputs), sticky 3-stat result aside (`calc__result--sticky`, signed net figure), full-width breakdown `<details>` with comparison rows + `.calc-source` tooltips and the ESG **citation block** (`data-calc-citation-block` + `[data-calc-copy-citation]`, heading now `<h2 class="calc__breakdown-heading | text-xl">Numbers formatted for direct quotation</h2>`) nested at the end of the breakdown body, then the §6 share row (share section OUTSIDE the root → `initCalcShare(root.parentElement, …)` like C1/C2 sticky layouts)
- `assets/js/modules/office-carbon.js` — port reusing `bindRovingRadio` from `calc-utils.js`; exports `initOfficeCarbon()`; wired into `main.js` + both `transitions.js` branches (Swup page:view + bfcache)
- `acf-json/group_two57_block_office_carbon.json` + `acf/office-carbon` registration in `functions.php`
- `assets/css/06-components/_calc-office-carbon.scss` — overrides only: `.calc-source__note` for engine-rendered source notes; `.calc__kg-unit` renders the kgCO₂e/tCO₂e unit in breakdown row values two steps down the type scale + baseline-aligned (`.office-carbon` row values only); `.office-carbon .wrapper` added to the mobile padding scoping in `_calc-base.scss`
- `inc/calc-share-email.php` — `office-carbon` cases: `two57_calc_sanitize_state()` (zero-start, bounds mirror the engine), `two57_calc_figures_office_carbon()` (constants in code, no ACF), compose dispatch + `two57_calc_compose_office_carbon()` (signed net figure in the summary + HTML headline; half-hour `hoursPerDay` no longer truncated in the summary). All calc emails wrapped by the shared letter intro/sign-off (see "C6 share row" note below)
- `office-carbon` page created (ID 1078) matching the single-ACF-block page pattern

**Verified:** `npm run build` clean; `php -l` clean; page renders 200; end-to-end share email tested against Mailhog (correct 1,418 kg / 488 kg / −488 kg figures for a 6-person team, copy-link deep link `?team=6&days=5&weeks=46&hours=8` round-trips through `readURL()`).

### C4 — Meeting costs implementation plan

> **Source (2026-08-17):** `calculator/meeting-costs/index.html` (1541 lines) + `shared-js/calc-meeting-costs.js` (545 lines). Compares running a meeting/workshop/away-day/event at a Wellington industry-standard venue vs at two/fiftyseven. The source is split engine → hidden sinks + `mc:rendered` → page coordinator (routes sinks to result figures, breakdown rows, share/URL). The port merges the coordinator into the block engine (one module, matching C2), drops the mailto `.calc-contact-inline` + print card for the shared §6 share row, and reads the room/AV/tea/materials/setup numbers from the ACF SSOT instead of `MEETING_PRICES`/`MEETING_AV`.

**Reuse map — everything C4 lifts from the shared system (no new `.calc__*` primitives expected):**

| Source element | Reuse | Notes |
|---|---|---|
| Section shell + intro | `.calc__body`, `.calc__inputs`, `.calc__field`, `.calc__field-label`, `two57_calc_intro()` | Same 50/50 body + inputs-card skeleton as C2/C5 |
| Group size stepper (0–200) | `.calc__slider-row` / `-controls` / `-stepper-btn` / `-slider` / `-slider-value` + `bindStepper` | Value-based (`valueFor: i => i`), like C1/C5/C6 team steppers. Readout keeps base 2ch (values ≤ 200 fit) |
| Day rows (date + start/end times) | `.calc__repeat` + `.calc__add-btn` + `.calc__day-row` shell (`-label`/`-inputs`/`-hours`/`-remove`) | C4 uses the **same native date + time pickers as C2** in the base 5-col `.calc__day-row-inputs` grid (date \| start \| → \| end \| hours) — no per-calc time widget (see "Implementation state") |
| Catering / AV / materials checkbox cards | `.calc__option-card`/`.calc__option-head` + `.calc__check` swatch, per-calc `.meeting-costs__card-list` | 2-col card grid (1-col < bp-sm); whole card is the click target; monospace price hint (`meeting-costs__price-hint`) under each title |
| Facilitation + setup radio cards | `.meeting-costs__radio-cards` of `<button role="radio" class="meeting-costs__radio-card">` + `bindRovingRadio` | 2-col cards with `.meeting-costs__radio-indicator` circle filled on `[aria-checked="true"]`; setup merged to **Standard** (default — pack-down included, no charge) + Complex |
| Impact discount | `.calc__option-card` + `.calc__option-head` (addon-card recipe) | Same as C2's Step 6 card; reads `impact.discountPct` |
| Custom expense lines | `.calc__repeat` + `.calc__add-btn` | Per-calc `.meeting-costs__custom-row` (label input + value input + remove); one blank row by default; comma-tolerant value input |
| Result aside | `.calc__result` + `.calc__result--sticky` + workspace-price-style `.calc__result-grid-headline` + shared `.calc__chart*` bars (promoted to `_calc-base.scss`) | Single headline figure (At two/fiftyseven) + venue-vs-ours bar chart (venue low/high band, ours solid, all stopped short of a `max(venue,ours)×1.25` ceiling for headroom) + Savings row — mirrors workspace-pricing, **not** a 3-col grid |
| Breakdown | `.calc__breakdown` (details id `methodology`) + `.calc__breakdown-grid` + 2 × `.calc__breakdown-col` | Each col is a `.calc__compare` list (`.calc__compare-row` / `-row-label` / `-row-value` / `-row--total`); `bindBreakdownTrigger(root, 'methodology')` |
| Citation sources | `.calc-source` tooltips + `bindSourceTooltips(root)` | Source's `.breakdown-row__source` anchors → the shared tooltip glyph/pop (workspace/carbon precedent) |
| Share row | `two57_calc_share()` + `initCalcShare(root.parentElement, { slug: 'meeting-costs', getState })` | Sticky aside ⇒ share sits **outside** the root (C2/C1 rule). Default "numbers" copy, maybe meeting-worded overrides |

**SSOT reads (`window.twofiftyseven`, no new ACF fields):**
- `rooms` — rates + `capacity` for `pickSpace()` auto-selection. Source `MEETING_PRICES` is exactly the ACF `rooms` shape; `two57_meet_rooms()` slugs/names win (source "Studios Whātaitai + Ngake" → ACF "Studio", etc.).
- `addons.av.{projector,sound}.flat` (was `MEETING_AV` $50 flat), `addons.tea.singlePerHead` (was `MEETING_TEA_PER_HEAD` 5).
- `impact.discountPct` — 50% off the 2/57 total (never the industry band), same as source.
- **SSOT-improvement over source:** materials post-its/printing ($30/$60) and complex setup ($200) are hardcoded in the source engine → read from ACF `materials_postits_charge` / `materials_printing_charge` / `setup_complex_charge`. Catering passes through at **industry midpoint** (source behaviour) — *not* the ACF `catering_organising_fee` (that's C2's model); keep source behaviour, note the discrepancy in the code comment.

**Stays in code:** the `IND` industry bands (29 constants — cited Wellington/Auckland research per §2) and `pickSpace`/`deriveDuration` methodology.

**Engine port (`assets/js/modules/meeting-costs.js`):** `initMeetingCosts()` on `[data-js="calc-meeting-costs"]`; `initCalc()` guard bails if `ssot.rooms` empty (C2 precedent). State: `size`, `days[{date,start,end}]` (native date + time pickers → 24h `HH:MM`), 11 option booleans + `impact`, `fac`, `setup`, `custom[{label,value}]`. URL sync via `readURL`/`writeURL` (compact scheme: `size`, `days` as comma-joined `YYYY-MM-DD|HH:MM-HH:MM`, `extras` token list incl. `impact-discount`, `fac`, `setup`, `custom` as `encodeURIComponent(label)|value` pairs — dates round-trip from picker through share link). `deriveDuration` stays module-local. Reuses `fmt$`, `bindStepper`, `bindRovingRadio`, `bindBreakdownTrigger`, `bindSourceTooltips`, `initCalcShare`; `fmtBand` kept for breakdown bands, `fmtRate` added for 2dp per-head rates.

**Email backend (`inc/calc-share-email.php`):** `two57_calc_sanitize_meeting_costs()` (size int 0–200, per-day date `YYYY-MM-DD` **carried through** + start/end validated to 24h `HH:MM`, 12 bools, `fac` whitelist + `setup` whitelist `standard`/`complex` (default `standard`), custom lines label + positive value, impact bool), `two57_calc_figures_meeting_costs()` (mirror compute(): rooms from ACF via `two57_meet_rooms()` + `get_field`, IND bands in code, av/tea/materials/setup from ACF; returns industry band, 2/57 total, saving band, space name), `two57_calc_compose_meeting_costs()` (summary: group size + duration + "X against industry band Y", saving band, space name; share-link `days` rebuilt with the carried dates). Plus the three dispatch switches (sanitize/figures/compose) gain the `meeting-costs` case.

**Files (per §4 checklist):**
1. `acf-json/group_two57_block_meeting_costs.json` — `colour_space` select + `mc_eyebrow`/`mc_heading`/`mc_tagline`. **No `room_set`** (C4 has no room picker — `pickSpace` auto-selects).
2. `blocks/meeting-costs/block.php` — identity class `meeting-costs`; body grid with: size slider, day-row repeat (date + time pickers), option-card groups (catering/AV/materials + facilitation/setup radio cards + impact card), custom-row repeat; sticky workspace-style result aside (headline + chart + savings) + breakdown trigger; `two57_calc_share()` outside the root; full-width `details#methodology` breakdown with 2 compare cols + `.calc-source` citations.
3. `assets/js/modules/meeting-costs.js` + `main.js` + `transitions.js` wiring (three call sites).
4. `assets/css/06-components/_calc-meeting-costs.scss` — per-calc only: `.meeting-costs__card-list` (2-col), `.meeting-costs__radio-cards` + `__radio-indicator`, `.meeting-costs__price-hint`, `.meeting-costs__custom-row`, `.meeting-costs__duration-label`. Forward in `_index.scss`. (Chart styles live in the shared `_calc-base.scss`, promoted out of `_calc-workspace-pricing.scss`.)
5. `functions.php` — `acf_register_block_type` `meeting-costs` (`257 Calc Meeting Costs`).
6. `inc/calc-share-email.php` — the three `meeting-costs` cases.

**Build order:** markup/ACF/registration → engine + wiring → SCSS → email cases. **Verify:** `npm run build` + `php -l` clean; band/duration switching; addon toggles + custom rows; impact discount; email to Mailhog (`calc_source = meeting-costs`); copy-link round-trip; zero-start empty state; no-JS fallback.

**Gotchas:** duration derives from populated day rows (2+ populated days → multi-day; start ≥ 17:00 → evening; <3h hourly; <6h half-day; else full-day) — this drives both the `IND` band and the 2/57 room rate (day/evening/hour), keep `deriveDuration` faithful. `pickSpace` auto-selects the space from group size (6/12/36/80/200) — no room tiles in markup; the space name shows in the aside unit + breakdown. The share row + breakdown live OUTSIDE the `[data-js]` root (`.calc__body`); query them from the wrapper (`scope`), the office-carbon pattern.

### C4 — Implementation state (2026-08-18)

Refinements landed on the staged build since the plan was written (all green; final hashes `main-D9XVR_p9.css` → `main-N-znvEJ9.css`, `main-CPe3QqZr.js` → `main-BnhulEqY.js`, then `main-BnBBbwHc.js` after the person/people fix):

- **Time pickers:** replaced the 12h AM/PM text-field + pill pair with the **same native `date` + `time` inputs as C2** in the base 5-col `.calc__day-row-inputs` grid; AM/PM widget SCSS/JS helpers (`to12h`/`to24h`, `meeting-costs__time-*`) removed. URL days schema changed to `YYYY-MM-DD|HH:MM-HH:MM` (both `writeURL` and the PHP compose builder); local-time `todayISO()`/`isoFormat()` prevent UTC date-shift; cleared pickers fall back so links always round-trip.
- **Cards:** catering/AV/materials via `li.meeting-costs__card.calc__option-card` in a 2-col `.meeting-costs__card-list`; facilitation/setup via `.meeting-costs__radio-cards` of radio buttons with `.meeting-costs__radio-indicator` circles; whole cards clickable; monospace price hints painted once (`meeting-costs__price-hint`) under card titles with sources mirroring `compute()`.
- **Aside redesign:** workspace-pricing layout — single "At two/fiftyseven" headline (venue big figure removed), `.calc__chart*` bar rows (venue low/high band + solid ours bar, anchored to `max(venue,ours)×1.25` ceiling for headroom), Savings row. Chart styles promoted to shared `_calc-base.scss`; workspace-pricing markup/JS converted 1:1 to the shared classes.
- **Setup merged** to Standard (default; "Standard setup + pack-down, included") + Complex; JS (+ `readURL`/`writeURL` gates) PHP sanitize whitelist (`standard`/`complex`), compose link gate all updated together.
- **Email fixes (2026-08-18):** `$band` closure missing `use ($money)` → fatal on non-empty sends; per-day `date` carried JS `getState` → sanitize → compose so the emailed share link reproduces picker dates; `sanitize_email`/`sanitize_key` guarded against non-scalar JSON (clean 400 instead of 500); `calc-share.js` `sync` hoisted out of its block (was `ReferenceError` in the send `finally`).
- **Email verified live (2026-08-18):** a real 2-day, size-1 booking with catering/tea/facilitation/materials/custom lines sent to Mailhog end-to-end ($8,383 ours vs $6,943–$12,490 venue, savings $0–$4,107). Follow-up singular grammar fix: `1 person` not `1 people` in the summary + industry room note (PHP compose + JS breakdown).
- **Full code review passed (2026-08-18):** JS↔PHP field mapping, business-math parity (identical IND bands, defaults, factors), share-link token parity (`impact-discount`, `fac`/`setup` gates, `custom` encoding), selectors/markup contract, empty-state handling all verified consistent. Known non-blockers: server caps days at 14 / custom lines at 10 while the JS "add" is unbounded (meet-pricing parity); public endpoint + non-atomic 3/10min rate limit (pre-existing, all calcs).

**Verify:** `npm run build` + `php -l` clean (done). Open items before merge: full in-browser pass (initial load + Swup reinit, deep-link restore in-browser, real REST POST to Mailhog for the meeting-costs email), then commit to `feature/calculators-continued`.

### C3 — Office costs implementation plan

> **Source (2026-08-18):** `calculator/office-costs/index.html` (2093 lines) + `shared-js/calc-office-costs-v2.js` (606 lines). Configures a Wellington office top-to-bottom — rent (grade + precinct modifiers), outgoings, utilities, cleaning, consumables, compliance/insurance, furniture amortisation, admin overhead, lease legals, booking software, custom lines — and compares the total against two/fiftyseven memberships in a savings band (`.oc-vs257`). Highest-complexity port: 7 config cards, a per-member days roster, 3-slot localStorage scenarios + compare dialog, and the custom-line repeat. The source is split engine → hidden sinks + `occv2:rendered` → page coordinator (repaints result mirror, breakdown rows, scenarios, URL). The port merges the coordinator **and the four standalone inline scripts** (team roster, grade→rent sync, savings band, take-it) into one ES module (C2/C4 one-module pattern), drops the mailto + PDF take-it cards for the shared §6 share row, and reads the savings-band membership prices from the ACF SSOT instead of the source's hardcoded `FLEXI{109..509}/DEDICATED 659`.

**Reuse map — everything C3 lifts from the shared system before anything new is built:**

| C3 element | Reuse | Notes |
|---|---|---|
| Section shell + intro | `.calc__body`, `.calc__inputs`, `.calc__field`, `.calc__field-label`, `two57_calc_intro()` | Same 50/50 body + inputs-card skeleton as C2/C4/C5 |
| Team size 1–15 | `.calc__slider-row` / `-controls` / `-stepper-btn` / `-slider` / `-slider-value` + `bindStepper` | Value-based (`valueFor: i => i`), exactly C1/C5/C6 team steppers; person/people suffix keeps the C4 singular fix |
| Grade (4 radios) | `.calc__radio-group` + `bindRovingRadio` | C1 commitment-radios precedent |
| Precinct (7 options) | `.calc__select` | Shared custom-chevron dropdown, `.oc-select` precedent |
| Number inputs | `.calc__input` — **free-edit, NOT `restrictStepperInputs`** | C3 is the "tune every variable" calc; typing is the point (source uses bare `<input type="number">` + min/max hints, engine doesn't JS-clamp beyond `toNum` defaults) |
| Custom expense lines | `.calc__repeat` + `.calc__add-btn` + per-calc `.office-costs__custom-row` | C2/C4 custom-rows precedent (label + value + remove row + add); source's `<template>` clone + `data-count` logic ports to the module |
| Per-member days sliders | per-calc roster **built on the shared `.calc__slider` primitives** (`.calc__slider` wrapper + range + readout) | Team-driven repeat of Member-N 1–5 day sliders (C1 roster pattern); not the team-level `.calc__radio-group--days` |
| Config card tooltips | `.calc-source` glyph/pop + `bindSourceTooltips` | Plan §12 already mapped C3's `.oc-tip` → `.calc-source` structured-panel variant; each field's tooltip holds default / range / sources |
| Result aside | `.calc__result` + `.calc__result--sticky` + `.calc__stat` rows | Headline annual + secondary stat rows (monthly / pp-month / pp-day / per-sqm); mirror pairs drop (module renders directly) |
| Savings band | reads `window.twofiftyseven.prices` (**SSOT**, no hardcoded FLEXI/DEDICATED) | per-member days → Flexi tier, 5 days counts as Dedicated high; saves = office total − 257 band |
| Breakdown | `.calc__breakdown` `details#methodology` + `.calc__compare` rows (`-row-label`/`-row-note`/`-row-value`/source link) + `bindBreakdownTrigger` | Category grid (6 cats + %) is genuinely new — the source's `[data-occv2-category-slot]` is dead markup; we render it |
| Citation sources | `.calc-source` tooltips + `bindSourceTooltips` | Breakdown rows keep the source's inline source **anchors** (target=_blank) since each row cites a distinct URL |
| Share row | `two57_calc_share()` + `initCalcShare(root.parentElement, { slug: 'office-costs', getState })` | Sticky aside ⇒ share **outside** the root (C1/C2/C4 rule). Drop mailto + PDF per §6 |

**New (genuinely per-calc, no `.calc__*` base additions expected):** the 3-slot **scenario system** — save/load/clear via prompt-naming, right-click/long-press clear (`contextmenu`), localStorage-backed, "Save current →" / "Compare all →" buttons and a compare `<dialog>` (`<div class="oc-compare" data-scenario-compare-dialog>`) — plus member-days roster, savings band, category grid, grade→rent display box (visible grade-adjusted rate ↔ hidden base `data-occv2-rent-sqm`) in `_calc-office-costs.scss`. Flag scenarios as the *only* new interaction type; promote to `_calc-base.scss` only if a second calc ever needs save/compare.

**SSOT reads (`window.twofiftyseven`, no new ACF fields):** `prices.dedicated` + `prices.flexi-5..1` for the savings band (source hardcoded 659/109–509). Everything else already injected but **not** used.

**Stays in code** (cited research per §2): `DEFAULTS` (rent 310/sqm, sqm/pp **9**, opex 0.27, furniture 2000/5yr, internet 200, power 50W/sqm × 1840h × $0.30, cleaning 1.2hr × $45, kb 300, insurance 200, firstAid 28, fireWarden 18, admin 0.06 × $70, legals 3500/3yr, booking $8 auto-on ≥10), `GRADE_MODIFIER` + `PRECINCT_MODIFIER`, the 14× `SOURCES` URLs, compute() (value-add Job 11 quantification, MHFR), 46 working weeks. Note: `sqmPerPerson` stays **9** (cited v2 default, user-editable) — documented divergence from C1's 10, reconciled only by comment per §5.

**Engine port (`assets/js/modules/office-costs.js`):** `initOfficeCosts()` on the block root `[data-js="calc-office-costs-v2"]` (legacy engine hook kept, **the only "v2" remnant** — C1 workspace-pricing already owns `[data-js="calc-office-costs"]`, so the two blocks can't share that query hook; block slug + all file/email/copy naming is plain "office costs"); `initCalc()` guard bails if root missing. Merges the engine + coordinator + 4 inline scripts into one module; **`window.occv2` is dropped** — no global, scenarios stay module-local (localStorage `occv2-scenarios` key kept), custom-lines `restoreCustomLines` becomes internal. State read from DOM per tick (C3's inputs are all DOM-carried, no state object distinct from the inputs). URL sync via `readURL`/`writeURL` keeping the source's compact keys: `team/days/pre/sqm/rent/opex/net/pw/phr/pkw/chrs/crt/kb/ins/fa/fw/adp/adr/leg/lty/fpp/fy/bc` + `grade`/`bt` (booking toggle)/`cNl cNv` (custom rows). Reuses `fmt$`/`fmtN`, `bindStepper`, `bindRovingRadio`, `bindBreakdownTrigger`, `bindSourceTooltips`, `initCalcShare`.

**Email backend (`inc/calc-share-email.php`):** `two57_calc_sanitize_office_costs()` (team clamp **0–15** — zero kept so the empty card emails as an empty-state prompt, mirroring the engine's `zeroResult`; days array per-member 1–5 rebuilt/padded to team length; per-number bounds mirror the input min/max; `grade`/`precinct` whitelist to the modifier sets; `bookingSoftware` bool; custom lines sanitize label + clamp positive value), `two57_calc_figures_office_costs()` (mirror compute(); membership prices from ACF via `get_field('membership_{slug}_monthly')` for the savings band — same slugs as workspace-pricing; returns annual/monthly/pp figures + line list + category split + value-add + saving band), `two57_calc_compose_office_costs()` (annual/monthly/pp-month/day + savings-band summary; share-link rebuilt with the engine's compact keys: `team`, comma `days`, `grade`/`pre` only when non-default, `bt` when booking on, non-default cost fields, and custom rows as `c{i}l`/`c{i}v`). Plus the three dispatch switches gain the `office-costs` case.

**Files (per §4 checklist):**
1. `acf-json/group_two57_block_office_costs.json` — `colour_space` select only (+ intro eyebrow/heading/tagline like the others).
2. `blocks/office-costs/block.php` — identity class `office-costs`; 7 config cards (`.calc__fields-grid`); team slider + member-days roster; sticky result aside (headline + stat rows + savings band + scenario slots); full-width `details#methodology` breakdown (compare cols + category grid + `.calc-source` citations); `two57_calc_share()` outside the root.
3. `assets/js/modules/office-costs.js` + `main.js` + `transitions.js` wiring (three call sites).
4. `assets/css/06-components/_calc-office-costs.scss` — per-calc only: member-days roster, savings band, scenario slots + compare dialog, category grid, custom rows, grade→rent box. Forward in `_index.scss`.
5. `functions.php` — `acf_register_block_type` `office-costs` (`257 Calc Office Costs`).
6. `inc/calc-share-email.php` — the three `office-costs` cases.

**Build order:** markup/ACF/registration → engine (merged coordinator + scenarios) + wiring → SCSS → email cases. **Verify:** `npm run build` + `php -l` clean; team/days-roster regen on team change; grade→rent display sync both directions; booking auto-on at team ≥ 10; scenarios save/load/clear/compare (localStorage persists); savings band vs ACF prices; custom-line add/remove; URL round-trip (incl. days + custom); email to Mailhog (`calc_source = office-costs`); zero-start; no-JS fallback.

**Gotchas:** team slider is **min 1 / default 1** in the source (roster + savings band populate immediately) while the engine still returns `zeroResult` for team 0 — keep the source default but guard a URL `team=0` into the empty card (`applyURL` clamps 0 and `readState` treats `team<=0` as empty). The source coordinator reads `[data-occv2-team-display].value` (an `<output>` — `.value` is always undefined) for the mailto body → fix by reading the team number from state, not the DOM. The `[data-occv2-category-slot]` cat grid is dead in the source (queried, never in markup) — the port adds a real grid. The roster rebuild takes an `afterInput` callback (the shared `retick`) instead of the source's synthesized bubbling `input` — every roster row, custom row and URL-applied value ticks through the same `retick`; no event dispatch needed.

### C3 — Implementation state (2026-08-18)

Built on `feature/calculators-continued` on top of the shared `.calc__*` catalogue. All four source pieces merged into **one module** (`assets/js/modules/office-costs.js`, no `window.occv2` global):

- **Engine** — `initOfficeCosts()` on `[data-js="calc-office-costs-v2"]` (the only "v2" remnant; C1 owns the non-v2 hook). Merges the v2 engine + page coordinator + 4 inline scripts (team roster, grade→rent sync, savings band, take-it). Team stepper via `bindStepper` (`rangeSel '[data-oc-team-range]'` … `valueFor: i => i`, `onUpdate: syncTeam`); grade roving radios + `bindGradeRent` (visible grade-adjusted `[data-oc-rent-display]` ↔ hidden base `[data-occv2-rent-sqm]`, both directions); roster rebuild (`rebuildRoster`) of per-member 1–5 day sliders into `[data-oc-days-roster]`; custom rows (`renderCustomList`, add/remove, comma-tolerant value, URL growth via `cNl cNv`); scenario system (`bindScenarios` — 3 localStorage slots, click-save/restore + prompt-naming, right-click clear, "Save current →" / "Compare all →" / "Reset all →", `compare-showModal()` dialog); URL `applyURL`/`writeURL` with the source's compact keys + comma-joined per-member `days` (port fix: source only captured the first member's slider); savings band reads `window.twofiftyseven.prices` SSOT (per-member days → Flexi tier, 5d = Dedicated, band `annual − {hi,lo}`); breakdown `bindBreakdownTrigger(root, 'methodology')` + per-line `.calc-source__note` + source-anchor `.office-costs__row-source` (target=_blank) + real category grid + value-add rows; share `initCalcShare(scope, { slug: 'office-costs', getState })` (full flat state incl. `customLines`).
- **Markup** — `blocks/office-costs/block.php` (identity `office-costs`, `data-color-space`): 7 group headings (Team / Office / Utilities / Cleaning+consumables / Compliance+insurance / Furniture+admin+legals / Add-ons+custom), team slider row + empty days roster UL, grade radio cards (2-col grid) + precinct select, `.office-costs__input-row` number fields with min/max/step + `.calc-source` tooltips, booking `.calc__option-card` (cost row hidden until toggled), custom-row repeat + add button, sticky `.calc__result--sticky` aside (annual headline + 4 `.calc__stat`s + `.calc__chart-savings` band hidden until positive + empty state), scenario slots + actions, `details#methodology` breakdown (lines + total + category grid + value-add), `two57_calc_share()` **outside** the root, compare `<dialog class="office-costs__compare">`.
- **ACF** — `acf-json/group_two57_block_office_costs.json` (`oc_eyebrow`/`oc_heading`/`oc_tagline` + `colour_space`, location `acf/office-costs`); `office-costs` registered in `functions.php` after `meeting-costs`. Default intro copy: eyebrow "Wellington office cost calculator", heading "how much does it cost to run an office in Wellington?", tagline "Your tool for true cost accounting on a Wellington office. Estimate the full costs with sourced figures on admin, compliance, lease legals and cleaning. No surprises."
- **SCSS + email** — `assets/css/06-components/_calc-office-costs.scss` (roster rows, input-row grids + suffixes, grade radio cards 2-col, scenario slots + filled state + compare dialog + backdrop, category grid, custom rows, compare-close button — no mixin dependency on `_calc-base`), forwarded in `_index.scss`; `inc/calc-share-email.php` `office-costs` cases written + `php -l` clean.
- **Wiring** — `main.js` + `transitions.js` (one import + three call sites: initial, Swup reinit, navigation reinit).

**Refinements landed during the build session (all green; final build `main-BhSdszS-.js` / `main-BT_1KjK2.css`):**

- **Stat aside legibility** — `.calc__stat-label` + `.calc__stat-unit` lifted to `--color-content-inverse` (was `--color-content-secondary` + opacity, illegible on the dark result card); stat rows switched to a 3-column equal-width grid (`1fr 1fr 1fr`) so label / value / unit align consistently across all four rows.
- **Grade radio cards** — replaced the small square `.calc__radio-label` buttons with full radio cards (`.office-costs__radio-card` — indicator circle + title + body description) in a 2-column grid (1-col < 600px), matching the meeting-costs radio-card pattern.
- **Input sizing** — `.calc__input` font stepped down one type-scale notch (`--text-l-size` → `--text-m-size`); `max-inline-size: 160px` (140px inside input rows) so suffixes wrap naturally; `.office-costs__input-row` changed from grid to `flex-wrap: wrap` (matches the demo's `oc-var__field-row` layout). Custom-row inputs exempted (`max-inline-size: none`) so they stretch full container width.
- **Per-input labels** — every input in multi-input sections (Power, Cleaning, Furniture, Admin, Lease legals) given its own field with label + tooltip + suffix, matching the demo's one-field-per-input layout. Bracketed units removed from all field labels (suffix next to the input carries the unit).
- **Percentage inputs** — outgoings + admin-pct inputs changed from decimal (0.27 / 0.06) to whole-number percent (27 / 6) with `%` suffix; `readState` divides by 100 for calculation; URL params still store the decimal for backwards compatibility; `restoreSaved` detects legacy decimal values (<1) and converts; reset handler restores to the whole-number default.
- **Team slider suffix** — removed the "person/people" suffix span (redundant with the "Team size" label).
- **Booking toggle** — whole option card is the click target (not just the label); `click` listener on `[data-oc-booking-card]` with guards for label + input clicks so the cost field stays editable; `change` listener kept as fallback.
- **Custom rows** — `renderCustomList` now sets `data-occv2-custom-row` on each `<li>` (was missing, causing "Add another" to re-render to a single row); remove × button `disabled` when only one row (hidden via `&:disabled { visibility: hidden }`), matching meeting-costs.
- **"Reset all →" button** — third scenario action restores every input to its default, clears custom rows, clears all 3 localStorage scenario slots, resets team slider to 0 (via `stepper.paintCurrent()` callback), syncs booking visibility, rebuilds roster, and reticks.
- **Compare dialog scroll lock** — `getScrollInstance()?.lenisInstance?.stop()` on open, `start()` on close (× button + native Esc `close` event), matching the `nav-mobile.js` pattern.
- **Result headline** — changed from "Annual total · your office" to "Annual total office costs".
- **Build hashes:** `npm run build` green; `php -l` clean on `blocks/office-costs/block.php` + `inc/calc-share-email.php` + `functions.php`.

**Verify (residual):** in-browser pass — roster regen on team change; grade→rent sync both directions; booking auto-on at team ≥ 10; booking-cost strip visibility; scenarios save/load/clear/compare/reset in localStorage; savings band vs ACF prices; custom-line add/remove + URL growth; URL round-trip incl. `days` + `cNl cNv` + `bt` + `opex`/`adp` decimal conversion; email to Mailhog (`calc_source = office-costs` — sanitize/figures/compose dispatched); zero-start empty card; no-JS fallback (hidden roster/result + empty state).

### C6 share row + email/copy link (built on C6, not C1)

The §6 share/email system was built on C6 (hours-to-impact) instead of the originally suggested C1 — C6 is already live with the full `.calc__*` system, and the backend infrastructure is fully generic, so C1–C5 now just add markup + a one-line `initCalcShare()` call + their per-calc `$calc` backend cases (sanitize/figures/compose). Decision recorded per §6.10's flexibility.

**Implemented:**
- `inc/calc-share-email.php` — REST endpoint `POST /wp-json/two57/v1/calc-share-email` (honeypot → `is_email` → consent gate → per-calc state sanitise/clamp → server-side recompute from ACF → email compose → send → MailPoet lead capture, all in one file, loaded from `functions.php`)
- **Email letter wrapper** — `two57_calc_email_letter_open()` (applied centrally in the compose dispatcher so every current + future calc gets it): te reo greeting *"Kia ora. / Ka pai for running your numbers at two/fiftyseven!"* up top and sign-off *"One of our friendly kaitiaki will be in touch… Ngā mihi nui."* just before the address/contact-policy imprint, in both plain + HTML (`2026-08-17`)
- Lead capture on shared **"Calculator leads"** MailPoet list + `calc_source` custom field, both auto-created on first send
- Send via MailPoet `MailerFactory` with `wp_mail()` fallback; QA log hook `TWO57_CALC_EMAIL_LOG` (defined → email body written to `error_log` instead of sent)
- Per-IP rate limit 3/10min via transient
- `assets/js/modules/calc-share.js` — **shared** handler module (`initCalcShare(root, { slug, getState })`): email submit (POST + honeypot + consent **and** email gating on the disabled submit + status UI), copy-link with `navigator.clipboard` → `execCommand` fallback, feedback on the button label (no copy status element)
- `.calc__share-*` classes added to `_calc-base.scss` (email + copy cards; honeypot uses the `visually-hidden` utility directly, no `@extend` across module scopes); buttons use the theme `.btn` system; `.btn:disabled` added to `_button.scss`
- `block.php` share markup inside the calc root after the breakdown `<details>` (consent `<p>` **inside** the `<form>` — required by the module)
- REST endpoint URL exposed to JS via `wp_localize_script` → `window.two57CalcShare.emailEndpoint`

**Client copy note:** email form copy (card titles "Email me these numbers" / "Share the numbers", "By submitting I agree to the Contact policy" ← `/contact-policy/`) matches §6.1; PDF card intentionally dropped; em-dashes removed from all user-facing copy (their design guide).

**Imports:** `two57CalcShare` localized in `functions.php`; `calc-share.js` imported only by `hours-to-impact.js` (runs via existing `initHoursToImpact` wiring in `main.js` + `transitions.js` — no new registrations).

**QA verified locally (DevKinsta + Mailhog, 2026-08-11):**
- REST route registered (`OPTIONS /wp-json/two57/v1/calc-share-email` → 200); honeypot → fake `{success:true}`; no-consent / invalid-email / unknown-calc all reject with clean messages
- Happy path → `{success:true}`; send verified end-to-end into **DevKinsta Mailhog** after pointing MailPoet at local SMTP (see "Local mail testing" below; the email arrived: subject "Your two/fiftyseven impact calculation" → recipient in Mailhog UI at `http://localhost:15400`)
- Lead captured: subscriber `subscribed` on **"Calculator leads"** list + `calc_source = "hours-to-impact"` custom field (list + field auto-created on first send)
- State clamping verified: `{team:99, days:0, weeks:60, hours:99}` → `{30,1,52,24}`; empty state → engine defaults `{1,5,46,8}`
- Rate limit: 3 ok then "blocked on 4"
- Composed email verified: summary "$3,680", per-person figures, shareable link `…?team=2&days=5&weeks=46&hours=8`, contact-policy footer
- Test lead cleaned up afterwards; `TWO57_CALC_EMAIL_LOG` remains as the no-Mailer diagnostic hook
- **Venue note (local)**: `home_url()` resolves to the WP-configured vanity domain (`two-fiftyseven.local:61448`) — the email "open the calculation" link uses whatever `WP_HOME`/siteurl is set to, so it's correct in production automatically. Test page currently 404s locally because no `/calculator/hours-to-impact/` page exists in this install (the demo lives on Cloudflare Pages).

**Post-review refinements (2026-08-11):**
- Gating extended to a **standard dual gate**: submit stays `disabled` until consent ticked **and** email non-empty; empty-email submits now show "Enter your email address to send." instead of the generic failure (review feedback — the generic "Couldn't send…" masked a missing-email submission). `sync()` re-runs on `input`/`change` and after the send attempt's revert.
- Copy-link simplified per review: no `[data-calc-share-copy-status]` box — the button label communicates state ("Link copied ✓" / fallback message) and reverts after 4 s.
- `.btn:disabled` visual added to `_button.scss` (opacity 0.4 + `not-allowed` + hover/active suppressed) so the gated submit reads as disabled.
- Minor plan-vs-code corrections folded into §6: reply-to "site admin" never shipped (removed from plan); client and server clamp floors differ at the degenerate edge (engine `Math.max(0,…)` vs server min 1) — harmless, values below 1 are nonsense and the plan documents server bounds.

### Local mail testing (DevKinsta) + go-live MailPoet setup

**How local email testing works.** In DevKinsta, real email delivery is unreliable (MailPoet's cloud Sending Service silently drops mail from a local vanity domain, and the free tier has sender-verification rules). DevKinsta ships a **Mailhog** as a mail trap. The compose + send code in `inc/calc-share-email.php` is **environment-agnostic** — it asks MailPoet for its default mailer and only falls back to `wp_mail()`, so no SMTP host/IP is hardcoded anywhere in the theme. All local mail config lives **in the local DB + container only, not in git, not deployed** (the theme ships zero mail-sending config; verified by grep for `devkinsta|mailhog|10501|172.172` — the only hits are the pre-existing Vite HMR block, which is gated to `.local`/`.localhost` hosts).

**Local mail config that was set (re-create after a DevKinsta stack restart):**
1. **MailPoet → Settings → Send with…** → **Your own SMTP** (not the Sending Service). Host `devkinsta-mailhog`, port `1025`, Auth **No**, encryption **None**.
   - `localhost:10501` does NOT work — that host-port mapping is Mac-side only; `1025` is the port *inside* the Docker network where the send actually runs.
2. The hostname must be `devkinsta-mailhog` (hyphen), **not** `devkinsta_mailhog` (underscore) — MailPoet's bundled PHPMailer rejects the underscore as an invalid hostname (`Invalid host:` error).
3. The hyphenated name needs resolving inside the fpm container. This `/etc/hosts` entry is ephemeral (resets with the container), re-add on restart:
   ```
   docker exec devkinsta_fpm sh -c 'echo "172.172.0.5 devkinsta-mailhog" >> /etc/hosts'
   ```
   The `172.172.0.5` is the Mailhog container IP on the Docker bridge — it's stable as long as the Mailhog container isn't recreated, but verify with `docker exec devkinsta_fpm getent hosts devkinsta_mailhog` if unsure.
4. Sanity-check the mailer is configured as expected:
   ```
   docker exec devkinsta_fpm php -r 'chdir("/www/kinsta/public/two-fiftyseven"); include "wp-load.php"; global $wpdb; $m=unserialize($wpdb->get_var("SELECT value FROM {$wpdb->prefix}mailpoet_settings WHERE name=\"mta\"")); echo json_encode(["method"=>$m["method"],"host"=>$m["host"],"port"=>$m["port"],"auth"=>$m["authentication"]]);'
   ```
   Expect `{"method":"SMTP","host":"devkinsta-mailhog","port":"1025","auth":"0"}`.

**Testing locally:** submit the calc's Send form and view the email at the **Mailhog UI** `http://localhost:15400` (or via API `http://localhost:15400/api/v2/messages`). Remember the per-IP rate limit (3/10 min) will block repeated test sends — clear it with:
```
docker exec devkinsta_fpm php -r 'chdir("/www/kinsta/public/two-fiftyseven"); include "wp-load.php"; global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"%two57_calc_share_rl_%\"");'
```
Alternative without Mailhog: define `TWO57_CALC_EMAIL_LOG` in `wp-config.php` → composed email written to `error_log` instead of sent. The two-fiftyseven site needs a WP page with the `257 Calc Hours to Impact` block to test in the browser (no such page exists locally yet — `/calculator/hours-to-impact/` 404s; the demo is on Cloudflare Pages).

**Go-live checklist (nothing code-related in the theme):**
1. **MailPoet plugin** installed + activated on the live site (must be the same/dot-compat API; the theme auto-detects it and falls back to `wp_mail()` if absent, so email still works without MailPoet — lead capture just won't happen).
2. In **MailPoet → Settings → Send with…** choose the real method:
   - **Cloud Sending Service** (MailPoet's) — recommended for deliverability; requires a **verified sender address** (`kiaora@…`) under **MailPoet → Settings → Sender/Key**, and a valid API key. Emails then send from MailPoet's servers.
   - or **Your own SMTP** (e.g. host SMTP, port 587, STARTTLS, auth On) from the host/ESP.
3. Sender name/email under Settings must be a real address the client checks (currently `kiaora@twofiftyseven.co`).
4. **Create the "Contact policy" page** at `/contact-policy/` (the email footer + consent checkbox link to it). The **"Calculator leads"** MailPoet list + `calc_source` custom field auto-create on the first calculator email submit — no manual setup.
5. **Test after deploy:** load a page with a calc block → Send a calculator email to a real inbox → confirm (a) the inbox receives "Your two/fiftyseven impact calculation", and (b) the sender appears in MailPoet → Subscribers, on the "Calculator leads" list, with `calc_source` = the calc slug. Copy-link needs no config.

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
- Team-size stepper upgraded to the shared `.calc__slider*` system (`699d816`, max 30 — see "Slider system" below)

### C1 — Workspace pricing implementation plan

> **Caveat (2026-08-11):** two engines share the `office-costs` name. This plan ports the **v1 engine** (`calc-office-costs.js`) + the **pricing coordinator** (`pricing/index.html`) — **not** `calc-office-costs-v2.js` (that is C3). The source `pricing/index.html` has **no share row** (it uses a mailto "Take it" row) — the §6 share markup is added to the block.

**Serve/hydrate mapping** (`docs/257-calculators-wp/source-calculators/`):

| v1 engine / pricing page | Block port |
|---|---|
| root `[data-js="calc-office-costs"]` (`pricing/index.html:674`) | block identity class `workspace-pricing`, keeps `[data-js="calc-office-costs"]` |
| Team stepper `[data-calc-team-*]` (0–15) | `.calc__slider-row` / `.calc__stepper-btn` / `.calc__slider-value` |
| Commitment radios `[data-calc-commitment]` (1/3/5) | `.calc__radio-group` `<button role="radio">` (§7 pattern) |
| Annual discount `[data-calc-annual]` checkbox | `.annual-check` label (hidden unless a Dedicated member is on the roster — `[data-calc-annual-wrap]`) |
| Member roster `<select>` `[data-calc-member]` (dedicated/flexi-5..1) | per-calc `.calc__roster-row`/`.calc__roster-select`; rebuilt on team change; stacks label-above-select ≤640px |
| Result sinks `[data-calc-private-*|ours-*|mini-total|dedicated-save|bridge-*]` | `.calc__result` layout; note: `saving-*`/`period` writes are dead (no markup) |
| Comparison chart (pricing coordinator `:1305-1577`) | per-calc `workspace-pricing__chart` (comparison table stays per-calc, not `.calc__*`) |

**Stays in code** (cited methodology/benchmarks, per §2): private-office model `M` (`rent 420/sqm, sqm/person 10, opex 0.27, furniture 2000, internet 2400, power 50W/sqm × 8h × 230d × $0.30, cleaning 45/hr × 1.2, consumables 300, insurance 200, mhfr 445/1:12/2.5yr, admin 0.06×$70, legal 3500, booking 75/mo ≥10 team`), comparison benches (`PRIVATE_OFFICE_PER_PERSON_YR 14200`, flexi `450–650` / dedicated `700–830` monthly, giving `8h×46wk×$1`), `SOURCES` citations, `desks` URL encoding.

**Reads from ACF SSOT** (already injected → `window.twofiftyseven.prices`): `dedicated | flexi-5..1` monthly prices (6 fields), `annualDiscountPct` (Dedicated only; JS falls back to 0.10 if missing/zero). Day passes are SSOT fields but **not** used by this calc — ignore.

**URL sync:** `readURL()/writeURL()` keeps `team, commitment, annual, desks` (one char per roster member: `d`/`1..5`/`x`) in the query string → copy-link reproduces the exact roster.

**Zero-start:** team 0 renders the empty state ("Select your team size to see your number") with hidden headline + chart; engine keeps 0, but the email submit clamps team to ≥1 server-side (a `$0` email is meaningless).

---

**Built (committed `b084d49`) — deviations from the original build order:**

1. **`inc/calc-share-email.php`** — all 3 additions done. Note: the `case 'workspace-pricing'` in `two57_calc_share_email_handle()`'s recompute `switch ($calc)` was initially missed and caused "Unsupported calculator." on email submit — fixed (the sanitize + compose switches had the case, the figures dispatch didn't). The email summary covers team size, membership count, totals + savings vs private office, and the per-member roster; the commitment term is **not** in the email copy (only used for the amortisation math).
2. **`acf-json/group_two57_block_workspace_pricing.json`** — one `colour_space` select (`neutral|forest|purple|maroon`, default `forest`) + `wp_eyebrow`/`wp_heading`/`wp_tagline`.
3. **`blocks/workspace-pricing/block.php`** — ported with `$allowed` whitelist + `$is_preview` fallback + `data-color-space` + §6 share markup. Breakdown shows: private-office line items + monthly/Annual totals, memberships (Dedicated-prepay line, monthly, annual total), Kaupapa bridge. Breakdown heading is "Estimated private office costs {current year}" (`gmdate('Y')`).
4. **`assets/js/modules/workspace-pricing.js`** — ported v1 engine, `initWorkspacePricing()` queried on `[data-js="calc-office-costs"]`; commitment radios, annual checkbox (hidden unless Dedicated on roster), `read/writeURL`, `initCalcShare(root, { slug: 'workspace-pricing', getState })`.
5. **`assets/css/06-components/_calc-workspace-pricing.scss`** — roster rows, annual toggle, source-tooltip rows, breakdown totals, comparison chart bars; forwarded in `_index.scss`.
6. **`functions.php`** — `acf_register_block_type` `workspace-pricing` (title `257 Calc Workspace Pricing`).
7. **`assets/js/main.js`** + **`transitions.js`** — import + `initWorkspacePricing()` (three call sites, mirroring `initHoursToImpact`).

**Post-commit refinements (after `b084d49`):** email figures/compose gained per-member `Member N:` roster lines (member index tracked in `two57_calc_figures_workspace_pricing()`); breakdown totals regrouped so Monthly + Annual sit flush in one `compare__list` (both columns), with the Annual row using the `compare__row--total` bold treatment and the Dedicated-prepay line moved above the totals. Later (`699d816`): commitment radios reordered below Memberships + relabelled "Private office lease term" (longer tooltip explaining it's a comparison assumption, not a commitment), Dedicated-prepay row now hides unless annual **and** ≥1 Dedicated member, team control replaced with the shared slider system, and new members default to Dedicated tier (see "Slider system" + "C1/C6 default membership tier" below).

**Verification:** `npm run build`; send a test via the block's email form → `TWO57_CALC_EMAIL_LOG` or Mailhog (rate limit cleared per §"Local mail testing"), lead `calc_source = workspace-pricing`; confirm copy-link round-trips `team/commitment/annual/desks`; no `/calculator/...` WP page exists locally → test on Cloudflare Pages demo or a throwaway page.

#### Future improvement (scoped, not built — 2026-08-11)

The private-office methodology numbers (`M` in `assets/js/modules/workspace-pricing.js:28`) and the source citations (`SOURCES`, ~13 × label/name/url) are **hardcoded in code** (both in the JS engine and mirrored in `two57_calc_figures_workspace_pricing()` in `inc/calc-share-email.php`). They stay hardcoded for now; the requested change is to move them into the ACF Options "Calculator data" page so they can be edited without a code deploy.

Feasible via the existing SSOT pipeline (ACF Options → `wp_head` injector in `functions.php` → `window.twofiftyseven`). Scoped shape:

- ACF: new "Workspace pricing · methodology" tab on `calculator-data-settings` — ~29 number fields (rent/sqm 420, opex 0.27, MHFR 445/1:12/2.5yr, benchmarks $14,200 / flexi 450–650 / dedicated 700–830, etc.) + a **repeater** for sources (sub-fields label/name/url)
- `functions.php`: emit `window.twofiftyseven.methodology` + `sources`
- JS: read from the global, drop `M`/`SOURCES`
- `inc/calc-share-email.php`: switch hardcoded numbers to `get_field()`

Complexity = **moderate** (proven wiring, but): (1) methodology is duplicated across JS + PHP recompute, so edits hit both and must stay in sync; (2) decimals (0.27, 1.2, 2.5) need careful casting; (3) empty-field fallbacks needed — a cleared number would silently render a $0 estimate line on a public page. Also note `M.GIVING_RATE = 1` in JS is hardcoded while `giving_rate_per_person_hour` already exists in ACF — inconsistent, worth auditing if this is picked up.

### Slider system (shared `.calc__slider*` primitives — committed `699d816`)

Replaces the old stepper-only team control on **C1 (workspace pricing)** and **C6 (hours-to-impact)**. Built once in `_calc-base.scss`, no per-calc SCSS.

**Markup** (both blocks use identical structure; only the range `max` differs — 15 for C1, 30 for C6):

```html
<div class="calc__slider-row">
  <div class="calc__slider-controls">
    <button type="button" class="calc__stepper-btn" data-calc-team-dec aria-label="Decrease team size">&minus;</button>
    <div class="calc__slider" data-calc-team-slider>
      <input type="range" class="calc__slider-input" data-calc-team-range min="0" max="15" step="1" value="0" aria-label="Team size">
    </div>
    <button type="button" class="calc__stepper-btn" data-calc-team-inc aria-label="Increase team size">&plus;</button>
  </div>
  <output class="calc__slider-value" data-calc-team-out aria-live="polite">0</output>
</div>
```

**Behaviour:**
- Slider + `−`/`+` steppers both drive the same `updateTeam()`; the readout (`<output>`) and range stay in sync
- Track fill is painted via a `--pct` custom property (JS sets `calc__slider`'s `--pct` = value/max × 100) — CSS `linear-gradient` background reads it; the round thumb is a styled native range thumb (webkit + moz), 2px inverse border, grab cursor, focus ring via `:focus-visible`
- **Mobile (< `bp-md` 768px):** the range input is `display: none` — stepper buttons + number only (slider is unusable at narrow widths). `.calc__slider-controls` collapses to `auto auto`
- Readout: display font, `--text-5xl-size`, `tabular-nums`, `white-space: nowrap`, `min-inline-size: 2ch` reserved so 1→2 digit doesn't shift the layout, right-aligned, number only (no "members" suffix)
- Min stays **0** to preserve the empty state on both calcs; max clamps to the calc's `M.MAX_TEAM`/`M.TEAM_MAX`
- Init sets range value, `--pct`, readout, and button disabled states from the URL-hydrated state (same as the stepper did)

**QA learned:** the `output` `aria-live="polite"` readout keeps the range accessible; buttons get `aria-label`s. Both calcs verified working locally before commit.

### Tooltip overflow fix (committed `699d816`)

The `calc-source` tooltips (`<span class="calc-source__pop">`) were the cause of the mobile horizontal-overflow bug — on narrow viewports the popup (positioned `left: 0` of the trigger, `inline-size: max-content`) ran past the right edge of the screen.

**Fix (in `_calc-base.scss`, applies to every calc that uses `.calc-source`):**
- `max-inline-size: min(280px, calc(100vw - 2 * var(--space-m)))` — the popup can never exceed the viewport minus page gutters
- Below `bp-md`: `left: auto; right: 0` — anchored to the trigger's **right** edge so it expands **leftward** instead of off-screen

Verified: deleting the offending DOM node removed the overflow (root-caused by bisection), then the CSS fix resolved it without markup changes.

### C1/C6 default membership tier (committed `699d816`)

New memberships now default to **Dedicated** (was Flexi 1 day/week): `M.DEFAULT_TIER = 'dedicated'` in `assets/js/modules/workspace-pricing.js`, used by all three member-creation sites (`readURL()` roster build, `readURL()` desks fallback, `updateTeam()` grow loop). Consequence: a cold visitor gets 1 Dedicated member at $659 (annual discount applies if the annual box is ticked). No plan/backend change — the server recompute clamps independently.

### C2 — Meet pricing implementation plan

> **Source (2026-08-11):** `meetings/pricing/index.html` (2304 lines) is a hand-built "quote tool" (`.quote-*` classes, root `.quote-calc`) — **not** the shared design-system family that C3/C4/C5 use. It shares only the §6 share row + FAQ with sitewide patterns. Ports to a `meet-pricing` block with a `room_set` ACF select for the Host variant (§"Host variant (C2)").

**Shared-SCSS refactor — ✅ DONE (commit after this doc sync).** Audit of C1's `_calc-workspace-pricing.scss` (381 lines) + the 4 future sources found most of C1's "per-calc" styles are shared-worthy. The live-code promotions shipped first; the remaining rows are new primitives built when their first consumer lands (C2):

| Primitive | From | Used by | Status |
|---|---|---|---|
| `.calc__select` (custom chevron dropdown) | C1 `.calc__roster-select` — renamed, moved to `_calc-base.scss` | C2 addon select, C3 `.oc-select`, C1 roster | ✅ done |
| `.calc__check` (custom checkbox/radio swatch primitive) | C1 `.annual-check`/`.checkbox__swatch` — renamed (`.calc__check-box`/`-label`), moved to base | C2 `.addon__check`/`.impact-toggle__check`, C3 booking checkbox, C4 `.checkbox`/`.radio`, C1 annual toggle | ✅ done |
| `.calc__compare` (label/value row list + `--total`) | C1 `.compare__list`/`__row`/`__row--total` — renamed `-row`/`-row-label`/`-row-value`, moved to base | C2 `.quote-items`, C3 `.value-add`/`.offset-math`, C5 `.esg-export__row`, C1 breakdown | ✅ done |
| `.calc-source` tooltip glyph + pop | C1 — moved to base as-is | C3 `.oc-tip` (structured panel variant), C1 breakdown | ✅ done |
| `.calc__result-empty` (centred empty state in the dark card) | C1 override — moved to base with stretch centring | C2 `.quote-item--prompt`, C4/C5 result asides | ✅ done |
| `.calc__body` stretch + result fill (stretched card) | C1 override — promoted to base default (`.calc__body` is now `align-items: stretch`, `.calc__result` flex column, `.calc__result-empty` fills) | all calcs (C1 proved the right default) | ✅ done |
| `.calc__roster` / `-row` / `-label` | C1 roster list — moved to base | C1 roster, C2/C3 member rows | ✅ done |
| `.calc__slider` (range + value display, reconcile 2 impls) | new | C2 `.people-slider`, C3 `.ux-slider` | ✅ done — built on C1, ported to C6 (see "Slider system" below) |
| `.calc__repeat` + `.calc__add-btn` (add/remove row) | new | C2 `.day-row`, C3 `.oc-custom-rows`, C4 `.custom-rows` | ✅ done with C2 |
| `.calc__day-row` (repeating date/time shell) | new | C2 native date/time, C4 AM/PM inset widget | ✅ done with C2 |
| `.calc__result--sticky` (pinned quote aside) | new — opt-in per calc; `align-self: start` + `position: sticky` below the fixed header, dropped ≤`bp-lg` when the grid stacks | C1 workspace aside, C2 meet aside | ✅ done with C2 (see "Sticky result column" note) |
| `.calc__add-on card` (bordered option card, top-right swatch, `:has(input:checked)` glow) | new — C2 `.meet-pricing__addon`/`__impact`, C1 `.workspace-pricing__annual-card` share the same recipe | C2 addons + impact toggle, C1 annual card | ✅ done — `.calc__option-card`/`.calc__option-head` promoted to `_calc-base.scss`; C2 addons + impact card + C1 annual card use them (per-calc sheets keep only their unique rules) |
| `.calc__contact` (inline email/contact form) | new | C2 full `.qf` form, C3/4/5 `.calc-contact-inline` | ⏳ parked — not built with C2; C2 shipped the §6 `.calc__share-*` row instead |

**Stay per-calc (genuinely unique):** C2 room-tile selector states (`recommended`/`disabled`), C2 `pricing-tiers` multi-rate table, C3 scenarios/compare dialog, chart bars, feature/inclusion card grids.

**C2 build order (after the refactor commit):** ✅ **all built + committed** (`1d36abb`) on `feature/calculators-continued` (see "C2 — Meet pricing implementation — built" below).

1. **`inc/calc-share-email.php`** — `two57_calc_sanitize_state()` `'meet-pricing'` case (people, room, duration, days `[{date,start,end}]`, addons, catering per-head, impact discount), `two57_calc_figures_meet_pricing()` (rooms + addons + impact discount + giving from ACF SSOT; recompute authoritative), `two57_calc_compose_meet_pricing()` (itemised quote, room, dates, impact-funding line)
2. **`acf-json/group_two57_block_meet_pricing.json`** — `colour_space` select + `room_set` select (`all` default / `host` = Workshop/Event/Entire only)
3. **`blocks/meet-pricing/block.php`** — `.meet-pricing` identity class on root; §6 share row inside the `data-js` root; people slider, room-tile grid, duration pills, day rows, addon reveal cards, sticky quote panel (total + itemised + impact-funding + impact-discount toggle)
4. **`assets/js/modules/meet-pricing.js`** — engine reading `window.twofiftyseven.rooms`/`addons`/`impact` (all already injected), `initCalcShare(root, { slug: 'meet-pricing', getState })`, URL sync (`people/room/duration/days/addons/impact`)
5. **`assets/css/06-components/_calc-meet-pricing.scss`** — per-calc only: quote layout (`1.5fr 1fr`), room tiles, quote panel, impact card, pricing tiers; forward in `_index.scss`
6. **`functions.php`** — `acf_register_block_type` `meet-pricing` (title `257 Calc Meet Pricing`)
7. **`assets/js/main.js`** + **`transitions.js`** — import + `initMeetPricing()` (three call sites; also see "Sticky result column" note re share placement)

**SSOT:** no new ACF data fields — rooms (6 × cap/day/hour/evening), addons (AV/tea/catering/materials/setup), impact (discount 50%, eligibility $200k, paid-forward $450k) all already exist in `group_two57_calculator_data.json` and inject into `window.twofiftyseven`.

**Verification:** `npm run build`; test quote end-to-end (room swap, day add/remove, addon reveal, impact toggle); email send → `TWO57_CALC_EMAIL_LOG`/Mailhog with `calc_source = meet-pricing`; copy-link round-trips `people/room/duration/days/addons/impact`; Host variant filters tiles via `room_set`.

### C2 — Meet pricing implementation — built (committed `1d36abb` on `feature/calculators-continued`)

All 7 build-order items above are implemented and staged. Additional notes from the build + review:

- **Share row placement deviates from §6.1.** The plan says the share row lives *inside* the calc's `data-js` root. C2 (and C1's later restructure) actually place it **outside** the root, sibling in `.wrapper`, because the sticky result aside must stay inside the body grid while the share row releases below it. Consequences:
  - The shared `initCalcShare(root, …)` contract ("the share node is inside root") no longer holds for calcs with a sticky aside. C2 calls `initCalcShare(root.parentElement, …)`; C1 was silently broken by its restructure until the review fix (`workspace-pricing.js:678` now passes `root.parentElement`). C6 still nests the share inside its root.
  - **Rule going forward:** calcs whose result aside is sticky (`calc__result--sticky`) → `initCalcShare(root.parentElement, …)`; calcs with the share inside the root → `initCalcShare(root, …)`. This is the $6.1 gotcha that bit twice already.
- **Sticky result column** — `.calc__result--sticky` (base) pins the quote aside below the fixed header on ≥`bp-lg`; base `_calc-base.scss` `--site-header-height` fallback is 4rem. Dropped ≤`bp-lg` (no travel room in the single-column stack). C1 + C2 opt in.
- **Impact Discount is a card now, not a toggle.** The old `impact-toggle` in the aside was replaced by a Step 6 `.meet-pricing__addon` card (`data-calc-addon="impact"`), reusing the addon checkbox loop — no separate `[data-calc-impact-checkbox]` binding.
- **Annual prepay in C1 became a card** (`.workspace-pricing__annual-card`) using the same addon-card recipe as C2 — now both share `.calc__option-card` (see that row in the shared-primitives table).
- **Copy/rename updates (staged, ask-then-committed):** "Entire two/fiftyseven" → **"Entire Space"** (`functions.php` injector, `block.php` room set, email composer); "Private Wellington office" → **"Private office"**; "Dedicated 7 days/week" → **"Dedicated Desk"** (labels in `functions.php` + email composer; prices stay ACF-driven). Consent copy now spans the policy link inline: *"By submitting, I agree to two/fiftyseven contacting me to follow up about these numbers — see the Contact Policy"* (all three blocks, wrapped in `.calc__share-consent-text`).
- **Meet people slider cap:** scale runs 1–60 then 70–200 by 10 (`PEOPLE_SCALE`, slider max = index 73). Base `.calc__slider-value` keeps **2ch** (the C1 15 / C6 30 readouts); meet overrides to 3ch via `.meet-pricing .calc__slider-value { min-inline-size: 3ch }` (base's 2ch would shift at 99→100).
- **Swup re-init:** `initMeetPricing()` added to `main.js` + both `transitions.js` call sites (page:view + bfcache pageshow). The bfcache branch also gained `initWorkspacePricing()` (it was missing there pre-restructure — a latent gap surfaced in review).
- **DRY refactor (2026-08-17, staged on `feature/calculators-continued`)** — all but one of the parked candidates shipped:
  - `.calc__option-card` / `.calc__option-head` promoted to `_calc-base.scss`; C2 addons + impact toggle + C1 annual card converted (markup in both `block.php`s; per-calc sheets keep only their unique rules — annual top margin, addon text-input caret).
  - Shared `assets/js/modules/calc-utils.js` — `fmt$` / `fmtN` (single Intl formatter; C2's `'$'+toLocaleString` variant retired for parity) + `bindRovingRadio(radios, onSelect)` (same capture-phase arrows + Enter/Space semantics each engine had). All three engines import it; the four duplicated roving blocks and `fmt$` copies are gone. `fmtHrs` intentionally stays per-engine (C6 `N hrs` vs C2 half-hour rounding, no space).
  - Single `two57_meet_rooms()` (functions.php) — slug → `name` + ACF `key`; consumed by `block.php` `$rooms_all`, the wp_head rooms injector, and `inc/calc-share-email.php` (figures + `$rooms_allowed`). Injector labels normalised to the block.php capitals ("Meeting Room" etc.).
  - **Still parked:** stepper/slider `paint/update` binding — each engine's slider has genuinely different scale/readout contracts; hold until a real second consumer forces a shared one.

### Code-review notes (2026-08-10)

- Fixed invalid token `--layout-content-wide` → `--layout-wide-size` (token doesn't exist in theme).
- Re-indented `block.php` (fields-grid wrapper + `<details>` had broken leading whitespace). Tags verified balanced.

### Code-review notes (2026-08-17)

- Fixed **C1 workspace-pricing share regression**: the sticky restructure moved `[data-calc-share]` out of the `[data-js="calc-office-costs"]` root, so `initCalcShare(root, …)` silently no-oped; now `root.parentElement` (matches C2).
- Restored the `initInjectPrices()` indentation accident in `transitions.js`; added `initWorkspacePricing()` to the bfcache pageshow branch.
- Both fixes folded into the C2 commit (`1d36abb`).
- **DRY refactor review (2026-08-17, staged on `feature/calculators-continued`):** option-card/rooms/JS-helper dedupe re-verified — `bindRovingRadio` preserves each engine's exact keydown semantics (capture phase, guard-on-disabled via the engine's `onSelect`), `fmt$` output matches the retired meet `'$'+toLocaleString` (en-NZ identical formatting), `.calc__option-card` carries the `position: relative` the swatch anchored to, and `two57_meet_rooms()` returns the same slugs/keys in the same order. Build + `php -l` clean; only `assets/dist/.vite/manifest.json` + hashed CSS/JS change in dist.
- Docs (`docs/`) not staged — reference-only, as before.

## 12. Refactor phase — shared styling + engine dedupe review

Reviewed 2026-08-17 across all four shipped calcs (C1 workspace-pricing, C2 meet-pricing, C5 office-carbon, C6 hours-to-impact). Scope: shared `.calc__*` styling that can be DRY'd and engine/template duplication worth a shared helper. Ordered by value/risk; each item notes what changes and where.

### CSS — `_calc-base.scss` + per-calc sheets

1. **Delete dead `.calc__stepper` block** (`_calc-base.scss:239-279`). Vestigial from an older design — all four block templates use `.calc__stepper-btn` + `.calc__slider-value`; nothing references `.calc__stepper` or its nested `button`/`output` selectors. ~40 lines of duplicate button/output styles. **Follow-up:** sync the two stale catalogue rows (this doc lines `§11` `.calc__stepper` row + the C1 plan stepper row).

2. **Extract a `@mixin calc-input()`** — the bordered-input recipe is repeated 4×:
   - `.calc__input` (`_calc-base.scss:485-502`)
   - `.calc__day-row-inputs input` (`_calc-base.scss:618-637`) — narrower padding + monospace
   - `.calc__share-input` (`_calc-base.scss:1073-1091`) — `flex` sizing on top
   - `.meet-pricing__addon-extra input[type="number"]` (`_calc-meet-pricing.scss:191-208`)
   Common: `border-tertiary`/`radius-xs`/`content-inverse` bg/`content-primary` color/`tabular-nums` + `:focus` outline. **As built:** `@mixin calc-input( $focus-offset: 2px )` — padding/font stay in the per-rule declarations, since the four inputs diverge there (the day-row fields swap to monospace + `1px` ring, the share input adds `flex` sizing + `field-sizing`).

3. **Extract a `@mixin calc-icon-btn($radius, $fill)`** — small square/round icon-button recipe repeated 3× (default fill = stepper look; `$fill: false` = transparent + hover-invert):
   - `.calc__stepper-btn` (`_calc-base.scss:298-328`) — `radius-xs`, filled bg + hover tint
   - `.calc__day-row-remove` (`_calc-base.scss:649-679`) — `50%`, transparent → filled on hover
   - `.calc-source__trigger` (`_calc-base.scss:935-957`) — `50%`, `18px` fixed
   Common: flex-center, `--space-l` sizing, `border-tertiary`, colour transitions, `:hover`/`:focus-visible` treatment.

4. **Consolidate accent-column naming** — three names, one concept:
   - `workspace-pricing__result-col--accent` (`_calc-workspace-pricing.scss:158`) — empty placeholder
   - `hours-to-impact__result-col--accent` (`_calc-hours-to-impact.scss:14`) — empty placeholder
   - `calc__result-col--save` (`office-carbon` block.php:130) — **used in markup, never defined in CSS**
   Collapse to a single shared `.calc__result-col--accent` in `_calc-base.scss`; update the three templates. (Currently inert — all three just piggyback `.calc__result-col` — but the missing definition for `.calc__result-col--save` is a real gap if anyone later styles it.)

5. **Tiny cleanup: `.calc__slider-value`** (`_calc-base.scss:423-436`) — declares `text-align: right` then immediately `text-align: center` (last wins). Drop the dead `right` declaration.

**Not refactoring:** the per-block `padding-block: var(--space-xl-3xl)` shell rule is the theme-wide `.block` convention (also in `_faq`, `_testimonial`, `_cta-section`, etc.) — already consistent, keep as-is.

### PHP — block templates (`blocks/*/block.php`)

6. **DRY the `.calc__share` section (~40 lines × 4).** The email form, honeypot, consent, status output and copy-link card are byte-identical across all four calcs; only the h2 (`save your quote, send it on` vs `save your number, share it, send it on`) and the two card body copies differ. Extract a single renderer — `two57_calc_share()` helper (in `functions.php`, echoing the shared section) or a `get_template_part('partials/calc-share')` — with per-calc copy passed as args.

7. **DRY the intro block (~20 lines × 4).** The `calc__intro` eyebrow/heading/tagline block (including the `$is_preview` fallback) is verbatim in all four templates. Same partial/helper treatment.

### JS — shared helpers into `assets/js/modules/calc-utils.js`

8. **`bindStepper()` — team/people stepper wiring.** `paintSlider()` (sets `--pct` + outgoing value + dec/inc disabled) and `updateTeam()` (clamp to max, sync range, disable buttons, repaint) are near-identical in C1, C5, C6, plus C2's people variant (which adds the scale-index contract). Parked in the 2026-08-17 refactor review ("no real second consumer forces a shared one") — C5 is now that fifth consumer; revisit. Signature sketch: `bindStepper(root, { rangeSel, sliderSel, outSel, decSel, incSel, max, valueFor, current, onUpdate })` — selectors passed as strings so each engine keeps its own `[data-calc-team-*]` / `[data-calc-people-*]` naming; `valueFor` (index → shown value) + `current` (→ index) express meet's stepped-scale contract.

9. **Dedupe small handlers:**
   - **Stepper-only number input** — the `stepperOnly` capture-phase keydown guard on `[data-calc-weeks], [data-calc-hours]` is duplicated in C5 + C6 → helper.
   - **Breakdown trigger → `<details>` proxy** (toggle `open`, sync `aria-expanded`, smooth-scroll into view) is duplicated ×4 → helper taking the details id.
   - **`bindSourceTooltips`** (`.calc-source__trigger` open/close + click-away) duplicated in C1 + C5 → helper.

### Execution order

Recommended sequence: **CSS 1, 4, 5** (pure deletions/naming, zero visual change) → **CSS 2, 3** (mixins) → **PHP 6, 7** (highest-value dedupe) → **JS 8, 9** (larger, independent — can land as a follow-up pass). Verify with `npm run build` + `php -l` after each batch; dist assets change only in the rebuilt manifest/hashes.

### Status

All items implemented (2026-08-17), in the recommended order:

- **CSS 1 (`.calc__stepper` deleted)** — dead block removed from `_calc-base.scss`; catalogue rows here (§11 + C1 plan) synced to `.calc__stepper-btn` / `.calc__slider-row`.
- **CSS 4 (accent column consolidated)** — single shared `.calc__result-col--accent` in base; the three per-calc names (`workspace-pricing__result-col--accent`, `hours-to-impact__result-col--accent`, and the never-defined `calc__result-col--save`) retired, three templates updated.
- **CSS 5** — dead `text-align: right` removed from `.calc__slider-value`; stale "both calcs cap at 15/30" comment corrected.
- **CSS 2 (`@mixin calc-input`)** — extracted in base; applies to `.calc__input`, `.calc__day-row-inputs input` (1px ring), `.calc__share-input`, `.meet-pricing__addon-extra input[type="number"]` (via `@use 'calc-base'`).
- **CSS 3 (`@mixin calc-icon-btn($radius, $fill)`)** — extracted in base; applies to `.calc__stepper-btn` (filled), `.calc__day-row-remove` (transparent, 50%), `.calc-source__trigger` (transparent, 50%, keeps its fill-on-focus override). Note: the trigger previously had **no** focus ring (fill-on-hover only) — the mixin's `:focus-visible` outline (2px, `border-primary`) is a deliberate a11y addition, kept alongside the trigger's fill-on-focus override.
- **PHP 6 (`two57_calc_share()` in functions.php)** — share row rendered once; per-calc copy passed as an array (meet overrides; workspace/hours/carbon use the defaults). All four `block.php`s call it.
- **PHP 7 (`two57_calc_intro()` in functions.php)** — eyebrow/heading/tagline intro (+ `$is_preview` fallback) rendered once; all four templates call it.
- **JS 8 (`bindStepper()` in calc-utils.js)** — wired in all four engines (team steppers value-based; meet people stepper index-based over `PEOPLE_SCALE`); engines keep only their state mutation in `onUpdate` (workspace roster sync). Replaces the "still parked" stepper note above — the four consumers now justify the shared helper.
- **JS 9** — `restrictStepperInputs()` (C5 + C6), `bindBreakdownTrigger()` (C1, C5, C6), `bindSourceTooltips()` (C1 + C5) extracted into calc-utils.js; local copies removed.

Verified: `npm run build` clean (only pre-existing swiper `@import` deprecation warnings, unchanged rules in dist — dead `.calc__stepper` gone, mixin-expanded `.calc__stepper-btn` / `.calc-source__trigger` present, all stepper/breakdown/tooltip selectors in the JS bundle) + `php -l` clean on `functions.php` and all four `block.php`s.

### T1 — Calc Widget implementation (2026-08-18)

Port of the standalone `meetings/index.html` `.quick-quote` teaser (`shared-js/quote-preview.js`) into a lightweight ACF block (`calc-widget`) using the shared `.calc__*` catalogue. No email/share, no URL round-trip, no breakdown — it's a teaser that deep-links to the full C2 meet-pricing calculator with state carried via URL params (`?room&dur=hour&hours&people&impact`).

- **Engine** — `assets/js/modules/calc-widget.js` exports `initCalcWidget()` on `[data-js="calc-widget"]`. People stepper via `bindStepper` (same non-linear `PEOPLE_SCALE` as meet-pricing: 1–60 by 1, then 70–200 by 10); hours stepper via `bindStepper` (1–12, value-based); room radio pills with `bindRovingRadio` (arrow-key nav); auto-recommendation always picks the smallest room that fits the current people count (re-recommends on every people-slider change, so the total tracks in both directions); impact discount toggle (whole-card click target, matching office-costs booking pattern); impact copy swap (giving vs receiving when discount is applied); CTA `href` updated on every render with `URLSearchParams` carrying `room/dur/hours/people/impact` to the full calc.
- **Markup** — `blocks/calc-widget/block.php` (identity `calc-widget`, `data-color-space`): intro (`two57_calc_intro`), `.calc__body` (1.5fr/1fr grid), inputs card (people slider row, hours slider row, room radio grid with capacity from SSOT, impact discount `.calc__option-card`), sticky `.calc__result--sticky` aside (estimated total + impact statement with label matching `.calc__result-label` styling + CTA button using `.calc__breakdown-trigger` with chevron rotated right). Room slugs + names from `two57_meet_rooms()`; capacities from ACF `room_<key>_capacity`; rates read by JS from `window.twofiftyseven.rooms` (no DOM duplication).
- **ACF** — `acf-json/group_two57_block_calc_widget.json` (`cw_eyebrow`/`cw_heading`/`cw_tagline` + `room_set` select [all/host] + `pricing_url` page_link [admin picks the full calc page] + `colour_space` select); location `acf/calc-widget`; registered in `functions.php` after `office-costs`. Default intro: eyebrow "Estimate", heading "get a number for your meeting", tagline "Pick a room, your duration and how many people…".
- **SCSS** — `assets/css/06-components/_calc-widget.scss` (shell padding, 1.5fr/1fr body grid, slider readout 2 steps down `--text-3xl-size` + 3ch, room pill grid 2-col→3-col, room tiles matching meet-pricing style, impact statement, CTA caret rotated -45deg to point right, `text-decoration: none` on the `<a>` trigger); forwarded in `_index.scss`.
- **Wiring** — `main.js` + `transitions.js` (one import + two call sites: bfcache + page:view).
- **Refinements:** impact label uses `.calc__result-label | text-l` (same as "Estimated total"); suffix + context merged into single line ("of subsidised space which has contributed $450,000+ paid forward since 2021") with "of subsidised space" in `<strong>`; talk-link block removed; `pricing_url` ACF field is `page_link` (page picker) not free-text `url`.

**Verify (residual):** in-browser pass — people slider auto-room-switch both directions; hours slider total update; room pill manual selection; impact discount toggle (whole card) + copy swap; CTA deep-link params; host room set renders only large rooms; no-JS fallback (static `$0` total + CTA href to pricing page).
