# Page Template Strategy — Feasibility & Pitfalls

> Exploring whether AI tools can write and manage page content via PHP
> template files instead of the WordPress block editor, while MCP
> continues to handle CPT content (events, organisations, people).
>
> **Context:** The client/editor is tech-savvy and uses Claude Code to
> manage content. They work in code, not the WordPress admin. This makes
> the template approach strongly recommended — the block editor is an
> obstacle, not a benefit, for this workflow.

---

## Client Workflow — Template-Based Pages

Since the editor uses Claude Code (or similar AI coding tools) rather than
the WordPress block editor, the entire workflow runs through git:

### Content change (text, images, links)

```
Editor opens Claude Code locally
    │
    ├── "Update the hero headline on the Home page to 'Welcome to two/fiftyseven'"
    │
    ▼
Claude Code edits the page's PHP template
AND/OR sets ACF field values via MCP on local DB
    │
    ▼
Editor reviews locally at http://two-fiftyseven.local/
    │
    ├── Looks good → commit + push to feature branch
    │
    ▼
git push origin feature/update-home-headline
    │
    ▼
Merge to main → push to deploy/staging
    │
    ▼
GitHub Action deploys to staging automatically
    │
    ▼
Editor reviews at https://stg-twofiftyseven-staging.kinsta.cloud
    │
    ├── Looks good → Kinsta Push to Live (files only)
    │
    ▼
On production: template changes are live immediately
    │
    ▼
Set ACF field values on production via MCP (or they're already in postmeta)
    │
    ▼
Done — live page updated
```

### Layout change (add/remove/reorder sections)

```
Editor tells Claude Code:
    "Add a new Stacked Cards section to the Home page between the
     Hero and the Text Block, with 3 cards: 'Base', 'Hub', 'Desk'"

Claude Code:
    1. Edits page-templates/template-home.php
       → adds: include blocks/stacked-cards/block.php
    2. Edits the page-level ACF field group (acf-json/)
       → adds fields: stacked_cards_items_0_heading, etc.
       OR if using functions (Approach C):
       → adds a two57_stacked_cards([...]) call with inline data
    3. If using ACF page-level fields: sets values via MCP on local DB

Editor reviews locally → push → staging → review → Push to Live
On production: run wp acf json sync (if field group changed)
                set ACF field values on production via MCP
```

### New page (new layout)

```
Editor tells Claude Code:
    "Create a new 'Pricing' page with a Hero, Three Cards section,
     and a CTA. Use the forest colour space."

Claude Code:
    1. Creates page-templates/template-pricing.php:
       get_header()
       two57_hero_page([...])
       two57_three_cards([...])
       two57_cta_section([...])
       get_footer()

    2. Creates ACF field group (acf-json/group_two57_page_pricing.json)
       with fields: hero_headline, hero_eyebrow, card_1_title, etc.

    3. Creates the page post via MCP:
       wp_create_post(post_type="page", title="Pricing", status="draft")
       wp_update_post_meta(post_id=X, key="_wp_page_template",
         value="page-templates/template-pricing.php")

    4. Sets ACF field values via MCP on local DB

Editor reviews locally → push → staging → review → Push to Live
On production: wp acf json sync + set ACF field values + publish page
```

### Adding images via MCP

Images need to live in the WordPress media library to get `srcset` responsive
sizes. MCP handles this — no WP Admin needed (except for SVGs).

**JPGs / PNGs — upload directly from the editor's machine:**

```
Editor: "Use /Users/editor/Desktop/hero-photo.jpg as the Home page hero background"

Claude Code:
  1. Reads the local file, base64-encodes it
  2. wp_upload_media(filename="hero-photo.jpg", content_base64="iVBORw0KG...")
     → WordPress stores in /wp-content/uploads/, generates thumbnail/medium/large sizes
     → returns attachment ID (e.g., 1521)
  3. wp_update_post_meta(post_id=PAGE_ID, key="hero_background_image", value=1521)

Template renders: wp_get_attachment_image(1521, 'large')
  → <img srcset="...768x432.jpg 768w, ...1536x864.jpg 1536w, ...1920x1080.jpg 1920w">
  → Full responsive srcset ✓
```

**From a public URL:**

```
Editor: "Use this image: https://example.com/photo.jpg"

Claude Code:
  1. wp_upload_media_from_url(url="https://example.com/photo.jpg", alt_text="Hero background")
     → downloads, stores in media library, generates sizes
     → returns attachment ID
  2. wp_update_post_meta(post_id=PAGE_ID, key="hero_background_image", value=ID)
```

**SVGs — must use WP Admin:**

Safe SVG plugin only hooks into the admin upload flow. MCP's `wp_upload_media`
blocks SVG files. For SVGs:

```
1. Editor: upload via WP Admin → Media → Add New (Safe SVG handles sanitisation)
2. Editor: "I uploaded the logo, it's media ID 1520"
3. Claude Code: wp_update_post_meta(post_id=PAGE_ID, key="brand_logo", value=1520)
```

SVGs don't need `srcset` — they scale infinitely at any viewport size.

**Per-environment sync:**

Images uploaded on production stay on production. To get them on staging/local:
pull production DB + files down (DevKinsta → Sync → Pull from Live → tick
Database + Files). This brings the attachment IDs and the generated image files.

| Image type | Upload method | srcset? | Sync |
|---|---|---|---|
| JPG / PNG (local file) | `wp_upload_media` (base64) via MCP | ✅ Full responsive | Pull DB + files from production |
| JPG / PNG (public URL) | `wp_upload_media_from_url` via MCP | ✅ Full responsive | Same |
| SVG | WP Admin → Media → Add New | Not needed (scales infinitely) | Pull DB + files from production |

### What stays in the WordPress admin

| Content type | Where edited | Why |
|---|---|---|
| **Blog posts** (Kōrero pānui) | WP Admin block editor | Frequent content, visual editing useful, no need for templates |
| **Events** | MCP (Claude Code) | Proven workflow — question tool → create → sort date auto-computes |
| **Organisations** | MCP (Claude Code) | Proven workflow — bulk import works |
| **People** | MCP (Claude Code) | Same as organisations |
| **Media items** | MCP (Claude Code) | Same |
| **Page templates** | Claude Code → git | Layout and structure — version controlled |
| **Page ACF content** | MCP (Claude Code) → production DB | Text, images, links — set per environment |

### Coexistence

Template-based pages and block-based pages run side by side with zero
conflict. WordPress checks for a page template first — if one is assigned,
it controls the output. If no template is assigned, the block editor's
`post_content` renders normally.

Pages that should stay in the block editor:
- **Blog posts** — the editor writes these frequently, visual editing is useful
- **Any page where a non-technical person needs to make changes** — unlikely given the tech-savvy editor

Pages that should move to templates:
- **Home** — layout rarely changes, content changes via MCP
- **Workspace** — complex layout that broke repeatedly via MCP block editing
- **Meetings** — same
- **Host Events** — same
- **Contact** — simple layout, good proof of concept
- **Our Story** — simple, content changes via MCP
- **Privacy Policy** — static content, rare changes

---

## Revised Recommendation

Given the tech-savvy editor using Claude Code:

**Approach C (parameterised section functions)** is strongly recommended.

The editor will never use the block editor. They'll work in code. This means:

1. **No need for block editor compatibility** — templates and functions are the primary content path
2. **AI-friendly** — Claude Code can read, understand, and modify PHP template files
3. **Git-tracked** — every layout and content change is version controlled
4. **MCP for CPTs** — events, orgs, people continue via MCP (proven, reliable)
5. **No serialization corruption** — page content uses postmeta, not block JSON

### What the other developer does

| Task | Tool | Steps |
|---|---|---|
| Change page layout | Claude Code → git | Edit template → review → push → staging review → Push to Live |
| Change page content | Claude Code + MCP | Edit template (if hardcoded) OR set ACF postmeta via MCP → same deploy path |
| Add new section to a page | Claude Code → git | Add function call to template + add ACF fields if needed → deploy |
| Create new page | Claude Code + MCP | Write template + create field group + create page post via MCP + set fields → deploy |
| Add/edit events | MCP | Question tool → create event → set fields → done. No deploy needed. |
| Add/edit organisations | MCP | Same workflow as events |
| Blog posts | WP Admin block editor | Standard WordPress workflow — only for blog content |

### Sync between developers

Both developers work in git. Standard git workflow:

```
Developer A:  feature/add-pricing-page  →  push  →  PR review
Developer B:  Review PR  →  merge to main  →  push to deploy/staging
Both:         Review on staging  →  Push to Live (files only)
Both:         Set ACF field values on production via MCP (or the values are in the ACF JSON for hardcoded content)
```

**No DB push needed for template changes.** Template files deploy with the
theme via git. ACF field values are set per-environment — staging gets its
own values during testing, production gets its own when going live.

---

## Background

### What happened

MCP successfully handles CPT content (events, organisations) because CPT
fields are stored in `wp_postmeta` — simple key/value pairs with no
serialization pipeline.

Page content uses ACF custom blocks stored as block-comment JSON inside
`post_content`. Every programmatic save goes through WordPress 7.0's
`serialize_block_attributes()` (encodes `< > & "` as `\u003c` etc. for
XSS protection) and `wp_unslash()` (strips backslashes from those escapes).
This makes block JSON editing via MCP unreliable — content corrupts on
every save cycle. Only the visual block editor is safe for page content.

### What we're exploring

Moving page content from block-based `post_content` to PHP template files
that AI can write and manage directly via the file system (git-tracked),
while keeping MCP for CPT postmeta fields.

---

## Current Architecture

```
Page (post_content = 27KB block JSON)
    ↓
Gutenberg Editor (visual block placement)
    ↓
ACF Blocks (14 types: hero-page, cta-section, text-block, etc.)
    ↓
Block render templates (blocks/{name}/block.php)
    ↓ get_field() reads from block attributes
Front-end HTML (CUBE CSS classes, Locomotive Scroll data-*)
```

- 14 ACF custom blocks, each with its own `block.php` render template
- Block field groups in `acf-json/` assigned to blocks (not post types)
- Block render templates use `get_field()` to read field values
- Content stored as JSON inside HTML comments in `post_content`
- Deployed: Local → main → deploy/staging → GitHub Action → staging → Kinsta Push to Live

---

## Proposed Architecture

```
Page (post_content = empty)
    ↓
Page Template (page-templates/template-{name}.php) — git-tracked
    ↓ get_field() reads from page-level ACF postmeta
Block render templates reused as template parts (blocks/{name}/block.php)
    ↓
Front-end HTML (same CUBE CSS, same data-* attributes)
```

- Each page has a PHP template file in `page-templates/`
- Template reads content from page-level ACF fields (postmeta)
- MCP reads/writes those postmeta fields safely (no block JSON)
- AI and developers edit template files via git
- `post_content` is empty — no block editor needed for pages

---

## Is This Standard WordPress?

No. WordPress has been moving toward blocks and the Site Editor since WP 5.0.
Page templates are the "classic" approach — supported but not the direction
WordPress is heading.

However:
- Page templates are fully supported and documented in WordPress core
- They work with WordPress 7.0 without issues
- They don't conflict with blocks (can coexist — some pages use templates, others use blocks)
- Many established themes still use this approach (e.g., StudioPress, Roots/Sage)

---

## Feasibility Assessment

### What works

| Capability | Feasible? | How |
|---|---|---|
| AI writes page templates | ✅ | AI edits PHP files directly via file system |
| AI manages page content | ✅ | AI sets ACF field values via MCP (postmeta — no serialization issues) |
| Developer collaborates | ✅ | Git handles file-based collaboration |
| Existing CSS/JS works | ✅ | Templates use same CUBE CSS classes, `data-*` attributes, Locomotive Scroll |
| MCP remains for CPTs | ✅ | Events, orgs, people, media — unchanged |
| Page renders correctly | ✅ | Block render templates can be reused as template parts |

### What's challenging

| Challenge | Impact | Mitigation |
|---|---|---|
| No visual editor for page layout | High — can't drag-and-drop sections | AI writes layout; dev reviews in browser |
| Multiple instances of same section | Medium — page-level fields are flat, not block-scoped | Use numbered field names (e.g., `cta_heading_1`, `cta_heading_2`) or ACF repeaters |
| Page-level ACF field groups | Medium — need new field groups assigned to `page` post type | Create one field group per page template, or one large generic group |
| Other dev needs to understand templates | Medium — steeper learning curve than block editor | Document each template's field list |
| Content changes require code deploy | Medium — field value changes via MCP are fine, but layout changes need git deploy | MCP for content (postmeta), git for layout (templates) |

---

## Three Approaches Compared

### Approach A: AI writes standalone page templates

Each page has a unique PHP template with hardcoded HTML structure:

```php
<?php /* Template Name: Home */
get_header();
$bg_id = get_field('hero_background_image');
?>
<section class="hero-page" data-block="full"
    style="--hero-bg: url('<?= esc_url(wp_get_attachment_url($bg_id)) ?>')">
    <div class="hero-page__content | [ flow ] [ flow-lg ]">
        <p class="hero-page__eyebrow | eyebrow"><?= esc_html(get_field('hero_eyebrow')) ?></p>
        <h1 class="hero-page__headline | text-6xl"><?= wp_kses_post(get_field('hero_headline')) ?></h1>
        <p class="hero-page__subtitle | text-m-l"><?= wp_kses_post(get_field('hero_subtitle')) ?></p>
    </div>
</section>

<section class="text-block | block" data-section>
    <h2 class="text-block__heading | text-3xl"><?= esc_html(get_field('text_block_heading')) ?></h2>
    <!-- ... more sections ... -->
</section>

<?php get_footer(); ?>
```

**Pros:**
- Full control — AI writes exactly what renders
- Self-contained — each template is independent
- No dependency on block render templates
- Easy for AI to generate (it's just PHP + HTML)

**Cons:**
- Duplicate HTML structure — each page rewrites the same section markup
- Layout changes need updating every page template individually
- More code to maintain
- Block render templates become dead code for pages (still used if any pages keep blocks)

### Approach B: AI writes templates that reuse block render templates

Page templates include the existing `blocks/{name}/block.php` files as
template parts, with page-level ACF fields providing the data:

```php
<?php /* Template Name: Home */
get_header();

// Block templates use get_field() which reads from page-level postmeta
// when not in a block rendering context.
set_query_var('is_preview', false);

include get_template_directory() . '/blocks/hero-page/block.php';
include get_template_directory() . '/blocks/text-block/block.php';
include get_template_directory() . '/blocks/cta-section/block.php';

get_footer();
```

**Pros:**
- Reuses existing block render code — no HTML duplication
- Layout = which block templates to include and in what order
- AI only manages the template file (includes) + ACF field values (content)
- CSS and JS integration already works (same HTML, same classes)

**Cons:**
- Block templates rely on `get_field()` reading from block context — need to verify `get_field()` reads page-level postmeta when not in block context
- `$is_preview` and `$block` variables need to be handled (initialized to false/null)
- Multiple instances of same block type — `get_field('cta_heading')` would return the same value for all CTA sections on the page
- Existing block field groups are assigned to blocks — need to create page-level field groups with same field names

**The multiple instances problem:**
If a page has 2 CTA sections, `get_field('cta_heading')` returns one value.
Solutions:
1. Numbered fields: `cta_1_heading`, `cta_2_heading` — requires block templates to accept a prefix parameter
2. ACF repeater: one repeater field `page_sections` with sub-fields per section type
3. Unique field names per use: `cta_meeting_heading`, `cta_book_heading`

### Approach C: AI writes templates with parameterised section functions

Refactor block render templates into callable functions:

```php
// inc/sections.php
function two57_hero_page(array $args): void {
    $eyebrow  = $args['eyebrow'] ?? '';
    $headline = $args['headline'] ?? '';
    $bg_image = $args['background_image'] ?? 0;
    // ... output HTML ...
}

function two57_cta_section(array $args): void {
    $heading = $args['heading'] ?? '';
    $link    = $args['link'] ?? [];
    // ... output HTML ...
}
```

Page template:
```php
<?php /* Template Name: Home */
get_header();

two57_hero_page([
    'eyebrow'         => get_field('hero_eyebrow'),
    'headline'        => get_field('hero_headline'),
    'background_image'=> get_field('hero_background_image'),
]);

two57_cta_section([
    'heading' => get_field('cta_meeting_heading'),
    'link'    => get_field('cta_meeting_link'),
]);

two57_cta_section([
    'heading' => get_field('cta_book_heading'),
    'link'    => get_field('cta_book_link'),
]);

get_footer();
```

**Pros:**
- Solves the multiple instances problem (each call passes different values)
- Reuses rendering logic (one function per section type)
- AI writes template + calls functions with ACF field values
- Clean separation: functions = rendering, template = layout, ACF = content

**Cons:**
- Requires refactoring all 14 block templates into functions (~1-2 days work)
- Block editor can't use these functions (they're for templates only)
- ACF field groups need to be redesigned for page-level use

---

## Deployment & Sync

### Current model

```
Content (post_content):   Production DB is source of truth
                          Pulled DOWN to staging/local for testing
                          Never pushed UP

Code (theme files):       Local → git → staging → production
                          Deployed via GitHub Action + Kinsta Push to Live
```

### Proposed model

```
Page layout (templates):  Git — same as code, deploys normally
Page content (ACF):        Production DB — same as current CPT content
                           MCP sets values on production, dev sets via admin
                           Pulled DOWN for staging testing

CPT content:               Production DB — unchanged
                           MCP handles events, orgs, people, media
```

The proposed model adds page template files to the "code" side. This is
actually cleaner than the current model — page LAYOUT is now in git
(version-controlled, reviewable) and page CONTENT is in the database
(MCP-editable, production source of truth).

### What the other developer does

| Task | Tool | Path |
|---|---|---|
| Change page layout or add a new section | Edit template PHP file | git → deploy |
| Change page content (text, images, links) | WP Admin ACF fields or MCP | Production DB |
| Create a new page with a new layout | Write new template + create page post + set ACF fields | git + admin/MCP |
| Create a new page using existing layout | Create page post + assign template + set ACF fields | Admin/MCP only |

### Sync workflow

1. **Developer A** writes `page-templates/template-new-page.php` locally
2. Pushes to `feature/new-page` branch → merge to `main` → push to `deploy/staging`
3. GitHub Action deploys theme to staging
4. **Developer B** pulls the branch, reviews the template
5. On staging: create the page post, assign the "New Page" template, set ACF field values
6. Test on staging front-end
7. Push to Live (files only — template deploys)
8. On production: create/reassign the page post, set ACF field values (via MCP or admin)

**The complication:** page template files deploy via git (fast, clean), but
page ACF field values live in the production database (per-environment). If
Developer A sets field values on staging, those don't carry to production.
Each environment needs its own field values set.

**Current system has this same problem** — block content in `post_content`
is per-environment. The proposed model doesn't make it worse.

---

## Gotchas & Pitfalls

### 1. The block editor becomes useless for template-based pages

Once a page uses a template instead of blocks, the block editor shows an
empty `post_content`. Non-technical content editors can't use the visual
editor for those pages.

**Mitigation:** Only convert pages where AI/dev will manage content. Leave
pages that need frequent visual editing as block-based. The two systems
coexist.

### 2. ACF flexible content vs flat fields

If pages need variable section counts (different pages have different
sections), flat field names (heading_1, heading_2...) are fragile. ACF's
flexible content field is designed for this, but it stores as a serialized
PHP array in postmeta — MCP can't easily write to it.

**Options:**
- Use ACF flexible content with WP Admin editing (works, but MCP can't edit)
- Use flat numbered fields (works with MCP, but rigid)
- Define unique field groups per page template (clean, but one field group per template)

**Recommendation:** Unique field groups per page template. Each template
has its own field group with exactly the fields it needs. No flexible
content needed — the template defines the layout, the field group defines
the content.

### 3. Block field group conflicts

Current block field groups are assigned to blocks (`acf/hero-page`). New
page-level field groups would use field names like `page_hero_headline`.
If both exist, `get_field('page_hero_layout')` in a template context reads
from postmeta (correct), and in a block context reads from block
attributes (also correct). No conflict — the context determines the source.

**But:** ACF field keys must be unique. The block field group uses
`field_two57_page_hero_layout` and the page-level group would need
`field_two57_page_tpl_hero_layout` (different key, same name). ACF allows
duplicate field names across different field groups as long as keys differ.

### 4. `$is_preview` and `$block` variables in block templates

Block render templates receive `$block`, `$content`, `$is_preview`, `$post_id`
from ACF. When included in a page template context, these are undefined.

**Fix:** Initialise them before including:
```php
$is_preview = false;
$block = [];
$content = '';
$post_id = get_the_ID();
include get_template_directory() . '/blocks/hero-page/block.php';
```

### 5. CSS and JS compatibility

The existing CUBE CSS system uses BEM-style class names (`hero-page__headline`,
`cta-section__heading`, etc.) and `data-*` attributes for JS. Page templates
must output the same classes and attributes for styling and scroll animations
to work.

**Mitigation:** If reusing block render templates (Approach B or C), this is
automatic. If writing standalone templates (Approach A), AI must understand
the CUBE CSS naming conventions (`docs/` has the layer system documented).

### 6. No WordPress revisions for layout changes

Currently, editing block content in the editor creates WordPress revisions.
Template file changes are tracked in git history instead. This is actually
better for developer workflow (code review, diff comparison) but worse for
non-technical users who rely on the revisions UI.

### 7. Adding a new section requires a code deploy

Want to add a new "Events Widget" section to the Home page? That's a
template file change → git push → deploy. Can't just drag a block in the
editor.

**Mitigation:** Template changes are fast (git push → GitHub Action → staging).
MCP can still set the content values once deployed. For the two/fiftyseven
use case (pages change rarely, content changes often), this is acceptable.

### 8. Test coexistence carefully

If some pages use templates and others use blocks, the rendering needs to
handle both. WordPress page templates have priority over `post_content`
rendering — if a template is assigned, it controls the output regardless of
`post_content`.

---

## Recommended Approach

**Approach C (parameterised section functions)** for the long term, but
start with **Approach B (reuse block templates as includes)** as an
intermediate step.

### Phase 1 — Proof of concept (Approach B)

1. Pick one page (e.g., Contact — simplest layout)
2. Create `page-templates/template-contact.php`:
   ```php
   <?php /* Template Name: Contact */
   get_header();
   $is_preview = false; $block = []; $content = ''; $post_id = get_the_ID();
   include get_template_directory() . '/blocks/hero-page/block.php';
   include get_template_directory() . '/blocks/text-block/block.php';
   include get_template_directory() . '/blocks/cta-section/block.php';
   get_footer();
   ```
3. Create page-level ACF field group "Contact Page Fields" assigned to
   pages using the Contact template (same field names as block fields)
4. Set ACF field values on the Contact page (via MCP or admin)
5. Assign the "Contact" template to the page
6. Verify front-end renders identically to the block-based version
7. Verify MCP can read/write all field values (safe postmeta)

### Phase 2 — Evaluate, then decide

After the proof of concept:
- Does the front-end render correctly?
- Can MCP set all field values?
- Does the other developer understand the workflow?
- Is the `get_field()` context switch reliable?

If yes → proceed to convert other pages.

### Phase 3 — Convert pages one at a time

For each page:
1. Audit which blocks are on the page
2. Create a page template that includes those block templates in order
3. Create a page-level ACF field group with matching fields
4. Set field values (copy from block content to postmeta)
5. Assign the template to the page
6. Test front-end matches original
7. Empty `post_content` (or keep as backup — the template overrides it)

### Phase 4 — Refactor to Approach C (optional)

If Approach B works but multiple instances of the same section type
becomes problematic:
1. Refactor block render templates into functions (`inc/sections.php`)
2. Update page templates to call functions with parameters
3. This fully decouples from block rendering context

---

## Content Management Summary

| Content type | Stored where | Edited by | Sync method |
|---|---|---|---|
| Page layout (template files) | Git | AI + developer | git push → deploy |
| Page content (ACF fields) | Production DB | MCP or WP Admin | Per-environment (set on each) |
| Events / orgs / people | Production DB | MCP | Per-environment |
| ACF field structure | `acf-json/` in git | Developer | git → `wp acf json sync` |
| CSS / JS | Git | Developer | git push → npm build → deploy |

---

## MCP Integration

MCP tools work perfectly with page-level ACF fields because they're
stored as plain `wp_postmeta` — no block JSON, no serialization pipeline:

```
# Read all page content fields
wp_get_post_meta(post_id=PAGE_ID) → returns all ACF field values

# Set a page content field
wp_update_post_meta(post_id=PAGE_ID, key="hero_headline", value="New headline")

# Assign a page template
wp_update_post_meta(post_id=PAGE_ID, key="_wp_page_template", value="page-templates/template-home.php")
```

No bridge needed. No `_mcp_b_` prefix. No serialization corruption.
Just standard WordPress postmeta — the same mechanism that works
flawlessly for events and organisations.

---

## What This Does NOT Solve

| Problem | Status |
|---|---|
| Non-technical content editing | Still requires a developer or AI — no visual editor for template pages |
| Multi-language content | Not addressed — would need WPML/Polylang or ACF options |
| Block editor removal | Blocks still exist for any pages that haven't been converted — coexistence is fine |
| Site Editor / Full Site Editing | Not compatible — FSE requires block-based content in the database |
| Future WordPress direction | WordPress is heading toward blocks. Templates are the "classic" approach. Supported but not the future. |

---

## Questions for the Team

1. **Are there pages that should stay block-based?** (Pages where the client
   needs to edit content visually without developer help.)

2. **How many unique page layouts exist?** (Each unique layout = one template
   file. If most pages share a layout, fewer templates needed.)

3. **Will the other developer be comfortable editing PHP templates?**
   (It's more code-oriented than the block editor but still approachable
   for a WordPress developer.)

4. **Is there budget to refactor block templates into functions (Approach C)?**
   (1-2 days of work, but makes long-term maintenance cleaner.)

5. **Does the client understand they can't use the block editor for converted
   pages?** (Important expectation-setting.)