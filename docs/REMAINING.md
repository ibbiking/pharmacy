# What is remaining

Snapshot of open work (updated 2026-11-11). The roadmap (`ROADMAP.md`) holds the full detail; this list
is the short version. Items move to "done" in the roadmap as they are built.

## 🚀 2026-11-20: launch preparation (branch `feature/fixed-business-type-credit-books`)

Done:
- Product name **Invora** (`APP_BRAND`, one name for every business type); neutral logo icon.
- Guide / landing: How it works story per type, navbar, Core explained, Packaging = Units of Measure, B2B modules part
  of the Wholesale type, dark mode default per user + off-white theme.
- Super admin: business page (`/admin/superadmin/businesses/{id}`) with every module and sub-module: switch on/off,
  **block** a module or a sub-module (wins over the owner), people, counts; **Visits** page (visitors per day by
  anonymous browser id, sign-ins, browsers / systems / devices, demo sessions).
- Demo: `DemoDataSeeder` for live (`DEMO_ACCOUNTS`, `DEMO_PASSWORD`), demo installation (`DEMO_INSTANCE`) with nightly
  `demo:rebuild`, a **private copy per visitor** (`demo:pool`, hourly `demo:cleanup`), demo login filled in on the
  sign-in page, `DemoGuard` (no people / roles / modules / settings / login changes).
- `deploy.sh` + release workflow (docs/DEPLOYMENT.md §10, §12). FBR groundwork parked (docs/FBR_INTEGRATION.md).

Deployed 2026-11-21: https://suite.alhamaad.com (live, RANA PHARMACY) and https://demo.suite.alhamaad.com (demo) on the
GoDaddy cPanel hosting (DNS A records at GoDaddy, AutoSSL). Open: product name inside the app (still "Invora").

## 🧭 2026-11-19: one business = one type, credit everywhere, books sync (branch `feature/fixed-business-type-credit-books`)

Done (phases 0–6, 8): see the progress log in [BUSINESS_TYPES_AND_SERVICES.md](BUSINESS_TYPES_AND_SERVICES.md) §8.
Waiting for the owner:
- Phase 7: which reference-app features to build ([REFERENCE_APPS_MODULES.md](REFERENCE_APPS_MODULES.md); recommended: apply / refund
  customer credit, advances & refunds, expense advances + claim reports, fiscal year closing, CSV imports).
- Branches / sub-businesses idea (BUSINESS_TYPES_AND_SERVICES.md §7): needs its own design.
- Merge the branch into `upgrade/php83-laravel13` after a look in the browser.

## 🔧 2026-11-18: POS regressions fixed + parity audit vs the Blade UI

Fixed (283 tests green):
- **POS "Out of stock" for goods without an expiry date** (stationery etc.): search, stock check and stock summary
  required `expiry_date >= now()`, so NULL-expiry lots never counted. All now use `LotAllocator::sellableQuery()` /
  `sellableQuantity()` (same rule as checkout: NULL = never expires; expired from the expiry date). With Inventory Pro
  the POS search counts only the POS warehouse, as checkout does.
- **POS product-detail bar restored** (`Components/POS/ProductDetailPanel`): hover/arrow a search result or hover a cart
  row → active sale price preference (+ source, tax included, FEFO/FIFO), price-change rule, unit prices, discount
  (+ max locked, line cap), stock this till can sell per unit, expired, "in your cart", held by other cashiers, other
  warehouses. `products.pos.availability` extended.
- **POS "Stock" button + Stock summary** restored (`Components/POS/StockSummaryModal`, per-warehouse breakdown).
- **Products list "Price summary"** restored (`Components/Products/PriceSummaryModal`): active sale preference, tax
  included, active / static / last-purchase prices per unit, batch prices.

Still lost vs `multi_tenancy_phase2` (audit 2026-11-18, waiting for the owner's go):
- HIGH: product search by formula/strength/type; low-stock colour/badge + "Low stock" tile; product row Delete /
  quick-stock; Import Generic quick modal; report PDF/Excel/CSV/Print; Sales report per-invoice table; P&L per-line +
  totals; owner can't create a staff login with a password (invite only); FBR settings (Roadmap step 6).
- MEDIUM: product details max discount + purchase price per unit; purchases list unit/paid cost columns + delete;
  purchase image upload; backups owner access; invoices list cash/change columns; expiry report months filter +
  columns; returns report product/qty.
- LOW: name type-ahead on create forms; dashboard expired tile / returns detail / 6-month expiring table; POS remove
  confirmation/undo; generic "Sync all"; address column (pharmacies/suppliers); sidebar shortcut links.

## ✅ Status 2026-11-11 (end of session)

Done on `upgrade/php83-laravel13` (280 tests green, verified in a real browser on the QA server):
- 13e security review, side drawer (edit / settings / add stock / details from the right), 13f indexes, 13g deployment guide.
- **Steps 7–12 built:** Inventory Pro (warehouses, transfers, adjustments, stock by warehouse), customers, B2B Sales
  (quotation → order → invoice → payment → credit note, statements, A/R ageing), B2B Purchasing (PO → receive →
  bill → payment → vendor credit, A/P ageing), Accounting (68-account chart, automatic journals from every module,
  backfill, manual journals, ledger, P&L, balance sheet, trial balance, tax summary), Expense (approvals with the
  self-approval policy, reimbursements, receipts), Advanced Reporting (dashboard with period comparison, CSV export,
  weekly email). A4 + thermal print for every document.
- Demo data and logins: `DEMO_ACCOUNTS.md` (QA database migrated and seeded; backup in `../db_backups/`).

To do by the product owner: `git push origin upgrade/php83-laravel13` (no GitHub credential in this session), add
Stripe test keys, decide on the `bec_*` tables (13h).

Still open (later passes):
- B2B: packages / shipments on sales orders, emailing documents as PDF attachments, multi-currency documents with
  exchange rates, applying a customer's leftover credit to a later invoice (today it stays on their balance), item variants.
- Expense: claim reports (grouping), advances, trips.
- Accounting: period closing (retained earnings), bank reconciliation, budgets.
- Step 6 FBR integration (needs the official FBR / PRAL specification first).
- Step 5 polish items below.

## ⏸ Checkpoint 2026-11-06 (earlier) (paused by the product owner: out of tokens)

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

## Steps 7–12: new modules ✅ built 2026-11-11

10–15 done (see the status at the top). Left for later: multi-currency documents, FBR Digital Invoicing (step 6),
"Save as quotation" at the POS, expense claim reports / advances / trips, packages and shipments.

## Step 13: hardening and launch

16. Rotate the credentials that were committed and stop tracking `.env`. Also revoke the GitHub token that was pasted in chat.
17. Replace the `.env` super-admin login shortcut with a normal super-admin account.
18. Rewrite stock alerts so each business only gets its own.
19. Per-business data export and backups, rate limits, security review, performance (indexes, caching).
20. Production deployment guide, and a decision on the `bec_*` tables that don't belong to this project.

### 2026-11-18 (later): POS layout, per-item expiry, dropdowns
- POS: the cart scrolls in its own box and the product-detail bar is frozen at its bottom (≥1200px; below that it is sticky to the page bottom). Cart rows and the detail bar are compact. Search results: hover / ↑ ↓ shows the item; cart rows: **click** selects (highlighted) and shows it, no hover. Total / Save & print bar compact. One POS page serves every business type.
- Items have **"This item can expire"** (`products.has_expiry`, product form › Expiry). On → every purchase / quick stock / B2B goods receipt line needs an expiry date (`PurchaseService::checkLine`; the forms' existing Expiry field just shows "Optional" or not per item); off → expiry optional. No new fields on purchase or POS screens (owner's call). New items default from the business (Pharmacy Pack → on). Migration `2026_11_18_150000` backfills: on for pharmacy businesses and any item that already has a dated batch.
- All searchable dropdowns (`AsyncSelect`, `LookupPicker`) render their menu on `<body>` so cards/modals no longer clip them.
