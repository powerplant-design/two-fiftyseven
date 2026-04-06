# Two Fifty Seven

Custom WordPress theme by [Powerplant Design](https://powerplant.design).

## Stack

- **WordPress** — local development via [DevKinsta](https://kinsta.com/devkinsta/)
- **Vite 6** — JS/CSS bundling with HMR
- **SCSS** — compiled via Vite's built-in Sass support
- **Tailwind CSS 4** — utility-first CSS, CSS-first config via `assets/css/tailwind.css`
- **`@tailwindcss/postcss`** — PostCSS integration, configured inline in `vite.config.js`
- **CSSNano** — minification in production builds
- **ES modules** — no jQuery

---

## Requirements

- Node.js 18+
- PHP 8.0+
- WordPress 6.4+
- DevKinsta (for local development)

---

## Local Development Setup

### 1. DevKinsta

Install [DevKinsta](https://kinsta.com/devkinsta/) and create a new WordPress site. The site will be available at `https://two-fiftyseven.local`.

### 2. Clone the theme

```bash
cd ~/DevKinsta/public/two-fiftyseven/wp-content/themes/
git clone https://github.com/powerplant-design/two-fiftyseven.git
```

### 3. Install dependencies

```bash
cd two-fiftyseven
npm install
```

### 4. Configure WordPress environment

Add the following to `wp-config.php` (above the "stop editing" line):

```php
define( 'WP_ENVIRONMENT_TYPE', 'local' );
```

This tells the theme to load assets from the Vite dev server instead of a built manifest.

### 5. Activate the theme

Go to `https://two-fiftyseven.local/wp-admin/themes.php` and activate **Two Fifty Seven**.

### 6. Start the dev server

```bash
npm run dev
```

Vite runs at `http://localhost:5173`. Do not open that URL directly — it only serves assets. Open `https://two-fiftyseven.local` in your browser and changes to SCSS or JS will hot-reload automatically.

---

## How Asset Loading Works

`functions.php` checks `WP_ENVIRONMENT_TYPE`:

- **`local`** — loads JS and CSS directly from the Vite dev server (`localhost:5173`), enabling HMR
- **anything else** — reads `assets/dist/.vite/manifest.json` and enqueues the built and hashed files

To test the production build locally without changing `WP_ENVIRONMENT_TYPE`, add this to `wp-config.php`:

```php
define( 'VITE_HMR', false );
```

Remove it when you're done testing.

---

## How CSS Compiles

`main.js` imports both CSS entry points, which Vite processes through PostCSS on every build:

1. `assets/css/tailwind.css` — `@import "tailwindcss"` pulls in Tailwind's base, components, and utilities. `@tailwindcss/postcss` scans all `.php` and `.js` files and generates only the utility classes that are actually used.
2. `assets/css/styles.scss` — custom SCSS compiled via Vite's built-in Sass support. Tailwind utilities and CSS custom properties are available here.

Both are merged into a single hashed CSS file in `assets/dist/assets/`, e.g. `main-Dh9v1Y0m.css`.

**Tailwind configuration** is CSS-first in Tailwind v4 — no `tailwind.config.js`. Theme tokens, custom utilities, and plugins are added directly in `assets/css/tailwind.css` using `@theme`, `@utility`, and `@plugin` directives.

**PostCSS** is configured inline in `vite.config.js` via the `css.postcss` option. There is no separate `postcss.config.js` — if one exists, Vite will use it exclusively and ignore the inline config, which would break the build.

---

## Production Build

```bash
npm run build
```

Every build is a clean recompile — `emptyOutDir: true` wipes `assets/dist/` before writing new output. Tailwind re-scans all templates, outputs only the classes in use, and CSSNano minifies the result.

The manifest at `assets/dist/.vite/manifest.json` is committed to version control so WordPress can resolve hashed asset paths on production without Node being available on the server. Everything else in `assets/dist/` is gitignored.

> Make sure to run `npm run build` before deploying — if the manifest is stale, WordPress will enqueue old hashed filenames that no longer exist.

---

## Kinsta Launch Runbook (DevKinsta -> Kinsta)

This is the recommended pre-launch order for this project:

1. Update WordPress core to latest (first)
2. Upgrade PHP to 8.3 (second)
3. Validate on Kinsta staging
4. Promote to production

### Why this order

- Core update first keeps WordPress, plugin, and admin compatibility in sync before changing runtime.
- PHP update second (to 8.3) gets you onto a security-supported version with strong plugin compatibility.

### Phase 1: Local (DevKinsta)

1. Ensure theme changes are committed.
2. Run a production asset build:

```bash
npm run build
```

3. Verify `assets/dist/.vite/manifest.json` exists and includes `assets/js/main.js`.
4. In WP Admin, update WordPress core to latest and test:
  - Front-end pages and navigation
  - ACF block editing/saving
  - Forms (WPForms)
  - Theme JS behavior (transitions, smooth scroll, reveal animations)

### Phase 2: Kinsta Staging

1. Create a staging environment in MyKinsta.
2. Deploy files/database from local to staging.
3. Confirm production asset mode is active (no Vite dev server dependency).
4. Update WordPress core to latest on staging (if needed).
5. Change PHP version on staging to **8.3**.
6. Run smoke tests:
  - Homepage and key templates
  - Custom blocks: Hero, CTA, Testimonial, Stacked Cards, FAQ
  - Form submission
  - Site Health screen
  - Error logs (no fatal errors)

### Phase 3: Production

1. Schedule a low-traffic launch window.
2. Create a fresh production backup in MyKinsta.
3. Deploy the already-tested staging state.
4. Ensure production is on:
  - Latest WordPress core
  - PHP 8.3
5. Re-test critical journeys and monitor logs for 24-48 hours.

### Rollback

If a critical issue appears after launch:

1. Restore the pre-launch backup in MyKinsta.
2. Revert the last deployment commit.
3. Re-open staging, fix, and re-run the checklist.

---

## Ongoing Deployment Workflow

### Pushing staging → live

In MyKinsta → Two-Fiftyseven → **Staging** tab → **"Push to Live"**.

Choose what to push:
- **Files + database** — for the initial launch only
- **Files only** — for all subsequent deploys once the client is adding content (avoids overwriting live content)

### Client admin account

Create the client's admin account on **Live** only — not staging. Staging is overwritten each time you push from DevKinsta or promote staging to live.

Steps: WP Admin (live) → Users → Add New → Role: Administrator

Tell the client to add and edit content on live only, never on staging.

### Keeping local in sync with live

Use **Sync → Pull from Kinsta** in DevKinsta and select the Live environment to pull the latest files and database down to local.

**Standard dev cycle:**

```
1. Pull from Live          — get latest client content locally
2. npm run dev             — develop with HMR
3. npm run build           — production build
4. git commit && git push  — commit manifest + changes
5. Push to Staging         — via DevKinsta Sync → Push to Kinsta → Staging
6. Test on staging URL
7. Push Staging to Live    — MyKinsta → Staging → Push to Live (files only)
```

**Key rule:** the client adds content on Live; you develop on Local. Pull from Live periodically to stay in sync with their content.

---

## Advanced Custom Fields (ACF)

Field groups are version-controlled via **ACF JSON**. When you save a field group in wp-admin, ACF automatically writes a `.json` file to `acf-json/`. Commit that file and the fields will be available on any environment that has the theme deployed.

**Workflow:**
1. Edit or create a field group in **Custom Fields → Field Groups**
2. Save — ACF writes/updates `acf-json/<group_key>.json`
3. `git add acf-json/ && git commit`
4. Deploy — ACF reads the JSON files on the live site automatically

If wp-admin shows a field group as "Sync available", click **Sync** to pull in changes committed by another developer.

---

## File Structure

```
two-fiftyseven/
├── acf-json/                 # ACF field group JSON (committed to git)
├── assets/
│   ├── css/
│   │   ├── tailwind.css      # Tailwind v4 entry point (@import "tailwindcss")
│   │   └── styles.scss       # Custom SCSS
│   ├── dist/                 # Built assets (gitignored except manifest.json)
│   └── js/
│       └── main.js           # JS entry point (imports both CSS files)
├── footer.php
├── functions.php             # Vite enqueue logic + theme setup + ACF JSON config
├── header.php
├── index.php
├── page.php
├── single.php
├── style.css                 # WordPress theme header
└── vite.config.js            # Vite + inline PostCSS config
```

---

## Scripts

| Command | Description |
|---|---|
| `npm run dev` | Start Vite dev server with HMR |
| `npm run build` | Production build — clean recompile, minified, hashed filenames |
| `npm run build-watch` | Watch mode — rebuilds to `assets/dist/` on file changes (no HMR) |
| `npm run preview` | Preview the production build locally |

---

## Colour Engine

The colour system separates two independent concerns — **colour space** (the brand palette) and **mode** (light or dark) — so they can be controlled by different parties without conflict.

### Concepts

| Concept | What it is | Who controls it |
|---|---|---|
| **Colour space** | The palette family: `neutral`, `maroon`, `forest`, `purple` | Content editor via ACF field |
| **Mode** | `light` or `dark` | OS preference, overridable by the user or the editor |
| **data-theme** | The resolved combination, e.g. `maroon-dark` | Written by JS; never hardcoded in PHP |

### The eight themes

`assets/css/02-tokens/_color-themes.scss` defines eight scoped token sets:

| Value | Space | Mode |
|---|---|---|
| `neutral-light` | Neutral | Light |
| `neutral-dark` | Neutral | Dark |
| `maroon-light` | Maroon | Light |
| `maroon-dark` | Maroon | Dark |
| `forest-light` | Forest | Light |
| `forest-dark` | Forest | Dark |
| `purple-light` | Purple | Light |
| `purple-dark` | Purple | Dark |

Each theme overrides the same set of 22 CSS custom properties across five categories: **Content**, **Surface**, **Border**, **Button**, **Link**. These are all defined under `:root` (the `neutral-light` defaults) and each `[data-theme="..."]` block only overrides that specific scope.

Crucially, the selectors target `[data-theme="..."]` on **any element**, not just `html`. This means a `<div data-theme="forest-dark">` anywhere in the DOM creates a fully isolated colour context for everything inside it — enabling block-level theming.

### Token categories

| Prefix | Tokens | Purpose |
|---|---|---|
| `--color-content-*` | primary, secondary, tertiary, inverse, secondary-inverse, tertiary-inverse, unchanged | Text and icon colours |
| `--color-surface-*` | primary, secondary, inverse-primary, inverse-secondary, unchanged | Background colours |
| `--color-border-*` | primary, secondary, tertiary, button | Border colours |
| `--color-btn-*` | primary-text, primary-bg, secondary-text, secondary-bg | Interactive control colours |
| `--color-link` / `--color-link-hover` | — | Anchor colours |

### Primitive palette

All token values resolve back to primitive colour vars defined in `assets/css/02-tokens/_primitive.scss`:

```
--color-neutral-{100–600}
--color-maroon-{100–600}
--color-forest-{100–600}
--color-purple-{100–600}
```

Shades 100–400 are light/tints; 500–600 are dark/shades. Each colour space uses its own palette for content and surface tokens, but neutral shades serve as tertiary/fallback values across all spaces.

### Runtime resolution order

Mode is determined at runtime through the following priority chain (highest first):

```
1. data-color-mode="light|dark" on the element   →  editor-forced (ACF block field)
2. localStorage 'color-mode'                      →  user toggle preference
3. OS prefers-color-scheme                        →  system default
```

The JS colour engine in `assets/js/main.js` applies this logic to every `[data-color-space]` element on the page whenever the page loads or the OS mode changes.

### No-FOUC strategy

A synchronous inline `<script>` in `<head>` (before `wp_head()`) runs before the browser paints. It reads `data-color-space` from `<html>`, checks `localStorage` then `matchMedia`, and immediately writes `data-theme` to `<html>`. Because it runs before any CSS is applied, there is no flash of incorrect colour on page load — including hard refresh.

```html
<script>/* no-FOUC */
(function(){
  var e = document.documentElement,
      s = e.getAttribute('data-color-space') || 'neutral',
      stored = localStorage.getItem('color-mode'),
      d = stored ? stored === 'dark' : window.matchMedia('(prefers-color-scheme:dark)').matches;
  e.setAttribute('data-theme', s + (d ? '-dark' : '-light'));
})();
</script>
```

### Page-level colour space (ACF)

The ACF field group `group_two57_page_colour` (`acf-json/group_two57_page_colour.json`) adds a **Colour Space** select field to the sidebar of every post and page. The `two_fiftyseven_get_colour_space()` PHP helper reads this field and outputs it as `data-color-space` on `<html>`. If no field value is set, it falls back to `neutral`.

```php
// In functions.php
function two_fiftyseven_get_colour_space(): string {
    if ( is_singular() && function_exists( 'get_field' ) ) {
        $space = get_field( 'colour_space' );
        if ( $space ) return sanitize_key( $space );
    }
    return 'neutral';
}
```

### Block-level colour space (ACF block)

The `acf/colour-section` block (`blocks/colour-section/block.php`) wraps inner blocks in a `<div>` with its own `data-color-space` and optional `data-color-mode`. This creates an isolated colour context — the block's children inherit a completely different theme from the rest of the page.

The block exposes two fields (ACF group `group_two57_block_colour`):

| Field | Options | Default | Behaviour |
|---|---|---|---|
| **Colour Space** | neutral, maroon, forest, purple | neutral | Sets the palette for inner content |
| **Mode** | auto, light, dark | auto | `auto` — follows OS/localStorage; `light`/`dark` — forces a mode regardless |

When mode is `auto`, JS resolves it the same way as the page-level theme (localStorage → OS). When mode is `light` or `dark`, the block template writes `data-theme` directly on the wrapper (no-JS fallback) and also sets `data-color-mode` so JS locks that element to the chosen mode even if the user toggles dark mode globally.

### User light/dark toggle

A `<button data-js="color-mode-toggle">` in `header.php` lets users override their OS setting. Clicking it flips `localStorage('color-mode')` between `'light'` and `'dark'` and re-applies all themes. The button's `aria-pressed` and visible label stay in sync with the current mode.

To programmatically clear the user's override and return to OS-driven behaviour:

```js
localStorage.removeItem('color-mode');
```

### Adding a new colour space

1. Add 6 primitive shades to `assets/css/02-tokens/_primitive.scss`:
   ```scss
   --color-{name}-100: #...;
   /* ... through 600 */
   ```
2. Add two new blocks to `assets/css/02-tokens/_color-themes.scss` — one for `[data-theme="{name}-light"]` and one for `[data-theme="{name}-dark"]`, following the same token structure as the existing eight.
3. Add `{name}: "Label"` to the `choices` array in both ACF JSON files (`group_two57_page_colour.json` and `group_two57_block_colour.json`), then click **Sync available** in WP Admin → ACF.
4. Run `npm run build`.

---

## Scroll Animations with Locomotive Scroll v5

We use **Locomotive Scroll v5** (built on [Lenis](https://www.lenis.dev/)) for both smooth scrolling and scroll-triggered reveal animations.

### What it does

- **Smooth scrolling** — Lenis provides buttery-smooth scroll with native scroll bar support and proper inertia handling
- **Scroll triggers** — Detects when elements enter the viewport and fires animations via the native IntersectionObserver API
- **Page transitions** — Integrates with Swup for seamless AJAX page swaps

### Configuration

File: `assets/js/modules/scroll.js`

```js
const locomotiveScroll = new LocomotiveScroll({
  lenisOptions: {
    lerp: 0.1,        // Smoothing intensity (0=instant, 1=no damping)
    duration: 1.2,    // Fallback scroll animation duration (seconds)
  },
  triggerRootMargin: '-50% 0px 0px 0px',  // Delay animations until ~50% centered
});
```

**`triggerRootMargin` explanation:**
- Format: `"top right bottom left"` (standard IntersectionObserver API)
- `-50%` = shrinks the observable area from the top by 50%
- Result: Elements only gain `.is-inview` class when they're roughly centered on screen
- Adjust for different timings:
  - `-40%` — fires earlier (less centered)
  - `-60%` — fires later (more centered)

### Markup: data-scroll elements

Add `data-scroll` and `--delay` CSS var to elements you want to animate:

```html
<h2 data-scroll style="--delay: 0ms">Headline</h2>
<p data-scroll style="--delay: 150ms">Body text</p>
```

- `data-scroll` — Registers element for viewport detection
- `style="--delay: Xms"` — Staggered timing (0ms = fires first, 150ms = fires second, etc.)
- When visible, Locomotive Scroll adds `.is-inview` class automatically

### CSS: Animation pattern

```scss
.element {
  opacity: 0;
  transform: translateY(3rem);
  transition:
    opacity 0.4s ease-out var(--delay),
    transform 0.8s ease-out var(--delay);

  &.is-inview {
    opacity: 1;
    transform: translateY(0);
  }
}
```

All scroll-triggered elements in this theme use this fade-in + slide-up pattern with the `--delay` variable controlling when each element's animation starts.

### Implemented components

| Component | Pattern |
|---|---|
| **Case Studies Cards** | Sequential reveals — each card fades in as it hits center (0ms, 300ms, 600ms) |
| **Testimonials** | Quote appears first (0ms), then attribution (150ms) |
| **CTA Section** | Heading (0ms), then button (150ms) |

### How Swup integration works

`assets/js/modules/transitions.js` manages the lifecycle:

- **On page enter** — Calls `initScroll()` to attach Locomotive Scroll observersand listeners
- **During AJAX transition** — Swup handles page swap while Locomotive Scroll stays alive
- **On page leave** — Calls `destroyScroll()` to clean up observers and reset animation state

This ensures scroll triggers work correctly after every page swap without stale event listeners or memory leaks.

### Key files

| File | Purpose |
|---|---|
| `assets/js/modules/scroll.js` | Initializes Locomotive Scroll, manages Lenis smooth scroll |
| `assets/js/modules/transitions.js` | Swup page lifecycle + scroll observer cleanup |
| `assets/js/main.js` | Entry point; imports all modules including scroll |
