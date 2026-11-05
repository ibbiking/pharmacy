# Business logic (current and future)

The **business rules** of the product, in plain language. For code-level pointers (file and method names) see
`CLAUDE.md`. Status tags: **[current]** = how the code behaves today · **[bug]** = verified defect, fix planned ·
**[decided]** = rule the product owner chose, to be implemented · **[future]** = planned rule for a later module.

Last verified: 2026-11-04 on Laravel 13 (branch `upgrade/php83-laravel13`).

---

## 1. Global rules (apply to every module, now and future)

1. **Tenancy.**
   - Every record belongs to one business.
   - A user can belong to many businesses and switches between them.
   - A super admin can see across businesses or impersonate one. **[current]**
2. **Pagination, always on the server.**
   - Lists, dropdowns and lookups never load a full table. They fetch 10 or 20 rows per page (per module) and
     search on the server.
   - Applies to every new screen and every converted screen.
   - **[current, partial]** List screens comply (22 server-side tables), but **~51 dropdowns still load whole
     tables** (e.g. the purchase form loads all categories, suppliers and taxes). These get fixed as each screen
     moves to Inertia.
3. **Server is the authority.** Prices, discounts, taxes, totals and stock are always recomputed on the server.
   Values sent from the browser are only "requests". **[decided]**
4. **Modules sync through events.** No module writes another module's tables. See
   [MODULES_AND_PLANS.md](MODULES_AND_PLANS.md). **[future]**
5. **Every document can be emailed** using the business's own SMTP, sent through the queue, in a **branded layout**
   (business logo, or a default initials badge when there is no logo). **[decided]**
6. **Wording follows the business type** (Pharmacy / Inventory / Inventory B2B). **[future]**
7. **Slow or external work runs on the queue**: email, FBR submission, PDFs, imports, exports, webhooks, accounting
   backfill. A cashier never waits for it. **[decided]**

## 2. Business, users, roles **[current]**

- **Sign-up:** email-verified sign-up (token valid 24 h), then set password. The user becomes a **Business Owner**.
- **First login:** forced business setup (name required).
- **Roles:**
  - Business Owner: full access within their businesses.
  - Sales Person: POS, profile and password only; any other page redirects to POS.
  - Super Admin: everything, across businesses, can impersonate.
- **Password reset:** a 6-digit code by email, with an expiry and a resend cooldown.
- **Creating a business [current, since step 4c]:**
  - any logged-in user can create a business: at first login, or any time from the workspace menu;
  - wizard: basics → type (Pharmacy / Inventory / Inventory B2B) → modules (free during launch) → review;
  - the creator becomes the **owner**.
- **Roles are per business [current, since step 4c]:** what a user may do depends on their role *in the
  current business*. A sales-person in one shop can own another shop.
- **Roles & permissions [current, since step 4i]:**
  - each business has **Owner** (everything in its active modules, not editable) and **Sales person** (POS);
  - the owner creates custom roles in **Settings → Roles** by ticking module permissions (e.g. Sell at the POS,
    Give discounts, Change prices, Process returns, Record purchases, See cost prices, Profit & loss) and can set
    a max discount %;
  - a permission only counts while its module is switched on;
  - **Settings → Team**: add people by email with a role, change roles, suspend, remove. A business always keeps
    an active owner.
  - Every screen and action is checked against the role. At the POS the strictest of role / business / product
    limits wins.
- **[future]** Invite users by email per business.

- **Switching business [current, since step 3]:** a user who belongs to several businesses switches from the top
  bar. Only to businesses they belong to; their open POS cart is released. Super-admins keep using
  impersonation.
- **Theme:** light / dark / system, saved per user.

## 3. Products and packaging **[current]**

- A product has a **packaging hierarchy**, e.g. Box → Strip (1 per box) → Tablet (14 per strip).
  - The smallest unit is the **base unit**.
  - All stock is counted in base units.
  - Prices for bigger units are base price × the number of base units they contain.
- New products start as **draft**. The setup wizard defines the hierarchy and a fixed purchase price and sale price
  per unit (the static prices), which makes the product non-draft.
- **Generic catalogue** (pharmacy): a super-admin-curated master list that a business imports. Single import is
  instant; bulk import is queued and processed every 15 minutes.

- **Taxes [current, since step 3]:**
  - a tax is a name + a rate (0–100%), unique **within a business** (it used to be unique across all businesses,
    which blocked a second business from creating "GST");
  - archiving a tax keeps it on the purchases that already use it; it just can't be picked again, and its name can
    be reused.

## 4. Purchasing and stock **[current]**

- **Stock only comes in through a Purchase.** Each purchase creates a **lot** with:
  - quantity in base units,
  - remaining quantity,
  - sale price per base unit,
  - sale tax per base unit,
  - expiry and manufacturing dates.
- Purchase taxes and sale taxes are attached per purchase.
- **GST** = the markup between Unit Cost Price and Paid Unit Cost Price. It is disclosure only and never changes
  what the customer pays.
- Four purchase-entry screens share one colour scheme: single, edit, bulk, and the quick "Add stock" modal.
- **Prefilled prices on a new purchase:**
  - Previous-inventory preference: copies the last purchase (converted to the chosen unit).
  - Static preference: uses the product's fixed prices.
  - Stock-wise preference: behaves like previous-inventory here.

## 5. Sale-price preferences (verified 2026-11-04)

**What decides the price.** Product preference first, then the business-wide preference, then default static.
"Sale price including tax" is a separate switch.

| Preference | Unit price shown at POS |
|---|---|
| **Static** | The fixed sale price from the product setup for that unit. Tax switch has no effect. |
| **Stock-wise** | Price of the **first lot still in stock and not expired** (currently = oldest purchase), converted to the unit. |
| **Previous inventory** | Price of the **most recent lot** (even if sold out), converted to the unit. |

**Verified with product #2** (Box = 1 Strip = 14 Tablets; lots: #1 39.30/tablet, tax 0, expires 2029;
#15 39.30/tablet, tax 7.07, expires 2026-12-23):

| | Box | Strip | Tablet |
|---|---|---|---|
| Static | 543.26 | 543.26 | 38.80 |
| Stock-wise (tax off/on) | 550.26 | 550.26 | 39.30 |
| Previous inventory (tax off) | 550.26 | 550.26 | 39.30 |
| Previous inventory (tax on) | 649.31 | 649.31 | 46.38 |

✅ Unit conversion is correct in all three preferences.

### Issues found → fixes

| # | Issue | Impact | Fix (Roadmap step 2) |
|---|---|---|---|
| P1 **[fixed]** | POS checkout charges the **price and discount the browser sends**. The correct price is calculated but only stored for reference. | A cashier can change a price or discount in the browser, and preferences are not enforced. | The server recalculates every line's price from the preference, plus the allowed override rules below. A mismatch is rejected or logged as an override. |
| P2 **[fixed]** | Business-wide preference rows exist **only for business #1**, and all are off. New businesses never get them. | Businesses 2–5 cannot set a business-wide preference, and everyone silently uses static. | Create the rows at business creation (and backfill existing businesses). The business-wide default stays **static** until changed. |
| P3 **[fixed]** | With "Sale price including tax" on, the unit price already contains the lot's sale tax, and checkout **adds the named Sale Tax again**. | Tax charged twice. | Apply decision D3. |
| P4 **[fixed]** | Stock-wise sells by **oldest purchase** (lot #1, expiring 2029) before an earlier-expiring lot (#15, expiring 2026). | Short-dated stock can expire on the shelf. | Apply decision D1. |
| P5 **[fixed]** | `app/Helpers/BaseStockHelper.php` has an unused copy of the pricing code with **wrong** unit conversion (multiplies by the immediate parent quantity only). | A trap for future code. | Delete; one pricing service only. |

### More defects found and fixed during step 2 (2026-11-04)

| # | Defect | Fix |
|---|---|---|
| B1 | Checkout grouped cart lines by **price only**: two different products at the same price had both quantities deducted from the first product's stock. | Lots are allocated per product. |
| B2 | The quantity to deduct came from the browser (`base_qty`), while the stock check used the server's quantity. | The server converts quantity to base units itself; the browser value is ignored. |
| B3 | Lots were not locked during checkout, so two tills could oversell the same lot. | The product's sellable lots are locked (`SELECT … FOR UPDATE`) for the checkout transaction. |
| B4 | The tax breakdown could use **expired** lots, while deduction skipped them. | Tax is attributed to the exact lots sold. |
| B5 | The invoice-level discount **amount** came from the browser. | Recomputed from type + value, and capped (D2). |
| R1 | Returns guessed which lot to restock (price match, or the newest lot). | Every consumed lot is recorded per invoice line (`invoice_item_lots`). Returns restock those exact lots, the most recently consumed first. |
| R2 | Profit/loss cost was a guess. With the static preference it used the *configured* static purchase price, not what was paid. | Cost = the actual paid cost of the lots sold, net of returns. Pre-step-2 invoices keep the old estimate. |
| R3 | A full-invoice return after a partial return **over-refunded**: the remaining discount was divided by the original quantity instead of the remaining quantity. | Divides by the remaining quantity. |
| R4 | Returns refunded **every** named tax on top of the line amount. | Tax included in the price (D3) is never refunded on top, and is not printed on the customer's return receipt. |
| F1 | `products.sale_price_preference_id` had a foreign key to the per-business `preferences` rows with `ON DELETE CASCADE` (deleting a preference row would delete products), while the app stores `product_preferences` ids. | Migration: foreign key now points to `product_preferences`, with `ON DELETE SET NULL`. |
| F2 | The tenant trait **overwrote** an explicitly set `business_id` with the session's business. | Only stamps when `business_id` is empty. |
| F3 | The seeder created business-wide preference rows **without a business** (invisible to everyone) on fresh installs. | Rows are always created per business (BusinessPreferenceDefaults), automatically when a business is created. |
| F4 | An FBR failure other than "not configured" became a generic error. | Any FBR failure still rolls the sale back and returns `fbr_failed`. |

Unused duplicate code removed: `BaseStockHelper`, `ProductController::saveInvoice()` + `deductStockByPriceGroup()` +
`handleStockWisePricingWithCategoryOptimized()`, `POSController::deductStockByPriceGroup()` + `computeSaleTaxBreakdown()`.
Still unused, but kept for now: `ProductSalePricePreferenceController` (not routed; delete in step 5).

### Decisions (product owner, 2026-11-04)

- **D1. Lot selection at POS: earliest-expiry-first (FEFO) or oldest-purchase-first (FIFO), chosen per line.**
  - Every cart line shows the **batch number and expiry** of the lot(s) it will sell from.
  - Every line has a **toggle**: Earliest expiry first / Oldest purchase first.
  - The default comes from a preference, set business-wide and overridable per product.
  - Stock deduction, the stock-wise price, the tax breakdown and the return restock all use the same lot order the
    line was sold with. The chosen mode is saved on the invoice line.
- **D2. Changing the price at the POS** is a preference, business-wide and per product:
  - `allow_price_change`: no / yes;
  - optional lowest allowed price (floor) or maximum markdown %;
  - `max_discount` (% and/or amount) per line and per invoice.

  The server enforces all of these. Overrides are written to the audit log (who, original price, new price).
- **D3. "Sale price including tax" means the named Sale Tax is inside the price**, not added on top.
  - The customer pays the shown price.
  - Tax portion = price × rate ÷ (100 + rate).
  - The tax breakdown is **visible only to owners**: in reports and on the owner's invoice view. It is hidden on the
    cashier screen and the customer receipt.
  - When it is off, the named Sale Tax is added on top, as today.
  - ⚠️ If the business uses **FBR invoicing**, the law requires tax to be shown on the receipt. The FBR module will
    force the tax line on the receipt for those businesses.

### How the rules work now (step 2, backend; screens arrive in step 5)

- **Business-wide preferences** (one set per business, created automatically):
  - sale price: static (default) / stock-wise / previous inventory, plus "including tax";
  - lot selection: **FEFO** (default) / FIFO;
  - POS: allow price change (default **on**, as before), minimum price %, maximum line discount %, maximum invoice
    discount %.
- **Per-product overrides** (empty = inherit): sale-price preference + including tax, lot selection mode, allow price
  change, minimum price %, and the existing locked maximum discount (% / amount).
- **Lot order** (one implementation, `LotAllocator`, used everywhere):
  - FEFO = earliest expiry first, ties by purchase order;
  - FIFO = purchase order;
  - a lot is sellable until the day **before** its expiry date (unchanged).
- **Checkout** (`CheckoutService`):
  - every price, discount, tax and total is recomputed on the server;
  - violations return clear errors: `price_change_not_allowed`, `price_below_minimum`, `discount_exceeded`,
    `invoice_discount_exceeded`, `stock_exceeded`;
  - allowed price changes are saved with the system price next to them and an "Price Override" history entry.
- **Each invoice line stores:**
  - the lot mode used;
  - the lots consumed, with their cost;
  - the system price;
  - the GST disclosure;
  - the included tax (owner-only).
- **Effect to be aware of:** with FEFO as the default, the lot sold first can change. Stock-wise prices and the sale
  tax follow the lot actually sold. Example: business #1, product #2 now sells from lot #15 first (expiring
  2026-12-23), which carries a 7.07/tablet sale tax, so a tablet costs 39.30 + 7.07 tax on top. Previously FIFO sold
  from lot #1, which has no tax.
- **Tests:** `tests/Feature/Sales/*` (38 scenarios) run against a dedicated `pharmacy_testing` database (the test
  suite refuses any other).

## 6. POS, invoices, returns **[current]**

- **Cart:** stored per login session, so concurrent cashiers don't share a cart. Each quantity change checks real
  available stock, net of the cart.
- **Save & Print** is the only checkout action, so every printed receipt is a saved invoice. Checkout:
  1. creates the invoice and its lines;
  2. deducts stock lot by lot;
  3. writes history;
  4. assigns the invoice number.
- **Invoice numbers:** `INV-` + the database row id.
  - **[future]** This number is global across all businesses, so one business sees gaps (INV-10, INV-12, …). B2B
    modules need **per-business number series** (INV-, EST-, SO-, PO-, BL-, …) with an editable prefix and next
    number.
- **Taxes:**
  - GST is disclosure only.
  - Named Sale Tax is added on top (see D3 for the planned change).
  - Both are stored per line.
  - Additive Sale Tax is part of the grand total.
- **Returns:**
  - whole invoice or a single line;
  - the discount and the additive tax are given back proportionally;
  - stock is put back into the correct lots;
  - numbers use the `RET-000123` format.
  - **[current gap]** Reprinting an old return shows today's remaining tax, not the tax at the time of that return.
- **FBR invoicing:** per-business opt-in. **The API calls are a stub and have not been verified against FBR. Keep
  businesses on local numbering.**
  - **[decided]** There will be a business-wide **"With FBR / Without FBR"** preference that the owner can change at
    any time (new invoices only, audit-logged).
  - **[decided]** Invoice numbering follows it: the local per-business number always exists; with FBR on, the FBR
    fiscal number, QR code and logo are printed as well.
  - **[decided]** There will be a guided FBR integration page per business.
  - Full plan: [FBR_INTEGRATION.md](FBR_INTEGRATION.md).

## 7. Reports **[current]**

- **Dashboard:** counts, a 7-day revenue chart, today's sales, latest sales and returns, items expiring soon.
- **Sales report:** totals, discounts, returns and net, for a date range.
- **Profit & loss:** per-item cost against revenue. Cost follows the preference: stock-wise uses the lot sold;
  static and previous-inventory use their own rules.
- **Expiry report:** red when ≤ 30 days, amber when ≤ 90 days.
- **Stock indication:** below minimum quantity, plus a "products without a minimum rule" report.

## 7a. Quotations **[decided, built during the merger (B2B Sales step)]**
- A quotation is a priced offer. It **never deducts stock**, and its prices are only an estimate until it is
  converted.
- **Retail (POS):**
  - "Save as quotation" from the POS cart: number QT-xxxx (per business), validity date (default set per
    business), customer name and phone.
  - Prints in thermal or A4, and can be emailed.
- **B2B:** Estimate/Quotation: Draft → Sent → Accepted / Rejected / Expired → converted to a sales order or invoice.
- **Converting** re-runs the server pricing and stock checks from today. The user sees any price or stock
  difference against the quote and confirms. The new document links back to the quotation.
- Both use one shared quotation engine.

## 8. Future modules: business rules to carry over
These are taken from the reference apps; see [REFERENCE_SCREENSHOTS.md](REFERENCE_SCREENSHOTS.md).

- **B2B Sales:**
  - Estimate: Draft → Sent → Accepted / Rejected → Closed.
  - Sales order: Draft → Confirmed, with packed and invoiced progress.
  - Package: Not shipped → Shipped → Delivered. Partial packing allowed.
  - Invoice: Draft → Sent → Overdue → Paid. Due date comes from payment terms.
  - Payment receipt: one receipt can pay many invoices; any excess becomes customer credit.
  - Credit note: Open → Partially applied → Consumed.
  - Sales return: Draft → Confirmed → Received; partial "return receive" puts stock back.
  - A document is locked from editing once it is fully fulfilled.
- **B2B Purchasing:**
  - Purchase order: Draft → Issued → Closed / Cancelled, with received and billed progress.
  - Receive (goods received) creates stock lots.
  - Bill: Draft → Open → Overdue → Paid. Each bill line carries an expense account.
  - Bill payment can pay one or more bills.
- **Accounting:**
  - Chart of accounts: category → type → sub-type, sub-accounts, opening balance with an as-of date.
  - Every document posts double entries automatically ("system generated"). Opening balances go to Opening Balance
    Equity.
  - Each item is linked to Income, COGS and Inventory Asset accounts.
  - Reports: journal, ledger with brought-forward and carried-forward balances, trial balance, P&L, balance sheet,
    AR/AP aging, tax collected vs tax paid.
- **Expense:**
  - Expense: single or bulk entry, receipt required, multi-currency, category, merchant, payment mode,
    reimbursable or not.
  - Expenses are grouped into claim reports.
  - Report: Draft → Awaiting approval → Approved / Rejected / Awaiting revision → Reimbursed. A report can be
    recalled. Self-approval is allowed only if the policy permits, and is tracked.
  - Advances are applied to a trip or report. Amount to reimburse = reimbursable − applied advances.
  - Trips are built from segments (flight, hotel, car, bus, train, ferry).
  - Approved expenses post to accounting.
- **Plans:** module gating, plan limits (items, users, warehouses) and an upgrade banner, as in IMS.

## 9. Known gaps outside sale prices (tracked in the roadmap)

- `NotifyStockAlert` would email **every user of every business**. It is deliberately disabled and must be
  rewritten per business before it is turned on.
- Super-admin login using credentials from `.env` (bypassing the normal password check). Replace it with a normal
  super-admin account. 2FA is not required for now.
- `.env` is tracked in git and contains live credentials. Rotate the credentials and stop tracking `.env` carefully
  (see ROADMAP).
- Two routes have no code behind them (`pharmacies/autocomplete-name`, `pos/create`), and the QR logo page needs the
  `imagick` PHP extension.
- The legacy `Sale` module, separate from POS, should be retired when reports move to Inertia.
- Backups cover the whole database, not one business. Per-business export is needed for SaaS.
- The queue is `sync`, so emails would block checkout. A queue worker is needed before email goes live.
