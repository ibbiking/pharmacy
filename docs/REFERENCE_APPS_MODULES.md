# Reference apps: candidate micro services (phase 7, waiting for the owner)

The owner asked (2026-11-19) to explore the reference **Books**, **Inventory** and **Expense** apps (private repos
`ibbiking/books_backend`, `ims_backend`, `expense_backend`, development branches) and to **ask before adding**
anything. Their business ideas are noted here as features only: no code, names or branding are taken. Nothing below
is built yet. Each line says what we already have, what is missing, and a recommendation.

Legend: ✅ we have it · 🟡 partly · ➕ missing · Types: P = Pharmacy, R = Retail & Inventory, W = Wholesale & Distribution.

## From the reference Books app

| Feature in the reference app | Here today | Recommendation |
|---|---|---|
| Estimates, sales orders, invoices, credit notes, payments received | ✅ (W; credit + payments in every type) | — |
| Purchase orders, receives, bills, bill payments, vendor credits | ✅ (W; bills from purchases in every type) | — |
| Packages / delivery methods | ✅ shipments with carrier + delivery note (W) | Add a reusable **delivery methods** list (carrier names) — small, W only |
| **Advance payments** (customer pays before the invoice; supplier advance) and **advance refunds** | ➕ (a payment with nothing applied stays "unapplied", no screen to use it later) | **Recommended**, micro service "Advances & refunds", all types (P/R: deposits for special orders) |
| **Apply customer credit** to a later invoice ("credit utilize") and **refund credit in cash** ("credit refund") | ➕ (credit stays on the balance; listed in REMAINING.md) | **Recommended**, part of Customer / Supplier credit, all types |
| Backorders / drop-ship (order goods for a customer from a supplier) | ➕ | Optional, W only |
| Tax groups (several taxes as one) and tax history | 🟡 single taxes | Optional, all types |
| Fiscal year, period closing | ➕ (REMAINING: retained earnings) | **Recommended** inside Books, all types |
| PDF templates, number preferences, email history of documents | 🟡 one print layout, numbering series, business email | Optional: "Email the document as PDF" (REMAINING) for W |
| Import / export of contacts and items (CSV) with preview | 🟡 exports exist, imports for generic catalogue only | **Recommended**, Core, all types |
| Attachments, tags, activity log on documents | 🟡 audit log, expense receipts | Optional |
| Multi-currency documents with exchange rates | ➕ (REMAINING) | Optional, W only |

## From the reference Inventory app

| Feature | Here today | Recommendation |
|---|---|---|
| Dashboard: receivables / payables, top-selling items and customers, sales vs purchases | 🟡 dashboard + Advanced Reporting | Add receivables / payables and top customers cards when Customer / Supplier credit is on — small, all types |
| Contacts with statements, active / inactive, unique display names | ✅ customers + suppliers with statements | — |
| Credit notes confirm / delete, apply, refund | 🟡 | Same as "apply credit / refund credit" above |
| Item cache / bill items lookups | ✅ paged lookups | — |
| Invites, roles per module | ✅ team + roles per business | — |

## From the reference Expense app

| Feature | Here today | Recommendation |
|---|---|---|
| Expenses with categories, receipts, approvals, reimbursements | ✅ Expense (all types) | — |
| **Advances** to employees (cash given before spending, settled by expenses) | ➕ | **Recommended**, part of Expense, all types |
| **Expense reports / claims** (group several expenses into one claim to approve) | ➕ (REMAINING) | **Recommended**, part of Expense |
| **Trips** (travel requests with flight / hotel / car / meal preferences) | ➕ | Optional: only for businesses with travelling staff (W distributors) |
| Merchants, departments, tags | 🟡 merchant text field | Optional: departments for reports |
| **Purchase requests** (staff ask, manager approves, then a purchase order) | ➕ | Optional, W (needs purchase orders) |

## Question for the owner

Which of these should be built, and as part of which type(s)? Recommended first set:
1. **Apply credit / refund credit** (customers and suppliers), all types.
2. **Advances & refunds** (customer deposits, supplier advances), all types.
3. **Expense advances + expense reports (claims)**, all types.
4. **Fiscal year / period closing** in Books, all types.
5. **CSV import of customers, suppliers and items**, all types.
