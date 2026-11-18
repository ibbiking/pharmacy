# Product guide: business types, modules, selling & buying

The product owner's questions and the answers, saved as given (2026-11-18). Each answer was checked against the code
on that date (`config/modules.php`, `config/business_types.php`, `ModuleManager`, `ModuleController`,
`CheckoutService`, `DocumentService`, `PurchaseService`). The public, plain-language version is the **Guide** page
(`/guide`, linked from the landing page navbar). When code changes, update both this file and that page.

Related: [BUSINESS_LOGIC.md](BUSINESS_LOGIC.md) (§1.8: shared features behave the same for every business type),
[MODULES_AND_PLANS.md](MODULES_AND_PLANS.md) (architecture, plans, billing).

---

## Q1. What is the basic business logic of each business type: how does a business get a module, and which setup can't have a module?

Right now **no business type is blocked from any module**. The only real limits come from module dependencies and
from billing, not from the business type.

### 1. Business types

| Type | For | Wording on screens |
|---|---|---|
| **Pharmacy** | Medical stores, chemists | Medicine, Generic, Formula, Strength, Batch, Expiry |
| **Inventory** | Shops: grocery, electronics, hardware | Item, Brand, Variant, Unit of Measure |
| **Inventory B2B** | Wholesalers, distributors, importers | Item, Customer account, Sales order, Vendor, Warehouse |

The type changes the **wording** and the **modules switched on at the start**. The owner can change the type later
in Business Settings; the wording changes and no data is touched.

### 2. Modules and what each needs

| Module | What it gives | Needs |
|---|---|---|
| **Core** | Business, users and roles, settings, customers, items catalogue, taxes, currencies | Always on, can't be switched off |
| **Stock & Purchasing** | Purchases, batches with expiry, FEFO/FIFO/Any batch, sale-price preferences, stock and expiry reports | — |
| **POS & Invoicing** | POS, receipts, invoices, returns, sales and profit reports | Stock |
| **Pharmacy Pack** | Generic medicine catalogue and import, formulas, strengths; new items start as "can expire" | Stock |
| **Inventory Pro** | Several warehouses, transfers, adjustments, stock by warehouse, valuation | Stock |
| **B2B Sales** | Quotations, sales orders, credit invoices, payments received, credit notes, customer statements | Stock |
| **B2B Purchasing** | Purchase orders, goods received, bills, bill payments, vendor credits, supplier statements | Stock |
| **Books / Accounting** | Chart of accounts, automatic journals, ledger, trial balance, P&L, balance sheet | — |
| **Expense** | Expenses with receipts, approvals, reimbursements | — |
| **Advanced Reporting** | Cross-module analytics, CSV exports, weekly summary email | — |
| **FBR Integration** | Fiscal numbers, QR codes | POS; **not available to anyone yet** ("coming soon", roadmap step 6) |

Switching a module on also switches on what it needs. For example, switching on POS also switches on Stock. You
can't switch off a module while another active module still needs it; Stock, for instance, stays on while POS is on.

### 3. Modules switched on when a business is created

| Type | Starts with |
|---|---|
| Pharmacy | Core, Stock, POS, **Pharmacy Pack** |
| Inventory | Core, Stock, POS, **Inventory Pro** |
| Inventory B2B | Core, Stock, POS, Inventory Pro, **B2B Sales, B2B Purchasing, Accounting, Expense** |

In the create-business wizard these come pre-ticked. The owner can untick or add any module there.

### 4. How a business gets more modules later

1. **Settings → Modules** (owner only):
   - While the super admin's **"Free for everyone"** setting is on (it is at launch), the module switches on
     immediately at no cost.
   - When it's off, the owner is sent to **Billing** to add the module to the plan (Stripe). The module switches on
     after payment.
2. **The super admin** can give a module to a business manually, or extend a trial.
3. **Turning a module off** keeps its data. Its screens are just blocked until it's switched on again.

### 5. Which setup can't have which module

| Setup | Can't have |
|---|---|
| Any business, any type | **FBR Integration** (not built yet) |
| Any business with **POS on** | Stock & Purchasing can't be switched off |
| Any business with **Pharmacy Pack, Inventory Pro, B2B Sales or B2B Purchasing on** | Stock & Purchasing can't be switched off |
| Paid mode ("Free for everyone" off), module not in the plan | That module, until it's added in Billing |

**Open decision (owner):** the project has a list of what each type is *allowed* to buy (`config/modules.php` →
`maximum`). By that list, **Inventory and Inventory B2B should not get the Pharmacy Pack**, and Pharmacy can get
everything. But the code never checks that list; only the demo data uses it. So today an Inventory or B2B business
can switch on the Pharmacy Pack from Settings → Modules. Options: (1) open to all: fix the list and docs to match
the code; (2) Pharmacy only: block the Pharmacy Pack for Inventory and B2B on the Modules page, the create wizard and
Billing.

---

## Q2. Can a business created as Pharmacy also have Inventory and B2B?

Yes. A business created as **Pharmacy** can have everything an Inventory or B2B business has.

"Inventory" and "B2B" are business types, not modules. What makes a business an Inventory or a B2B business is the
set of modules it has switched on, and a Pharmacy business can switch on all of them:

| To get what this type has | A Pharmacy business switches on |
|---|---|
| **Inventory** | **Inventory Pro** (several warehouses, transfers, adjustments, stock by warehouse, valuation). It already has Stock and POS. |
| **Inventory B2B** | Inventory Pro + **B2B Sales** + **B2B Purchasing** + **Accounting** + **Expense** |

The owner does this in **Settings → Modules**, or ticks the modules when creating the business. While "Free for
everyone" is on, a module switches on immediately; otherwise it goes through Billing.

What stays different from a real Inventory or B2B business:
- **Wording** follows the business type, so screens still say Medicine, Generic, Formula and Strength instead of
  Item, Brand and Variant.
- **Pharmacy Pack** stays on: generic medicine catalogue, formulas and strengths.
- New items start as "can expire".

So a Pharmacy business with every module is a superset: everything Inventory B2B has, plus the Pharmacy Pack. Demo
(QA database): **Demo Pharmacy** has every module switched on; **QA Pharmacy** has only the starting set (Stock, POS,
Pharmacy Pack).

---

## Q3. If a business has Inventory or B2B, can it also have Pharmacy, and the other way round?

In today's code, yes: every direction works, because nothing checks the business type when a module is switched on.

| Business type | Can also have | How |
|---|---|---|
| **Pharmacy** | Everything Inventory and B2B have | Switch on Inventory Pro, B2B Sales, B2B Purchasing, Accounting, Expense |
| **Inventory** | B2B | Switch on B2B Sales, B2B Purchasing, Accounting, Expense |
| **Inventory** | Pharmacy | Switch on **Pharmacy Pack** (generic catalogue, formulas, strengths) |
| **Inventory B2B** | Inventory | It already has it (Inventory Pro is in its starting set) |
| **Inventory B2B** | Pharmacy | Switch on **Pharmacy Pack** |

All of this is in Settings → Modules. Two things stay tied to the type itself:
- **Wording:** an Inventory business with the Pharmacy Pack still says "Item", not "Medicine".
- **"Can expire" default:** new items start as "can expire" only when the Pharmacy Pack is on, whatever the type.

The owner can also **change the business type** in Business Settings. That changes only the wording; data and modules
stay as they are.

**The one conflict:** see the open decision under Q1 (Pharmacy Pack for Inventory / B2B). Recommendation given:
**open to all**, because a general store or distributor may also sell medicines.

---

## Q4. What is the major difference between Inventory and B2B?

The difference is **how they sell**: Inventory sells over the counter, while B2B sells to other businesses on
documents and on credit. In the code, the difference is only the **modules switched on at the start** and **four
words**.

| | **Inventory** | **Inventory B2B** |
|---|---|---|
| **For** | Shops and retail: grocery, electronics, hardware, clothing | Wholesale and distribution: distributors, wholesalers, importers, manufacturers |
| **Typical sale** | Walk-in customer at the counter, paid on the spot (POS) | Business customer: quotation → sales order → invoice on credit → payment later |
| **Typical buying** | Purchase entry straight into stock | Purchase order → goods received → supplier bill → bill payment |
| **Modules at start** | Core, Stock, POS, Inventory Pro | Same **plus** B2B Sales, B2B Purchasing, Accounting, Expense |
| **Money owed** | Hardly any; mostly cash sales | Customer and supplier balances, statements, receivables/payables ageing |
| **Books** | Not on by default | Accounting on: every sale, purchase, payment and expense posts journals (ledger, trial balance, P&L, balance sheet) |

What only B2B has at the start:
- **B2B Sales:** quotations, sales orders, credit invoices, payments received, credit notes, customer statements.
- **B2B Purchasing:** purchase orders, goods received, supplier bills, bill payments, vendor credits, supplier
  statements.
- **Accounting** and **Expense** (expenses with approvals and reimbursements).

**Wording differences** (the rest of the wording is the same, for example Item):

| Inventory | B2B |
|---|---|
| Supplier | Vendor |
| Customer | Customer Account |
| Store | Warehouse |

There's no hard wall between them. An Inventory business can switch on B2B Sales, B2B Purchasing, Accounting and
Expense in Settings → Modules and then works like B2B, just with the shop wording. A B2B business still has the POS
for counter sales. Picking the type mostly decides which modules start on and what the screens call things.

---

## Q5. What is the difference between Pharmacy, B2B and Inventory?

All three types run on the same product with the same features; any of them can switch on any module. The type
decides **who the business is for**, **which modules start on** and **what the screens call things**.

| | **Pharmacy** | **Inventory** | **Inventory B2B** |
|---|---|---|---|
| **For** | Medical stores, chemists, hospital pharmacies | Shops: grocery, electronics, hardware, clothing | Wholesalers, distributors, importers, manufacturers |
| **How it sells** | Counter sale to patients (POS), paid on the spot | Counter sale to walk-in customers (POS), paid on the spot | To businesses on documents and on credit: quotation → order → invoice → payment later. POS still available |
| **How it buys** | Purchase entry straight into stock | Purchase entry straight into stock | Purchase order → goods received → supplier bill → bill payment |
| **Modules at start** | Core, Stock, POS, **Pharmacy Pack** | Core, Stock, POS, **Inventory Pro** | Core, Stock, POS, Inventory Pro, **B2B Sales, B2B Purchasing, Accounting, Expense** |
| **Its own extras** | Generic medicine catalogue and import, formulas, strengths | Several warehouses, transfers, adjustments, stock valuation | Customer and supplier balances, statements, ageing; full books; expenses with approvals |
| **Expiry** | New items start as "can expire": expiry is required on purchases | New items start as "can't expire": expiry is optional | Same as Inventory |
| **Selling order (default)** | FEFO, earliest expiry first | FEFO; items with no expiry sell oldest first | Same as Inventory |

### Wording on screens

| Pharmacy | Inventory | Inventory B2B |
|---|---|---|
| Medicine | Item | Item |
| Packaging | Unit of Measure | Unit of Measure |
| Manufacturer | Brand | Brand |
| Formula | Specification | Specification |
| Strength | Variant | Variant |
| Dosage Form | Item Type | Item Type |
| Generic Medicines | Item Catalogue | Item Catalogue |
| Supplier | Supplier | **Vendor** |
| Patient / Customer | Customer | **Customer Account** |
| Pharmacy | Store | **Warehouse** |

Short version:
- **Pharmacy** = Inventory + the medicine catalogue and medicine wording, with expiry required.
- **B2B** = Inventory + selling and buying on documents and credit, with full accounts.

Any of them can add the others' modules in Settings → Modules.

---

## Q6. Can a customer buy anything, in any business type, on credit or on partial credit? Can the business (user) buy from a supplier on credit or partial credit?

Credit doesn't depend on the business type. It depends on two modules, **B2B Sales** and **B2B Purchasing**, and any
business type can switch those on (B2B has them from the start).

### 1. A customer buying from the business

| Where | Full credit | Partial credit |
|---|---|---|
| **POS** (every type) | ❌ No | ❌ No |
| **B2B Sales invoice** (needs the B2B Sales module) | ✅ Yes | ✅ Yes |

- **POS is cash only.** It has no customer field, and Save & print stays blocked until cash received covers the
  total. The old Blade POS didn't have credit either, so this isn't a lost feature.
- **B2B Sales invoices are on credit by default:**
  - The invoice gets a **due date** from the customer's payment terms (days).
  - The invoice stays **unpaid**, becomes **partial** after any part payment, and **paid** when settled. The customer
    can pay in several instalments.
  - Paying **more** than is owed keeps the extra as customer credit for later invoices.
  - **Credit limit** per customer: an invoice that would push the balance over the limit is refused.
  - **Opening balance** per customer, plus customer statements and receivables ageing.

### 2. The business (owner/user) buying from a supplier

| Where | Full credit | Partial credit |
|---|---|---|
| **Purchases** (Stock module, every type) | ❌ Not tracked | ❌ Not tracked |
| **B2B Purchasing bill** (needs the B2B Purchasing module) | ✅ Yes | ✅ Yes |

- **A plain purchase** only records stock and cost. It has no "paid / owed" field, so the system can't tell whether
  the supplier was paid.
- **B2B Purchasing:** purchase order → goods received → **bill** with a due date, from the supplier's payment terms.
  The bill can be paid in parts (**unpaid → partial → paid**). It also handles vendor credits, the supplier's
  **opening balance**, supplier statements and payables ageing.
- Suppliers have **no credit limit** field; only customers do.

### In short

| Business setup | Customer can buy on credit / partial | Business can buy on credit / partial |
|---|---|---|
| Pharmacy or Inventory (starting modules) | ❌ POS is cash only | ❌ Purchases don't track payment |
| Any type with B2B Sales on | ✅ through invoices (not the POS) | — |
| Any type with B2B Purchasing on | — | ✅ through bills (not plain purchases) |
| Inventory B2B (starting modules) | ✅ | ✅ |

Two gaps a shop or pharmacy will likely hit (proposed, not built):
1. **Credit sale at the POS** (khata/udhaar): pick a customer, take part payment, the rest goes on their account.
2. **"Paid / owed" on a plain purchase**, so a shop can track what it owes suppliers without the full B2B flow.

---

## Q7. Does that mean B2B has the bills module and not the direct purchase module?

No. A B2B business has **both**. Plain **Purchases** belongs to the Stock module, which every business type has, B2B
included. B2B Purchasing is an extra layer on top of it.

| | **Purchases** (Stock module, every type) | **B2B Purchasing** (extra module) |
|---|---|---|
| What it records | Stock coming in | The order, the delivery and what you owe the supplier |
| Steps | One screen: supplier, items, cost, sale price, batch, expiry → saved | Purchase order → **goods received** → **bill** → **bill payment(s)** |
| Adds stock? | ✅ Immediately | ✅ At **goods received** (it creates the same purchase batches) |
| Tracks money owed? | ❌ No | ✅ The bill: due date, unpaid / partial / paid |
| Credit / part payment | ❌ | ✅ |

How the B2B flow works:
1. **Purchase order:** what you ordered from the vendor. No stock yet.
2. **Goods received:** stock enters the warehouse. Behind the scenes this creates normal purchase batches (batch,
   expiry, cost), so they appear under **Purchases** too.
3. **Bill:** the vendor's invoice, i.e. the amount you owe. **A bill can only be created from a purchase order**;
   there's no standalone bill.
4. **Bill payments:** pay the bill in one go or in parts.

So in a B2B business:
- **A quick cash buy** (no order, paid on the spot): use **Purchases** directly. It's simple, but nothing records
  whether the supplier was paid.
- **A buy on credit or with part payment:** use the **purchase order → goods received → bill** flow.

Two limits in the current setup:
- You **can't enter a bill directly**, for example when goods arrived without a purchase order.
- A plain purchase **can't be marked as on credit**.

These are the same two gaps listed under Q6.

---

## Related rules decided on 2026-11-18

- **Shared features = every business type** (POS, items, purchases, stock, batches, reports…), B2B documents
  included, unless the owner says otherwise; only wording/titles and type defaults differ
  ([BUSINESS_LOGIC.md](BUSINESS_LOGIC.md) §1.8).
- **Which batch is sold:** FEFO / FIFO / Any batch (most stock first). Business-wide setting → the item's own setting
  overrides it → on a POS cart row the cashier may pick the batch printed on the pack (the field starts empty; empty =
  the rule). A picked batch is sold first; if it runs short, the rest comes from the next batch by the rule. Expired
  batches are never sold.
- **"This item can expire"** (item form › Expiry): on = every purchase / goods receipt line needs an expiry date; off =
  optional. New items default from the business (Pharmacy Pack on → can expire).
- **Batches with no expiry date** never expire; under FEFO they sell after dated batches, oldest first (FIFO).
- **Profit in the packaging step** is shown on cost (sale 200, cost 100 → 100% on cost); the margin on the sale price
  (50%) shows on hover. The purchase form uses the same "over what you paid" basis.
- **Packaging** (Carton → Box → Piece) is set per item in **Packaging & prices**; new units can be added from a pop-up
  there. Packaging locks once the item has purchases, stock or sales (prices stay editable).
