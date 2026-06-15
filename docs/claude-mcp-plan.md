# Claude MCP Integration Plan

Enable the client to manage and build content on this site using Claude in natural language — no WP Admin required for day-to-day work.

---

## How It Works (Non-Technical Summary)

The client installs **Claude Desktop** on their Mac. A plugin on the WordPress site acts as a secure bridge. The client opens Claude, types what they want ("add a new event called X on 15 July", "update the hero headline on the home page", "add a new organisation called Y with logo Z"), and Claude does it — reading and writing directly to WordPress.

No coding. No WP Admin. Just chat.

---

## Why Not Native WP 7.0?

WP 7.0 ships two AI-related features, but neither covers this use case:

**AI Connectors screen** — connects AI writing services to the block editor for generating titles, excerpts, and alt text. Editor-only. Not MCP.

**Abilities API** (landed in 6.9) + `wordpress/mcp-adapter` package — this is the proper MCP plumbing. Core ships it, but only registers **3 default abilities**: site info, user info, environment info. No post CRUD, no custom fields, no CPTs. The plumbing is there; the tools are not.

**Why this site specifically can't rely on native:**

1. **ACF custom blocks** — ACF field data is stored in `postmeta` with ACF's own type-aware serialization. Native WordPress has no concept of ACF field types, field groups, or how to read/write that data correctly. A text field, a repeater, an image field, and a relationship field all look like raw strings in `postmeta` — without ACF-specific tooling, Claude would corrupt the data.
2. **ACF block markup** — The custom blocks use `<!-- wp:acf/block-name -->` markup in `post_content`, with actual field data stored in `postmeta` keyed to ACF field keys. The native API sees only the raw block comment, not the structured field data underneath.
3. **CPTs** — Core does not auto-register CRUD abilities for custom post types. You'd need plugins to fill that gap regardless.

Bottom line: you'd need plugins to fill the gaps anyway. One well-integrated plugin is cleaner than patching three separate gaps.

---

## Recommended Plugin: Royal MCP

**Free. Install: `royal-mcp` from wordpress.org.**

No pro tier, no upsells. Version 1.4.26, updated daily, tested to WP 7.0.

**Maturity note:** ~4,000 active installs — growing fast but still a newer plugin (MCP itself is new). Not a battle-tested plugin with years of history. For this site the risk is low: the client is one person doing content on a low-traffic site. If the plugin breaks in an update, the fallback is WP Admin directly. It is not a critical path dependency.

Why Royal MCP over building on the native Abilities API approach:

- **Native Claude Desktop connector** — client connects via OAuth (one-time click, no API key to manage manually)
- **Dedicated ACF integration** — reads and writes ACF fields with correct type-aware formatting (repeaters, relationships, images all work as expected)
- **CPT support built in** — can create, read, update, delete organisations, people, events, media items
- **Security-first** — API key auth, per-IP rate limiting, full activity log of every Claude action
- **67 core tools + ACF-specific tools** out of the box — no configuration needed for ACF detection
- No extra `mcp-adapter` plugin needed — self-contained

---

## What Claude Can Do via MCP

### CPT Content Management
- Create a new Organisation (name, logo, description, all ACF fields)
- Create a new Person (name, role, bio, brand logo)
- Create a new Event (date, title, description, recurring/one-off, colour space)
- Create a new Media Item
- Edit any of the above — update a field, change a date, swap an image
- List all events, organisations, people for review
- Delete or unpublish any CPT entry

### Page Content Editing
- Read the current content of any page
- Update text inside existing ACF blocks (headline, body copy, CTA text, FAQ items)
- Update linked URLs, button labels, image alt text
- Review and compare revisions (WP 7.0 visual revisions feature)

### Site Review
- List all pages and their publish status
- Check which events are upcoming vs passed
- Review all organisations in the directory

---

## What Still Needs the Block Editor

Claude can edit the **data inside blocks** but cannot place new blocks onto a page from scratch via MCP. The ACF block structure (which blocks appear in which order on a page) lives in `post_content` as raw block markup — Claude generating that from scratch is fragile.

**Practical rule for the client:**
- **New page layout** (placing Hero, Stacked Cards, CTA blocks etc.) → developer or client builds the structure in WP Admin block editor on staging, then Claude can fill in all the content
- **Editing existing pages** → Claude handles it end-to-end
- **All CPT entries** (events, orgs, people, media items) → Claude handles end-to-end, no block editor needed

---

## Staging vs Live — Recommended Workflow

| Task | Where |
|---|---|
| Add/edit/delete an organisation, person, event, media item | **Live directly** — it is content, not code, safe to do live |
| Edit text/images on an existing page | **Live directly** — WP 7.0 visual revisions available for rollback |
| Build a brand new page layout (new block structure) | **Staging first** — place blocks in editor, test, push to live |
| Any theme or plugin changes | **Staging always** — never live |

The client does not need to think about staging for normal content work. The only time staging is relevant is when building a new page from scratch.

---

## Implementation Steps

### Phase 1 — Plugin Setup (Developer)
1. Install **Royal MCP** on staging via WP Admin → Plugins → Add New → search "royal-mcp".
2. Go to **WP Admin → Royal MCP → Settings** → generate an API key.
3. Enable the **ACF integration** toggle — auto-detects ACF Pro is active.
4. CPT tools are enabled by default — all four CPTs (organisation, person, event, media_item) discoverable automatically.
5. Set required capability to `edit_posts` so the client's editor account can use it.
6. Test all operations on staging before moving to live.

### Phase 2 — Client Setup (One Time)
1. Client downloads and installs **Claude Desktop** from claude.ai/download.
2. Claude Desktop → Settings → Connectors → Add Connector → WordPress.
3. Enter the site URL — Royal MCP handles OAuth from there (one click, no keys to type).
4. Test: ask Claude "list all events on the site" — should return the event list.

### Phase 3 — Context Document (Developer, Most Important Step)
Write a Claude Project context document covering site structure so Claude can act confidently. See the **Context Document** section below. Without this Claude works generically. With it, Claude knows the exact field keys, CPT rules, and conventions for this site.

### Phase 4 — Handoff
1. Install Royal MCP on live (same as staging setup).
2. Walk the client through the one-time Claude Desktop connector setup.
3. Hand over the prompt cheat sheet.
4. Monitor the Royal MCP activity log for the first week.

---

## Context Document for Claude (What We Need to Write)

A Claude Project instruction file — pasted into the client's Claude Project as the system context. Covers:

### Site Overview
- What the site is, who the client is, the purpose
- Live URL and staging URL
- The rule: CPT content = live directly. New page layouts = staging first.

### Custom Post Types

**Organisation** (`organisation`)
- Represents clients and partners in the workspace directory
- Key fields: post title (org name), `field_two57_brand_logo` (SVG attachment ID), body content (description)

**Person** (`person`)
- Team members and contacts
- Key fields: post title (full name), `field_two57_brand_logo` (SVG attachment ID), body content (bio)

**Event** (`event`)
- Workspace events, talks, meetups
- Key fields: `event_date` (date), `event_sort_date` (calculated next occurrence), `event_has_passed` (true/false — auto-set by cron, do not set manually on recurring), `event_colour_space` (neutral/forest/purple/maroon)
- Recurring events: use a weekday + "EVERY" prefix pattern. `event_has_passed` is never set on recurring events — the cron job manages sort dates automatically.
- One-off events: `event_has_passed` is set to 1 by cron when `event_date` < today.

**Media Item** (`media_item`)
- Press coverage and media appearances
- Key fields: post title (headline/publication name), body content (summary), `field_two57_brand_logo` (publication logo SVG attachment ID)

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
- **SVG logos:** must be uploaded to the media library first (Safe SVG plugin sanitises on upload), then pass the attachment ID to the ACF image field — never pass a URL
- **Image fields:** pass attachment ID (integer), not a URL
- **Relationship fields:** pass post ID (integer)

### Standing Instructions for Claude
- Always confirm before deleting or unpublishing anything
- Always read the current state of a post before updating it
- For new pages, always create a draft on staging — never create new page layouts on live
- Never modify theme files, plugin files, or wp-config.php
- When adding an event, always ask whether it is recurring or one-off before creating

---

## ACF 6.8 AI Feature Flag (Optional)

ACF 6.8 ships an `enable_acf_ai` feature flag that exposes field groups, post types, and taxonomies to the WordPress Abilities API. Royal MCP already handles ACF natively without this flag.

To enable when ready, add to `functions.php`:

```php
add_filter( 'acf/settings/enable_acf_ai', '__return_true' );
```

Not needed for initial setup — assess after Royal MCP is running.

---

## Prompt Cheat Sheet (for Client)

```
List all upcoming events
Add a new event called [X] on [date]. It is a one-off event.
Add a recurring event called [X] that happens every Thursday.
Add a new organisation called [X]. I have uploaded their logo to the media library.
Show me all organisations in the directory
Update the FAQ on the home page — change question 3 to say [X]
Update the CTA headline on the [page name] page to say [X]
Create a draft page called [X] — I will add the block layout in the editor
Unpublish the event called [X] — confirm before doing it
```

---

## Risk Assessment

| Risk | Likelihood | Mitigation |
|---|---|---|
| Claude edits wrong post | Low | Royal MCP activity log; WP 7.0 visual revisions for rollback |
| Claude deletes something unintended | Low | "Always confirm before deleting" in context document |
| Client works on live when they meant staging | Low | Client works live for content — staging is developer-only |
| MCP plugin security issue | Low | API key auth + rate limiting; keep plugin updated |
| ACF field type mismatch | Medium | Detailed field type docs in context document is the fix |
