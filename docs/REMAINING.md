# What is remaining

Snapshot of open work, taken 2026-11-06 after step 5.11. The roadmap (`ROADMAP.md`) holds the full detail; this list
is the short version. Items move to "done" in the roadmap as they are built.

## ⏸ Checkpoint 2026-11-06 (paused by the product owner: out of tokens)

- Done and pushed on `upgrade/php83-laravel13` (all tests green): 5.11, Pharmacies screen, 13a secrets, 13b super admin,
  13c per-business stock alerts + bell, 13d data export + scheduled backups.
- **In progress on branch `wip/step-13e`** (NOT merged; full suite has ~13 failing tests to fix first): security-review
  fixes: owner/permission escalation guards (Team, Roles), suspended members blocked, lookup permissions per list
  (likely cause of most failures: tests/roles without `core.catalogue.view` calling `/admin/lookup`), SMTP host/port
  restrictions, atomic reset-code attempts, full logout, `AuthenticateSession` + security headers, super admin must open
  a business before invoice/return numbers, `/qr-logo` removed, Inertia devtools off by default, rate limits on lookup /
  POS / returns. Resume: `git checkout wip/step-13e && php artisan test`, fix failures, merge into the main branch.
- Not started: 13f indexes, 13g `docs/DEPLOYMENT.md`, demo data + `docs/DEMO_ACCOUNTS.md`. Then migrate the working DB
  (back it up first) and the QA DB for `business_exports`.

## Being done now (asked for by the product owner on 2026-11-06)

- [ ] Convert the unused **Pharmacies** screen to Inertia.
- [ ] **Step 13: hardening and launch** (every item below).
- [ ] **Demo businesses and users with data** (B2B inventory, inventory, pharmacy, and one person with several
      businesses), logins listed in `DEMO_ACCOUNTS.md`.

## Step 5: finish the move to the new design

1. Stat strip with filter chips on every list screen that doesn't have one yet.
2. Fix the remaining full-table dropdowns (about 51 were counted at the start; fixed screen by screen).
3. Helper pages for sale preferences, POS preferences and lot selection.
4. Live POS receipt polish (animated line add/remove etc.); the live receipt itself was built in 5.7.

## Step 4 leftovers (platform)

5. Queue infrastructure: a background worker run by `setup.sh` / supervisor.
6. `GlobalAllCurrencySeeder` prints a debug line per currency (harmless, cosmetic).
7. **Product owner:** add Stripe test keys and run the billing test checklist (step 4e).
8. Later: plan limits (users / products / invoices per month), manual overrides (comp a module, extend a trial).

## Step 6: FBR integration (on hold until approved)

9. FBR setup wizard, "With / Without FBR" preference, FBR invoice numbers, several FBR terminals per business,
   queued submission with retries and an offline mode. Real FBR endpoints must be verified against the official docs first.

## Steps 7–12: new modules

10. Shared B2B foundation: full contacts, multi-warehouse, multi-currency, document engine, thermal + A4 print for every document.
11. B2B Sales: quotations → orders → invoices → payments → credit notes, FBR Digital Invoicing, "Save as quotation" at the POS.
12. B2B Purchasing: purchase orders → receives → bills → payments → vendor credits.
13. Accounting: default chart of accounts, ledger, trial balance, P&L, balance sheet, AR/AP ageing.
14. Expenses: claims, approvals, advances, trips, reimbursements.
15. Advanced reporting: cross-module dashboards, scheduled email reports, exports.

## Step 13: hardening and launch

16. Rotate the credentials that were committed and stop tracking `.env`. Also revoke the GitHub token that was pasted in chat.
17. Replace the `.env` super-admin login shortcut with a normal super-admin account.
18. Rewrite stock alerts so each business only gets its own.
19. Per-business data export and backups, rate limits, security review, performance (indexes, caching).
20. Production deployment guide, and a decision on the `bec_*` tables that don't belong to this project.
