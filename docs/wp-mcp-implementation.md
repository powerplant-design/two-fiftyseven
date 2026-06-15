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

### 0h. Install the MCP Block Field Bridge

Block ACF fields (Hero headline, CTA text, FAQ items, etc.) are stored
inside `post_content` as block-comment JSON, not in `wp_postmeta`. Royal
MCP's standard tools cannot see or edit them.

**The bridge** (`inc/class-mcp-block-bridge.php`) solves this:

| Direction | Hook | What happens |
|---|---|---|
| **Read** (WP Admin → postmeta) | `save_post` | Parses `post_content` blocks, extracts field values, writes to `wp_postmeta` as `_mcp_b_{field_name}` |
| **Write** (MCP → post_content) | `added_post_meta` / `updated_post_meta` | Detects `_mcp_b_*` changes, finds the matching block in `post_content`, updates the JSON, re-serializes safely |

**Bridge is installed at** `inc/class-mcp-block-bridge.php` and loaded via
`functions.php`:

```php
require_once get_template_directory() . '/inc/class-mcp-block-bridge.php';
Two57_MCP_Block_Bridge::init();
```

**Verified on Home page (ID 10):**
- 372 `_mcp_b_*` postmeta entries synced across 17 blocks
- Read → write → front-end render confirmed
- Full round-trip: `wp_get_post_meta` → `wp_update_post_meta` → value visible on site

**Bridge field naming:** `_mcp_b_{original_field_name}`
(e.g. `_mcp_b_page_hero_headline`, `_mcp_b_cta_link`, `_mcp_b_faq_items`).

Discover all block fields on a page with `wp_get_post_meta(post_id)` and
filter for the `_mcp_b_` prefix.

### 0i. Verify Rollback via Revisions

Every `wp_update_post` or `wp_update_post_meta` write creates a WordPress
revision automatically. MCP can rollback to any revision:

```
# List revisions
wp_get_post_revisions(post_id=10)
→ 237 revisions with dates and authors

# Rollback to a safe revision
wp_restore_revision(post_id=10, revision_id=980)
→ { success: true, restored_revision_id: 980 }

# The current content becomes the previous revision (nothing is lost)
```

**Verified:** Rollback from corrupted content (7.8K) → revision 980 (27.2K),
all 17 blocks intact, front-end 200 OK.

> Never use raw MySQL to restore content — it corrupts block JSON when
> `\r\n` or special characters are present. Always use `wp_restore_revision`.

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
3. Test creating a draft event
4. **Test editing a block field on an existing page:**
   - `wp_get_post_meta(post_id, key="_mcp_b_page_hero_headline")` — read
   - `wp_update_post_meta(post_id, key="_mcp_b_page_hero_headline", value="New")` — write
   - Verify the new headline renders on the front-end
5. Test creating an organisation with a logo (upload to media library first, then reference the attachment ID)
6. Test updating an ACF repeater field (FAQ questions)
7. **Test revision rollback:**
   - `wp_get_post_revisions(post_id)` — list revisions
   - `wp_restore_revision(post_id, revision_id=N)` — rollback
   - Verify content is restored and pages load clean

**Key test:** If the AI can read and write ACF block fields via
`_mcp_b_*` postmeta keys, and rollback via `wp_restore_revision`,
the integration is working properly.

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

**Page block fields:** Use the bridge's `_mcp_b_*` postmeta keys:

```
# Discover all block fields on a page
wp_get_post_meta(post_id=10)  → filter for _mcp_b_ prefix

# Read a specific block field
wp_get_post_meta(post_id=10, key="_mcp_b_page_hero_headline")

# Write a block field (bridge auto-rebuilds post_content)
wp_update_post_meta(post_id=10, key="_mcp_b_page_hero_headline", value="New headline")

# Create a new page with blocks (template clone pattern)
content = wp_get_post(id=TEMPLATE_PAGE_ID).content
wp_create_page(title="New Page", content=content, status="draft")
→ bridge syncs all block fields to postmeta on save
→ wp_update_post_meta for each field you want to change
→ wp_update_page(id=NEW_ID, status="publish")
```

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
bridge are pure code.
