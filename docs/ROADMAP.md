# Roadmap

**Rules for working through this list**
1. A step starts **only after the product owner approves it**. Before asking, this file and the related docs must
   already describe the step. Steps can be resumed at any time.
2. **No work is done twice.** Nothing is built in Blade that will be rebuilt in Inertia. Until a screen moves to
   Inertia, only backend logic, data model and tests change for it.
3. **Reuse first.** Shared logic goes into Core services and module service providers, never copied.
4. **Proper channels only.** Every schema change and every data fix or backfill is a migration. Default and
   reference data (preferences, number series, chart of accounts, tax presets, currencies) comes from idempotent
   seeders that can run per business. Nothing is changed by hand in a database.
5. Every step ends with automated tests, updated `docs/*.md`, an updated `INSTALL_NOTES.txt` (commands only) and an
   updated `CLAUDE.md`. Then commit and push.

Status: ✅ done · 🚧 in progress · ⏸ waiting for approval · 🔜 later

> ### ▶ Resume here
> **Last checkpoint:** Step 2 complete and committed. **Next:** Step 3 (Inertia foundation), approved in advance.
> Before continuing, run `php artisan test`; it must be all green.

---

## ✅ Step 0: Laravel 13 / PHP 8.3 upgrade
Branch `upgrade/php83-laravel13`.
- Framework and package upgrade, moved to the Laravel 11+ app structure.
- `setup.sh` one-command installer and commands-only install notes.
- Database copied to `pharmacy_laravel13`; the original was backed up and left untouched.
- Verified: all pages, data tables, detail pages and a real login over HTTP; a fresh install with seeding; the
  installer tested in a clean Ubuntu container.

## ✅ Step 1: Documentation
`docs/BUSINESS_LOGIC.md`, `ROADMAP.md`, `MODULES_AND_PLANS.md`, `DESIGN_SYSTEM.md`, `REFERENCE_SCREENSHOTS.md`,
`FBR_INTEGRATION.md`.

## ✅ Step 2: Sale engine correctness (backend and tests), DONE 2026-11-04
- [x] One shared engine in `app/Services/Sales/`: `PackagingService`, `SalePreferenceResolver` / `SaleSettings`,
      `LotAllocator`, `SalePriceCalculator`, `SaleTaxCalculator`, `CheckoutService`. POS check-stock, the cart tax
      preview, checkout, returns and profit/loss all use it.
- [x] P1 server-side pricing + D2 price-change / floor / discount caps (business-wide and per product, audit-logged).
- [x] P2 per-business preference rows (data migration + automatic on business creation) incl. new lot / POS rules.
- [x] P3/D3 tax inside the price, owner-only; additive tax unchanged.
- [x] D1 FEFO/FIFO lot engine. Mode per invoice line (POS request field `lot_selection_mode`), default from preference.
- [x] P5 dead pricing/checkout copies removed.
- [x] Extra defects found and fixed: B1–B5, R1–R4, F1–F4 (see BUSINESS_LOGIC.md §5).
- [x] 38 feature tests (`tests/Feature/Sales`), dedicated test DB with a safety guard. Real-data dry run (sale →
      return → reports, rolled back) passes.
- UI for the per-line FEFO/FIFO toggle, batch/expiry chips, price-override prompts and the preference screens is
  built once, in the Inertia POS and settings (step 5).

## 🚧 Step 3: Inertia foundation and design system, IN PROGRESS (pre-approved by the product owner)
- [ ] Vite replaces Laravel Mix. React 19 + TypeScript + Inertia 2 + Ziggy.
- [ ] Design system ([DESIGN_SYSTEM.md](DESIGN_SYSTEM.md)): Bootstrap 5.3 tokens, dark/light/system theme saved per
      user, Inter font, gradient interactive sidebar, top bar with business switcher, KPI cards, section cards,
      ECharts wrappers.
- [ ] Core components: server-paginated `<DataTable>` (10/20 per page, search, sort, filters, column chooser),
      `<AsyncSelect>` (paginated search), form kit, split list→detail view, modal/drawer, toasts, empty states,
      skeleton loaders.
- [ ] Shared Inertia props: user, current business, **business type and wording dictionary**, active modules,
      permissions, theme.
- [ ] Generic paginated lookup API (`/lookup/{entity}?q=&page=`) with a server-side page size.
- [ ] Pilot: convert one simple module (Taxes) end to end as the reference pattern, then delete its Blade views.

## ⏸ Step 4: Platform core (built straight in Inertia)
- [ ] Module system: `app/Modules/*` with a service provider each, `module.json`, `EnsureModuleActive` middleware,
      module-aware sidebar, `ModuleActivated` seeding.
- [ ] Business type (Pharmacy / Inventory / Inventory B2B) and the create-business wizard, available any time from
      the switcher.
- [ ] Package plans (super admin): module prices, plans, custom plans, limits, coupons, "Free for everyone" launch
      switch (ON).
- [ ] Stripe via Laravel Cashier: the business is billable; checkout, prorated module add-ons, webhooks, grace
      period, "Bill of payment" invoices; **Stripe test mode running in dev** with the Stripe CLI.
- [ ] Billing page (customer) and subscriptions/revenue (super admin).
- [ ] Per-business SMTP/Mailgun settings (encrypted, test email) and editable email templates.
- [ ] **Branded email layout:** the business logo in the header, or a generated default (business initials on a
      brand-colour badge) when there is no logo; business colours, a clean responsive card layout, footer with
      address and contact. The same layout is used for every document email and system email, with dark-mode-safe
      colours.
- [ ] **Queue infrastructure:** `QUEUE_CONNECTION=database`, a worker run by `setup.sh` / supervisor, a
      failed-jobs screen for the super admin. Jobs added in this step: all outgoing mail, Stripe webhook
      processing, `ModuleActivated` seeding, generic-product bulk import (moves from the 15-minute cron to an
      immediate queued job, with the cron kept as a safety sweep). Later steps add their own jobs; see the list in
      MODULES_AND_PLANS.md.
- [ ] Per-business number series (prefix and next number per document type). The POS invoice number follows the
      business's **FBR / Local** preference (see [FBR_INTEGRATION.md](FBR_INTEGRATION.md) §2). The FBR side of it
      is completed in step 6.
- [ ] Audit log (who changed what), used by price overrides, approvals and settings.
- [ ] Helper-page framework ("How it works" interactive flows and ⓘ preference explainers).

## ⏸ Step 5: Convert the existing pharmacy app to Inertia
In order, deleting each Blade screen once its Inertia version passes the tests:
master data → products and setup wizard → purchases (all four entry forms become one reusable form) → **POS**
(per-line FEFO/FIFO toggle, batch and expiry, price-override rules, owner-only tax view) → invoices and returns →
reports → dashboard with the new charts.
- [ ] **Live POS receipt, the centrepiece of the POS.** A professional, interactive on-screen receipt that updates
      as the cart changes:
      - animated line add/remove;
      - click a line to edit qty, unit, discount or the FEFO/FIFO lot toggle, with batch and expiry chips;
      - running totals with tax split (included tax visible to owners only);
      - change-due calculator, customer details;
      - FBR number, QR code and logo when FBR is on;
      - keyboard and barcode-scanner friendly, dark/light aware.

      It is the same component as the printed receipt, so screen and paper always match.
- [ ] Fix all ~51 full-table dropdowns as each screen is converted.
- [ ] Helper pages for sale preferences, POS preferences and lot selection.
- [ ] Retire the legacy `Sale` module.

## ⏸ Step 6: FBR integration (POS Integration first)
Details: [FBR_INTEGRATION.md](FBR_INTEGRATION.md).
- [ ] Settings → **FBR Integration** page: step-by-step guided wizard (are you required → prepare → register on IRIS →
      enter details and terminals → sandbox test → go live → status panel), with plain-language ⓘ help.
- [ ] Business-wide **"With FBR / Without FBR"** preference, changeable any time (new invoices only, audit-logged,
      blocked until a sandbox test passes).
- [ ] Invoice numbering per the preference: the local per-business number always exists; the FBR fiscal number,
      QR code and logo are printed when FBR is on.
- [ ] Multiple FBR terminals (POS IDs) per business, each POS till linked to one.
- [ ] `SubmitInvoiceToFbr` queued job (retries with backoff), FBR-offline preference (Block / Save and retry),
      submissions log, daily reconciliation job, returns linked to the original FBR invoice.
- [ ] Replaces the stub `FbrInvoiceService`. **Endpoints and fields must be verified against the current official
      FBR / PRAL specification and the business's IRIS sandbox first.**
- Digital Invoicing (B2B) uses the same page and module, and is completed in step 8 (B2B Sales).

## 🔜 Step 7: Shared B2B foundation
- Full contacts (customer/supplier profile, addresses, terms, opening balance, tax exemption).
- Multi-warehouse stock, transfers and adjustments (Inventory Pro).
- Multi-currency with exchange rates.
- Document engine: PDF, email, clone, History tab, related-document tabs.
- **Two print formats for every document in every module:**
  - **Thermal**: small roll receipt, 58/80 mm, configurable margins;
  - **A4**: full-page branded document with logo, addresses, line table, tax summary, terms, signature and QR codes.

  The business picks a default per document type; users can choose either at print time.
  The POS gets thermal + A4 in step 5.

## 🔜 Step 8: B2B Sales
Quotations/estimates → sales orders → packages → shipments → invoices → payment receipts → credit notes, plus sales returns and
return receives. Customer emails throughout. **FBR Digital Invoicing** for B2B invoices and credit/debit notes
(PRAL token, HS codes, sale types, 28 sandbox scenarios).

**Quotations (built here, in the merger, as one shared engine):**
- B2B Estimate/Quotation: Draft → Sent → Accepted / Rejected / Expired → convert to a sales order or invoice.
- The same engine is also offered at the POS ("Save as quotation") for every business type:
  - no stock deducted; per-business number series (QT-); validity date; customer details;
  - thermal + A4 print and email;
  - "Convert to sale" re-checks prices and stock on the server and shows what changed since the quote.

## 🔜 Step 9: B2B Purchasing
Purchase orders → receives → bills → bill payments → vendor credits. Supplier emails throughout.

## 🔜 Step 10: Accounting (Books)
Chart of accounts: a **`DefaultChartOfAccountsSeeder`** modelled on SeeBiz Books' default accounts (category → type → sub-type, incl. A/R, A/P, Cash, Bank, Inventory Asset, COGS, Sales, Discounts, Tax payable, Opening Balance Equity), seeded per business on `ModuleActivated`. Auto-posting listeners for every module (with history backfill on activation), manual journals,
ledger, trial balance, P&L, balance sheet, AR/AP aging, tax summary.

## 🔜 Step 11: Expense
Expenses (single and bulk), claim reports, approvals (with self-approval policy), advances, trips, reimbursement,
accounting posting.

## 🔜 Step 12: Advanced reporting and analytics
Cross-module dashboards, period comparisons, scheduled email reports, exports.

## 🔜 Step 13: Hardening and launch
- Rotate the credentials that were committed. Stop tracking `.env` safely: each teammate copies their `.env` first,
  and `setup.sh` handles a missing one.
- Replace the `.env` super-admin bypass with a normal super-admin account. (2FA is not required for now, at the
  product owner's request.)
- Rewrite `NotifyStockAlert` per business.
- Per-business data export and backups. Rate limits and security review. Performance (indexes, caching).
- Production deployment guide.
- Decide on the `bec_*` tables in the database, which are not part of this project.
