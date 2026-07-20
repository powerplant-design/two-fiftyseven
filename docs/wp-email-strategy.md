# WordPress Email Strategy & Plan

> Research and plan for migrating the two/fiftyseven email newsletter from
> Mailchimp to a self-hosted WordPress solution. Keep as much within
> WordPress + Kinsta as possible, with minimal plugins.

---

## Current State

| Item | Status |
|---|---|
| Email service | Mailchimp (external SaaS — ~$90 NZD/mo, ~1,400 contacts, ~921 active subscribers) |
| Newsletter content | Published as WordPress `post` CPT (Kōrero pānui) |
| Mailchimp links in content | Tracking/click URLs (`twofiftyseven.us1.list-manage.com/track/...`) embedded in existing pānui posts — used for email link tracking |
| Signup form | No form on site — appears to be managed entirely in Mailchimp |
| SMTP plugin | None installed |
| Email sending | WordPress default `wp_mail()` via PHP `mail()` (likely unreliable on Kinsta) |
| Plugins installed | ACF Pro, Safe SVG, WP Crontrol, WPForms Lite |

---

## Architecture

```
                     WordPress Admin
                          │
                    MailPoet Plugin
              (templates, subscribers,
               campaigns, signup forms)
                          │
                 MailPoet Sending Service
                  (included in subscription)
                          │
                     Email recipients
```

**Single plugin approach.** MailPoet handles everything — template editing in WP admin,
subscriber management in WordPress database, campaign sending via MailPoet's own Sending
Service (powered by a third-party email relay). No separate SMTP plugin or external SMTP
account required.

---

## Plugin Choice

### MailPoet (Business)

| Feature | Details |
|---|---|
| Templates | Drag-and-drop + raw HTML source toggle, built in WP admin |
| Subscribers | Stored in WordPress database tables (self-hosted, not external SaaS) |
| Sending | MailPoet Sending Service (included) — no separate SMTP relay account needed |
| Mailchimp import | One-click via API key — pulls audiences, subscribers, custom fields |
| Forms | Native form builder with double opt-in, embed via shortcode, widget, or Gutenberg block |
| Post/event linking | `[posts]` shortcodes + custom shortcodes |
| Free tier | Up to 500 subscribers, 5,000 emails/month, includes MailPoet Sending Service |
| Pricing (1,500 subs) | **Business: $20 USD/mo** (~$32 NZD) vs current Mailchimp ~$90 NZD/mo |

---

## Migration Plan

### Phase A — Setup & Test (Free Tier)

*No accounts needed beyond MailPoet.net free registration.*

1. **Install MailPoet** (free from WP.org plugin repo)
2. **Run setup wizard** — connect to MailPoet.net free account
3. **Add `[two57_events]` shortcode** to theme `functions.php`
   - Pulls upcoming events by `event_sort_date`
   - Filters out passed events
   - Returns simple list: title, date, "Read more →" link
4. **Design email template**
   - Pick a pre-built template close to brand
   - Customise colours (neutral/forest/maroon/purple from site)
   - Add logo header (SVG brand logo)
   - Content blocks: Editor's note, upcoming events, recent posts, footer
5. **Create signup form**
   - MailPoet native form builder
   - Double opt-in enabled
   - Embed in **footer** via widget
   - Reusable Gutenberg block pattern for flexible page placement
6. **Test template** — send preview emails to yourself (works on free tier)
   - Verify desktop + mobile layout
   - Check event shortcode pulls real events
   - Confirm unsubscribe link works

### Phase B — Go Live (Upgrade to Paid)

7. **Get Mailchimp API key** → MailPoet one-click import
   - Pulls all ~921 active subscribers + custom fields + list membership
   - Verify count matches
8. **Upgrade to MailPoet Business** ($20/mo for 1,500 subscribers)
   - Activates MailPoet Sending Service for real delivery
   - Removes MailPoet branding from email footers
9. **Configure DNS records** — SPF/DKIM for sending domain
   - MailPoet provides values after activation
   - Set via domain registrar or Kinsta DNS panel
10. **Send test campaign** to a small segment
    - Verify inbox delivery (not spam)
    - Check spam score (mail-tester.com)
    - Test unsubscribe flow

### Phase C — First Real Campaign

11. Draft the next pānui in MailPoet
12. Send to full subscriber list
13. Monitor delivery, opens, clicks in MailPoet analytics
14. Once confirmed working → **cancel Mailchimp** (~$90 NZD/mo saved)

---

## Linking to Events and Posts in Emails

MailPoet supports dynamic content in email templates:

| Content type | How to embed |
|---|---|
| Latest posts | `[posts]` shortcode with parameters (count, category, post_type) |
| Specific post/event | Merge tag: `[post:title]`, `[post:url]`, `[post:featured_image]` |
| Event details | Custom `[two57_events]` shortcode pulling ACF fields |
| Custom post types | `[posts post_type="event" limit="5"]` |

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

Custom shortcode for event-specific ACF fields (added to `functions.php`):

```php
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

## Existing Content Migration

The existing pānui posts (Kōrero hānuere, Kōrero pēpuere, etc.) are published as
WordPress `post` CPT entries with Mailchimp tracking links embedded. These don't
need to change — they're historical content. Going forward, new pānui would be:

1. **Drafted in MailPoet's email template editor** (with event/post shortcodes)
2. **Sent as a campaign** via MailPoet Sending Service
3. **Optionally archived** as a WordPress post for the website archive

The Mailchimp tracking links in old posts can remain — they still redirect to
the original URLs. No need to update historical content.

---

## Kinsta Considerations

| Concern | Resolution |
|---|---|
| Kinsta email sending | Kinsta doesn't provide email hosting. MailPoet Sending Service handles delivery externally. |
| Cron reliability | Kinsta's cron is reliable. WP Crontrol already monitors it. MailPoet uses WP-Cron for queue processing. |
| Database storage | MailPoet subscriber tables are small (~1KB per subscriber). For ~1,500 subscribers, negligible. |
| Performance | MailPoet sends via background cron — no impact on page load. |
| DNS records | SPF/DKIM/DMARC set via Kinsta DNS panel or domain registrar (values from MailPoet). |

---

## Alternatives Considered

| Option | Pros | Cons |
|---|---|---|
| **MailPoet + separate SMTP** (Brevo/SendLayer/Gmail) | Cheaper SMTP tiers | More pieces to maintain, needed separate SMTP account |
| **FluentCRM + SMTP** | Free, more powerful CRM | Overkill for newsletters, needs separate SMTP |
| **Newsletter plugin + SMTP** | Very lightweight | Less polished editor, addons cost extra |
| **Brevo-only** (no MailPoet) | One platform | External SaaS, manual content, no WP admin editing |
| **Custom-built** (no plugins) | Zero plugin dependencies | 2-3 days dev, no analytics/A-B testing |

**Decision:** MailPoet alone — built-in sending makes it the simplest single-plugin
solution. Business tier at $20/mo replaces $90 NZD/mo Mailchimp.

---

## Plugin Count

| Plugin | Purpose | Free? |
|---|---|---|
| MailPoet | Email templates, subscribers, campaigns, sending | Free tier up to 500 subs; Business $20/mo for 1,500 |

**Total new plugins: 1** — self-hosted, no external SaaS dependency
(beyond MailPoet's own sending infrastructure).

---

## Summary

| Question | Answer |
|---|---|
| Can WordPress be used as an email client? | Yes — MailPoet plugin with its built-in Sending Service |
| Can I build custom email templates in WP admin? | Yes — MailPoet's drag-and-drop + raw HTML editor |
| Do I need a separate SMTP plugin? | No — MailPoet Sending Service is included |
| Can templates link to events and posts? | Yes — via shortcodes and merge tags, including custom `[two57_events]` for event CPT |
| Can I migrate from Mailchimp? | Yes — MailPoet has one-click Mailchimp import (API key) |
| How to collect signups on site? | MailPoet native form builder — embed in footer (widget) + reusable Gutenberg block |
| How many new plugins? | 1 (MailPoet only) |
| Does this work on Kinsta? | Yes — MailPoet Sending Service bypasses Kinsta's email restrictions |
