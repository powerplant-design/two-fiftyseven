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
      "url": "https://two-fiftyseven.local/wp-json/royal-mcp/v1",
      "transport": "http",
      "headers": {
        "X-Royal-MCP-Key": "your-api-key-here"
      }
    }
  }
}
```

> DevKinsta uses self-signed SSL. Set `NODE_TLS_REJECT_UNAUTHORIZED=0` in
> your shell environment before running opencode (local dev only).

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
      "url": "https://two-fiftyseven.local/wp-json/royal-mcp/v1",
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
4. Test editing an existing page's hero headline
5. Test creating an organisation with a logo (upload to media library first, then reference the attachment ID)
6. Test updating an ACF repeater field (FAQ questions)
7. Verify everything renders correctly on the staging front-end

**Key test:** If the AI can read and write ACF repeater, relationship, and
image fields correctly, the integration is working properly.

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

1. Connect opencode / Claude Desktop to `two-fiftyseven-production`
2. Edit content directly: "Update the hero headline on the home page to..."
3. Content is saved to production DB immediately
4. For new pages that need block layouts, create a draft on staging first, build the block structure in the editor, then Push to Live (files only — the page content IS in the DB, but since it was created on staging with production data pulled down, it's safe)

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

If the MCP integration causes a critical issue:

1. Disable the `enable_acf_ai` filter (set return to `__return_false` or comment it out)
2. Deploy the disabled version to production via normal workflow
3. Optionally deactivate `royal-mcp` plugin on production
4. Investigate, fix on staging, re-enable

No database changes are needed for rollback — the feature flags are pure code.
