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
> **Approved 2026-11-05 by the product owner (work queue, in this order):**
> 1. ✅ **4e Stripe billing** (done 2026-11-05): complete flow with **empty keys** (`STRIPE_KEY`, `STRIPE_SECRET`,
>    `STRIPE_WEBHOOK_SECRET`). Without keys every billing action shows a clear "not connected yet" state instead of
>    failing. All Stripe errors become friendly messages. The product owner adds test keys later and tests.
> 2. ✅ **5.0 Registration flow** (done 2026-11-05; 13 tests + a real-browser run of the whole journey on a log-mailer
>    server: landing → sign-up → email link → password → wizard → business → wrong password → forgot password →
>    wrong code → code → new password → sign-in; light/dark/mobile checked). Built: landing page `/`, sign-in, sign-up with business-type picture
>    blocks, check-email (resend cooldown), set password (strength meter), link expired/invalid page, forgot password
>    (code boxes, 5-try limit), reset; type preselected in the wizard; legacy unverified `register` retired; login
>    throttled + session regenerated; branded platform emails). Original ask: sign up → verify email → set password → business type as picture blocks
>    (Pharmacy / Inventory / Inventory B2B / …) → modules → review → into the new business. The login and password
>    screens move to the same design.
> 3. **Step 5**: every remaining screen gets the Taxes-page design (server-paginated list, stat strip, modals,
>    dark/light), screen by screen, in the order listed in step 5.
> **Last checkpoint (2026-11-05, safe stop requested by the product owner):** steps 0–4 done; 5.0 built; 163/163
> tests green; working DB migrated (backup `../db_backups/*_before_5.0_*`). Step 5 in progress: 5.1 master data, 5.2 packaging, 5.3 suppliers done (188/188 tests). Next: products, then purchases.
> Run the app with `bash start.sh` (http://127.0.0.1:8080/admin/login). Before continuing, run `php artisan test`
> (it must be green). Always back up the working DB (`../db_backups`) before migrating it.

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

## ✅ Step 3: Inertia foundation and design system, DONE 2026-11-04
- [x] Vite 8 replaces Laravel Mix (unused Mix sources and outputs removed). React 19 + TypeScript 5.9 + Inertia v3 +
      Ziggy v2. `setup.sh` installs Node 22 when needed and builds the frontend.
- [x] Design system ([DESIGN_SYSTEM.md](DESIGN_SYSTEM.md)): Bootstrap 5.3 compiled CSS + CSS-variable tokens
      (`resources/css/app.css`); light/dark/system theme saved per user (`users.theme`, applied before first paint);
      Inter font; gradient interactive sidebar (collapsible, mobile drawer, groups open on their own); top bar with
      **business switcher** (new: multi-business users could not switch before), theme toggle and user menu.
- [x] Shared components (`resources/js/Components`): `DataTable` (server-side search/sort/page/per-page, URL
      state, skeletons, empty states), `AsyncSelect` (paginated lookup), `PageHeader`, `KpiCard`, `SectionCard`,
      `Modal`, `ConfirmDialog`, `Toaster` (flash + events), `EmptyState`, `Chart` (own theme-aware ECharts 6
      wrapper, loaded only on pages with charts).
- [x] Shared Inertia props (`HandleInertiaRequests`): user, business, businesses, **wording per business type**
      (`config/business_types.php`), permission-filtered navigation (`config/navigation.php`), flash.
- [x] Paginated lookup API `GET /admin/lookup/{entity}?q=&page=` (20 per page, business-scoped).
- [x] Pilot: **Taxes** converted end to end (Blade views and the unused autocomplete endpoint deleted):
  - server-paginated list with KPIs, a rate chart, and a modal create/edit form;
  - archive with confirmation; old create/edit links still work.
  - Fixes: tax names were unique **across all businesses** (now per business, ignoring archived taxes);
    the rate is capped at 100%.
- [x] QA:
  - 11 platform feature tests (51 in total);
  - real-browser end-to-end click test: create → edit → search → archive → sort → classic-page navigation;
  - screenshots checked in light, dark, modal and mobile.

  Fixed during QA: a root-view asset bug; `btn-light` and chart axes not following dark mode; modal padding; a
  stale search being sent with a sort click.
- [x] `node_modules/` was committed (13,517 files, like `vendor/`): no longer tracked; `setup.sh` runs `npm ci`.
      Fresh-machine install re-verified in a clean Ubuntu 24.04 container: setup, Node 22, build, 51/51 tests,
      second run. That run also caught and fixed a `setup.sh` bug when it runs as root.
- [x] Design revision after the product owner's review (image 503):
  - sidebar scrollbar hidden until hover;
  - new sidebar header (brand + collapse control, aligned with the top bar);
  - a workspace card (current business, type, switch);
  - top bar with "Jump to a screen" search (Ctrl+K) and a segmented theme toggle;
  - KPI cards fill the row;
  - a compact clickable **stat strip** above lists, which also filters the list (Taxes: All / In use / Not used
    yet).
- Moved to step 7 (document engine): split list→detail view and drawer component.
- Noted for step 5: the old Blade login page logs `$(...).select2 is not a function` (it was already there before).

## 🚧 Step 4: Platform core (built straight in Inertia), IN PROGRESS (approved 2026-11-04)
Order of work (each sub-step is tested and committed before the next):
4a queue + audit log → 4b module registry, business type, module gating → 4c create-business wizard →
4d plans and module prices (super admin, "Free for everyone") → 4e Stripe billing (test mode) → 4f per-business
email + branded layout → 4g per-business numbering → 4h helper-page framework → 4i roles & permissions per module →
4j timestamps + soft deletes everywhere.
Existing pharmacy code moves into `app/Modules/*` while each screen is converted (step 5); new code starts there.
- [x] **4b** Module system:
  - registry `config/modules.php`: 11 modules, dependencies, routes owned, bundles per business type,
    "coming soon" flag;
  - `business_modules` table + `businesses.business_type` (existing businesses backfilled: Pharmacy with core,
    stock, POS and pharmacy pack);
  - `ModuleManager` (activate with dependencies, deactivate guarded by dependents) and the `ModuleActivated` event
    (seeds defaults);
  - the `EnsureModuleActive` middleware blocks a switched-off module's routes: JSON 403 for AJAX, an Inertia
    "Locked" page otherwise;
  - locked sidebar items link to the **Modules** store (owners switch modules on/off; free during launch);
  - wording follows the business type;
  - module changes are audited.

  New code goes into `app/Modules/*` as each feature is built; existing pharmacy code moves there while it is
  converted (step 5).
- [x] **4c** Create-business wizard (Inertia, own onboarding layout):
  - Basics (with a paginated currency search) → Business type → Modules (dependencies switched on and locked
    automatically) → Review;
  - used at first login (the old `business/setup` page redirects to it) and any time from the workspace menu
    ("Create new business");
  - the creator becomes owner and the app switches into the new business.
  - **Roles are now per business:**
    - someone who is a sales-person in one shop can own another and is not restricted there;
    - the sales-person lockdown, `is_owner` and the Modules menu use the role in the *current* business.
  - `ClassicPageRedirects` middleware: when an Inertia request is redirected to a screen that isn't Inertia yet,
    the browser does a full page load (409), with the flash message mirrored for classic pages. This fixed
    log out, switch business and the wizard landing on classic pages (found in the browser test). Converted
    screens are listed in `config/inertia.php` → `screens`.
- [ ] Noted: `GlobalAllCurrencySeeder` prints a debug line for every currency ("currency not found"). Harmless
      noise; clean up in step 5.
- [x] **4d** Package plans (super admin, **Plans & pricing** page):
  - module prices (monthly/yearly, currency);
  - plans: modules with dependencies added automatically, prices, limits (users / businesses / products /
    invoices per month / warehouses), trial days, visibility, order, archive;
  - the **"Free for everyone"** launch switch (ON) with an optional grace date.
  - `PricingService` quotes any set of modules (custom plan) with dependencies.
  - `BillingCatalogSeeder` provides **placeholder** prices and 4 starter plans. It is idempotent and never
    overwrites the super admin's edits; `setup.sh` runs it on every install.
  - The Modules page shows module prices; while launch pricing is off, switching modules on needs billing (4e).
  - Coupons move to 4e (they live in Stripe).
- [x] **4e Stripe billing (2026-11-05)**, built with empty keys as the product owner asked; details in
      [MODULES_AND_PLANS.md](MODULES_AND_PLANS.md) → "Billing: Stripe":
  - own layer over `stripe/stripe-php` (the business is the customer);
  - checkout, free plans without Stripe, prorated changes, cancel/resume, customer portal;
  - signed + idempotent webhooks through a queued job; payment history;
  - entitlements and a daily sweep after launch pricing;
  - friendly handling of every Stripe error and of missing keys;
  - Settings → Billing and Super admin → Subscriptions;
  - 21 tests.
  - **To test with real Stripe (product owner):** put test keys in `.env`, `php artisan config:clear`, run
    `stripe listen --forward-to http://127.0.0.1:8080/api/stripe/webhook` and copy its `whsec_…` into
    `STRIPE_WEBHOOK_SECRET`, then on Super admin → Plans & pricing turn "Free for everyone" off (or simply pay for
    a plan while it is on).
  - Later: plan limit enforcement (users / products / invoices per month), manual overrides (comp a module, extend
    a trial).
- [x] **4f** Per-business email:
  - **Email & branding** page (owner only): platform mailer / own SMTP / Mailgun, provider presets (Gmail,
    Microsoft 365, Zoho, Amazon SES), sender, reply-to;
  - passwords and keys encrypted, never sent back to the browser or logged; blank means keep the saved one;
  - "Send test email" with the result recorded and secrets stripped from errors;
  - live preview; logo upload + brand colour (stored in `public/storage/business-logos` via the new `uploads`
    disk, because `public/storage` is a real folder in this app);
  - `BusinessMailer` sends through the business's own account, falling back to the platform mailer under the
    business's name;
  - `SendBusinessMail` queued job (3 tries with backoff); `BusinessMessage` branded mailable.
  - Editable per-document templates arrive with the document engine (step 7).
- [x] **Branded email layout:** the business logo in the header, or a generated default (business initials on a
      brand-colour badge) when there is no logo; business colours, a clean responsive card layout, footer with
      address and contact. The same layout is used for every document email and system email, with dark-mode-safe
      colours.
- [ ] **Queue infrastructure:** `QUEUE_CONNECTION=database`, a worker run by `setup.sh` / supervisor, a
      failed-jobs screen for the super admin. Jobs added in this step: all outgoing mail, Stripe webhook
      processing, `ModuleActivated` seeding, generic-product bulk import (moves from the 15-minute cron to an
      immediate queued job, with the cron kept as a safety sweep). Later steps add their own jobs; see the list in
      MODULES_AND_PLANS.md.
- [x] **4g** Per-business number series (`number_series`: prefix, next number, digits per document type):
  - `NumberSeriesService` hands out numbers under a row lock and skips numbers that already exist (legacy
    formats); the POS preview uses the same rule;
  - `invoices.invoice_no` is now unique **per business**;
  - invoices and returns use the series when FBR is off;
  - **Settings → Numbering** page (owner) refuses a next number at or below the highest one already used;
  - existing businesses continue after their own documents; new businesses get default series;
  - the POS invoice number follows the business's **FBR / Local** preference (see
    [FBR_INTEGRATION.md](FBR_INTEGRATION.md) §2); the FBR side is completed in step 6.
- [x] **4a** Audit log (`audit_logs`, `AuditLogger`, `Auditable` trait on Tax / Preference / Business; secrets never logged; POS price overrides logged). Database queue (`jobs`, `job_batches`, `QUEUE_CONNECTION=database`) + `start.sh` (web server + queue worker + scheduler in one command).
- [x] **4h** Helper-page framework:
  - **Help Center** (search, topic cards; only topics of the business's active modules);
  - topic pages with an **interactive step flow** (click or ← → keys, "Open this screen" links, which are hidden
    for switched-off modules), worked examples and FAQs;
  - a "How it works" button in `PageHeader` (`help="topic"`), used by Taxes and Modules;
  - content lives in `config/help.php`, written from BUSINESS_LOGIC.md; the first topics are Getting started,
    Sale price preferences, FEFO/FIFO, Selling at the POS, Taxes;
  - a guard test fails if a help link points to a route that doesn't exist;
  - sales-persons can read help.
  - Each screen converted in step 5 adds its own topic and the ⓘ explainers next to its preferences.
- [x] **4i Roles & permissions per module** (requested 2026-11-04, done 2026-11-05; model: SeeBiz Books / Expense role screens).
  - **Design decision (2026-11-05):** our own per-business tables (`business_roles`, `business_role_permissions`,
    `business_user.business_role_id`), checked through Laravel's `Gate` (`$user->can('pos.sell')`). This is
    instead of switching Spatie to "teams" mode, which would rewrite every existing role assignment. Spatie stays
    for super-admin and the legacy Blade screens until step 5 replaces them.
  - Built in 4 checkpoints: 4i-1 catalogue + tables + backfill + Gate → 4i-2 role editor → 4i-3 team page
    (members, roles) → 4i-4 enforcement (routes, POS limits).
  - [x] **4i-1 (2026-11-05):**
    - permissions declared per module in `config/modules.php` (core 6, stock 5, pos 7, pharmacy 3);
    - `business_roles` (soft deletes, `system_key`, `limits`), `business_role_permissions`, and on
      `business_user`: `business_role_id` + `status`;
    - backfill: every business has Owner and Sales person, members linked by their current role;
    - `PermissionService` + `Gate::before`: Owner gets everything of the active modules; a permission only
      counts while its module is on; suspended members are denied; legacy Spatie permissions still work.
  - [x] **4i-2 (2026-11-05):** **Settings → Roles**:
    - roles list (server-paginated, member counts);
    - editor with a **permission matrix** per module (select all / clear all, search, plain-language descriptions,
      switched-off modules greyed with "no effect");
    - role limit: max discount %;
    - Owner is read-only; Sales person keeps its name but its permissions can change;
    - custom roles are archived only when nobody holds them;
    - needs `core.users.manage`.
  - [x] **4i-3 (2026-11-05):** **Settings → Team**:
    - members list (paginated, search, stat strip);
    - add a person by email with a role: existing users are added, new users get an account plus a branded,
      queued invitation (they set their password via "Forgot password"; no password is emailed);
    - change role, suspend / reactivate, remove from the business;
    - the business always keeps an active owner; nobody can change or remove themselves;
    - the legacy pivot role (owner / sales-person / staff) is kept in step; changes are audited.
    - ⚠️ Until 4i-4, custom roles decide what the Gate allows, but the legacy Blade routes are not yet checked
      against them: don't hand out custom roles in production before 4i-4 (done, see below).
  - [x] **4i-4 (2026-11-05):** enforcement.
    - `config/route_permissions.php` maps every route to a permission (or `@super-admin`, or open to members).
      The `EnsureRoutePermission` middleware shows a friendly "No access" page, or a JSON 403 for AJAX.
    - A guard test fails if any authenticated admin route is unmapped.
    - The sidebar derives visibility from the same map; screens of switched-off modules still show locked.
    - POS: discounts need `pos.discount` and price changes need `pos.price_change`; the role's max discount %
      tightens the business and product caps (the strictest wins); owners are unaffected.
    - Email, numbering and modules settings use `core.settings.manage` (owners can delegate).
    - **Security fix:** the legacy Roles / Permissions screens edit the *global* Spatie roles shared by all
      businesses, so they are now super-admin only.
  - **Permission catalogue per module:** each module declares its permissions in `config/modules.php`, for
    example:
    - POS: sell, give discount, change price, return, reprint;
    - Stock: view / create / edit / delete purchases, adjust stock, see cost prices;
    - Reports: view each report, export;
    - Settings: preferences, FBR, email, billing.

    The permissions of a switched-off module are hidden and have no effect.
  - **Roles per business** (Spatie "teams" with team = business):
    - built-in roles Owner (everything, cannot be edited) and Sales person (POS only);
    - the owner can create custom roles (Manager, Cashier, Store keeper, Accountant, …) and copy or edit them;
    - users can hold different roles in different businesses.
  - **Role editor ("design control"):**
    - a matrix of modules × actions (view / create / edit / delete / approve / export) with "select all" per
      module, search, and a plain-language description of each permission;
    - limits that are part of a role, e.g. maximum discount %, can change price, can see cost and profit;
    - a preview of what the role's sidebar will look like.
  - **Enforcement everywhere:**
    - route middleware + policies;
    - the sidebar and buttons hide what a role can't do;
    - the hard-coded sales-person lockdown is replaced by the built-in Sales person role;
    - every change is audited.
  - Invite users by email per business with a role (accept link); remove or suspend users per business.
- [x] **4j Timestamps + soft deletes everywhere** (requested 2026-11-04, done 2026-11-05). A guard test (`SoftDeletesTest`) fails if any new table lacks timestamps or soft deletes without a documented exemption.
  - **Rule from now on:** every business table has `created_at` / `updated_at` and `deleted_at`, and its model uses
    `SoftDeletes`. Deleting means archiving: data can be restored and history and reports stay correct.
  - **Add soft deletes via migration (scan 2026-11-04):** businesses, currencies, pharmacies, business_modules
    (re-activation must restore the archived row), business_mail_settings, module_prices, invoice_item_lots,
    invoice_item_returns, invoice_item_taxes, invoice_histories, return_histories,
    generic_product_import_batches.
  - **Exempt by design** (documented in each migration):
    - framework tables: migrations, jobs, job_batches, failed_jobs, the permission pivots;
    - short-lived records: signup_requests, pos_cart_reservations;
    - platform_settings (key/value);
    - audit_logs (append-only: never deleted, never edited).
  - Unique indexes on soft-deleted tables must ignore archived rows (validation `whereNull('deleted_at')`, as
    Taxes does).
  - Add "Archived" filters and "Restore" actions in the Inertia lists where it makes sense.

## 🚧 Step 5: Convert the existing pharmacy app to Inertia (approved 2026-11-05)
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
- [ ] **Business dashboard (Inertia), the owner's home screen.** Everything is computed on the server for the
      current business only, with deferred props and a cache per business.
  - **Filters:** date range (Today / 7 / 30 / 90 days / This month / custom), compared with the previous period;
    category / product type; cashier.
  - **KPIs with trend arrows:** sales, invoices, average basket, gross profit (actual lot cost), returns, items
    sold, stock value.
  - **Interactive charts:** sales over time (area, click a day to open its invoices); sales by hour × weekday
    heatmap; top products and categories; profit margin; stock value by expiry bucket.
  - **Action panels:** low stock, expiring soon (FEFO), dead stock, latest invoices and returns.
  - **Suggestions ("what to do next"), each with an action button:**
    - reorder suggestions (below minimum quantity, with a suggested quantity from average daily sales);
    - short-dated stock to discount;
    - products selling below cost;
    - price overrides by cashier;
    - products without a minimum-quantity rule;
    - unused taxes / master data.
  - Respects permissions: sales-persons go straight to the POS.
- [x] **5.1 Master data (2026-11-06):** Manufacturers/Brands, Dosage forms/Item types, Strengths/Variants and
      Formulas share **one** Inertia screen (`MasterData/MasterDataController` + `Pages/MasterData/Index.tsx`):
  - server-paginated list with search and sort, plus usage per entry ("Used by medicines");
  - stat strip (All / In use / Not used / Archived / Added in 30 days) that filters the list;
  - modal add/edit; archive is blocked while products use the entry; restore from the Archived filter;
  - the old create/edit links open the modal; the select2 autocomplete for the classic product form is kept (shared
    code); Blade views deleted; help topic "Master data".
  - Fixes:
    - names were unique across **all** businesses (now per business, ignoring archived rows);
    - archiving an entry still used by products silently broke those products (now blocked);
    - products keep **several** strengths/formulas as a comma list (`products.strength_id` / `farmula_id`, 841
      products with several formulas). Usage is matched inside the list; a first version missed 81 formulas in use;

    - `Supplier` and `User` models lacked `SoftDeletes`, so deleting removed rows for good (fixed; an archived
      account's email is reserved and sign-up / team invites explain this instead of crashing);
    - new guard test: every model whose table has `deleted_at` must use `SoftDeletes`.
  - **Pending, tracked by that guard test:** the 8 generic-catalogue models (`Generic*`) also lack `SoftDeletes`. 30
    rows deleted on purpose in March–April 2026 currently show again in the generic catalogue. This is fixed with
    archive-aware lookups when the generic catalogue screens are converted (one live generic product uses a deleted
    strength).
- [x] **5.2 Packaging (2026-11-06):** the units list (Box, Strip, Tablet…) uses the same shared screen. A unit counts
      as used when a product's packaging chain, unit/price chain or minimum-quantity rule uses it, so units in use
      can't be archived. Blade views deleted.
- [x] **5.3 Suppliers (2026-11-06):**
  - list with purchase count, total purchased and last purchase (sortable), search across name, phone, email and
    company;
  - stat strip: all / with purchases / none yet / archived / purchased in the last 30 days;
  - **profile page:** KPIs, contact card (tap to call or email), 12-month purchases chart, searchable purchase
    history with batch/expiry chips;
  - modal add/edit with validation; archive keeps the name on past purchases (`Purchase::supplier()` includes
    archived suppliers); restore.
  - Fixes:
    - the name was limited to 20 characters and unique across all businesses;
    - `suppliers.show` was routed but had no method, so it crashed;
    - edit/delete buttons depended on old global permissions that owners might not have.
- [ ] **5.4 Products** in four checkpoints:
  - **5.4a** one product list replacing All / Drafts / Out of stock / Expired, using the real lot stock (the old
    "Out of stock" checked the purchased quantity, which never changes, and "Expired" only showed batches
    expiring exactly today), plus a product page (lots, packaging, prices);
  - **5.4b** add/edit form with paged pickers;
  - **5.4c** setup wizard (packaging chain + unit prices);
  - **5.4d** sale-price preferences (product + business-wide) with explainers.

  POS lookups (`products.search`, `category-price`, `pos.checkStock`) stay until the POS moves.
- [ ] **Every list screen** gets the compact **stat strip** above the table, and its chips filter the list (same
      pattern as Taxes).
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
