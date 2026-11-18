# Fixed business type, micro services, credit and Books sync

Branch: `feature/fixed-business-type-credit-books` (from `upgrade/php83-laravel13` @ `1c9d5cae`).
Requested by the product owner on 2026-11-19. Status: **answers received (§6), building phase by phase (§8).**

This file holds (1) the owner's request word for word, (2) what the code does today, (3) the rules as understood,
(4) the phased plan and (5) the open questions. Every phase starts only after the owner's answers.

---

## 1. The owner's request (verbatim, 2026-11-19)

> in the guide we mentioned mixing, its not mixing actually, its a straight rule
>
> this is the major thing that must not be happen and must never being permitted, this is the basic conflict of our
> product business logic Mixing business types;
>
> Mixing business types
> “Inventory” and “B2B” are not walls. They are just a starting set of modules. Any business can add the modules of
> the others.
>
> | You are | You want to also work like | Switch on |
> |---|---|---|
> | Pharmacy | Inventory | Inventory Pro |
> | Pharmacy | Inventory B2B | Inventory Pro, B2B Sales, B2B Purchasing, Accounting, Expense |
> | Inventory | Inventory B2B | B2B Sales, B2B Purchasing, Accounting, Expense |
> | Inventory or B2B | Pharmacy | Pharmacy Pack |
>
> we need to know that each of the business will have only one business type of you can say whatever to it, the
> business type, the product or the anything you better know the specific proper professional trending name on get
> started page, we have 3 types of business listed, this is the final selection of business type, we can also mention
> it, that to use another business type, you will have to create a new business. now there is a biggest gap of design
> control here, when user click on get started, simply sign in or sign up/register, when user will register, then he
> will login, on first login without having a single business, user will be forced to create a business, then there
> will be 3 options of business type in this same style of business settings, then we have to warn the user that this
> is your final move to select the business type, if you want to use any else business type, you will have to create
> a new business add this in business logic of product first also add below in business logic as well so, if a user
> selected pharmacy, he can have the micro services in future and same for inventory and b2b the core features or
> modules of business types will be there always on the start up of each business type themseleves. now i think you
> have better understanding of this, now create new branch, do this work into that, name that branch according to
> task, and after doing the work, please update the guide, accordingly, also a major need is we need to create
> customers in pharmacy and inventory if not already exisiting, and also need a scenario to sale and purchase the
> items on credit record will be save against each customer or supplier or vendor for payments like view of journal
> entry or the balance sheet and then for all business type, when user will enable the books/accounting module, all
> the already created invoices, bills, purchases, cutomers payments, bills and all other transaction will be go into
> books and accounting, means creation of journal entries and all other things will be happen on this action in queue
> job and by telling user that all your data is converting in background, menawhile now when you will create new
> transactions, they will follow books/accounting, means the enabling of accounts module, so the chart of accounts
> can have everyone on enabling it, the credit scenario can have everyone on start up, we can add the creation of
> bills for on credit purchase in pharmacy and inventory, if marked as paid, then bill will be paid, if marked as
> credit then will be not paid and if purchase marked some payment of total, then its bill will be partially paid,
> and b2b will simply have the bills instead of purchase, so user can directly mark it as paid or partially paid with
> payment amount and not paid, same for invoices, in pos, we can give credit option for only already saved customers,
> not for the walkin customer, but in b2b, no walkin cutomer concept but the supplier or vendor in all busines types
> will be needed the credit notes for the customers will be created in all over the business types, which invoice
> will create as credit, if i am giving you wrong name of credits, you can use professional term as name, i don't
> remember the exact concept of credit notes bills, credits, these 2 features will also on startup needed in all
> business types, but please keep them as micro service, we can in future let them out on startup, you can add them
> as micro services, but default enabled, so Pharmacy Pack, Inventory Pro, Inventory B2B will be business type now
> and all other modules will be actually the micro services, that user can enable anytime in his business against
> business and business type so lets talk about other modules instead of books/accountings so a point for
> books/accounting, if any new thing will be added in the books/accounting, like any kind of record of transaction or
> report will be added, it will definitely fetch that specific business transactions according to business type of
> already created items in background, also if on side by side, user enabled books/accoutning on his business, there
> can be mess if side by side he is doing transactiong while the queue job or cron job is running in the background,
> because it can make transaction id order of entry, dates and other things manipulate, so you have the choice to do
> best in this case, the ony solution in my mind is while running job in background for that business, create
> temporary transaction, when that job will done, add temporary transactions then as original, but only for the
> aspect of accounting/books by adding a key or column in database tables, in this case if temporary record of
> transaction exist against any business and no running job in background for books/accounting for that business,
> run job to add that temporary record, this will happen again and again untill all the record enters orginally by
> order and no job is running in background you can have best paractice and experience on such scenarios and can
> suggest best, you can ask open simple questions save all this prompt and requirements first in any .md file and
> todays working, commited or not commited, then start wrok, and do the work in chunks please, implementation plan
> must be solid expense module must also be on startup by enabling default in all business types needed the main
> estimation module as well in b2b business only, for the customer, also purchase order, shipping, and also you can
> explore books, expense and inventory modules and can ask me to add them into b2b or not, all projects like books,
> expense and inventory of which ideas i aksed you to take from these products, tjhe any additional module that is
> necessary, as micro service, you can ask me, you have to see these projects from github as github has all its
> updated code, get them from there, ask me, if i suggest to add them, note there business loigics and concept in any
> .md first, then start work on this, you please create a proper open question one time for me for all this prompt,
> then start work in phases one by one at the end i need a proper guide of all business types for a demo to any
> client, specifically for each business type, all the scenarios, all the micro services he can and he cannot have.
> according to your demo things i will guide the clients in demo, i also want proper web page for this in main page
> where we have option of guide in navbar, needed this in short but very end user friendly prompt, with not even a
> single step or feature missing, proper tour guide according to each business type with all allow to enabled micro
> services, all discussed b2b related modules will be on start up of b2b, but will be micro service default enabled,
> but user simply cannot add estimation, purchase order, sale order, direct bill and direct credit into pharmacy and
> inventory pro, but indirect bills and indirect credit can be done as described above please start work in phases,
> split all the safe side work in phases first and then start phases one by one, add in .md first must update the
> guide go ahead

## 2. Work done before this request (2026-11-18, all committed and pushed on `upgrade/php83-laravel13`)

- `62b06f52` POS sells goods without an expiry date; product-detail bar, stock summary, products price summary restored.
- `eb0f556c` POS frozen detail bar, compact cart, click-to-select rows; per-item "can expire"; dropdowns no longer clipped.
- `64a1c69d` Rule: shared features behave the same for every business type.
- `78273455` Batch selling: "Any batch" rule and a POS batch picker.
- `e445db0e` Item form: Next/Save in the header, sticky action bar.
- `4f612dd2` Packaging step: add a unit in a pop-up, profit as % on cost.
- `146dbcca` POS batch field starts empty; clearing it goes back to the rule.
- `14add8d1` Public Guide page (`/guide`) + owner's Q&A in `PRODUCT_GUIDE.md`.
- `1c9d5cae` CLAUDE.md: read `PRODUCT_GUIDE.md` and `REMAINING.md` before any work.

On 2026-11-19 (this laptop): imported the other laptop's database as `pharmacy_laravel13` (`migrate` = nothing to
migrate), copied its `.env`, built a local demo database `pharmacy_qa` with `DemoDataSeeder`. No code changes.

## 3. What the code does today (checked 2026-11-19)

| Area | Today |
|---|---|
| Business type | Chosen at sign-up and in the create-business wizard (`BusinessOnboardingController`, `Pages/Business/Create.tsx`). Only sets wording + the starting modules (`config/modules.php` → `bundles`). The guide says the type can be changed later. |
| Mixing | Any business can switch on any module from Settings → Modules. `config/modules.php` → `maximum` exists but nothing enforces it. The guide's "Mixing business types" section says this is allowed. **This is the conflict the owner wants removed.** |
| Customers | Exist in Core for every type (`CustomerController`, `customers.*`), but only B2B Sales documents use them. The POS has no customer field. |
| Credit | Only through B2B Sales invoices (customers) and B2B Purchasing bills (suppliers). POS is cash only; a plain purchase has no paid / owed. A bill can only come from a purchase order. |
| Credit notes / vendor credits | Only in B2B Sales / B2B Purchasing. |
| Books backfill | Switching Accounting on queues `BackfillAccountingForBusiness` → `LedgerPoster::backfill()`. Every journal is keyed by source + event, dated by the transaction's own date, and re-posting replaces it (idempotent). No progress shown, no lock against live postings at the same moment. |
| Expense | Module, on at the start only for Inventory B2B. |

## 4. The rules as understood (to confirm through the questions in §6)

1. **One business = one business type, chosen once, never changed.** Three types: Pharmacy, Inventory, Inventory B2B
   (names to confirm, Q2). Another type = create another business (one login can own many).
2. Sign-up → sign in → **forced "create your business"** on first login → choose one of the three types in the same
   card style as Business Settings → a clear **"this is final"** warning before saving.
3. **Type modules** (the identity of the type, always on, cannot be switched off or added to another type):
   Pharmacy → Pharmacy Pack; Inventory → Inventory Pro; Inventory B2B → the B2B document flows
   (estimates, sales orders, purchase orders, shipping, direct bills, direct credit notes / vendor credits).
4. **Micro services**: everything else. A business can switch them on/off any time; some start **on by default**
   (still switchable): Credit (customers & suppliers), Expense; for B2B also its document flows.
5. **Not allowed in Pharmacy / Inventory, ever:** estimates, sales orders, purchase orders, direct bills, direct credit
   notes. **Allowed there indirectly:** a purchase marked credit / part-paid creates its bill automatically; a credit
   sale's return creates its credit note automatically.
6. **Credit at the POS** (Pharmacy, Inventory): only for a **saved customer**, never the walk-in customer. Paid,
   part-paid or unpaid; the rest goes on the customer's account (statement, payments, ageing).
7. **Credit purchases** (Pharmacy, Inventory): purchase marked **paid** → bill paid; **credit** → bill unpaid;
   **part payment** → bill partly paid. Inventory B2B enters **bills directly** (paid / part-paid / unpaid), and
   invoices the same way.
8. **Books / Accounting** is a micro service any type can switch on. Switching it on posts **all past** sales,
   returns, purchases, bills, invoices, payments, credits and expenses in a background job and tells the user
   ("your data is being converted in the background"). New transactions post as they happen. Anything new added to
   Books later must also be back-filled from the past data.
9. Every customer / supplier / vendor has a record of what they owe / are owed, viewable like a ledger.

### Books sync while the user keeps working (proposal, Q8)

The owner's idea: hold new transactions as "temporary" while the job runs, then add them in order. Recommended,
same idea made safe:
- A per-business **books sync status** (`idle` / `running`, with progress) and a lock, so only one sync per business.
- While a sync runs, a live transaction does **not** post directly; it writes a row to a small **pending postings**
  table (business, source, event). When the sync finishes it drains that table **in order**, and keeps looping until
  the table is empty and no sync is running (exactly the owner's "again and again" rule). A scheduled sweep catches
  anything left if a worker died.
- Order and dates stay correct anyway: every journal carries the **transaction's own date** and reports sort by date;
  re-posting the same source replaces its journal, so nothing is posted twice.
- The original sales / purchases are never touched; only the Books side waits.

## 5. Phased plan (each phase: docs first → code → tests → guide update → commit)

| Phase | What | Depends on |
|---|---|---|
| **0** | This file; fix the "Mixing" section in `PRODUCT_GUIDE.md`, `BUSINESS_LOGIC.md` and the `/guide` page to the straight rule; record the answers. | answers |
| **1** | **Fixed business type**: create-business on first login with the three type cards (Business Settings style) and the "final choice" warning; type read-only in Settings; type modules locked; modules not allowed for a type refused everywhere (Modules page, wizard, Billing, API); a migration for existing businesses (Q4). | 0 |
| **2** | **Micro services** in `config/modules.php`: type module vs micro service, "on by default" per type, switchable; Modules page grouped; billing prices (Q9). | 1 |
| **3** | **Customer credit at the POS** (Pharmacy, Inventory): saved-customer picker, paid / part-paid / unpaid, customer balance, receive payment, statement, ageing, credit limit (Q6); **credit notes** from returns of credit sales (indirect). | 2 |
| **4** | **Supplier credit on purchases** (Pharmacy, Inventory): paid / credit / part-paid → automatic bill, bill payments, supplier statement and ageing; indirect vendor credit from purchase returns. | 2 |
| **5** | **Inventory B2B**: direct bills and invoices with paid / part-paid / unpaid; estimates, sales orders, purchase orders, **shipping** (Q11); POS rule for B2B (Q13). | 2 |
| **6** | **Books sync**: progress banner, lock, pending-postings outbox, drain loop, sweep; every posting type (incl. phases 3–5) has its back-fill. | 3–5 |
| **7** | Extras from the reference Books / Inventory / Expense apps the owner approves (Q1, Q10). | answers |
| **8** | **Demo guide**: `/guide` rebuilt as a tour per business type (every scenario, allowed / not allowed micro services), `docs/DEMO_GUIDE.md`, demo seed data updated to match. | all |

## 6. Owner's answers (2026-11-19) — these override §4 where they differ

| # | Question | Decision |
|---|---|---|
| 1 | Reference apps | Private repos `ibbiking/books_*`, `expense_*`, `bills_fe`, `crm` and others. Their **brand name is never written** anywhere (code, docs, UI, logos, commits): say "the reference Books / Inventory / Expense app". |
| 2 | Type names | **Pharmacy**, **Retail & Inventory** (shop / store), **Wholesale & Distribution (B2B)**. Internal keys stay `pharmacy`, `inventory`, `inventory_b2b`. |
| 3 | Warehouses | A **micro service open to all three types, on by default** in all three (today's "Inventory Pro": warehouses, transfers, adjustments). Owner's idea of **sub-businesses / branches** (one business, many branches, like a restaurant chain) is **not built**: warehouses already cover locations; branches with their own staff / POS / reports stay a future idea (§7) to design separately so it doesn't create a mess. |
| 4 | Existing businesses | A migration switches off modules a type no longer allows. Data is kept (hidden), nothing deleted. |
| 5 | Return of a credit sale | Creates a **credit note** automatically (lowers what the customer owes). A cash sale's return stays a cash refund. |
| 6 | Credit limit at the POS | Enforced, like B2B invoices. |
| 7 | Books at the start | **On by default in all three types** (switchable). Switching it on later back-fills the past. |
| 8 | Books sync while working | Approved: lock + pending-postings queue drained in order, repeat until empty, scheduled sweep. |
| 9 | Price | Default-on micro services are free at launch pricing; others through Billing. |
| 10 | Expense | On by default in all three types, can be switched off. Advances / trips / claim reports later, only if approved. |
| 11 | Shipping (B2B) | Simple packages and shipments on a sales order: carrier, tracking no., shipped / delivered, delivery note print. No courier API. |
| 12 | Demo guide | `/guide`: one tab per type, step-by-step tour, every scenario, and **under each type the allowed micro services shown as blocks**; same in `docs/DEMO_GUIDE.md`. |
| 13 | POS in B2B | B2B keeps the POS layout but it is named **"Invoice"**: it is an invoice against a **customer**. No customer selected → product search and adding items are blocked. No walk-in. |
| 14 | Push | Push after each finished phase and record what was done in §8 of this file. Every flow gets automated tests. |

### Module model after the answers

| Module | Kind | Pharmacy | Retail & Inventory | Wholesale & Distribution |
|---|---|---|---|---|
| Core, Stock & Purchasing, POS / Invoice | always on | ✅ locked | ✅ locked | ✅ locked (POS named "Invoice") |
| Pharmacy Pack | **type module** | ✅ locked on | ❌ not allowed | ❌ not allowed |
| B2B Sales (estimates, sales orders, direct invoices, shipping, direct credit notes) | micro service | ❌ not allowed | ❌ not allowed | ✅ on by default |
| B2B Purchasing (purchase orders, direct bills, direct vendor credits) | micro service | ❌ not allowed | ❌ not allowed | ✅ on by default |
| Customer credit (credit sales at the POS for saved customers, payments, statements, credit notes from returns) | micro service | ✅ on by default | ✅ on by default | ✅ on by default |
| Supplier credit (purchases paid / part-paid / on credit → bill, bill payments, statements, vendor credits from purchase returns) | micro service | ✅ on by default | ✅ on by default | ✅ on by default |
| Warehouses (today's Inventory Pro) | micro service | ✅ on by default | ✅ on by default | ✅ on by default |
| Books / Accounting | micro service | ✅ on by default | ✅ on by default | ✅ on by default |
| Expense | micro service | ✅ on by default | ✅ on by default | ✅ on by default |
| Advanced Reporting | micro service | optional (paid) | optional (paid) | optional (paid) |
| FBR Integration | micro service | coming later | coming later | coming later |

### Design for phases 3–4: indirect invoices, bills and credit notes (Pharmacy, Retail)

One engine for money owed, in every type: the B2B **documents + payments + allocations** (statements, ageing,
customer credit, Books). Pharmacy and Retail don't get the document screens; their POS sales and purchases create
**linked documents** in the background:

| Event | Linked document | Stock | Books |
|---|---|---|---|
| POS sale to a **saved customer**, paid / part-paid / unpaid | `sales_invoice` with `origin = pos`, `invoice_id` → the POS invoice; paid part = a payment allocated to it | none (the POS already moved it) | the POS sale posts cash for the paid part and **customer owes (AR)** for the rest; the linked document posts nothing |
| Return of such a sale | `credit_note` with `origin = pos_return`, applied to the linked invoice (lowers what is owed; extra stays as customer credit) | none (the return already restocked) | the POS return credits AR instead of cash for the credit part |
| Purchase marked **paid / part-paid / credit** | `bill` with `origin = purchase`, `purchase_id` → the batch; paid part = a payment allocated to it | none (the purchase moved it) | the purchase posts cash for the paid part and **supplier owed (AP)** for the rest |
| Purchase return (vendor credit) | `vendor_credit` with `origin = purchase_return` | the return moves it | as today |

- Receive payment from a customer / pay a supplier, statements and ageing screens become available to every type
  (Customer credit / Supplier credit micro services). Estimates, orders and **direct** invoices / bills / credit notes
  stay Wholesale-only.
- Walk-in sales stay cash only; credit needs a saved customer; the customer's **credit limit** is checked.
- Wholesale: the POS screen is named **Invoice** and needs a customer before any item can be searched or added.

## 7. Future ideas (not approved for building)

- **Branches / sub-businesses** (owner, 2026-11-19): one business with several branches that the owner can switch
  between, each branch with its own staff, POS and reports, rolling up to the main business. Needs its own design.

## 8. Progress log (updated after every phase)

- 2026-11-19: requirements saved (`3eade981`); answers recorded; reference brand name removed from code and docs.
- 2026-11-19 **Phases 0–2 done**:
  - `config/modules.php` → `types` (locked / defaults / allowed per type); "Inventory Pro" renamed **Warehouses**
    (on by default for every type); Books and Expense on by default for every type.
  - `ModuleManager`: `allowedFor / lockedFor / isAllowed / isLocked`; `activate()` refuses a module of another type,
    `deactivate()` refuses a locked one. `Business` refuses a type change (`DomainException`) and defaults to Pharmacy.
  - Create-business wizard: types named Pharmacy / Retail & Inventory / Wholesale & Distribution (B2B); only the
    type's modules are offered (locked ones locked); **"this is your final choice"** warning with a required tick
    (`type_confirmed`). First login without a business is still forced into the wizard.
  - Modules page: only the type's modules, grouped "Part of every … business" and "Micro services".
  - Billing: a module of another type is refused; plan modules of another type are skipped; locked modules are never
    switched off by the entitlement sweep.
  - Migration `2026_11_19_100000_enforce_business_type_modules`: existing businesses lose modules of another type
    (rows archived, data kept) and get their locked modules on.
  - Guide (`/guide`): "Mixing business types" replaced by **"One business, one type"** with the allowed modules of
    each type as blocks (part of the type / on from the start / add any time / not for this type).
  - Demo seeder: B2B documents only in the Wholesale business. Tests: `BusinessTypeRuleTest` (9) + updated module,
    billing, onboarding, help, permissions tests. Full suite: all green except 2 tests that fail only on MariaDB 10.4
    (this laptop; MySQL 8 elsewhere): `SuperAdminScreensTest` (window function) and `BillingCatalogTest` (JSON collation).
- 2026-11-19 **Phases 3–4 done (credit in every type)**:
  - New micro services **Customer credit** (`customer_credit`: sell on credit, payments received, customer statement,
    receivables ageing) and **Supplier credit** (`supplier_credit`: purchases on credit, bill payments, supplier
    statement, payables ageing); on by default in every type; B2B Sales / Purchasing now need them. Payment screens use
    `customer_credit.*` / `supplier_credit.*` permissions (`customer_credit.sell` = sell on credit at the POS).
    Migration `2026_11_19_120000` switches both on for existing businesses and copies B2B payment permissions of
    custom roles.
  - Migration `2026_11_19_110000`: `invoices.customer_id`, `invoices.payment_status`, `purchases.payment_status`,
    `purchases.amount_paid`, `documents.origin / invoice_id / purchase_id`.
  - `App\Services\Credit\CreditService`: linked documents (see the design table above), credit-limit check, customer
    balance. `CheckoutService` takes `customer_id` + `on_credit` (credit only for a saved customer, module on, permission,
    within the limit; Wholesale always needs a customer); the quote returns `totals.credit`. `ReturnService` makes the
    credit note. `PurchaseService::create` takes `payment` (paid / partial / unpaid) + `amount_paid`; a bill with
    payments locks its batches; an unpaid bill follows edits / archive.
  - Books: a credit POS sale posts to AR (customer), its return credits AR, a purchase not paid in full posts to AP;
    linked documents post nothing; payments post as before. Backfill gives the same books.
  - Screens: POS customer picker + "Rest on account" switch with paid now / on account / balance / limit; in a
    Wholesale business the POS is named **Invoice** (menu + title) and needs a customer before items can be searched or
    added. Purchase form: "Payment to the supplier" (paid / partly paid / on credit). Menu: "Customer accounts" and
    "Supplier accounts"; modules a type can never have are hidden from the menu (not shown locked). Guide updated.
  - Demo seeder: two credit sales in the Pharmacy and Retail demos; every Wholesale sale has a customer.
  - Tests: `CreditFlowsTest` (9 flows: part-paid, unpaid + payment, walk-in / limit / module refusals, live quote,
    return → credit note, purchases paid / partial / credit + bill payment + lock, backfill, Wholesale needs a customer).
- 2026-11-19 **Phase 5 done (Wholesale & Distribution)**:
  - Issuing a sales invoice / bill asks "paid now": nothing (on credit) / in full / part (amount + method); the payment
    is recorded and allocated in the same transaction (`DocumentController::action`, `paid_now` + `method`).
  - **Direct bills**: Buying → Bills has "Enter a supplier bill (stock in)" = the purchase form with paid / partly
    paid / on credit; its bill appears in the Bills list next to PO bills. Estimates (quotations), sales orders and
    purchase orders were already built (Roadmap 8–9); credit notes / vendor credits are made from their invoice / bill.
  - **Shipping**: table `shipments` (migration `2026_11_19_130000`), `Shipment` model, `ShipmentService`
    (packed → shipped → delivered, delivered ones kept), `ShipmentController` (`selling.shipments.*`), section
    "Shipping" on a confirmed sales order / issued invoice, printable **delivery note**.
  - Linked documents (from a POS sale / return / purchase) show "Made from a …" and only offer Record payment / Pay bill.
  - Tests: `WholesaleFlowsTest` (6): part / full / credit at issue, shipping + delivery note, linked bill actions,
    no B2B screens or menus in a pharmacy, the Wholesale POS is the Invoice screen.
