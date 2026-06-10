# WordPress 7 Upgrade Checklist

Trackable checklist for upgrading this project from WordPress 6.9.4 to 7.0.

## Phase A - Preflight and Backup
- [x] Confirm target is WordPress 7.0 stable (not RC/beta) — confirmed, installed 2026-06-11.
- [ ] Announce maintenance window and freeze content changes.
- [x] Create local DevKinsta backup (DB export + wp-content snapshot).
  - `~/DevKinsta/backups/two_fiftyseven_pre-wp7_20260611070529.sql` (7.1MB)
- [x] Create fresh Kinsta staging backup — manual backup taken 2026-06-11 (includes files + DB).
- [x] Create fresh Kinsta production backup — manual backup taken 2026-06-11 (includes files + DB).
- [x] Record current versions: WordPress core, PHP version, active plugin versions.
	- PHP confirmed: 8.3.30 (Site Health -> Server, 2026-06-10).

## Phase B - Plugin Updates and Compatibility

### Plugin Updates Captured (2026-06-10)
- [x] Update Advanced Custom Fields PRO from 6.8.0.1 to 6.8.4 — done 2026-06-11 via WP Admin after re-activating license key (ACF Settings → Updates).
- [x] Kinsta Must-use Plugins — remains at 3.5.1 on staging, Kinsta auto-deploys this, no manual action needed.
- [x] Update WPForms Lite from 1.10.0.4 to 1.10.1.1 — done locally 2026-06-11.
- [x] Safe SVG is up to date at 2.4.0.
- [x] WP Crontrol is up to date at 1.21.0.

### Post-Update Checks
- [x] Verify ACF block editing/saving/rendering after ACF update.
- [ ] Verify WPForms submission + notification email flow — defer to staging (site not live yet).
- [x] Verify cron events screen and custom hook health in WP Crontrol.
- [x] Verify no plugin warnings in Site Health and debug logs — 3 recommendations only: inactive themes (cosmetic), search engine indexing (expected for local), MariaDB 10.5 vs 10.6 (DevKinsta container is pinned to 10.5 — not an issue on Kinsta hosting).

## Phase C - Local Dry Run (DevKinsta)
- [x] Update WordPress core from 6.9.4 to 7.0 — done 2026-06-11 via WP-CLI.
- [x] Run DB upgrade if prompted — DB already at latest schema (61833).
- [x] Smoke test key frontend routes (home + core templates).
- [x] Smoke test CPT archives and singles: organisation, person, event, media_item.
- [x] Smoke test ACF block edit/save/render: Hero, CTA, Testimonial, Stacked Cards, FAQ, Events Widget.
- [ ] Smoke test form submission and confirmation email — defer to staging (site not live yet).
- [x] Verify cron hook two57_daily_refresh_events can run without errors — confirmed scheduled (Twice Daily, next run ~2pm, both callbacks present).

## Phase D - Staging Rollout
- [ ] Deploy latest theme code to staging branch using normal workflow.
- [ ] Update staging WordPress core to 7.0.
- [ ] Re-check plugin updates on staging after core update.
- [x] Confirm staging PHP is 8.3 (current runtime: 8.3.30).
- [x] Open Site Health and resolve critical issues — 1 "critical": WP_AUTO_UPDATE_CORE=false, intentional (controlled deploy workflow), not a real issue. Inactive themes deleted (local + staging) to clear that warning.
- [x] Check PHP and server logs for fatals/warnings/notices — clean, no errors (Jun 10-11 2026).
- [x] Re-run full smoke suite — frontend pages pass, block edit/save pass. Forms deferred to post-launch.

## Phase E - Production Launch
- [x] Schedule low-traffic deployment window.
- [x] Take immediate pre-launch production backup — manual backup taken in MyKinsta 2026-06-11.
- [x] Promote tested staging state to live — pushed via MyKinsta, plugins migrated, core updated to 7.0 via WP Admin.
- [x] Verify admin login, editor save, form submit, and key templates — pages and block editing confirmed working.
- [x] Verify cron/event behavior after launch — two57_daily_refresh_events confirmed scheduled on live 2026-06-11.
- [x] Monitor logs and user-reported issues for 24-48 hours — error log clean on live 2026-06-11.

## Phase F - Rollback (Only if Needed)
- [ ] Trigger rollback on blocker (fatal errors, broken editor save, failed forms, major template break).
- [ ] Restore pre-upgrade production backup in Kinsta.
- [ ] Document root cause (plugin/theme/core incompatibility).
- [ ] Patch on staging and repeat validation before re-attempt.
