# Two Fifty Seven

Custom WordPress theme by [Powerplant Design](https://powerplant.design).

## Stack

- **WordPress** — local development via [DevKinsta](https://kinsta.com/devkinsta/)
- **Vite 6** — JS/CSS bundling with HMR
- **SCSS** — compiled via Vite's built-in Sass support
- **Tailwind CSS 3** — utility-first CSS
- **PostCSS** — Autoprefixer + CSSNano (production only)
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

## Production Build

```bash
npm run build
```

Output goes to `assets/dist/`. The manifest at `assets/dist/.vite/manifest.json` is committed to version control so WordPress can resolve asset paths on production without a build step being run on the server.

Everything else in `assets/dist/` is gitignored.

---

## File Structure

```
two-fiftyseven/
├── assets/
│   ├── css/
│   │   └── styles.scss       # Main stylesheet (Tailwind + custom SCSS)
│   ├── dist/                 # Built assets (gitignored except manifest.json)
│   └── js/
│       └── main.js           # JS entry point
├── footer.php
├── functions.php             # Vite enqueue logic + theme setup
├── header.php
├── index.php
├── page.php
├── postcss.config.js
├── single.php
├── style.css                 # WordPress theme header
├── tailwind.config.js
└── vite.config.js
```

---

## Scripts

| Command | Description |
|---|---|
| `npm run dev` | Start Vite dev server with HMR |
| `npm run build` | Build and hash assets to `assets/dist/` |
| `npm run preview` | Preview the production build locally |
