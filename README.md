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
