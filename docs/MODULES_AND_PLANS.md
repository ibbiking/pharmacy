# Modules, package plans & billing

## Architecture: modular monolith (not separate microservices)

The request is "every module like a micro service — sold, enabled and priced separately". We get that with a
**modular monolith**: one Laravel app and one database, split into self-contained modules.

**Why not real microservices?**
- A POS sale has to deduct stock, write the invoice, post tax and (if Accounting is on) post journal entries
  **in one database transaction**.
- Separate services would need distributed transactions, several deployments and several databases. That means
  more cost, more failure points and slower checkout, with no gain for the customer.
- The modular design still lets a module be extracted later if it ever needs to scale on its own.

**Each module is a folder `app/Modules/<Name>/` with:**
- its own `ModuleServiceProvider` (registers routes, migrations, Inertia pages, policies, listeners, scheduled jobs,
  sidebar items, settings and help pages);
- `Models/`, `Services/` (the business logic; controllers stay thin), `Http/`, `Events/`, `Listeners/`,
  `database/migrations`, `resources/js/Pages`, `tests/`;
- a `module.json` manifest: `key`, `name`, `depends_on`, `business_types`, `sidebar`, `permissions`, `settings`,
  `limits`.

**Reuse rule:** shared things (contacts, items, units, taxes, currencies, number series, documents/PDF/email,
attachments, audit history, approvals, the pagination base and the lookup API) live in **Core**. Modules use
them; they never copy them.

### How modules switch on and off at runtime
- Every module's service provider is always registered.
- Each request is checked against the business's active modules by `EnsureModuleActive` middleware (`module:<key>`).
- A module that isn't active:
  - its routes return a friendly "Upgrade" page;
  - its sidebar items show with a lock icon;
  - its event listeners do nothing for that business.
- Turning a module on (a new purchase) or off takes effect immediately, with no deploy. Existing data stays and
  becomes visible again if the module is re-activated.

### How modules stay in sync (events)
Modules talk to each other through **domain events**, never by calling each other's tables directly.

| Event (raised by) | Listeners (only if that module is active) |
|---|---|
| `SaleCompleted` (POS / B2B Sales) | Stock: deduct lots · Accounting: Dr A/R or Cash, Cr Income, Cr Tax payable, Dr COGS / Cr Inventory · Reports: refresh aggregates · Notifications: low stock |
| `SaleReturned` | Stock: restock lots · Accounting: reversal · Credit note |
| `GoodsReceived` (Purchase / B2B Purchasing) | Stock: create lots · Accounting: Dr Inventory, Cr GRNI / A/P |
| `BillPosted`, `PaymentRecorded`, `ExpenseApproved`, `ExpenseReimbursed` | Accounting: journals · Email: send document |
| `StockAdjusted`, `StockTransferred` | Accounting: inventory adjustment · Reports |
| `ModuleActivated` | Run that module's idempotent per-business seeders (e.g. `DefaultChartOfAccountsSeeder` for Accounting, number series, preferences) |

Because the listeners are idempotent and tagged with the source document, Accounting can also **backfill** the
history of a business that buys it later, re-posting past sales and purchases.

## Module catalogue (proposed split)

| Key | Module | Contents | Depends on |
|---|---|---|---|
| `core` | **Core** (always on, free) | Business & multi-business switcher, users & roles, business settings, per-business **SMTP** + email templates, contacts (customers/suppliers), items/products + packaging units, taxes, currencies, number series, attachments, audit history, notifications, helper pages, basic dashboard | — |
| `stock` | **Stock & Purchasing** | Purchases (stock-in), batch/lot tracking with expiry, FIFO/FEFO lots, sale-price preferences, stock reports, low-stock/expiry alerts | core |
| `pos` | **POS & Invoicing** | POS checkout, live receipt, thermal + A4 receipts, invoices, returns, POS preferences, sales reports | stock |
| `pharmacy` | **Pharmacy Pack** | Generic medicine catalogue + import, formula/strength/type, drug-style labels, expiry reports, FBR invoicing hook | stock |
| `inventory_pro` | **Inventory Pro** | Multiple warehouses, transfers, adjustments, item groups/variants, reorder points, inventory valuation, FIFO cost-lot report | stock |
| `b2b_sales` | **B2B Sales** | **Quotations** (shared engine, also offered at the POS for every business type) → sales orders → packages → shipments → invoices → payment receipts → credit notes; sales returns; customer statements; credit terms | stock, (inventory_pro recommended) |
| `b2b_purchase` | **B2B Purchasing** | Purchase orders → receives (GRN) → bills → bill payments → vendor credits; supplier statements | stock |
| `accounting` | **Books / Accounting** | Chart of accounts, auto journals from all modules, manual journals, ledger, trial balance, P&L, balance sheet, AR/AP aging, tax summary, bank accounts | core |
| `expense` | **Expense** | Expenses (single + bulk, receipts), categories, merchants, claim reports, approvals (approve / reject / revise / recall / reimburse, self-approval policy), advances, trips | core (posts to accounting if active) |
| `analytics` | **Advanced Reporting** | Cross-module dashboards, custom date-range comparisons, scheduled email reports, exports | any |
| `fbr` | **FBR Integration** (add-on) | Guided IRIS registration page, With/Without FBR preference, FBR terminals, POS Integration (retail) and Digital Invoicing (B2B), queued submission with retries, reconciliation. See [FBR_INTEGRATION.md](FBR_INTEGRATION.md) | pos (DI also needs b2b_sales) |

### Business types → default bundle (chosen at business creation)

| Business type | Wording | Default modules |
|---|---|---|
| **Pharmacy** | Medicine, Generic, Formula, Strength, Batch, Expiry, Pharmacy | core + stock + pos + pharmacy |
| **Inventory** | Item, Category, Brand, Variant, Batch, Store | core + stock + pos + inventory_pro |
| **Inventory B2B** | Item, Customer account, Sales order, Vendor, Warehouse | core + stock + pos + inventory_pro + b2b_sales + b2b_purchase + accounting + expense |

- The wording comes from one dictionary per business type (`lang/<locale>/business_types/<type>.php`), shared with
  React through Inertia props. No screen hard-codes "Medicine" or "Item".
- The type can be changed later in Business Settings. The wording changes immediately; data is untouched.

## Roles & permissions (Roadmap 4i)

- **Every module declares its permissions** (e.g. `pos.sell`, `pos.discount`, `pos.price_change`, `stock.purchase.create`,
  `reports.profit_loss.view`, `settings.billing.manage`). The role editor builds its matrix from them, so new modules
  appear in it automatically.
- **Roles belong to a business** (Spatie permission "teams", team = business).
  - Built-in: **Owner** (all permissions of the active modules, cannot be edited) and **Sales person** (POS only).
  - Owners create custom roles and assign them per user per business.
- **A permission only counts while its module is switched on**, so a cheaper plan automatically narrows what
  roles can do. Re-activating a module restores the roles' previous permissions.
- **Role limits:** maximum discount, may change price, may see cost/profit, may approve (expenses, purchase
  orders). These feed the POS and approval rules, combined with the business and product preferences: the
  stricter one wins.

## Package plans (super admin)

- **Module prices:** each module has a monthly and a yearly price, a currency and optional limits (users,
  businesses, items, invoices per month, warehouses).
- **Plans:** named bundles of modules (for example "Pharmacy Starter", "Inventory Pro", "B2B Complete") with a plan
  price (usually discounted against the sum of its modules), limits, trial days, a visibility flag and a sort order.
- **Custom plan:** the customer picks modules à la carte. The price is the sum of the module prices, and the
  dependency graph auto-adds anything required, with an explanation.
- **Launch default:** a super-admin setting **"Free for everyone"**, which is **ON** at launch. Every business gets
  all modules at 0 (the "Free Launch" plan). When the super admin turns it off, existing businesses keep their
  current modules until a date the super admin chooses (a grace period).
- **Plan screens:**
  - for the customer: Billing page showing the current plan, active modules, usage against limits, upcoming
    invoice, an "Add modules" checkout, payment methods and invoice history (PDF);
  - for the super admin: plans, module prices, coupons, subscriptions per business, a revenue dashboard and manual
    overrides (comp a module, extend a trial).

## Billing: Stripe (built in 4e, 2026-11-05)

- **Own thin layer over `stripe/stripe-php`** (not Laravel Cashier: Cashier is user-centric and price-id based;
  our prices live in `plans` / `module_prices`). The **Business** is the paying customer
  (`businesses.stripe_customer_id`).
  - `App\Services\Billing\Gateway\PaymentGateway` is the only door to Stripe (`StripeGateway`; tests use
    `tests/Support/FakePaymentGateway`).
  - `BillingService` holds the whole flow; `BillingController` (Settings → Billing) and
    `SuperAdminBillingController::subscriptions` are thin.
- **Stripe Products/Prices are created on first use** and their ids stored on the plan / module price. Changing a
  price or currency clears the stored id, so the next checkout creates a new Stripe price (Stripe prices are
  immutable). Existing subscribers keep their old price until their plan is changed.
- **Flow:**
  1. Quote (server-side, dependencies added).
  2. A total of 0 → switched on at once (local subscription, no Stripe).
  3. Otherwise → Stripe Checkout (subscription mode, coupons allowed, plan trial days on the first subscription
     only).
  4. On return, the page confirms with Stripe directly, so it works even before or without the webhook.
  5. Modules are switched on (`source = subscription`).
- **Change plan / add modules:** prorated subscription update (old items deleted, new items added). Moving from a
  free local plan to a paid one goes through checkout.
- **Cancel** = at the end of the paid period (access continues; "Keep my subscription" undoes it). A free local
  plan cancels at once.
- **Statuses:**
  - `trialing`, `active` and `past_due` give access. While `past_due`, Stripe retries the card and a banner asks
    for a new card.
  - `canceled`, `unpaid` and `incomplete_expired` have ended.
- **Entitlements** (`BillingService::applyEntitlements`):
  - while launch pricing ("Free for everyone", including its grace date) is on, nothing is switched off;
  - afterwards, every module not covered by an entitled subscription is switched off, dependents first. Data is
    kept and comes back when the business subscribes again.
  - The daily `billing:enforce-entitlements` command (02:30) applies this to every business. It does nothing while
    launch pricing is on.
- **After launch pricing:**
  - new businesses start with Core only;
  - the create-business wizard sends the owner to Billing with the chosen modules preselected;
  - the Modules page sends uncovered modules to Billing.
- **Webhooks:** `POST /api/stripe/webhook` (stateless, throttled).
  - The signature is verified; without `STRIPE_WEBHOOK_SECRET` the endpoint answers 503 `not_configured`.
  - Each event is stored once in `stripe_events` (append-only inbox, idempotent) and processed by the queued
    `ProcessStripeEvent` job (5 tries, backoff).
  - Handled: `checkout.session.completed`, `customer.subscription.*`, `invoice.*`, which mirror Stripe invoices
    into `billing_invoices` (Payment history, with hosted link + PDF). Anything else is stored as `ignored`.
- **Errors:** every Stripe error becomes a `BillingException` with a message safe to show, and the technical
  detail goes to the log (never keys).
  - Kinds: not configured, card declined, rate limit, network, rejected keys, invalid request, provider error,
    bad webhook signature, wrong business, mixed currencies.
  - A global handler turns any uncaught one into a flash message (or a JSON error).
- **Empty keys are a supported state:**
  - the Billing page explains "not connected yet" and disables paying;
  - free plans still work;
  - the super admin's Subscriptions page shows how to connect.
- **Screens:**
  - **Settings → Billing** (permission `core.billing.manage`): current plan hero, plan cards with a monthly/yearly
    toggle, a module builder with a live quote, a review modal, cancel/resume, Stripe customer portal, payment
    history.
  - **Super admin → Subscriptions:** MRR, 30-day revenue, Stripe/webhook health, a status stat strip, the list.
- **Development:** test keys in `.env` (`STRIPE_KEY=pk_test_…`, `STRIPE_SECRET=sk_test_…`,
  `STRIPE_WEBHOOK_SECRET=whsec_…` from `stripe listen --forward-to http://127.0.0.1:8080/api/stripe/webhook`).
  Test card `4242 4242 4242 4242`. Not built yet: usage limits enforcement (users/products/invoices per plan) and
  manual overrides (comp a module, extend a trial).

## Creating a business (any user, any time)
1. "Create new business" is available from the Business switcher, not only at first login.
2. The wizard has these steps:
   - name, logo, country, currency, timezone;
   - **business type**, shown as cards with an explanation;
   - modules / plan, pre-selected from the type; all free while "Free for everyone" is on;
   - tax setup;
   - optional SMTP;
   - finish, then go to that module's "How it works" helper page.
3. The creator becomes the business **owner**. Other users are invited by email with a role.
4. Buying more modules later goes through Billing → Add modules → Stripe checkout → modules unlocked immediately.
   Each new module's defaults are seeded by `ModuleActivated`.

## Per-business email
- **Settings → Email** works like the SeeATS screen (410):
  - SMTP or Mailgun, with provider presets;
  - sender name and email, and reply-to;
  - credentials **encrypted**;
  - a "Send test email" button that records when it was last delivered.
- At send time the mailer is built from that business's settings. If a business hasn't configured email, it falls
  back to the platform mailer with that business's name as the sender name.
- **Documents sent:**
  - to customers: invoice, estimate, sales order, payment receipt, credit note, statement;
  - to suppliers: purchase order, bill payment advice;
  - internal: expense approvals.

  Each document has an editable email template with placeholders and the PDF attached.
- All emails go through the **queue** (`QUEUE_CONNECTION=database` plus a worker), so checkout never waits on SMTP.
- **Branded layout** for every email:
  - business logo in the header, or a **default badge** (business initials on the business colour) when there is no
    logo;
  - a responsive card layout that is safe in dark mode;
  - document summary (number, date, amount, due date, status pill) with a "View / Pay" button and the PDF attached;
  - footer with business address and contact, and the reply-to set to the business.

## Queue jobs (where the queue is required)

| Job | Added in step | Why queued |
|---|---|---|
| `SendDocumentEmail` / all notifications | 4 | SMTP is slow or unreliable; never block the user |
| `ProcessStripeWebhook` | 4 | Stripe expects a fast 2xx response; activation work happens after |
| `SeedModuleDefaults` (on `ModuleActivated`) | 4 | Chart of accounts, number series and preferences can be large |
| `ImportGenericProducts` (bulk) | 4 | Moves off the 15-minute cron for instant start; cron kept as a safety sweep |
| `SubmitInvoiceToFbr`, `ReconcileFbrDaily` | 6 | Retries with backoff; FBR outages |
| `GenerateDocumentPdf` | 7 | Heavy rendering |
| `BackfillAccountingForBusiness` | 10 | Re-posts history when Accounting is bought later |
| `ExportReport`, `SendScheduledReport` | 12 | Large exports and scheduled reports |
| `StockAlertDigest` (per business) | 5 | Replaces the cross-tenant `NotifyStockAlert` |
| `BackupBusinessData` | 13 | Per-business export |
