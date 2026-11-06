# CLAUDE.md

This file gives Claude Code the context needed to work in this repository.

## Start here: product docs and working rules

Product direction, business rules and the step-by-step plan live in `docs/` (index: `docs/README.md`).
Rules that apply to every session:
- **Ask the product owner before starting any roadmap step** (`docs/ROADMAP.md`). The docs must describe the step
  first. Unapproved steps stay listed and can be resumed any time.
- **Never build something twice:** no new Blade UI for screens scheduled to move to Inertia. Until a screen moves,
  only backend logic and tests change for it.
- **Pagination everywhere:** lists, dropdowns and lookups page on the server (10/20 per page). Never load a full
  table into the UI.
- **Server is the authority** for price, discount, tax, totals and stock. Browser values are requests only.
- Modules communicate through events and shared Core services (`docs/MODULES_AND_PLANS.md`).
- **Every database change goes through a migration**: schema changes *and* data fixes or backfills (e.g. creating
  missing per-business preference rows). Never hand-edit a database, never change an already-applied migration's
  effect on existing databases, and never change data from tinker or SQL for a permanent fix.
- **Every new business table has timestamps and soft deletes** (`$table->timestamps(); $table->softDeletes();` +
  `SoftDeletes` on the model). Exempt only framework tables, short-lived tokens/carts and append-only logs; say why
  in the migration. Uniqueness checks must ignore archived rows.
- **Default and reference data comes from seeders** that are idempotent (`firstOrCreate`/`updateOrCreate`) and can
  be run per business (e.g. `DefaultChartOfAccountsSeeder` modelled on SeeBiz Books' 67 default accounts, seeded
  when the Accounting module is activated for a business).
- After every step, update `docs/*.md`, `INSTALL_NOTES.txt` (commands only) and this file.

## What this is

A multi-tenant pharmacy management / POS system built on **Laravel 13** (PHP ^8.3; upgraded from Laravel 8 on branch `upgrade/php83-laravel13`). Originally a
single-tenant CodeAstro template ("pharmacy"); this branch (`multi_tenancy_phase2`) is converting it
to multi-tenant SaaS, where each **Business** is a tenant and one physical login (`User`) can belong
to multiple businesses.

Stack: Blade views + jQuery/Bootstrap admin theme, `yajra/laravel-datatables-oracle` (v13) for list screens,
`spatie/laravel-permission` for roles/permissions, `spatie/laravel-backup` for backups,
`qcod/laravel-app-settings` for app-wide settings, `simplesoftwareio/simple-qrcode`,
`icehouse-ventures/laravel-chartjs` for dashboard charts (maintained fork of `fx3costa/laravelchartjs`, same `app()->chartjs` API; `config/chartjs.php` pins Chart.js v2 with `custom` delivery because the dashboard loads its own Chart.js v2 bundle). No SPA/API-first frontend — this is a classic
server-rendered admin panel under the `admin/*` URL prefix.

## Running it

Non-developers / fresh Ubuntu-Debian machines: `bash setup.sh` (installs PHP 8.3 + extensions, Composer,
MySQL; creates `.env` + DB user if needed; backs up an existing DB to `../db_backups/`; composer install,
migrate, seeds only an empty DB; clears caches). Commands for teammates live at the top of `INSTALL_NOTES.txt`.

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed           # RolesAndPermissionsSeeder, UserSeeder, SalePricePreferenceSeeder, GlobalAllCurrencySeeder
php artisan serve --port=8080
```

Compiled assets (`public/js/app.js`, `public/css/app.css`) are committed; `npm run dev` (laravel-mix) is
only needed when changing `resources/js` / `resources/sass`.

App structure is the Laravel 11+ streamlined layout: there is **no** `app/Http/Kernel.php`,
`app/Console/Kernel.php`, `app/Exceptions/Handler.php` or `RouteServiceProvider`. Middleware aliases,
redirects, trusted proxies and exception config live in `bootstrap/app.php`; providers in
`bootstrap/providers.php`. **Event auto-discovery is deliberately disabled** in `bootstrap/app.php`
(`withEvents(discover: false)`) — `App\Listeners\NotifyStockAlert` was never registered under Laravel 8,
and auto-discovery would start firing it, notifying every user across all businesses.

Scheduler: `generic-products:process-bulk-imports` and `pos:cleanup-stale-reservations` (every 15 minutes) and
`billing:enforce-entitlements` (daily), defined in `routes/console.php`. Needs `php artisan schedule:run` in system cron.

After upgrading an existing checkout, run `php artisan optimize:clear && php artisan permission:cache-reset`
— a Spatie permission cache written by v5 breaks every page under v8 (`Call to a member function first() on array`).

Carbon 3: `diffIn*()` returns floats (and is signed by default) — cast with `(int)` when displaying.

Default seeded admin (from template, may be stale): `admin@mail.com` / `codeastro.com`.

## Tenancy model — how `business_id` scoping works

- `Business` (`app/Models/Business.php`) is the tenant. `User` ↔ `Business` is **many-to-many** via
  a pivot table with a `role` column (`owner` / `sales-person`) — see `User::businesses()`.
- The **active tenant for the current request** is `session('business_id')`, set by
  `EnsureUserHasBusiness` middleware (`app/Http/Middleware/EnsureUserHasBusiness.php`) on every
  authenticated request: if the user has no business yet, they're redirected to
  `business.setup`; otherwise the session is pinned to a business they actually belong to.
- Almost every tenant-owned model (`Product`, `Purchase`, `Invoice`, `Pharmacy`, `Category`,
  `Company`, `Supplier`, taxes, etc.) uses the `App\Traits\BelongsToBusiness` trait, which:
  - stamps `business_id = session('impersonate_business_id') ?? session('business_id')` on create,
  - applies a global `App\Models\Scopes\BusinessScope` that filters every query to that same id.
- **Super-admins bypass scoping**: `BusinessScope` skips the filter entirely for users with the
  `super-admin` role who are *not* currently impersonating, giving them a native cross-tenant view.
- **Impersonation**: `SuperAdminBusinessController::impersonate($id)` sets
  `session('impersonate_business_id')`; `EnsureUserHasBusiness` re-syncs `business_id` from it on
  every request until `stopImpersonating()` clears both keys.
- Roles/permissions themselves (Spatie) are **global**, not per-tenant — only the
  user↔business pivot `role` and route middleware decide what a given login can do inside a
  given business.

## Roles

Seeded by `database/seeders/RolesAndPermissionsSeeder.php`:
- `super-admin` — all permissions, cross-tenant, can impersonate any business.
- `Business Owner` — assigned to whoever completes signup; full access within their business/es.
- `sales-person` — assigned by an owner from inside a business; locked by
  `RestrictSalesPersonAccess` middleware to POS + profile/password routes only (redirected to
  `pos.index` for anything else).

## The end-to-end user journey (register → sell → report → logout)

All routes below live under `routes/web.php` and are prefixed `/admin`.

### 1. Registration / signup (superseded by Roadmap 5.0, see "Sign-up & sign-in" above; history below)
Two parallel paths existed (legacy + current):
- **Current (email-verified) path** — `App\Http\Controllers\Admin\Auth\SignupController`:
  1. `GET/POST signup` → `processSignup()` validates name/email, creates a `SignupRequest` token
     (24h expiry), emails a verification link (`App\Mail\VerifyEmail`).
  2. `GET verify-email?token=...` → `verifyEmail()` validates the token, shows the
     "complete registration" password form.
  3. `POST complete-registration` → `completeRegistration()` creates the `User`, marks
     `email_verified_at`, assigns Spatie role `Business Owner`, deletes the `SignupRequest`,
     logs them in (`auth()->login()` + session regenerate), redirects to `dashboard`.
- **Legacy path** — `RegisterController::store()` (`GET/POST register`): creates the user directly
  with no email verification, assigns `Business Owner`, logs in immediately. Both paths converge on
  the same next gate.

### 2. First login → forced business setup
`EnsureUserHasBusiness` sees the new user has zero businesses and redirects everything to
`GET business/setup`. `BusinessController::storeSetup()` requires only a business `name`
(address/phone/note optional); on submit it creates the `Business`, attaches the current user with
pivot `role = owner`, sets `session('business_id')`, and — notably — redirects to
**`generic_products.index`**, not the dashboard, nudging the new owner to import from the shared
generic catalog rather than the dashboard.

### 3. Login (returning users) / logout
- `LoginController::login()` (`POST login`): normal `auth()->attempt()`. Also contains a
  **super-admin env-credential bypass** — if the submitted email/password match
  `SUPERADMINEMAIL`/`SUPERADMINPASSWORD` in `.env`, it `firstOrCreate`s that user, force-assigns
  `super-admin`, and logs them in without going through the users table password check. Be aware of
  this when reasoning about auth security in this codebase.
- `LogoutController::index()` (`POST logout`): plain `auth()->logout()` + redirect to `login`.
  Explicitly whitelisted in `EnsureUserHasBusiness` so logout always works regardless of business
  state.
- Password reset: `ForgotPasswordController`/`ResetPasswordController` implement an email-a-code,
  verify-code, then reset flow (`forgot-password`, `forgot-password/verify`, `reset-password`) —
  separate from Laravel's default token-link reset.

### 4. Setup / master data (owner, before anything is sellable)
Before a product can be sold, stock must exist, and before stock can exist, a product needs
non-draft parameters. Typical order:
1. Master data CRUD (all simple, tenant-scoped, DataTable-backed controllers under
   `app/Http/Controllers/Admin/`): `CategoryController` (packaging unit hierarchy, e.g.
   Box→Strip→Tablet), `CompanyController`, `ProductTypeController`, `StrengthController`,
   `FarmulaController`, `TaxController`, `SupplierController`.
2. Add a `Product` (`ProductCatalogController::store()` → `ProductFormService`) — lands as a **draft** (`is_draft = true`).
3. Run the product's **Setup Wizard** (`products/{product}/setup-wizard`, Inertia; rules in
   `ProductSetupService`): the packaging chain largest unit first plus optional static prices per unit,
   stored as `ProductParameter` + `ProductCategory` rows — this flips `is_draft` to `false`. The chain is
   locked once the product has purchases, stock or sales (prices stay editable).
4. Optionally set a `sale_price_preference` per product (`static` / `stock-wise-price` /
   `previous-inventory-price`) or rely on the business-wide global preference.
5. **Record a Purchase** (`PurchaseService::create()` via `PurchaseController::store()`) — the *only* path that
   populates `ProductStock.current_stock`, `StockPrices`, and `BaseStockSalePrice` (FIFO cost
   lots). A product with zero purchases has zero stock and cannot be sold regardless of draft
   status.

### 4a. Purchase form (one, Inertia — Roadmap 5.5c)
There is **one** purchase form, `resources/js/Pages/Purchases/Form.tsx`: a supplier bill header + one or more lines.
It serves new purchases, bulk entry, edit (one line, `PUT` with `line`) and "Add stock" from a product
(`purchases.create?product_id=`; the old `products.quick-stock` and `purchases.create-multiple` URLs redirect there).
The browser only sends what was bought; `PurchaseService` computes every amount and checks every reference. The old
four Blade forms, the quick-stock modal and `_purchase-section-styles` are gone. `PurchaseController::store()` still
translates the old flat field names into a line (the test helper `SalesScenario::purchase()` uses them).

**Shortcut — Generic Products**: `GenericProductController` exposes a shared, super-admin-curated
masterlist (`GenericProduct` + its own Generic* master-data tables). A business can `import()` one
directly (clones company/type/strength/category/parameters straight into a ready, non-draft
`Product`), or queue many via `bulkImport()`/`importAll()`, which insert rows into
`generic_product_import_batches` processed by the 15-minute cron
(`generic-products:process-bulk-imports` → `GenericProductController::processPendingBulkImportBatches()`).
A Purchase is still required afterward for sellable stock.

### 5. Selling — the POS screen
`POSController` + `ProductController` (no dedicated service layer; logic lives in controllers):
1. Cashier searches/adds a product on `GET pos` (`POSController::index`); each add calls
   `products.pos.checkStock` → `ProductController::handlePOSQuantityChange()`, which nets the
   current in-browser cart against real available stock (`BaseStockSalePrice`, price-group aware)
   before allowing the quantity.
2. Cart lives in `session('pos_cart')` — a per-login-session PHP session variable, not a DB record,
   so concurrent cashiers on the same business have independent carts. The right-hand panel is a
   live, on-screen receipt preview (`resources/views/admin/pos/index.blade.php`) that mirrors the
   printed receipt's markup/CSS 1:1 via a shared partial (see "Receipts" below), including a
   cosmetic preview of the next invoice number (`Invoice::previewNextLocalInvoiceNo()`, refreshed
   via `GET pos/next-invoice-number` after every save — not reserved, so it can drift by one if two
   tills save at the same moment).
3. The only checkout action is **Save & Print** (`POSController::saveInvoice()`) — there is no
   separate "print without saving" button; a printed receipt always corresponds to a saved
   `Invoice`. It re-validates stock, creates the `Invoice` + `InvoiceItem` rows (one per cart line),
   deducts stock FIFO across `BaseStockSalePrice` lots via `deductStockByPriceGroup()`, decrements
   `ProductStock.current_stock`, writes an `InvoiceHistory` "Created" entry, assigns the real
   invoice number (see "Invoice/return numbering" below), and prints via
   `InvoiceController::printInvoice()` / `POSController::printReceipt()` using the saved
   `invoice_id`.

Note: `Route::resource('pos', POSController::class)` in `routes/web.php` declares `create`/`store`/
`update`/`destroy` but `POSController` doesn't implement them — those verbs are dead routes.
`SaleController`/`Sale` model is a separate, simpler legacy single-product "record a sale" flow
predating the POS/Invoice system; kept mainly for its sales-report view.

### 5a. Receipts (shared styling) and invoice/return numbering
All three receipt surfaces — the POS live preview, the sale receipt print view
(`admin.pos.receipt-print`, also used for reprints via `InvoiceController::printInvoice`), and the
return receipt print view (`admin.invoices.return-receipt-print`) — share one CSS partial,
`resources/views/admin/partials/_receipt-styles.blade.php` (`.pmx-receipt` / `.pmx-header` /
`.pmx-meta` / `.pmx-items` / `.pmx-totals` / `.pmx-footer` classes). Edit that partial, not the
individual pages, to keep all three in sync — in particular the container's left/right padding is
the single place thermal-printer margins are controlled; don't add one-off `padding-right` hacks to
table cells again.

Invoice numbers are short and locally unique by construction: `Invoice::assignLocalInvoiceNo()`
inserts the row with a random placeholder, then renames it to `INV-` + the row's own zero-padded
auto-increment id (globally unique, no locking needed — `invoices.invoice_no` has a DB unique
constraint). Return numbers (`InvoiceController::generateReturnNo()`) use a similar short format,
`RET-000123`, reserved via a `lockForUpdate()` read against `return_histories.max(id)` inside the
same DB transaction the return already runs in.

### 5b. FBR invoicing (per-business preference, partially stubbed)
Business Settings (`admin.business.settings`) has an **Invoice Numbering** section where an owner
picks `invoice_source`: `local` (default, the short numbers described above) or `fbr`. Selecting
`fbr` reveals a form for the business's FBR link: `fbr_business_name`, `fbr_ntn`, `fbr_strn`,
`fbr_pos_registration_no`, `fbr_environment` (sandbox/production), and `fbr_api_token` (encrypted
at rest via `Crypt::encryptString()` in `Business::setFbrApiTokenAttribute()`, never mass-assignable
or serialized — see `$hidden` on the model).

When `invoice_source = fbr`, `POSController::saveInvoice()` and `InvoiceController::generateReturnNo()`
call `App\Services\FbrInvoiceService` instead of generating a local number, and **block the sale/
return (rolls back the transaction) if that call fails** — chosen deliberately so a business that
opted into FBR compliance can't silently ship a non-compliant local number instead.

**`FbrInvoiceService` is NOT verified against a real FBR endpoint.** Its docblock spells out exactly
what's unconfirmed: which FBR scheme applies (Sales Tax POS Integration under SRO 1006(I)/2021 vs.
the newer Digital Invoicing regime), the real base URL/payload/response field names, and what
should happen on an FBR outage at checkout. Do not treat its HTTP calls as correct without
confirming against the business's actual FBR sandbox/API docs first. Until that's done, businesses
should stay on `invoice_source = local` (the default) — nothing forces `fbr`.

### 5c. Split sale tax at the POS: additive Sale Tax vs. disclosure-only GST
Every `Purchase` batch can carry two independent tax-like signals (see "Purchase forms" below):
`extra_paid_percent` (the markup between Unit Cost Price and Paid Unit Cost Price, labeled "GST" on
receipts) and zero-or-more named `SaleTax` rows (the "Sale Information & Sale Tax" section — real
`Tax` records with a rate). Neither of these was ever surfaced past the purchase itself before this
feature — `SaleTax` fed only into purchase-time price rollups, and nothing read it at sale time.
**They behave differently once a sale happens — this distinction is load-bearing, don't conflate them:**
- **GST** (`extra_paid_percent`) is **disclosure-only**. `unit_sale_price` already is the final price
  the customer pays; GST just discloses how much of that price is tax. It never changes any total.
- **Named Sale Tax** (`SaleTax`/`Tax`) is a **real, additive charge**. It is added on top of the
  discounted subtotal to arrive at `grand_total` — i.e. the customer pays *more* than
  `unit_sale_price × qty` once a Sale Tax is configured on the purchase batch(es) being sold from.

At `POSController::saveInvoice()`, `computeSaleTaxBreakdown($productId, $baseQty, $rowTotal)` runs a
**read-only** FIFO simulation over that product's `BaseStockSalePrice` rows (same id-order the real
`deductStockByPriceGroup()` uses, but it does not mutate stock) to figure out which `Purchase`
batch(es) the sold quantity is coming from, then splits `row_total` proportionally into named
buckets (`GST` plus one bucket per distinct `Tax` name), each computed as
`taxable_amount × rate/100`. The additive (non-GST) buckets are summed into
`$invoiceAdditiveTaxTotal` and folded into `grand_total` **server-side, authoritatively** —
`saveInvoice()` does NOT trust the client-posted `grand_total`/`change_return` for this reason; it
recomputes both after the item loop. This matters because the live POS cart/change-received math
must match: see `POSController::cartTaxBreakdown()` (route `pos.cart-tax-breakdown`), a read-only
preview of the same computation called (debounced) by the POS page's `recalcTotals()` →
`scheduleCartTaxRefresh()`/`applyGrandTotalWithTax()` JS, so Grand Total and Change Return are
correct *before* the cashier hits Save & Print, not just after.

Persistence: `InvoiceItem.tax_amount` = **GST only** (disclosure, per item — this also finally makes
the pre-existing `is_tax_included`/`tax_amount` check in `InvoiceController::show()` a real,
load-bearing branch instead of the dead no-op it was before this column existed).
`Invoice.tax_amount` = **additive Sale Tax total only** (the amount that actually inflated
`grand_total` — `recalculateInvoiceTotals()`: `grand_total = total - invoice_discount_amount + tax_amount`).
Every bucket (both kinds) is also persisted per-item in `InvoiceItemTax` (table `invoice_item_taxes`:
`tax_id` nullable — null = GST bucket, else a real `Tax`), which `Invoice::consolidatedTaxBreakdown()`
merges across items for display; callers/views filter by `tax_id !== null` (Sales Tax, shown as a
"+amount" line *before* Grand Total) vs `tax_id === null` (GST, shown *after* Grand Total as
disclosure, never added). Consumers of this split: the live POS receipt, the sale receipt at
checkout and on reprint (`POSController::printReceipt`, `InvoiceController::printInvoice`), the
Invoice detail page (`InvoiceController::show` → `admin.invoices.show`), and return receipts.

Returns: `InvoiceController::processItemReturn()` proportionally claws back each `InvoiceItemTax`
row by the returned fraction — GST clawback reduces `InvoiceItem.tax_amount`; additive-tax clawback
reduces `Invoice.tax_amount` **and** is added into the refund amount (`returnVal`/`totalReturn` in
`returnProduct()`/`returnInvoice()`), since that money was actually collected from the customer and
must be given back. `recalculateInvoiceTotals()` re-derives `grand_total` from the (now reduced)
`tax_amount` afterward, so a reprint of the *original* sale invoice after a partial return shows the
correct remaining additive tax and grand total. Known gap: `ReturnController::printReturn()`
(reprinting an *old* return by `return_no`) has no historical per-event tax snapshot — it shows the
*current* cumulative remaining split, not what applied at that specific past return; would need its
own `invoice_item_return_taxes`-style table to fix properly. Not built.

### 5d. Currency (global masterlist + per-business custom currencies)
`App\Models\Currency\GlobalCurrency` (table `currencies`) is **not** business-scoped via
`BelongsToBusiness` — it has its own bespoke visibility rule instead, because a currency can be
either global (`business_id = null`, seeded once, visible to *every* business) or a business's own
custom addition (`business_id = <id>`, visible only to that business). Use
`GlobalCurrency::visibleTo($businessId)` (global ∪ own) everywhere instead of relying on the usual
`BusinessScope`. `CurrencyController` lets a business add its own currency (always stamped with its
own `business_id`) but only lets it edit/delete rows it owns — editing/deleting a global row requires
the `super-admin` role (`CurrencyController::authorizeManage()`).

Seeded by `database/seeders/GlobalAllCurrencySeeder` (113 currencies incl. PKR and USD; idempotent —
checks `business_id = null AND currency_code = ...` before inserting, safe to re-run).

A business picks one in Business Settings (`Business.currency_id` → `Business::currency()`). The
symbol is surfaced via `Business::currencySymbol()` / the global `currency_symbol()` helper
(`app/Helpers/Helpers.php`, resolves the current `session('business_id')`'s business), which now
backs every place that used to call `settings('app_currency', ...)` /
`AppSettings::get('app_currency', ...)` directly (`ProductController`, `PurchaseController`,
`SaleController`, `dashboard.blade.php`). Falls back to the `app_currency` app-setting when a
business hasn't picked a currency yet. Receipts intentionally do **not** print a currency symbol —
they didn't before this feature either, and the ask was to apply the symbol only where one already
appears, not to add new ones.

### 6. After the sale — invoices & returns
- `InvoiceController`: `index`/`show` (with returns netted out), `printInvoice` (reprint).
- Returns are processed **in `InvoiceController`**, not `ReturnController`:
  `returnInvoice()` (whole invoice) / `returnProduct()` (single line) both call
  `processItemReturn()`, which validates returnable qty, claws back proportional discount, writes
  `InvoiceItemReturn` + `InvoiceHistory`/`ReturnHistory`, and **restocks** via
  `restoreStockBySalePreference()` (puts base qty back into the correct `BaseStockSalePrice` lots)
  plus increments `ProductStock.current_stock`.
- `ReturnController` is read-only: `report`/`show`/`printReturn` over `InvoiceItemReturn`.

### 7. Reporting & monitoring
- `DashboardController::index()` — first screen after login: purchase/category/supplier/sales
  counts, a pie chart, a 7-day revenue bar chart, today's sales total, latest 5 sales/returns,
  next 6 expiring purchases.
- `ReportController`: `sales` (date-ranged totals/discounts/returns/net), `profitLoss` (per-item
  cost vs revenue via `getBaseUnitCost()`, which resolves cost from stock-wise/static/latest-purchase
  depending on preference), `expiry` (purchases nearing/at expiry, red/amber banding).
- `StockIndicationReportController`: `index` (products at/below their min-qty alert threshold),
  `unaligned` (products with no resolvable min-qty rule at all — a "gaps in your alerting" report).
- `NotificationController` + `App\Notifications\StockAlertNotification`: low/out-of-stock triggers
  a `PurchaseOutStock` event → `NotifyStockAlert` listener → mail+DB+broadcast notification to
  every user, linking to `purchases.edit`.
- `BackupController` wraps `spatie/laravel-backup` (`backup:run` on demand; no scheduled backups —
  only the generic-import job is on the scheduler).

### 8. Logout
`POST logout` → `LogoutController::index()` → `auth()->logout()` → redirect to `login`. Always
reachable even mid-forced-business-setup (explicitly whitelisted in `EnsureUserHasBusiness`).

## Frontend (Inertia, Roadmap step 3)

The new UI is **Inertia v3 + React 19 + TypeScript + Vite 8**, root view `resources/views/inertia.blade.php`;
classic Blade pages keep `admin.layouts.app` until they are converted (Roadmap step 5). The reference pattern
is `TaxController` + `resources/js/Pages/Taxes/Index.tsx`.

**To build an Inertia screen:**
- The controller returns `Inertia::render('Module/Page', [...])`: a paginator (`->paginate()->withQueryString()`),
  `filters` (validated `q`, `sort` whitelist, `dir`, `per_page` ∈ 10/20/50) and lazy `stats` closures.
- The page uses the shared components in `resources/js/Components`: `DataTable` (never load full lists), `AsyncSelect`
  (or `LookupPicker` from the same file for multi-select / "add a new name"; send `toRef()` = `{id}` or `{name}`) for dropdowns (`/admin/lookup/{entity}`; add entities in `LookupController::ENTITIES`), `PageHeader`, `KpiCard`,
  `SectionCard`, `Modal`, `ConfirmDialog`, `Chart`, `toast()`.
- Colours only from the CSS variables in `resources/css/app.css` (light and dark).
- Add the screen to `config/navigation.php` with `'inertia' => true`, then delete its Blade views.
- Simple "name + description" lists extend `App\Http\Controllers\Admin\MasterData\MasterDataController` (only
  `definition()` per entity) and render `Pages/MasterData/Index.tsx`; don't write another copy of that screen.

Shared props come from `App\Http\Middleware\HandleInertiaRequests` (user, business, businesses, wording,
navigation, flash). **Never use those names for a page's own props** (a page prop `business` replaced the shared one and
crashed the sidebar). The wording per business type lives in `config/business_types.php`. Don't create a root `lang/`
folder: it would hide `resources/lang`.

Commands:
- `npm run dev`: live reload.
- `npm run build`: production assets in `public/build`, which are not committed; `setup.sh` builds them.
- `npm run typecheck`
- Inertia feature tests call `$this->withoutVite()`.

## Sign-up & sign-in (Inertia, Roadmap 5.0)

Landing page `/` (`WelcomeController`, `Pages/Welcome.tsx`); auth screens in `Pages/Auth/*` with `Layouts/AuthLayout`.
Flow: `SignupController` (type + name + email → `PlatformMessage` link → set password → `business.create?type=`).
Business types live in `App\Support\BusinessTypes` (shared by landing, sign-up and the wizard via
`Components/BusinessTypePicker`). Login is throttled (5 tries/min per email+IP). Reset codes die after 5 wrong tries.
The old `register` route only redirects to sign-up.

## Billing (Stripe, Roadmap 4e)

`app/Services/Billing/BillingService.php` holds the whole flow (quote → checkout or instant free activation →
confirm/webhook → modules on; change, cancel, resume, portal, entitlements). Stripe is reached only through
`Gateway\PaymentGateway` (`StripeGateway`; tests bind `tests/Support/FakePaymentGateway`). Every Stripe error
becomes a `BillingException` with a safe `userMessage`; empty keys are a supported state ("not connected yet").
Webhook: `POST /api/stripe/webhook` → `stripe_events` inbox → queued `ProcessStripeEvent`. Daily
`billing:enforce-entitlements` switches off uncovered modules only after launch pricing ends.

## Sale engine (single source of truth, Roadmap step 2)

All pricing, lot, tax and checkout rules live in `app/Services/Sales/`. Controllers must call these, never
re-implement them:
- `PackagingService`: unit ↔ base-unit conversion.
- `SalePreferenceResolver` → `SaleSettings`: product override → business-wide preference → default.
- `LotAllocator`: FEFO/FIFO lot order; sellable = remaining > 0 and the expiry date is after today.
- `SalePriceCalculator`: static / stock-wise / previous-inventory unit prices.
- `SaleTaxCalculator`: GST disclosure, named Sale Tax additive or included.
- `CheckoutService`: server-authoritative POS checkout; writes `invoice_item_lots`.

Returns restock the exact lots from `invoice_item_lots`, and profit/loss cost comes from the same table.
Business-wide preference rows come from `App\Services\Business\BusinessPreferenceDefaults` (hooked on
`Business::created`).

Tests: `php artisan test` uses the `pharmacy_testing` database only (enforced in `tests/CreatesApplication.php`).
Scenario helpers are in `tests/Support/SalesScenario.php`.

## Key files map

| Concern | Path |
|---|---|
| Auth (signup/login/logout/reset) | `app/Http/Controllers/Admin/Auth/*` |
| Tenancy gate | `app/Http/Middleware/EnsureUserHasBusiness.php` |
| Sales-person lockdown | `app/Http/Middleware/RestrictSalesPersonAccess.php` |
| Tenant auto-scoping | `app/Traits/BelongsToBusiness.php`, `app/Models/Scopes/BusinessScope.php` |
| Business/Pharmacy | `app/Http/Controllers/Admin/BusinessController.php`, `PharmacyController.php` |
| Super-admin impersonation | `app/Http/Controllers/Admin/SuperAdminBusinessController.php` |
| Products list / page / setup wizard (Inertia) | `app/Http/Controllers/Admin/Catalogue/ProductCatalogController.php`, `app/Services/Catalogue/ProductSetupService.php`, `resources/js/Pages/Products/*` |
| Product add/edit rules | `app/Services/Catalogue/ProductFormService.php` (same controller as the list) |
| Sale preference screens (business + per product) | `app/Http/Controllers/Admin/Catalogue/SalePreferenceController.php`, `app/Services/Catalogue/SalePreferenceService.php` |
| POS lookups, stock/price helpers (still Blade/AJAX) | `app/Http/Controllers/Admin/ProductController.php` |
| Generic catalog + import cron | `app/Http/Controllers/Admin/GenericProductController.php`, `app/Services/GenericProductService.php`, `app/Console/Commands/ProcessGenericProductBulkImports.php` |
| Purchases / stock-in (rules + amounts) | `app/Services/Stock/PurchaseService.php` (server computes every amount; edit/archive rules), `app/Http/Controllers/Admin/PurchaseController.php`, `resources/js/Pages/Purchases/*` |
| POS / checkout | `app/Http/Controllers/Admin/POSController.php` |
| Invoices / returns | `app/Http/Controllers/Admin/InvoiceController.php`, `ReturnController.php` |
| Reports / dashboard | `app/Http/Controllers/Admin/ReportController.php`, `StockIndicationReportController.php`, `DashboardController.php` |
| Roles/permissions seed | `database/seeders/RolesAndPermissionsSeeder.php` |
| Routes | `routes/web.php` (single flat file, all admin routes) |
| Shared receipt CSS | `resources/views/admin/partials/_receipt-styles.blade.php` |
| FBR invoicing (stub) | `app/Services/FbrInvoiceService.php`, `app/Exceptions/FbrNotConfiguredException.php` |
| Shared purchase-form color scheme | `resources/views/admin/partials/_purchase-section-styles.blade.php` |
| Additive Sale Tax / disclosure-only GST | `app/Models/InvoiceItemTax.php`, `POSController::computeSaleTaxBreakdown()` / `cartTaxBreakdown()`, `Invoice::consolidatedTaxBreakdown()` |
| Currency | `app/Models/Currency/GlobalCurrency.php`, `app/Http/Controllers/Admin/CurrencyController.php`, `currency_symbol()` helper |

## Gotchas worth remembering

- `.env` is currently tracked/modified in this repo (`git status` shows `M .env`) — treat it as
  containing live secrets (DB creds, mail creds, `SUPERADMINEMAIL`/`SUPERADMINPASSWORD`); never
  print its values or commit changes to it without checking with the user first.
- Dead resource routes exist for `pos` (`create`/`store`/`update`/`destroy` have no controller
  methods) — don't assume every `Route::resource` verb is wired up; check the controller.
- Two separate "record a sale" systems coexist: the real one (POS → Invoice/InvoiceItem) and a
  legacy one (`SaleController`/`Sale` model). Don't extend `Sale` thinking it's on the POS path.
- `SaleTax` is now load-bearing at sale time (`POSController::computeSaleTaxBreakdown()` reads
  `Purchase::Saletaxes` to build the receipt/invoice tax breakdown) — it is **not** dead code
  anymore; don't remove it thinking it's unused.
- There's a leftover git worktree at `.claude/worktrees/odk-qr-code-a1b374/` — a full duplicate
  checkout. Don't `find`/`grep` recursively from the repo root without excluding it, and don't
  treat files under it as the canonical source.
- Stock math is FIFO-lot-based (`BaseStockSalePrice.remaining_base_stock` per purchase batch), not a
  single running counter — `ProductStock.current_stock` is a derived cache kept in sync alongside it,
  not the source of truth for per-lot cost/expiry.
