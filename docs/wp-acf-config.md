# Two-Fifty-Seven — WordPress & ACF Architecture Overview

## Stack

- **WordPress 7.0** with **ACF Pro 6.8** for all content modelling
- **Vite 6** for asset bundling (theme at `wp-content/themes/two-fiftyseven/`)
- Local dev via **DevKinsta** at `http://two-fiftyseven.local`

### ACF 6.8 Feature Flags

These filters live in `functions.php` and control opt-in ACF 6.8 features:

```php
// Expose field groups, CPTs, and taxonomies to the WordPress Abilities API.
// Enables AI tools (Claude, opencode) to discover and interact with ACF
// content models via MCP. Required for the MCP integration plan.
add_filter( 'acf/settings/enable_acf_ai', '__return_true' );

// Enable ACF Datastore — exposes field values through wp.data and the
// REST API. Required for Block Bindings UI support (ACF 6.8.1+).
add_filter( 'acf/settings/enable_datastore', '__return_true' );

// Enable automatic Schema.org JSON-LD output. Maps ACF fields to
// schema.org types for AI crawler and search engine discoverability.
add_filter( 'acf/settings/enable_schema', '__return_true' );
```

See [AI + MCP Integration Plan](./wp-mcp-plan.md) for how these connect to Claude / opencode.

---

## How ACF is used

ACF is the single tool for attaching structured data to anything in WordPress. There are three main patterns in use:

1. **Block fields** — data attached to a specific placed block instance on a page
2. **Post meta fields** — data attached to a CPT post (e.g. an Event, Organisation, Person)
3. **Options page fields** — global/site-wide data not tied to any post

---

## Custom Blocks

Blocks are registered via `acf_register_block_type()` in `functions.php`, hooked into `acf/init`. Each block has:

```
blocks/{block-name}/block.php          ← PHP render template
acf-json/group_two57_block_{name}.json ← ACF field group (auto-synced by ACF)
assets/css/06-components/_{name}.scss  ← styles
assets/js/modules/{name}.js            ← JS (init/destroy exports for Swup)
```

The field group JSON files in `acf-json/` are the source of truth for field structure — ACF reads from and writes to this folder automatically when you edit fields in the WP admin. They are committed to the repo, so field structure is version-controlled.

ACF 6.8 added WP CLI commands for managing JSON:

```bash
wp acf json sync       # Sync local JSON changes to the database
wp acf json export     # Export field groups from DB to acf-json/
wp acf json import     # Import field groups from acf-json/ to DB
```

This is useful for CI/CD pipelines and for bulk-syncing after pulling from git.

### Current blocks

| Block | ACF slug |
|---|---|
| Hero (home) | `acf/hero-home` |
| Hero (page) | `acf/hero-page` |
| Text Block | `acf/text-block` |
| Three Cards | `acf/three-cards` |
| Stacked Cards | `acf/stacked-cards` |
| CTA Section | `acf/cta-section` |
| FAQ | `acf/faq` |
| Impact | `acf/impact` |
| Testimonial | `acf/testimonial` |
| Case Studies | `acf/case-studies` |
| Gallery Slider | `acf/gallery-slider` |
| Events Widget | `acf/events-widget` |

Blocks are placed on pages via the WordPress block editor (Gutenberg). Each block's fields appear in the editor sidebar when the block is selected.

---

## Custom Post Types

Registered in `functions.php`:

| CPT | Slug | Notes |
|---|---|---|
| Organisation | `organisation` | Clients / partners |
| Person | `person` | Team members |
| Media Item | `media_item` | Press / media appearances |
| Event | `event` | Has date meta and repeating weekday logic |

CPT field groups live in `acf-json/` alongside block field groups, prefixed consistently: `group_two57_*`.

---

## Global Options Page

An ACF Options page is registered at **WP Admin → Archive Settings**. Fields here are retrieved with the `'option'` argument:

```php
get_field( 'field_name', 'option' );
```

The `'option'` second argument is what distinguishes a global setting from a per-post field. Without it, `get_field()` defaults to the current post.

---

## Recommended approach for Pricing — Single Source of Truth

For pricing data that needs to be available across multiple pages/calculators, the cleanest approach is a **dedicated ACF Options page**. One place to edit rates; any block or template reads from it.

### 1. Register a Pricing options page

Add to `functions.php` inside the existing `acf/init` action:

```php
acf_add_options_page( [
    'page_title'  => __( 'Pricing', 'two-fiftyseven' ),
    'menu_title'  => __( 'Pricing', 'two-fiftyseven' ),
    'menu_slug'   => 'pricing-settings',
    'capability'  => 'manage_options',
    'parent_slug' => '',
    'autoload'    => false,
] );
```

### 2. Define fields in ACF admin

Create a field group in WP Admin → Custom Fields, assign it to the `Options Page: Pricing` location. Typical structure for a rate table:

- **Repeater** field (`pricing_services`) with sub-fields:
  - Text — service name
  - Number — base rate
  - Number — unit (per hour / per day / fixed)
  - Textarea — description

Once saved, ACF writes the field group to `acf-json/` — commit this file.

### 3. Read pricing data in PHP

```php
// In any block template or theme template:
$services = get_field( 'pricing_services', 'option' );

foreach ( $services as $service ) {
    echo $service['name'];   // e.g. "Brand Strategy"
    echo $service['rate'];   // e.g. 1200
}
```

### 4. Expose to JavaScript (for a calculator UI)

Pass the data to JS via `wp_localize_script()` or inline JSON in the block template:

```php
// In block.php
$pricing = get_field( 'pricing_services', 'option' );
?>
<div
    data-js="pricing-calculator"
    data-pricing="<?php echo esc_attr( wp_json_encode( $pricing ) ); ?>"
>
```

```js
// In JS module
const el      = document.querySelector( '[data-js="pricing-calculator"]' );
const pricing = JSON.parse( el.dataset.pricing );
```

### Option B — Per-block configuration

If calculators need per-instance config (e.g. "show only the retainer tier on this page"), combine both patterns: global options hold the rate table; a calculator block has its own field group for display options (which tiers to show, default quantities, etc.) and reads the global rates from options at render time.

---

## ACF 6.8: Abilities API & Schema.org

### Abilities API Integration

When the `enable_acf_ai` filter is active, ACF registers the following abilities into WordPress's native Abilities API:

- Read field groups (structure, field types, choices, validation rules)
- Register/update field groups
- Read/customize post types and taxonomies
- Register new post types and taxonomies
- Per-post-type CRUD abilities automatically registered for each CPT

These abilities are discoverable by any MCP-compatible AI tool connected via `wordpress/mcp-adapter`. For the full integration plan, see [AI + MCP Integration Plan](./wp-mcp-plan.md).

### Schema.org Mapping

When the `enable_schema` filter is active, each post type's Advanced Configuration gains a Schema tab. You can:

1. Select the schema.org type (e.g. `Event`, `Organization`, `Article`)
2. Map individual ACF fields to schema.org properties (e.g. `event_date` → `startDate`)
3. ACF automatically injects JSON-LD structured data into the page `<head>`

This makes content more discoverable to AI crawlers (ChatGPT, Claude, Google AI Overviews) without changing the front-end appearance.

### Block Bindings (ACF 6.8.1+)

With the `enable_datastore` filter active, ACF field values become available in the Block Bindings UI. Editors can bind native blocks (Heading, Paragraph, Image, Button) directly to ACF fields from within the block editor. Changes sync bidirectionally and participate in WordPress's native autosave/revision system.

For each field you want to expose, enable "Allow Access to Value in Editor UI" in the field's Presentation settings.

---

## Key conventions

- Field keys follow the pattern `field_two57_{descriptor}`
- Field group keys follow the pattern `group_two57_{descriptor}`
- `get_field()` with no second argument reads from the current post
- `get_field( 'name', 'option' )` reads from an options page
- `get_field( 'name', $post_id )` reads from a specific post
