# Demo guide: one tour per business type

For the product owner to walk a client through the app. The same tours are on the public **Guide** page
(`/guide` → "Demo tour"). Demo logins and data: [DEMO_ACCOUNTS.md](DEMO_ACCOUNTS.md) (owner `demo.owner@example.test`,
password `DemoPass!2026`; switch business from the top of the sidebar).

**The one rule to say first:** a business has **one type for ever**: Pharmacy, Retail & Inventory, or Wholesale &
Distribution (B2B). It is chosen when the business is created (the wizard asks to confirm it is final) and never
changes or mixes. Another type = another business; one login can own several and switch between them.

---

## What each type can have

| Module (micro service unless marked) | Pharmacy | Retail & Inventory | Wholesale & Distribution |
|---|---|---|---|
| Core, Stock & Purchasing, POS | ✅ always (part of the type) | ✅ always | ✅ always (the POS is called **Invoice**) |
| Pharmacy Pack (generic medicines, formulas, strengths, expiry by default) | ✅ always (part of the type) | ❌ never | ❌ never |
| Warehouses (several stores, transfers, adjustments) | ✅ on from the start | ✅ on from the start | ✅ on from the start |
| Customer credit (credit sales for saved customers, payments, statements, ageing) | ✅ on from the start | ✅ on from the start | ✅ on from the start |
| Supplier credit (purchases on credit with their bill, bill payments, statements) | ✅ on from the start | ✅ on from the start | ✅ on from the start |
| Books / Accounting | ✅ on from the start | ✅ on from the start | ✅ on from the start |
| Expense | ✅ on from the start | ✅ on from the start | ✅ on from the start |
| B2B Sales (estimates, sales orders, direct invoices, shipping, credit notes) | ❌ never | ❌ never | ✅ on from the start |
| B2B Purchasing (purchase orders, goods received, bills, vendor credits) | ❌ never | ❌ never | ✅ on from the start |
| Advanced Reporting | ➕ add any time | ➕ add any time | ➕ add any time |
| FBR Integration | ⏳ coming | ⏳ coming | ⏳ coming |

"On from the start" modules can be switched off and on again in **Settings → Modules** (data is kept). "Always" ones
can't be switched off. "Never" ones don't appear anywhere for that type (not in the menu, not on the Modules page, not
in Billing).

---

## Start (every type): sign up and create the business

1. Landing page → **Get started** → pick the type → name + email → open the email link → set a password.
2. First sign-in: the **create-business wizard** opens by itself.
   - Basics (name, phone, currency, address).
   - **Business type**: three cards; tick "I understand: this business will always be a … business".
   - **Modules**: only the ones the type may have; the type's own modules are locked on.
   - Review → **Create business**.
3. Show **Settings → Modules**: "Part of every … business" (locked) and "Micro services" (switchable).

---

## Tour 1: Pharmacy (Demo Pharmacy)

1. **Medicines**: Generic Medicines (import from the catalogue) → Medicines → a medicine → Packaging & prices
   (Box → Strip → Tablet, profit % on cost). Every medicine "can expire" by default.
2. **Buying**:
   - Purchases → Add purchase: supplier, bill no, items with batch + expiry.
   - **Payment to the supplier**: *Paid in full* / *Partly paid* (amount) / *On credit*.
   - On credit or part paid → its **bill** is kept: Supplier accounts → Bill payments → pay it later (in parts if
     needed); the supplier statement and **Payables ageing** show what is owed.
   - Demo: the "CT-…" purchase is on credit with 40 % paid.
3. **Selling at the POS**:
   - Walk-in sale: scan / search, the app picks the batch (FEFO), take cash, **Save & print**.
   - Pick the batch printed on the pack in the cart row (sold first).
   - **Credit sale**: pick a saved customer at the top → switch on **Rest on account** → take part (or none) of the
     money. The panel shows paid now / on account / what they owe before and after / their limit. Over the limit →
     refused.
   - Walk-in customers can never buy on credit.
4. **Returns**: POS invoices → open one → Return. A cash sale → cash refund. A credit sale → **credit note**: it lowers
   what the customer owes (more than they owe stays as their credit).
5. **Customer accounts**: Payments received → record money from the customer (allocate to their open sales);
   customer statement; **Receivables ageing**.
6. **Warehouses**: add a branch store, transfer stock, write off damaged stock, choose the POS warehouse.
7. **Books**: Chart of accounts, Journals (every sale, purchase, payment and expense posted automatically), Financial
   reports (P&L, balance sheet, trial balance). Switching Books off and on again converts the history in the
   background; the notice "Your data is being converted…" shows until it is done, and new work waits its turn.
8. **Expenses**: new expense with receipt → approval (self-approval policy) → reimbursement.
9. **Reports**: sales, profit & loss (per batch cost), expiry, stock alerts; Advanced Reporting if added.
10. **Not here**: no estimates, sales orders, purchase orders, direct bills or direct credit notes; to do wholesale,
    create a second business of type Wholesale & Distribution.

## Tour 2: Retail & Inventory (Demo Inventory Store)

Same as the pharmacy tour, with shop words (Item, Brand, Variant, Unit of measure) and these differences:

1. Items **can't expire** by default (switch it on per item, e.g. food). No generic medicine catalogue, formulas or
   strengths (no Pharmacy Pack, ever).
2. Purchases: paid / partly paid / on credit, exactly as in the pharmacy.
3. POS: walk-in cash sales and credit sales for saved customers; returns of credit sales become credit notes.
4. Warehouses, Customer / Supplier accounts, Books, Expense, Reports: as in the pharmacy.
5. **Not here**: Pharmacy Pack, B2B Sales, B2B Purchasing.

## Tour 3: Wholesale & Distribution (Demo B2B Distributors)

1. **Customers** with payment terms, credit limit and opening balance.
2. **Invoice screen** (the POS layout): pick the customer first: until then search and adding items are blocked; no
   walk-in. Paid in full, part paid or **Rest on account**.
3. **Selling on documents**: Sales documents → **Quotation** (estimate) → mark sent / accepted → **Sales order** →
   confirm → **Create invoice** → **Issue** (takes the stock) and choose *Nothing yet / In full / Part of it* (+ method).
4. **Shipping**: on the order or invoice → **Add shipment** (carrier, tracking, packages, weight) → Mark shipped →
   Mark delivered; print the **delivery note**. Demo: the first invoice is delivered by "TCS Courier".
5. **Credit notes** from an invoice (restocks); a paid invoice's credit stays as the customer's credit.
6. **Buying on documents**: Purchase documents → **Purchase order** → **Receive goods** (batch, expiry, sale price)
   → **Create bill** → **Enter bill** and choose what was paid now → pay the rest later; **Vendor credit** from a bill.
7. **Direct supplier bill**: Bills → *Enter a supplier bill (stock in)* (= the purchase form with paid / partly paid /
   on credit).
8. **Customer / Supplier accounts**: payments, statements, receivables and payables ageing (overdue in red).
9. **Books, Expense, Warehouses, Reports**: as in the other tours.
10. **Not here**: Pharmacy Pack.

---

## Scenarios checklist (all types unless marked)

| Scenario | Where | Result |
|---|---|---|
| Cash sale to a walk-in customer | POS | paid, receipt |
| Sale to a saved customer, paid in full | POS (customer picked) | paid, on their history |
| Sale on credit / part payment | POS → Rest on account | linked invoice unpaid / partly paid, AR in the books |
| Credit limit reached | POS | sale refused with the numbers |
| Return of a cash sale | POS invoice → Return | cash refund |
| Return of a credit sale | POS invoice → Return | credit note, owes less |
| Payment received | Customer accounts → Payments received | invoice partly paid / paid |
| Purchase paid / partly paid / on credit | Purchases → Add purchase | bill unpaid / partly paid (none when paid) |
| Pay a supplier | Supplier accounts → Bill payments | bill partly paid / paid |
| Statements and ageing | Customer / Supplier accounts | what is owed, overdue buckets |
| Books switched on later | Settings → Modules | history converted in the background, notice shown, new work follows in order |
| Expense with approval | Expenses | posted to the books when approved |
| Estimate → order → invoice → shipment (Wholesale) | Sales documents | stock out at issue, delivery note |
| Purchase order → receipt → bill (Wholesale) | Purchase documents | stock in at receipt, bill payable |
