# Reference screenshots

Source images live on the product owner's machine (`~/Downloads/image (N).png`), not in git.
They are **references for behaviour and layout**, not designs to copy pixel-for-pixel. The visual
language we follow is in [DESIGN_SYSTEM.md](DESIGN_SYSTEM.md) (screens 410–416, "SeeATS").

| Range | App | Used for |
|---|---|---|
| 410–416 | SeeATS (own product) | Visual design language: sidebar, KPI cards, charts, settings pages |
| 417–445 | SeeBiz **Books** | Dashboard, products, customers, suppliers, estimates, invoices, payment receipts, credit notes, bills |
| 446–464 | SeeBiz **Books** | Bill payments, chart of accounts, journal, ledger, trial balance, reports hub & reports |
| 465–480 | SeeBiz **IMS** | Dashboard, returns, packages, sales orders, purchase orders, reports hub, settings/subscription menu |
| 481–502 | SeeBiz **Expense** | Dashboards, trips, expenses, reports (claims), advances, approvals |

---

## 417–445 — SeeBiz Books (books.seebiz.com)

None of these show package plans / subscription / pricing / module activation / organization creation /
SMTP / email templates / preferences (Settings is in the sidebar but never expanded).

| # | Module & screen | Key fields / columns / actions | Business rules / flow hints |
|---|---|---|---|
| 417 | Dashboard (lower) | Order details tabs Estimates/Invoices/Bills (Date, No, Customer, Status, Amount); Top Customers carousel (Company, License no, Balance Due, Total Sale); Sales vs Purchase weekly chart, "This Month" filter | Estimate statuses Draft/Closed |
| 418 | Dashboard (top) | Estimates donut; Invoices Required/Invoiced; Purchase info (Qty Ordered, Total Cost, Bills to be paid, Low stock 320, Active 1038/1038); Receivables & Payables due/overdue with progress bars; Top Selling Products carousel | Dashboard pulls inventory stats |
| 419 | Dashboard (bottom) | Estimates Summary daily line; Estimate total, Credits issued, Credit Notes count/amount | — |
| 420 | Products & Services list | KPI: Low stock, Out of stock; Filters, Show Columns, Rows 20, pager; cols Name, SKU, Type, Sales Price, Cost, Category, QOH, Reorder; pin-column locks; "New Product" split button | SKUs `IMS-…` (shared item master with IMS); red row accent = out of stock |
| 421 | Product edit (top) | Type (Inventory), image, Name*, SKU* + Generate SKU + format gear, description (255), Category, "Calculate Tax per ml/gram" | Configurable SKU auto-format |
| 422 | Product edit (mid) | Brand, Selling unit*, Manufacturer, UPC/Barcode, Weight, MPN, Dimensions, EAN, ISBN; Opening qty, Opening value/unit, Reorder point, Inventory asset account* | Opening stock posts to Inventory Asset GL |
| 423 | Product edit (bottom) | Sales: description, price, Income account*, Sales tax. Purchases: description, Cost, Expense account* (COGS), Preferred supplier; Notes | Item ↔ Income / COGS / Inventory accounts |
| 424 | Customers list | KPI Active/Inactive; cols Display/Company, Phone, Email, Current Balance; A–Z index; "New customer" | Balances can be negative |
| 425 | Customer create (top) | Title, First*/Last name, Company, Display name*, Sales Person, Referred By, Department, Designation, License no, Currency*, Email, Language | Currency per contact |
| 426 | Customer create (contact) | Phones/mobiles (primary/secondary), Website, social links | — |
| 427 | Customer create (address) | Billing + Shipping (Attention, Phone, Street 1/2, City, State, Zip, Country, Fax), "Same as billing" | — |
| 428 | Customer create (bottom) | Attachments (5 MB); Payment method, Terms, Account no; Tax exempt + reason/details/rate; Opening balance + As-of date | Exemption fields gated by checkbox |
| 429 | Suppliers list | Same grid as customers; "New supplier" | — |
| 430–432 | Supplier create | As customer + Business ID / SSN, Billing rate per hour, opening balance | — |
| 433 | Estimates list | KPI Sent/Rejected/Closed/Accepted (amount+count); cols Date, No, Customer, Expiry, Sending date, Amount, Status | Draft→Sent→Accepted/Rejected→Closed |
| 434 | Estimate create (header) | Customer*, Billing addr, Email, Payment Terms, auto EST-000003, Reference, Date*, Expiry* (+7d default), Salesperson, Notes, T&C | Per-business number series |
| 435 | Estimate create (lines) | Product/Service, **Warehouse**, Qty, Rate, Discount %/amt, Tax, Amount; drag rows; bulk add; clear all; Subtotal, Discount, Tax, Shipping, Total | Pick customer before products |
| 436 | Invoices list | KPI Sent/Overdue/Paid; cols Date, No, Customer, Amount, Balance, Status, Due | Multi-currency amounts |
| 437–438 | Invoice create | Customer*, Terms, Date*, Due (derived), auto INV-000497, Reference, Order no, Salesperson; lines as estimate; Adjustment, Balance due | Due date from payment terms |
| 439 | Payment receipts list | KPI Applied/Unapplied/Partially applied; cols incl. multiple Invoice Nos, Credit | One receipt → many invoices; excess = credit |
| 440 | Payment receipt create | Customer* or find by invoice no; Date*, auto no, Reference, Payment method* (GL account), Amount*, "Received full amount" | Method = cash/bank GL account |
| 441 | Credit notes list | KPI Open/Consumed/Partially applied | Draft→Open→Partially Applied→Consumed |
| 442 | Credit note create | Type: New / Link invoices / Without items; auto CR-; Deductions, Payment due, Issuable credits | Credit offsets invoice dues |
| 443 | Bills list | KPI Open/Overdue/Paid | Draft→Open→Overdue→Paid |
| 444–445 | Bill create | Supplier*, Date*, auto BL-, Order no, Reference, Terms, Due*; lines: Product, **Expense account**, Warehouse, Qty, Rate, Tax; bill-level Discount, Rounding/Adjustment | Each bill line → expense GL |

**Flows (Books):** Customer → Estimate → Invoice → Payment receipt (multi-invoice, credit carry) → Credit note.
Supplier → Bill → Bill payment. Items map to Income/COGS/Inventory GL accounts; contacts carry dated opening balances.

**Layout patterns (Books):** grouped left sidebar; top bar with global "+" quick-create, entity search, settings, help.
Every list = title + split "New X" button + 2–4 status KPI cards + Filters/Download/Columns/Rows-per-page/pager,
status pills, row ⋮ menu. Forms = one long centred card, two-column fields, char counters, drag-drop attachments,
read-only auto numbers. All transaction documents share one line-item grid + right-aligned totals block.

---

## 446–464 — SeeBiz Books (accounting & reports)

| # | Module & screen | Key fields / columns / actions | Business rules / flow hints |
|---|---|---|---|
| 446 | Bill payments list | Date (sortable), Supplier, Payment no, Amount; Filters, Columns, Rows 20, pager; total count | Sort/page state in URL |
| 447 | Bill payment detail (split view) | Supplier card, Memo, Reference, Bill no, Date, Method (GL 1001), Amount; tabs Details/Attachments/History; PDF/print/email/attach | Payment applied to bill(s) → bill Paid |
| 448 | Chart of accounts list | Name, Category, Type, Sub type, Account No, Currency; "+ New Account"; 67 seeded accounts | Category → Type → Sub type |
| 449–450 | New account modal | Category radio (Assets/Liabilities/Equity/Income/Expenses), Type, Sub-type, Name*, No, Description, Bank acct no, Is sub-account + Parent, Currency*, Default tax, Opening balance + as-of date | Cascading type pickers; account hierarchy |
| 451–452 | Journal | Date, Transaction type, Num, Name, Narration "System Generated Entries", Account, Debit, Credit; Customize/Print/Download | Auto-posting: Invoice (Dr A/R, Cr Income + Dr COGS, Cr Inventory), Payment receipt, Invoice payment, Refund, Credit note (reverses income + restocks) |
| 453–454 | Ledger | Account, Transaction, No, Date, Dr, Cr, running balance tagged Dr/Cr; Balance b/f and c/d; TOTAL | Opening stock → Inventory Asset; bills increase / invoices decrease inventory |
| 455 | Trial balance | Account, No, Description, Category, Type, Sub-type, Dr/Cr balances; balanced totals | Opening Balance Equity offsets opening balances |
| 456 | Reports hub | Accounts (Journal, Ledger, TB, GL); Sales (by product/customer/salesperson); Receivables (invoice, estimates, customer balance, AR aging summary/details); Purchase (bill, bill payment, by supplier); Payment receipts/refunds/credit notes; Taxes (summary, by product/customer/supplier, time-duration variants) | Full Books report catalogue |
| 457–461 | Sales by product / customer / salesperson | Customize panel: date presets (Today…Last Year) + entity filter; columns incl. tax, sales with tax; salesperson report nets credit notes | Net sales = invoices − credit notes; unassigned → "Other" |
| 462 | Refund report | Date, Payment #, Customer, Method, Reference, Amount, total | — |
| 463 | Invoice report | Invoice/Due date, No, SO/EST no, Customer, Status, Balance due, Amount | Invoice links to source SO/estimate |
| 464 | Tax summary | Tax, Authority, Rate, Expected/Collected to collect, Expected/Paid to pay | Tracks output and input tax |

## 465–480 — SeeBiz IMS (inventory.seebiz.com)

| # | Module & screen | Key fields / columns / actions | Business rules / flow hints |
|---|---|---|---|
| 465–466 | Dashboard | Widgets with own period pickers: Sales info (SO donut, invoices required/invoiced), Package & Shipment (to pack/ship/deliver), Purchase info (qty ordered, cost, qty to receive, bills to pay, low stock, active items 1038/1038), Receivables/Payables due vs overdue, Top selling items, Order details tabs, Top customers, Sales vs Purchase chart | **"Free Plan Activated. Upgrade" banner + "Subscription Plan" menu**; item count vs plan cap |
| 467 | Sales returns list | Date, SOR-, SO-, Customer, Add to stock, Status, Return items | Draft → Confirmed → Received |
| 468 | Sales return detail | Tabs History / Return Receive; SO link, return date, items added back to stock, reason; items × warehouse × ordered/shipped/returned | Return tied to SO + warehouse + reason |
| 469 | Create return receive (modal) | RR- auto no, receive date*, reason; Item, Warehouse, Return qty, Already received, Currently receiving | Partial receiving; restock on receive |
| 470–471 | Packages list / create | PKG- auto no, Sales order*, date*; Items × Warehouse × Ordered/Packed/Stock/Packed qty | Not Shipped → Shipped → Delivered; partial packing |
| 472 | Sales orders list | Date, SO, Customer, Status, Packed (dot), Invoiced (dot), Amount | Draft/Confirmed; empty/half/full progress dots |
| 473–474 | Sales order detail | Tabs History/Packages/Invoices(/Sales Return); items with warehouse & unit; edit/PDF/print/email/clone/delete; "+ Package" | Edit/delete/+Package disappear once fully packed & invoiced |
| 475–476 | Sales order create | Customer*, Bill/Ship to, auto SO-, Order date*, Expected shipment, Salesperson, Delivery method, Notes, T&C yes/no, Reference; lines Item, **Warehouse**, Qty, Price, Tax, Amount; Discount $/%, Adjustment, Shipping | Items locked until customer chosen; "Save as Draft ▾" split button |
| 477 | Purchase orders list | Date, PO, Vendor, Status, Billed (dot), Received (dot), Amount | Draft → Issued → Closed / Cancelled |
| 478 | Purchase order create | Vendor*, auto PO-, Order date*, Delivery date*, Reference, **Warehouse*** (per PO), T&C; lines Item, **Account**, Qty, Price, Tax | Items locked until vendor chosen; line GL account (default COGS) |
| 479 | PO detail (split view) | Status chip, edit/PDF/print/email/copy/delete, "Create ▾"; tabs History/Receives/Bills | PO → Receive → Bill |
| 480 | Reports hub + Settings menu | Inventory: details, **valuation summary, FIFO cost lot tracking**, warehouse, committed stock, product sales, active POs; Sales: SO, invoice, payments, refunds, packaging, returns, by customer/item/salesperson, fulfilment; Purchase: PO, receive, bill, payments made | Settings → **Manage Businesses**, User & Access, System, Integrations, My Account; separate **Subscription Plan** item |

## 481–502 — SeeBiz Expense (expense.seebiz.com)

| # | Module & screen | Key fields / columns / actions | Business rules / flow hints |
|---|---|---|---|
| 481 | My view dashboard | Pending approval, Unreported expenses, Unreported advances; Quick add (expense/report/advance); recent reports | Sidebar "My view" vs "Admin view"; PWA |
| 482–486 | New trip | Domestic/International, Name*, Purpose, Official, Amount*, **Approver***; itinerary segments Flight (one-way/round/multi-city, seat/meal pref, class), Hotel, Car (need driver), Bus, Train, Ferry | Save as Draft / Save & Submit / **Save & Approve** (self-approval) |
| 487 | Expenses list | Exp-000007, Date, Report, Category, Merchant, Amount, Status | Status follows parent report |
| 488–489 | New expense | Single / **Bulk add**; Report*, Personal, Date*, Merchant*, Vendor* (+temporary vendor), Customer*, Reference*, Currency*, Itemize, Category*, Amount*, Paid through, Claim reimbursement, Tax*, Description*, Payment mode*, **Attachment*** | Receipt mandatory; multi-currency; links to contacts |
| 490–491 | Reports (claims) list / new | RE- no, Name, Trip, Expense count, Approver, Total, Reimb. amount, Status; new: name, purpose, duration, trip | DRAFT / APPROVED / REJECTED |
| 492 | Record advance | Amount*, Date*, Paid through, Reference, Notes, Apply to trip | Advance → trip → report |
| 493–495 | Report detail | Tabs Expenses / Advances & Refund / History; Submit; add unreported expenses; Total, Non-reimbursable, Applied advance, **To be reimbursed** | Reimbursable − applied advances = payable to employee |
| 496–497 | Approved report | Expense cards with "Tax (Exclusive)", per-line comments; delete disabled once approved | Lock after approval |
| 498 | History tab | Timeline: submitted to approver email, expense added/updated | Full audit trail + approver email notification |
| 499 | Trips for approval | Tabs All / Awaiting Approval / Awaiting Revision / Approved / Rejected / Discard / Closed / Void (counts) | Trip state machine |
| 500–501 | Reports for approval | Tabs All / Awaiting / Approved / Rejected / **Reimbursed** / ReCalled; side panel Reimbursed / Reject | Approve → Reimburse; recall |
| 502 | Admin dashboard | Trip KPI cards by status; approver details incl. **self-approved** trips/reports | Self-approval tracked; Settings only in admin view |

**Cross-app observations**
- IMS and Books share one item master (SKUs `IMS-…`) and post into one ledger → in our product: **one item/stock master, one ledger**, modules write to it.
- Every transaction document: auto number series per business (EST-, SO-, PKG-, INV-, PR-, CR-, PO-, RR-, BL-, SOR-, RE-, Exp-), status pills,
  split list→detail view, PDF/print/**email**/clone actions, History (audit) tab, related-document tabs.
- Plan gating exists in IMS ("Free Plan Activated. Upgrade", item caps) — the pricing/plan screens themselves were not captured.
