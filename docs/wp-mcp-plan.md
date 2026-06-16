# AI + MCP Integration Plan

Enable AI tools (Claude, opencode) to manage content on this site using natural language — no WP Admin required. Uses Royal MCP (free plugin, 4K+ installs, dedicated ACF integration, 72 tools) as the MCP server.

---

## Architecture

```
AI tool (Claude Desktop / Claude Code / opencode)
        │ MCP protocol (HTTP or stdio via mcp-remote bridge)
        ▼
Royal MCP plugin  —  MCP server (OAuth 2.0 + API key, 72 tools)
        │ WP REST API + Abilities API
        ▼
WordPress 7.0 Core  ←→  ACF 6.8 (enable_acf_ai filter)
                              │
                              ├── Field groups
                              ├── Custom post types
                              ├── Taxonomies
                              ├── Field values (CRUD)
                              └── Schema.org mappings
```

---

## How It Works

1. The Royal MCP plugin exposes WordPress content and ACF data via the MCP protocol
2. ACF 6.8's `enable_acf_ai` filter registers ACF-specific abilities into WordPress's native Abilities API — field groups, CPTs, taxonomies, and per-post-type CRUD operations
3. AI tools connect to the site via MCP and discover these abilities automatically
4. The AI can read/write structured ACF content, list posts, manage CPTs, and inspect the data model

Ian Pollson (ACF Product Manager) demoed this exact setup at Decode 2026 — Claude Code reading a CSV of 15 holiday properties, registering a `property` CPT with 3 taxonomies and a multi-field field group, then importing all 15 records with correct taxonomy relationships. Zero UI clicks.

---

## Supported AI Tools

### Claude Desktop
- **User:** The client (non-technical, chat-based)
- **Setup:** Claude Desktop → Settings → Connect MCP Server → enter site URL
- **Best for:** Day-to-day content tasks — add events, update page text, upload media
- **Docs:** https://modelcontextprotocol.io/quickstart/user

### Claude Code
- **User:** The developer (terminal-based)
- **Setup:** Claude Code reads MCP config from `.mcp.json` in the project root
- **Best for:** Data model work (create CPTs, field groups, taxonomies), bulk imports, site scaffolding
- **Docs:** https://docs.anthropic.com/en/docs/claude-code

### opencode
- **User:** The developer (terminal-based)
- **Setup:** Configure MCP servers in `opencode.json` or `~/.config/opencode/`
- **Best for:** Content review + code work in a single session — the same tool that edits theme files can also push content changes via MCP
- **Key advantage:** opencode already has full access to this theme's codebase. With MCP connected, it can implement a feature (e.g., a new block) and populate test content for it in one flow.

---

## Why Royal MCP

ACF 6.8 registers its own abilities into the Abilities API — this is not theoretical, it shipped in March 2026:

| Capability | Royal MCP | Other MCP plugins |
|---|---|---|
| Read field groups & field structure | Yes — auto-registered | Yes |
| Register post types & taxonomies | Yes — via abilities | Varies |
| CRUD on CPT posts (read/add/edit/delete) | Yes — per-post-type abilities registered automatically | Varies |
| ACF field value read/write | Yes — type-aware (repeater, relationship, image) | Varies |
| Schema.org field mapping | Yes — ACF 6.8 feature | No |
| WP CLI JSON sync | Yes — ACF 6.8 feature | No |
| Future AI field suggestions | ACF roadmap feature | No |
| Additional plugin required | Only `royal-mcp` (official) | Additional third-party plugin |
| Maintenance surface | One free plugin (Royal MCP) + ACF you already have | Additional plugin to maintain |

**Bottom line:** Royal MCP is the practical choice — 4K+ active installs, tested with WP 7.0, built-in ACF integration, actively maintained.

---

## What the AI Can Do

### CPT Content Management
- Create, read, update, delete: Organisations, People, Events, Media Items
- Bulk-import structured data from CSV/JSON
- Set taxonomy terms, feature images, and all ACF field values
- List and filter posts by taxonomy, date, or ACF meta

### Page Content Editing
- Read the current content of any page
- **Update any ACF block field** on existing pages (Hero headline, CTA text, FAQ items, image IDs, etc.)
- Update linked URLs, button labels, image alt text
- Review revisions (WP 7.0 visual revisions)
- Create new pages by cloning templates with pre-placed blocks

### Page Content — Not Supported

Page block content cannot be edited via MCP. Block ACF fields (Hero headlines,
CTA text, FAQ items) are stored inside `post_content` as block-comment JSON.
WordPress 7.0's `serialize_block_attributes()` encodes `< > & "` for XSS
protection, and `wp_update_post()` calls `wp_unslash()` which strips escapes —
making every programmatic save a corruption risk.

**Pages must be edited in WP Admin.** CPTs (events, organisations, people, media)
use plain postmeta and are fully supported via MCP.

### Data Model Work (Developer — via Claude Code / opencode)
- Register new field groups with typed fields
- Create and configure custom post types and taxonomies
- Map ACF fields to schema.org types for AI discoverability
- Sync ACF JSON to/from database via WP CLI

### Site Review
- List all pages and their publish status
- Check which events are upcoming vs passed
- Review all organisations in the directory
- Audit field group structure

---

## What Still Needs the Block Editor

The AI can edit **data inside blocks** via ACF field values, but it cannot place new blocks onto a page from scratch. ACF block structure lives in `post_content` as raw block markup — generating it from scratch is fragile.

**Practical rule:**
- **New page layout** (placing Hero, Stacked Cards, CTA blocks etc.) → build structure in WP Admin block editor, then AI fills in all content
- **Editing existing pages** → AI handles it end-to-end
- **All CPT entries** (events, orgs, people, media items) → AI handles end-to-end

---

## Staging vs Live Workflow

| Task | Where |
|---|---|
| Add/edit/delete CPT content (orgs, people, events, media) | **Live directly** — content, not code |
| Edit block content on pages | **WP Admin only** — block JSON in post_content is fragile to programmatic saves |
| Build a new page layout (block structure) | **Staging first** — place blocks in editor, test, push to live |
| Create new field groups / CPTs / taxonomies | **Staging first** — data model changes should be tested |
| Any theme or plugin changes | **Staging always** |

The client works live for normal content. Staging is for structural changes and page layouts.

---

## Schema.org Integration (ACF 6.8)

ACF 6.8 can automatically inject JSON-LD structured data into page source, mapping ACF fields to schema.org types. This makes content more discoverable to AI crawlers, search engines, and answer engines (ChatGPT, Claude, Google AI Overviews, Copilot).

**Setup:**
1. Enable via filter: `add_filter( 'acf/settings/enable_schema', '__return_true' );`
2. Edit a post type → Advanced Configuration → Schema tab → enable "Add JSON-LD structured data"
3. Select the schema.org type (e.g. `Event`, `Organization`, `Article`)
4. In each field group, map individual ACF fields to schema.org properties

No change to the front-end appearance — the JSON-LD is embedded in a `<script>` tag in the page source, invisible to humans but readable by machines.

---

## Implementation Steps

### Phase 1 — Plugin Setup (Developer)

1. Install **Royal MCP** on staging via WP Admin → Plugins → Add New
2. Add two filters to `functions.php` (or an MU plugin):

```php
// Expose ACF field groups, CPTs, and taxonomies to the Abilities API
add_filter( 'acf/settings/enable_acf_ai', '__return_true' );

// Enable schema.org JSON-LD output (optional — improves AI discoverability)
add_filter( 'acf/settings/enable_schema', '__return_true' );
```

3. Go to Royal MCP → Settings — enable the plugin, note the API key, enable ACF integration
4. **Set `show_in_rest: 1`** on all ACF field groups in `acf-json/` and sync to DB
5. Verify abilities are registered — the AI should discover field groups, CPTs, and CRUD operations when connecting
6. Test all operations on staging before enabling on live

### Phase 2 — Environment Configuration

Each AI tool connects differently. Royal MCP exposes the MCP endpoint at `https://yoursite.com/wp-json/royal-mcp/v1`.

**Claude Desktop:**
```json
{
  "mcpServers": {
    "royal-mcp": {
      "command": "npx",
      "args": [
        "-y", "mcp-remote",
        "https://stg-twofiftyseven-staging.kinsta.cloud/wp-json/royal-mcp/v1",
        "--header",
        "X-Royal-MCP-API-Key:YOUR_KEY"
      ]
    }
  }
}
```

**Claude Code** (`.mcp.json` in project root):
```json
{
  "mcpServers": {
    "two-fiftyseven-staging": {
      "type": "http",
      "url": "https://stg-twofiftyseven-staging.kinsta.cloud/wp-json/royal-mcp/v1",
      "headers": {
        "X-Royal-MCP-API-Key": "YOUR_KEY"
      }
    }
  }
}
```

**opencode** (`opencode.json`):
```json
{
  "mcp": {
    "two-fiftyseven-staging": {
      "type": "remote",
      "url": "https://stg-twofiftyseven-staging.kinsta.cloud/wp-json/royal-mcp/v1",
      "enabled": true,
      "oauth": false,
      "headers": {
        "X-Royal-MCP-API-Key": "{env:ROYAL_MCP_STAGING_KEY}"
      }
    }
  }
}
```

### Phase 3 — Create a Dedicated WP User

Create a WordPress user account specifically for the AI (not a shared admin account):

1. WP Admin → Users → Add New
2. Role: Editor (or a custom role with `edit_posts`, `upload_files`, `manage_categories`)
3. Username: `ai-assistant`
4. After creating, go to that user's profile → Application Passwords → generate one
5. Use that application password in the MCP config

This isolates AI actions from human users in the activity log and allows precise capability control.

### Phase 4 — Test on Staging

- Connect Claude Desktop / Code to staging
- Ask it to list all events, organisations, and pages
- Test creating a draft event
- Test editing an existing page's hero headline
- Test creating an organisation with a logo
- Verify everything renders correctly on the front-end

### Phase 5 — Deploy to Live

1. Install **Royal MCP** on live (same as staging setup)
2. Add the same `enable_acf_ai` and `enable_schema` filters
3. Create the `ai-assistant` user on live
4. Update MCP configs to point to the live URL
5. Monitor for the first week — check the WP activity log for unexpected AI actions

---

## Context Document for AI

A system-level instruction set that gives AI tools full awareness of this site's structure. Paste into Claude Project or `.opencode/context.md`.

### Site Overview
- Custom WordPress theme by Powerplant Design
- Four CPTs: Organisation, Person, Event, Media Item
- 12 custom ACF blocks (Hero, CTA, FAQ, etc.)
- Colour engine with 4 colour spaces × 2 modes (8 themes)
- Live: `twofiftyseven.kinsta.cloud` (will change at launch) | Staging: `stg-twofiftyseven-staging.kinsta.cloud` (will change at launch)

### Custom Post Types

**Organisation** (`organisation`)
- Represents clients and partners in the workspace directory
- ACF fields: `field_two57_brand_logo` (SVG attachment ID), body content (description)
- Taxonomies: `organisation_category`, `use_type` metadata (base/hub/desk/meet/events)

**Person** (`person`)
- Team members and contacts
- ACF fields: `field_two57_brand_logo` (SVG attachment ID), body content (bio)
- Taxonomies: `person_category`

**Event** (`event`)
- Workspace events, talks, meetups
- ACF fields: `event_date` (date), `event_sort_date` (calculated next occurrence), `event_has_passed` (auto-set by cron — never set manually on recurring), `event_colour_space` (neutral/forest/purple/maroon)
- Taxonomies: `event_category`
- Recurring events: use weekday + "EVERY" prefix pattern. `event_has_passed` is never set — the cron job manages sort dates automatically
- One-off events: `event_has_passed` is set to 1 by cron when `event_date` < today

**Media Item** (`media_item`)
- Press coverage and media appearances
- ACF fields: `field_two57_brand_logo` (publication logo SVG attachment ID), body content (summary)
- Taxonomies: `media_item_category`

### Custom Blocks (Edit Values Only — Never Generate Block Markup)

| Block | Key Fields |
|---|---|
| Hero (Home) | Background image, headline, 3 linked card titles/URLs, icon marquee toggle |
| Hero (Page) | Background image, headline, subtitle, marquee toggle and mode |
| Stacked Cards | Tab label, rich content per card, CTA link, image |
| Testimonial | Quote text, attribution, optional background image, colour space |
| Case Studies | 3 selected Organisation post IDs, heading, archive CTA |
| CTA Section | Heading, button label, button URL, optional SVG background |
| FAQ | Eyebrow text, repeater of question/answer pairs |
| Three Cards | Heading, 3 cards each with title/URL/background/colour space |
| Events Widget | Manual event selection toggle, selected event IDs, CTA label/URL |
| Text Block | H2 heading, 2-3 column text items with optional subheadings |
| Impact | Repeater of stat number + label pairs |
| Gallery Slider | Repeater of full-screen images |

### Conventions
- **Colour spaces:** `neutral`, `forest`, `purple`, `maroon` — used across blocks and events
- **SVG logos:** uploaded to media library first (Safe SVG plugin sanitises), then pass attachment ID — never pass a URL
- **Image fields:** pass attachment ID (integer), not a URL
- **Relationship fields:** pass post ID (integer)
- **Dates:** use `Y-m-d` format for `event_date`

### Standing Instructions
- Always confirm before deleting or unpublishing anything
- Always read the current state of a post before updating it
- New pages: create drafts on staging — never create new page layouts on live
- Never modify theme files, plugin files, or wp-config.php
- When adding an event, always ask whether it is recurring or one-off before creating
- For image fields, ensure the media item exists before referencing its ID
- **SVG logos and featured images**: tell the user to upload SVGs via WP Admin → Media (Safe SVG is required). After upload, use the attachment ID with `wp_update_post_meta` or `wp_set_featured_image`. Non-SVG images can be uploaded via `wp_upload_media_from_url`.
- **Term assignment**: use term IDs (e.g. `terms=[11]`) with `wp_add_post_terms`, not slugs. Slugs can fail silently with case mismatches.
- **Page content**: never attempt to edit page block content via MCP. WordPress 7.0's serialization pipeline makes it unreliable. Direct users to WP Admin for page edits.
- **Colour space on organisations**: always set explicitly to `forest` via `wp_update_post_meta`. The ACF default does not render when set via MCP.
- **Event sort date**: always call `wp_update_post(id=X)` after setting all event fields — this triggers `save_post` which fires `mcp-event-helper.php` to compute `event_sort_date`.

---

## Prompt Cheat Sheet

```
List all upcoming events
List all organisations in the directory
Add a new event called [X] on [date]. It is a one-off event.
Add a recurring event called [X] that happens every Thursday.
Add a new organisation called [X]. I have uploaded their logo to the media library.
Update the FAQ on the home page — change question 3 to say [X]
Update the CTA headline on the [page name] page to say [X]
Create a draft page called [X] — I will add the block layout in the editor
Show me all field groups for the Event post type
Map the event_date field to schema.org startDate
Unpublish the event called [X] — confirm before doing it

# Pages are edited in WP Admin — MCP does not touch page block content.

# Full event creation via MCP — question flow:
# 1. Title?  2. One-off or recurring?  3. Date (one-off) or day (recurring)?
# 4. Start/end time?  5. Location? → if offsite: venue + map link
# 6. Free/paid? → if paid: price  7. Category?  8. Calendar link?  9. External links?
#
# Never suggest recurring dates when event is one-off, and vice versa.
  → wp_create_post(post_type="event", title="...", status="publish")
  → wp_update_post_meta(post_id=X, key="event_subheading", value="...")
  → wp_update_post_meta(post_id=X, key="event_recurring", value="1")          # "1" or ""
  → wp_update_post_meta(post_id=X, key="event_day_of_week", value="THU")     # recurring only
  → wp_update_post_meta(post_id=X, key="event_date", value="20260715")       # one-off only (Ymd)
  → wp_update_post_meta(post_id=X, key="event_time_start", value="17:30")    # H:i
  → wp_update_post_meta(post_id=X, key="event_time_end", value="20:00")
  → wp_update_post_meta(post_id=X, key="event_location_type", value="offsite")
  → wp_update_post_meta(post_id=X, key="event_location_name", value="Venue")  # offsite only
  → wp_update_post_meta(post_id=X, key="event_location_map_link",             # offsite only
        value='{"title":"Map","url":"https://maps...","target":"_blank"}')
  → wp_update_post_meta(post_id=X, key="event_cost_type", value="paid")
  → wp_update_post_meta(post_id=X, key="event_cost_price", value="25")       # paid only
  → wp_update_post_meta(post_id=X, key="event_add_to_calendar",              # optional
        value='{"title":"Add to Calendar","url":"https://cal...","target":"_blank"}')
  → wp_update_post_meta(post_id=X, key="post_links",                        # optional
        value='[{"link":{"title":"Tickets","url":"https://...","target":"_blank"}}]')
  → wp_add_post_terms(post_id=X, taxonomy="event_category", terms=[11])     # use term ID
  → wp_update_post(id=X)                                                     # triggers sort date compute

Upload a featured image for the event
  → wp_upload_media_from_url(url="https://example.com/photo.jpg")
  → wp_set_featured_image(post_id=X, media_id=Y)

Add the event to a category
  → wp_add_post_terms(post_id=X, taxonomy="event_category", terms=["kai"])

# Organisation creation:
Ask for: name, subheading, use type (base/hub/desk/meet/events),
category (or create new), external links (optional), description.
  → wp_create_post(post_type="organisation", title="...", status="publish")
  → wp_update_post_meta(post_id=X, key="post_subheading", value="...")
  → wp_update_post_meta(post_id=X, key="organisation_use_type", value="base")
  → wp_update_post_meta(post_id=X, key="colour_space", value="forest")       # always set explicitly
  → wp_create_term(name="FinTech", taxonomy="organisation_category")  # if new
  → wp_add_post_terms(post_id=X, taxonomy="organisation_category", terms=[ID])
  → wp_update_post_meta(post_id=X, key="post_links", value='[{"link":{"title":"...","url":"...","target":"_blank"}}]')

# Bulk import organisations from JSON:
Give me a JSON array of organisations with: name, subheading, use_type,
category, website, description.
  → loop each record: wp_create_post → set fields → assign category
  → category name→ID: Design=14, EDU=16, Energy=15, Food=9, Govt=7, Tech=8
```

---

## Risk Assessment

| Risk | Likelihood | Mitigation |
|---|---|---|
| AI edits wrong post | Low | WP 7.0 visual revisions for rollback; activity log tracks all AI actions |
| AI deletes something unintended | Low | Standing instructions require confirmation; Trash, not permanent delete |
| Client works on live when they meant staging | Low | Context document rules: CPT content = live, page layouts = staging |
| AI-generated block markup is malformed | Medium | Standing instructions: never generate block markup; edit field values only |
| ACF field type mismatch | Low | ACF 6.8 abilities API exposes field structure — AI knows the types before writing |
| Application password leaked | Low | Rotate passwords periodically; the AI user has Editor role, not Administrator |
| Royal MCP abandoned | Low | Free plugin with 4K+ active installs; fallback is direct WP Admin access |
| Block field write corrupts page | Low | `wp_restore_revision` via MCP restores any broken page in seconds; every write creates an auto-revision |
| `serialize_blocks()` round-trip alters content | Low | Tested round-trip preserves 17/17 blocks on Home page; revisions provide rollback safety net |
| Raw MySQL operations corrupt block JSON | Medium | **Never use raw MySQL for content.** Always use MCP tools — they call WP core functions that handle serialization safely. Bridge + `wp_restore_revision` eliminate the need for direct DB access. |

---

## Current State (June 2026)

### Done (Local)

| Item | Status |
|---|---|
| Royal MCP 1.4.27 installed and enabled | ✓ |
| ACF Pro 6.8.4 with `enable_acf_ai` + `enable_schema` | ✓ |
| `opencode.json` configured with `mcp-remote` stdio bridge | ✓ |
| MCP event helper (`inc/mcp-event-helper.php`) — computes `event_sort_date` on save_post | ✓ |
| Events CRUD via MCP: create, all 17 fields, categories, sort date, archive listing | ✓ |
| Organisation CRUD via MCP: create, all fields, categories, external links | ✓ |
| Bulk import organisations from JSON | ✓ |
| Image upload via `wp_upload_media_from_url` (non-SVG) | ✓ |
| Revision rollback via `wp_restore_revision` | ✓ |
| Page block content editing | ✗ Not supported — see "Page Content — Not Supported" above |

### Still to Do

| Item | Phase |
|---|---|
| Deploy `enable_acf_ai` + `enable_schema` + `mcp-event-helper.php` to staging | Phase 1 |
| Install Royal MCP on Kinsta staging | Phase 2 |
| Create `ai-assistant` user on staging | Phase 3 |
| Connect opencode to staging | Phase 4 |
| Pull production DB → staging, test all operations | Phase 5 |
| Deploy to production (files only, no DB push) | Phase 6 |
| Production monitoring (1 week) | Phase 7 |

### Known Limitations

- **Page block content:** Cannot be edited via MCP. WordPress 7.0's `serialize_block_attributes()` + `wp_unslash()` makes programmatic saves unreliable. Pages stay in WP Admin.
- **SVG uploads:** Safe SVG only hooks into the admin upload flow. SVGs must be uploaded via WP Admin → Media, then reference the attachment ID via MCP.
- **Event sort date:** Must call `wp_update_post(id=X)` after setting all event fields to trigger `save_post` for `mcp-event-helper.php` to compute `event_sort_date`.
- **Organisation colour space:** Must be set explicitly to `forest` — the ACF default does not render when fields are set via MCP.
- **Category assignment:** Use term IDs (not slugs) with `wp_add_post_terms` for reliability.
