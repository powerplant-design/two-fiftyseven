# MCP Implementation Runbook

> **Note:** staging and production URLs will change when the site launches.
> Current values:
> - Staging: `stg-twofiftyseven-staging.kinsta.cloud`
> - Production: `twofiftyseven.kinsta.cloud`
without ever pushing a database to production. Uses **Royal MCP** (free plugin,
4K+ active installs, tested with WP 7.0, dedicated ACF integration) as the
server-side MCP bridge.

---

## Architecture Decision

### Content vs Code Separation

| What | Where it lives | How it moves |
|---|---|---|
| **ACF field structure** (field groups) | `acf-json/*.json` in git | Deployed with theme code. Synced to DB via `wp acf json sync`. |
| **ACF field values** (content) | Production database only | Managed via MCP directly on production. Never pushed from lower environments. |
| **Theme code / blocks / CSS / JS** | git repo | Local → staging (git push). Staging → live (Kinsta Push to Live, files only). |
| **Plugin files / WP core** | Managed per-environment | Updated in-place on staging, tested, then updated on production. Synced via Kinsta Push to Live (files only). |
| **Database** | Each environment has its own | Production DB pulled DOWN to staging for testing. Never pushed UP. |

### Key Principle

The production database is the single source of truth for content. Field structure travels with code via `acf-json/`. Content never leaves production — it only gets copied down to staging for testing. MCP tools read and write content directly on production.

Local exists for rapid development + MCP testing. Content created locally is ephemeral — use MCP to quickly populate test data while building, then discard. Pull production DB down when you need realistic data. The local database is never pushed to any other environment.

```
Content flow:          MCP ←→ Production DB  (bidirectional)
Dev/test flow:         MCP ←→ Local DB       (bidirectional, disposable — never pushed)
DB test flow:          Production DB → Staging  (pull down only, one direction)
DB test flow:          Production DB → Local    (pull down only, one direction)
Code flow:             Local → Staging → Production  (git + Kinsta Push to Live)
Field structure flow:  acf-json/ → git → deploy → wp acf json sync
```

---

## Phase 0 — Local MCP Setup (Dev Environment)

Set up MCP on the DevKinsta local site first. This proves the integration
works end-to-end before touching staging, and gives you a sandbox for rapid
content prototyping during development.

### 0a. Enable Feature Flags Locally

Add the feature flags to `functions.php` so ACF registers its abilities:

```php
// ACF 6.8 — Expose field groups, CPTs, and taxonomies to the Abilities API.
// Required for MCP integration. Registers ACF data model as discoverable
// abilities that AI tools can read, write, and manage.
add_filter( 'acf/settings/enable_acf_ai', '__return_true' );

// ACF 6.8 — Enable automatic Schema.org JSON-LD output.
// Maps ACF fields to schema.org types for AI crawler discoverability.
add_filter( 'acf/settings/enable_schema', '__return_true' );
```

The `functions.php` changes will deploy to staging/production with the theme
in a later phase. For now, they just need to work locally.

### 0b. Install Royal MCP on Local

1. Open `https://two-fiftyseven.local/wp-admin`
2. Plugins → Add New → search `Royal MCP`
3. Install and activate
4. Royal MCP → Settings → configure:
   - Authentication: API Key (generate one)
   - Enable ACF Integration toggle
   - Required capability: `edit_posts`
   - Rate limiting: enable (default) for safety

> Royal MCP auto-detects ACF Pro and registers ACF-aware tools automatically.
> CPT tools are enabled by default — all four CPTs discoverable.

### 0c. Create AI User on Local

1. WP Admin → Users → Add New
2. Username: `ai-assistant`
3. Role: Editor
4. Go to Users → ai-assistant → Application Passwords
5. Generate a new password (label: `opencode-local`)
6. Save the password

### 0d. Connect opencode to Local

Royal MCP exposes an MCP endpoint at `https://two-fiftyseven.local/wp-json/royal-mcp/v1`
via OAuth 2.0 + API key auth.

First, enable the plugin in WP Admin → Royal MCP → Settings:
- Toggle **Enabled** ON
- Note the auto-generated API key

Add to `opencode.json`:

```json
{
  "mcp": {
    "two-fiftyseven-local": {
      "url": "http://two-fiftyseven.local/wp-json/royal-mcp/v1",
      "transport": "http",
      "headers": {
        "X-Royal-MCP-Key": "your-api-key-here"
      }
    }
  }
}
```

> Local DevKinsta uses HTTP (no SSL) to avoid self-signed cert issues.
> Staging and production use HTTPS with Kinsta's Let's Encrypt certificates.

### 0e. Verify Local MCP Works

Restart opencode and test:

```
List all events on two-fiftyseven-local
Create a draft event called "MCP Test Event" for next Tuesday at 6pm — one-off event, colour space: forest
Read back the event you just created to verify all fields were saved correctly
Change the event colour space to purple
Delete the test event
```

**Success criteria:** The AI can create, read, update, and delete an event
with all ACF fields (date, colour space, has-passed flag) working correctly.

### 0f. Rapid Prototyping with Local MCP

While building new features, use MCP locally to populate test content instantly:

```
I'm building a new Testimonial block. Create 5 test testimonials on two-fiftyseven-local with varied quote text and author names. Use different colour spaces for each.
```

```
Create 3 test organisations on two-fiftyseven-local with different use types (base, hub, desk). Use placeholder SVG logos from the media library.
```

All local content is disposable. Pull production DB down when you need
the real dataset. The local DB is **never** pushed to any other environment.

### 0g. Enable ACF `show_in_rest` on All Field Groups

Royal MCP's `acf_get_fields` and `acf_update_field` tools need ACF field
groups registered in the REST API. All 24 `acf-json/group_*.json` files
must have `"show_in_rest": 1` (default is 0).

**Bulk change** — edit all files in `acf-json/`:

```bash
sed -i '' 's/"show_in_rest": 0/"show_in_rest": 1/g' acf-json/*.json
```

Then sync the JSON changes to the database. If `wp acf json sync` is
unavailable, use a one-time PHP snippet:

```php
$json_dir = get_template_directory() . '/acf-json';
foreach ( glob( "$json_dir/*.json" ) as $file ) {
    $json = json_decode( file_get_contents( $file ), true );
    if ( $json && ! empty( $json['key'] ) ) {
        $existing = acf_get_field_group( $json['key'] );
        if ( $existing ) {
            $json['ID'] = $existing['ID'];
            $json['show_in_rest'] = 1;
            acf_update_field_group( $json );
        }
    }
}
```

**Verified:** 25 field groups synced with `show_in_rest=1`.

### 0h. MCP Event Sort Date Helper

`event_sort_date` is a computed field that drives the Events archive ordering.
The existing `acf/save_post` hook (functions.php:1030) computes it during
WP Admin saves, but `acf/save_post` does not fire when fields are set via
`wp_update_post_meta` (MCP's standard approach).

**The helper** (`inc/mcp-event-helper.php`) hooks into WordPress's `save_post`
action (priority 100) and computes `event_sort_date` for any event save,
covering both admin and MCP paths.

Loaded via `functions.php`:

```php
require_once get_template_directory() . '/inc/mcp-event-helper.php';
```

**CRITICAL:** After setting all event fields via MCP, trigger a `save_post`:

```
wp_update_post(id=X) → fires save_post → helper computes event_sort_date
```

Without this final save, `event_sort_date` is empty and the event won't
appear in the upcoming archive listing.

### 0i. What MCP Cannot Do (Page Block Content)

Block ACF fields (Hero headlines, CTA text, FAQ items) are stored inside
`post_content` as block-comment JSON. WordPress 7.0's `serialize_block_attributes()`
encodes `< > & "` as `\u003c \u003e \u0026 \u0022` for XSS protection, and
`wp_update_post()` calls `wp_unslash()` which strips the leading backslash,
breaking the unicode escapes. Every programmatic save through the WordPress
pipeline risks corruption.

**Page block content editing via MCP is not supported.** Pages should be
edited in WP Admin. CPTs (events, organisations, people, media) use plain
postmeta with no serialization pipeline and are fully supported.

---

## Phase 1 — Deploy Feature Flags to Staging

The `enable_acf_ai` and `enable_schema` filters are already in `functions.php`
from Phase 0. Commit and deploy them so staging gets them too:

```bash
git add functions.php
git commit -m "feat(ACF): enable Abilities API and Schema.org integration for MCP"
```

Push through the normal workflow (merge to main, push to `deploy/staging`).
The GitHub Action deploys the updated theme to staging.

> `enable_datastore` can wait — it's only needed for Block Bindings (ACF 6.8.1+).
> Add it later if Block Bindings are used.

---

## Phase 2 — Install MCP Adapter on Staging

1. WP Admin on **Kinsta staging** → Plugins → Add New
2. Search for `royal-mcp` (official WordPress.org plugin)
3. Install and activate
4. Go to Settings → MCP Adapter → configure:
   - Authentication: **Application Passwords** (recommended)
   - Minimum capability: `edit_posts`
   - Leave other settings at defaults

> Plugin updates happen directly on Kinsta, not locally. See README plugin
> update workflow.

---

## Phase 3 — Create the AI User Account on Staging

1. WP Admin → Users → Add New
2. Username: `ai-assistant`
3. Role: **Editor** (`edit_posts`, `upload_files`, `manage_categories`)
4. After creating, go to Users → ai-assistant → scroll to Application Passwords
5. Generate a new application password (label: `opencode-local`)
6. Save the password — it's shown once

---

## Phase 4 — Connect opencode to Staging

Add the staging MCP server to `opencode.json` alongside the local entry
from Phase 0. The full config with all three environments:

```json
{
  "mcp": {
    "two-fiftyseven-local": {
      "url": "http://two-fiftyseven.local/wp-json/royal-mcp/v1",
      "transport": "http",
      "headers": {
        "X-Royal-MCP-Key": "your-local-api-key"
      }
    },
    "two-fiftyseven-staging": {
      "url": "https://stg-twofiftyseven-staging.kinsta.cloud/wp-json/royal-mcp/v1",
      "transport": "http",
      "headers": {
        "X-Royal-MCP-Key": "your-staging-api-key"
      }
        "WORDPRESS_APPLICATION_PASSWORD": "xxxx-xxxx-xxxx-xxxx"
      }
    }
  }
}
```

> The `mcp-remote` npm package acts as a local stdio MCP
> server that proxies REST API calls to the WordPress site. The WordPress
> site does not need to be publicly accessible for this to work — only
> reachable from your machine.

---

## Phase 5 — Test on Staging

Pull production content down to staging for realistic testing:

```
DevKinsta → Sync → Pull from Kinsta → Live → tick Database + Files → Pull
```

Then test the MCP connection:

1. Restart opencode so it picks up the MCP config
2. Ask it to list events, organisations, and pages on staging
3. Test creating a draft event with all ACF fields:
   - `wp_create_post(post_type="event", ...)` → set all fields via `wp_update_post_meta`
   - Assign event category via `wp_add_post_terms`
   - `wp_update_post(id=X)` → triggers `event_sort_date` computation
   - Verify the event appears in the `/events/` archive
4. Test creating an organisation:
   - Set `post_subheading`, `organisation_use_type`, `colour_space`
   - Assign category, add `post_links`
   - Verify renders at `/organisation/{slug}/` and `/organisations/`
5. Test uploading a non-SVG image via `wp_upload_media_from_url`
6. Test assigning the uploaded image as featured via `wp_set_featured_image`

**Key test:** If the AI can create events and organisations end-to-end
with all ACF fields, categories, and links, the integration is working.

---

## Phase 6 — Deploy to Production

### 6a. Theme code

1. Merge the `enable_acf_ai` + `enable_schema` filter changes to main
2. Merge main → `deploy/staging` and push (GitHub Action deploys to staging)
3. Test on staging one final time
4. Kinsta → Staging → Push to Live → **Files only** (✅ themes, ✅ plugins, ☐ uploads, ☐ database)

### 6b. MCP Adapter plugin

1. WP Admin on **Kinsta production** → Plugins → Add New
2. Install `royal-mcp` (same as staging setup)
3. Configure identically to staging

### 6c. AI User on Production

1. WP Admin on **Kinsta production** → Users → Add New
2. Create `ai-assistant` with Editor role
3. Generate application password for production
4. Add the production entry to `opencode.json`. The full config across all three environments:

```json
{
  "mcp": {
    "two-fiftyseven-local": { ... },
    "two-fiftyseven-staging": { ... },
    "two-fiftyseven-production": {
      "url": "https://twofiftyseven.kinsta.cloud/wp-json/royal-mcp/v1",
      "transport": "http",
      "headers": {
        "X-Royal-MCP-API-Key": "your-production-api-key"
      }
    }
  }
}
```

### 6d. Sync ACF JSON

If the theme deploy included new or changed `acf-json/` files, run on production:

```bash
wp acf json sync
```

This activates the new field group structure in the production database
without touching any content.

---

## Phase 7 — Production Monitoring

For the first week after go-live:

- Check the WordPress activity log for AI user actions (WP Admin → Users → ai-assistant)
- Verify no unexpected content changes
- Test a few MCP commands against production:
  - `List all events`
  - `Show me the FAQ field group structure`
- Keep the staging MCP config active for testing future changes

---

## Ongoing Workflows

### Adding New ACF Fields (Dev)

1. Developer creates/modifies field groups in WP Admin **locally**
2. ACF auto-writes to `acf-json/`
3. `git add acf-json/ && git commit -m "feat(ACF): add rating field to Organisation CPT"`
4. Push through normal workflow (main → deploy/staging)
5. GitHub Action deploys theme with updated `acf-json/` to staging
6. `wp acf json sync` on staging (or via Kinsta SSH, or the MCP ad-hoc)
7. Pull production DB to staging, verify existing content + new fields coexist
8. Kinsta Push to Live (files only)
9. `wp acf json sync` on production
10. New fields are now live — MCP can populate them

### Content Editing via MCP (Day-to-Day)

**CPT content (events, orgs, people, media):** Full CRUD via MCP tools.
`wp_create_post` / `wp_update_post` / `acf_update_field` handle everything.

**Event creation — full recipe:**

**Required question flow (branching by answers):**

```
1. Event title?
2. One-off or recurring?
   → If one-off:     ask for date (only suggest calendar dates like "July 15")
   → If recurring:   ask for day of week (MON–SUN)
3. Start time? End time? (H:i format)
4. Location?
   → If offsite:     ask for venue name AND map link URL
5. Free or paid?
   → If paid:        ask for ticket price
6. Event category? (use term ID matching)
7. Calendar link? (optional)
8. External links? (optional — title + URL pairs, stored as post_links)
```

**MCP commands:**

```
# 1. Create the event post
wp_create_post(post_type="event", title="Summer Social", status="publish",
  content="Join us for an evening of drinks and good company.")

# 2. Set ACF fields (one per call via wp_update_post_meta)
wp_update_post_meta(post_id=X, key="event_subheading", value="An evening of connection")
wp_update_post_meta(post_id=X, key="event_recurring", value="")            # "" = one-off, "1" = recurring
# For recurring:  wp_update_post_meta(post_id=X, key="event_day_of_week", value="THU")
# For one-off:    wp_update_post_meta(post_id=X, key="event_date", value="20260715")  # Ymd
wp_update_post_meta(post_id=X, key="event_time_start", value="17:30")     # H:i
wp_update_post_meta(post_id=X, key="event_time_end", value="20:00")
wp_update_post_meta(post_id=X, key="event_location_type", value="two_fiftyseven")
# For offsite:  wp_update_post_meta(post_id=X, key="event_location_name", value="Venue")
# For offsite:  wp_update_post_meta(post_id=X, key="event_location_map_link",
#                  value='{"title":"Map","url":"https://maps...","target":"_blank"}')
wp_update_post_meta(post_id=X, key="event_cost_type", value="free")
# For paid:  wp_update_post_meta(post_id=X, key="event_cost_price", value="25")
# Optional:  wp_update_post_meta(post_id=X, key="event_add_to_calendar",
#                value='{"title":"Add to Calendar","url":"https://cal...","target":"_blank"}')
# Optional:  wp_update_post_meta(post_id=X, key="post_links",
#                value='[{"link":{"title":"Tickets","url":"https://...","target":"_blank"}}]')
# event_sort_date is auto-computed by mcp-event-helper.php on save_post
# event_has_passed is auto-set by twice-daily cron for one-off events

# CRITICAL: After setting all fields, trigger a save to compute event_sort_date
wp_update_post(id=X)  → fires save_post → mcp-event-helper computes event_sort_date

# 3. Assign event category (use term IDs for reliability)
wp_add_post_terms(post_id=X, taxonomy="event_category", terms=[11])

# 4. Verify
wp_get_post_meta(post_id=X)  → check all fields are set
curl http://two-fiftyseven.local/event/summer-social/  → 200 OK

# ── Admin-only tasks (not available via MCP) ──
# SVG brand logo: upload via WP Admin → Media → Add New (Safe SVG required)
#   Then reference the attachment ID via:
#   wp_update_post_meta(post_id=X, key="brand_logo", value=media_id)
#
# Featured image: can be set via MCP for non-SVG images:
#   media = wp_upload_media_from_url(url="https://...", alt_text="...")
#   wp_set_featured_image(post_id=X, media_id=media.id)
#   For SVG featured images, upload via WP Admin first, then use
#   wp_set_featured_image with the media ID.
```

**Event field reference:**

| Field | Type | Format | Required for |
|---|---|---|---|
| `event_subheading` | text | string | All events |
| `event_recurring` | true_false | `"1"` or `""`  | All events |
| `event_has_passed` | true_false | `"1"` or `""` | Auto-set by cron — never set manually on recurring |
| `event_day_of_week` | select | `"MON"`–`"SUN"` | Recurring only |
| `event_date` | date_picker | `"20260715"` (Ymd) | One-off only |
| `event_time_start` | time_picker | `"17:30"` (H:i) | All events |
| `event_time_end` | time_picker | `"20:00"` (H:i) | Optional |
| `event_add_to_calendar` | link | JSON array string | Optional |
| `event_location_type` | radio | `"two_fiftyseven"` / `"offsite"` | All events |
| `event_location_name` | text | string | Offsite only |
| `event_location_map_link` | link | JSON array string | Offsite only |
| `event_cost_type` | radio | `"free"` / `"paid"` | All events |
| `event_cost_price` | text | `"69"` | Paid only |
| `event_sort_date` | computed | `"202607151730"` (YmdHi) | **Auto-computed** — do not set manually |
| `brand_logo` | image | media ID (int) | Optional — SVG via WP Admin only |
| `show_featured_image` | true_false | `"1"` or `""` | Optional |
| `image_orientation` | select | `"portrait"` / `"landscape"` | Optional |
| `post_links` | repeater | JSON array of link objects | Optional |

**Image upload:**

| Method | Tool | Format | SVG? |
|---|---|---|---|
| From URL | `wp_upload_media_from_url(url=…)` | Public HTTPS URL | No |
| Base64 | `wp_upload_media(filename, content_base64)` | Base64-encoded bytes | No |
| WP Admin | Media → Add New | Upload directly | Yes (Safe SVG) |

**SVG brand logos** must be uploaded via WP Admin — Safe SVG only hooks into
the admin upload flow. After uploading, reference the attachment ID in
`brand_logo` via `wp_update_post_meta`.  Let the user know they need to
handle SVG uploads in the admin before you can set the field.

---

**Organisation creation — full recipe:**

**Required question flow:**

```
1. Organisation name?
2. Short description? (post_subheading — displayed below title on cards)
3. Use type? (base / hub / desk / meet / events)
4. Category? (Design / EDU / Energy / Food / Govt / Tech / "create new")
   → If "create new": ask for new category name, then:
     wp_create_term(name="FinTech", taxonomy="organisation_category")
5. External links? (website, social — optional, title + URL pairs)
6. Full description? (post_content — body text)
```

**MCP commands:**

```
# 1. Create the organisation post
wp_create_post(post_type="organisation", title="Acme Corp", status="publish",
  content="Full description of the organisation...")

# 2. Set ACF fields
wp_update_post_meta(post_id=X, key="post_subheading", value="Accounting + Finance")
wp_update_post_meta(post_id=X, key="organisation_use_type", value="base")      # base/hub/desk/meet/events
wp_update_post_meta(post_id=X, key="colour_space", value="forest")             # always set — default fails to render

# 3. Assign category (use term ID)
wp_add_post_terms(post_id=X, taxonomy="organisation_category", terms=[14])

# 4. Optional: external links (repeater — store as JSON)
wp_update_post_meta(post_id=X, key="post_links",
  value='[{"link":{"title":"Website","url":"https://...","target":"_blank"}}]')

# 5. Verify
wp_get_post_meta(post_id=X) → check fields
curl http://two-fiftyseven.local/organisation/acme-corp/ → 200 OK

# Admin-only: SVG brand logo via WP Admin → Media → then:
wp_update_post_meta(post_id=X, key="brand_logo", value=MEDIA_ID)

### Bulk import (JSON/CSV):
# Provide a JSON array of organisations. The AI loops each row.

**JSON format:**
[
  {
    "name": "Acme Corp",
    "subheading": "Accounting + Finance",
    "use_type": "base",
    "category": "Tech",
    "website": "https://acme.com",
    "description": "Full description..."
  }
]

**MCP loop (for each record):**
id = wp_create_post(post_type="organisation", title=rec.name,
  status="publish", content=rec.description)
wp_update_post_meta(post_id=id, key="post_subheading", value=rec.subheading)
wp_update_post_meta(post_id=id, key="organisation_use_type", value=rec.use_type)
wp_update_post_meta(post_id=id, key="colour_space", value="forest")
# If website present: build post_links JSON and set via wp_update_post_meta
# Match category name → term ID using the term list below
wp_add_post_terms(post_id=id, taxonomy="organisation_category", terms=[TERM_ID])

# Category name → ID mapping:
# Design=14, EDU=16, Energy=15, Food=9, Govt=7, Tech=8
# If category not found: wp_create_term(name="...", taxonomy="organisation_category")
```

**Organisation field reference:**

| Field | Type | Choices/Format | Default |
|---|---|---|---|
| `post_subheading` | text | String | — |
| `organisation_use_type` | select | `base`, `hub`, `desk`, `meet`, `events` | — |
| `colour_space` | select | `neutral`, `maroon`, `forest`, `purple` | `forest` — **always set explicitly** |
| `brand_logo` | image | Media attachment ID | Admin-only (SVG) |
| `show_featured_image` | true_false | `"1"` or `""` | `"1"` (show) |
| `image_orientation` | select | `portrait`, `landscape` | `landscape` |
| `image_contain` | true_false | `"1"` or `""` | `""` (cover) |
| `post_links` | repeater | JSON array of link objects | — |

**Category terms:**

| ID | Name |
|---|---|
| 14 | Design |
| 16 | EDU |
| 15 | Energy |
| 9 | Food |
| 7 | Govt |
| 8 | Tech |

**Page block content:** MCP does not edit page content. Pages must be edited in WP Admin. WordPress 7.0's `serialize_block_attributes()` + `wp_unslash()` pipeline makes every programmatic save a corruption risk for block-level ACF fields.

**Revision rollback (if anything breaks):**

```
# List recent revisions
wp_get_post_revisions(post_id=10)

# Rollback to a known-good revision
wp_restore_revision(post_id=10, revision_id=980)

# Verify
curl http://two-fiftyseven.local/
```

**Never use raw database queries** for content operations. MySQL can
corrupt block JSON when special characters (`\r\n`, unicode escapes)
are present. Always use MCP tools (`wp_restore_revision`,
`wp_update_post_meta`, `wp_get_post`) — they use WordPress core functions
that handle serialization safely.

### Local MCP Rapid Prototyping (Dev)

While building new blocks, CPTs, or features, use MCP on the local site to
instantly populate test content instead of clicking through WP Admin:

```
I'm adding a new field to the Event CPT. Add 3 test events on two-fiftyseven-local: one upcoming, one past, one recurring every Wednesday. Use different colour spaces.
```

```
Show me all organisations on two-fiftyseven-local. For any that are missing use_type metadata, set it — base for the first 2, desk for the next.
```

```
Create a page called "Test Blocks" on two-fiftyseven-local and list all 12 ACF blocks I can place on it.
```

All local content is disposable. Pull production DB down when you need
realistic data. The local DB is **never** pushed up.

### Pulling Production Content for Local Dev

```
DevKinsta → Sync → Pull from Kinsta → Live → Database + Files
```

Do this periodically to keep local content realistic. The pull is one-directional — never push local back.

### Plugin / WP Core Updates

Per the README runbook: update plugins directly on Kinsta staging, test, then Push to Live (files only). Never update on local and expect it to deploy — the deploy pipeline only rsyncs the theme folder.

---

## Environment Config Summary

| | Local | Staging | Production |
|---|---|---|---|
| **WordPress** | DevKinsta (`two-fiftyseven.local`) | Kinsta staging | Kinsta live |
| **Theme** | git main branch | GitHub Action deploy from `deploy/staging` | Kinsta Push to Live from staging |
| **DB content** | Disposable test data; pulled from production for realism | Pulled from production for testing | **Source of truth** |
| **ACF fields** | Defined locally, synced via `acf-json/` | Synced via `wp acf json sync` | Synced via `wp acf json sync` |
| **MCP adapter** | Installed (dev/test/rapid prototyping) | Installed (test target) | Installed (primary target) |
| **MCP user** | `ai-assistant` (Editor) | `ai-assistant` (Editor) | `ai-assistant` (Editor) |

---

## Rollback

### Per-Page Rollback (MCP Revision Restore)

If a single page is broken by an MCP content update:

1. `wp_get_post_revisions(post_id=N)` — list all revisions
2. Find the last known-good revision (check date, author)
3. `wp_restore_revision(post_id=N, revision_id=GOOD_ID)` — instant rollback
4. Verify the page renders correctly

WordPress revisions are created automatically on every save, so every
MCP write creates a rollback point. No MySQL access needed.

### Full MCP Rollback

If the MCP integration itself causes issues:

1. Disable the `enable_acf_ai` filter (set return to `__return_false` or comment it out)
2. Deploy the disabled version to production via normal workflow
3. Optionally deactivate `royal-mcp` plugin on production
4. Investigate, fix on staging, re-enable

No database changes are needed for rollback — the feature flags and
`mcp-event-helper.php` are pure code.
