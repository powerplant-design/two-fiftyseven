# WordPress Email Strategy & Plan

> Research and plan for migrating the two/fiftyseven email newsletter from
> Mailchimp to a self-hosted WordPress solution. Keep as much within
> WordPress + Kinsta as possible, with minimal plugins.

---

## Current State

| Item | Status |
|---|---|
| Email service | Mailchimp (external SaaS) |
| Newsletter content | Published as WordPress `post` CPT (Kōrero pānui) |
| Mailchimp links in content | Tracking/click URLs (`twofiftyseven.us1.list-manage.com/track/...`) embedded in existing pānui posts — used for email link tracking |
| Signup form | No form on site — appears to be managed entirely in Mailchimp |
| SMTP plugin | None installed |
| Email sending | WordPress default `wp_mail()` via PHP `mail()` (likely unreliable on Kinsta) |
| Plugins installed | ACF Pro, Safe SVG, WP Crontrol, WPForms Lite |

---

## Can WordPress Be Used as an Email Client?

**Yes, with the right setup.** WordPress can send transactional emails (password
resets, form notifications) and marketing emails (newsletters, campaigns). But
two separate problems need solving:

### Problem 1: Email Delivery (SMTP)

WordPress's default `wp_mail()` uses PHP's `mail()` function, which lacks
authentication (SPF, DKIM, DMARC). Emails often land in spam.

**Solution:** Install an SMTP plugin that routes emails through an authenticated
sending service. This is separate from the newsletter plugin — it handles
*delivery* for all WordPress emails.

### Problem 2: Newsletter Management (Subscribers, Templates, Campaigns)

WordPress core has no subscriber list, no email template builder, no campaign
manager. This requires a newsletter plugin.

---

## Architecture

```
                     WordPress Admin
                          │
              ┌───────────┼───────────┐
              │           │           │
         Newsletter    SMTP Plugin   Signup Form
         Plugin        (delivery)    (WPForms or
         (content,                    newsletter
          subscribers,                plugin native)
          campaigns)
              │           │           │
              │     SMTP Service      │
              │    (SendLayer,        │
              │     Brevo, Gmail)     │
              │           │           │
              └───────────┼───────────┘
                          │
                     Email recipients
```

---

## Plugin Recommendations

### SMTP Plugin (Required — Email Delivery)

| Plugin | Free? | Notes |
|---|---|---|
| **WP Mail SMTP** | Yes (Pro $99/yr) | 4M+ installs, 15 mailer integrations, setup wizard. Already in the WPForms ecosystem (WPForms Lite is installed). |
| **FluentSMTP** | Yes (free) | Lightweight, OAuth 2.0, API-based. Good alternative if WP Mail SMTP feels heavy. |

**Recommendation:** WP Mail SMTP — it's from the same team as WPForms (already
installed), setup wizard is excellent, and the free version covers all needs.
Pair with a transactional sending service:

| Service | Free tier | Notes |
|---|---|---|
| **Brevo** | 300 emails/day | Best free tier for small lists |
| **SendLayer** | Trial | WP Mail SMTP's #1 recommended, simplest setup |
| **Google/Gmail SMTP** | 100/day | Use existing Google Workspace account if available |
| **Postmark** | 100/month trial | High deliverability, paid |

**Recommendation:** Brevo (300 free emails/day) or Gmail SMTP (if the site
already has a Google Workspace account — 100 emails/day may be enough for a
monthly newsletter to ~900 subscribers split across several batches).

### Newsletter Plugin (Required — Content & Subscribers)

All options below store subscriber data in WordPress database tables (not external SaaS):

| Plugin | Subscribers in WP | Custom HTML Templates | Mailchimp Import | Bulk Campaigns | Link to Posts/Events |
|---|---|---|---|---|---|
| **MailPoet** | ✅ Custom tables | ✅ Drag-and-drop + raw HTML | ✅ One-click (API key) | ✅ Cron-based | ✅ Merge tags + shortcodes |
| **Newsletter** | ✅ Custom tables | ✅ Visual composer + raw HTML/PHP | ✅ Add-on (API/CSV) | ✅ Cron-based | ✅ Custom shortcodes |
| **Mailster** | ✅ Custom tables | ✅ WYSIWYG + HTML source | ✅ Add-on (API) | ✅ Queue-based | ✅ Merge tags |

**Recommendation: MailPoet**

Reasons:
1. **One-click Mailchimp migration** — enter API key, pulls audiences,
   subscribers, custom fields, list membership. The easiest migration path.
2. **Self-hosted subscriber storage** — all data lives in WordPress database
   tables, not external SaaS.
3. **Built-in template editor** — drag-and-drop block editor + raw HTML
   source toggle. Pre-built responsive themes that can be fully customised.
4. **Sends via WordPress cron** — the site already uses WP Crontrol to manage
   cron jobs (including `two57_daily_refresh_events`).
5. **Post/event linking** — MailPoet supports `[posts]` shortcodes and merge
   tags that can pull event/post titles, URLs, featured images into emails.
6. **600K+ active installs** — mature, actively maintained, WordPress 7.0
   compatible.
7. **Free tier** — up to 1,000 subscribers and unlimited emails (sends via
   WordPress cron + your SMTP plugin). Paid tier adds MailPoet's own sending
   service if you don't want to manage SMTP separately.

### Signup Form (For Collecting New Subscribers)

| Option | Plugin needed | Notes |
|---|---|---|
| **MailPoet native form** | MailPoet (already installed) | Built-in form builder, embed via shortcode or widget, stores subscribers directly |
| **WPForms** | Already installed | Create a newsletter signup form, integrate with MailPoet via addon or custom hook |
| **Custom block** | None (theme code) | Build an ACF block with email input, POST to a REST endpoint that adds to MailPoet |

**Recommendation:** MailPoet's native form builder — it stores subscribers
directly, handles double opt-in, and embeds via shortcode or as a Gutenberg
block. Can also place a signup form in the footer or a dedicated block.

---

## Linking to Events and Posts in Emails

MailPoet supports dynamic content in email templates:

| Content type | How to embed |
|---|---|
| Latest posts | `[posts]` shortcode with parameters (count, category, post_type) |
| Specific post/event | Merge tag: `[post:title]`, `[post:url]`, `[post:featured_image]` |
| Event details | Custom shortcode in `functions.php` that pulls `event_date`, `event_time_start` etc. |
| Custom post types | `[posts post_type="event" limit="5"]` — MailPoet ACF block or custom shortcode |

For the two/fiftyseven use case (monthly pānui featuring upcoming events):

```html
<!-- In MailPoet email template -->
[posts post_type="event" limit="5" taxonomy="event_category"]
  <!-- Repeated for each event -->
  <h3>[post:title]</h3>
  <p>[post:excerpt]</p>
  <a href="[post:url]">Read more</a>
[/posts]
```

Or with a custom shortcode added to `functions.php`:

```php
// Add to functions.php for MailPoet email templates
add_shortcode('two57_events', function($atts) {
    $events = new WP_Query([
        'post_type'      => 'event',
        'posts_per_page' => $atts['limit'] ?? 5,
        'meta_key'       => 'event_sort_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => [
            ['key' => 'event_has_passed', 'value' => '1', 'compare' => '!=']
        ]
    ]);
    ob_start();
    // Output HTML formatted event cards
    // ...
    return ob_get_clean();
});
```

---

## Migration Plan: Mailchimp → WordPress

### Phase 1: SMTP Setup (30 minutes)

1. Install WP Mail SMTP plugin (free)
2. Configure with Brevo or Gmail SMTP
3. Set up SPF, DKIM, DMARC DNS records (via Kinsta DNS or registrar)
4. Send test email — verify it reaches inbox (not spam)

### Phase 2: MailPoet Installation (15 minutes)

1. Install MailPoet plugin (free)
2. Run MailPoet setup wizard
3. Configure sender name/email (match SMTP From settings)

### Phase 3: Mailchimp Migration (30 minutes)

1. Get Mailchimp API key (Mailchimp → Account → Extras → API keys)
2. In MailPoet: Settings → Advanced → Migration tools → Import from Mailchimp
3. Enter API key — MailPoet pulls audiences, subscribers, custom fields
4. Verify subscriber count matches Mailchimp
5. Verify list segmentation carried over

### Phase 4: Email Template Design (1-2 hours)

1. Choose a MailPoet pre-built template close to the two/fiftyseven brand
2. Customise colours to match the site's colour spaces (neutral/forest/maroon/purple)
3. Add logo header (SVG brand logo — same as the site)
4. Create content blocks for:
   - Editor's note (free text)
   - Upcoming events (`[two57_events]` custom shortcode)
   - Recent posts (`[posts post_type="post" limit="3"]`)
   - Featured organisation (custom shortcode or manual)
   - Footer with unsubscribe link (MailPoet handles automatically)

### Phase 5: Signup Form (30 minutes)

1. Create a MailPoet signup form in the form builder
2. Configure double opt-in (recommended for deliverability)
3. Embed on site:
   - Footer (via shortcode or widget)
   - Dedicated block on a "Subscribe" section
   - Or as a Gutenberg block via MailPoet's block

### Phase 6: Testing (30 minutes)

1. Send a test campaign to yourself
2. Verify on desktop (Gmail, Outlook, Apple Mail)
3. Verify on mobile
4. Check spam score (mail-tester.com)
5. Verify all links resolve correctly
6. Verify unsubscribe works

### Phase 7: First Real Campaign

1. Draft the next pānui in MailPoet
2. Send to full subscriber list
3. Monitor delivery, opens, clicks in MailPoet analytics
4. Once confirmed working, cancel Mailchimp subscription

---

## Plugin Count

| Plugin | Purpose | Free? |
|---|---|---|
| WP Mail SMTP | Email delivery (SMTP) | Yes |
| MailPoet | Newsletter management | Yes (up to 1,000 subscribers) |

**Total new plugins: 2** — both free, both self-hosted, no external SaaS dependency
(except the SMTP relay service, which is just delivery infrastructure).

---

## Kinsta Considerations

| Concern | Resolution |
|---|---|
| Kinsta email sending | Kinsta doesn't provide email hosting. SMTP plugin routes through external service (Brevo/Gmail). |
| Cron reliability | Kinsta's cron is reliable. WP Crontrol already monitors it. MailPoet uses WP-Cron for queue processing. |
| Database storage | MailPoet subscriber tables are small (~1KB per subscriber). For 900 subscribers, negligible. |
| Performance | MailPoet sends via background cron — no impact on page load. |
| DNS records | SPF/DKIM/DMARC set via Kinsta DNS panel or domain registrar. |

---

## Existing Content Migration

The existing pānui posts (Kōrero hānuere, Kōrero pēpuere, etc.) are published as
WordPress `post` CPT entries with Mailchimp tracking links embedded. These don't
need to change — they're historical content. Going forward, new pānui would be:

1. **Drafted in MailPoet's email template editor** (with event/post shortcodes)
2. **Sent as a campaign** via MailPoet + SMTP
3. **Optionally archived** as a WordPress post for the website archive

The Mailchimp tracking links in old posts can remain — they still redirect to
the original URLs. No need to update historical content.

---

## Alternative: Custom-Built Solution (No Newsletter Plugin)

If plugin minimalism is paramount, a fully custom solution is possible:

1. **Subscribers:** Custom database table or CPT for subscriber emails
2. **Signup form:** Custom ACF block with email input + AJAX handler
3. **Email templates:** PHP template files in `theme/email-templates/`
4. **Sending:** `wp_mail()` + WP Mail SMTP + WP-Cron batching
5. **Admin UI:** Custom admin page for composing and sending newsletters

**Trade-off:** More development time (~2-3 days), full control, zero plugin
dependencies beyond WP Mail SMTP. But loses: analytics (opens, clicks),
A/B testing, subscriber management UI, template builder.

**Recommendation:** Start with MailPoet. If the newsletter grows beyond
MailPoet's free tier (1,000 subscribers) or the team wants more control,
a custom solution can be built later using the same SMTP infrastructure.

---

## Summary

| Question | Answer |
|---|---|
| Can WordPress be used as an email client? | Yes — with SMTP plugin + newsletter plugin |
| Can I build custom email templates in WP admin? | Yes — MailPoet's drag-and-drop + raw HTML editor |
| Do I need a plugin? | Yes — 2 plugins (WP Mail SMTP for delivery, MailPoet for newsletters) |
| Can templates link to events and posts? | Yes — via shortcodes and merge tags, including custom shortcodes for event CPT |
| Can I migrate from Mailchimp? | Yes — MailPoet has one-click Mailchimp import (API key) |
| How to collect signups on site? | MailPoet native form builder — embed via shortcode or Gutenberg block |
| How many new plugins? | 2 (both free, both self-hosted) |
| Does this work on Kinsta? | Yes — SMTP via external service, cron for sending, data in WP database |