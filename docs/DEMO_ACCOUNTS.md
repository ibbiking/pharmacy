# Demo accounts

Seeded by `database/seeders/DemoDataSeeder.php` into the **QA database** (`pharmacy_qa`) and, when switched on, a live
or demo server (docs/DEPLOYMENT.md §12). The password below is the local / QA one; a live server uses its own
`DEMO_PASSWORD` from `.env`. Demo logins can't change their email or password, and a demo server (`DEMO_INSTANCE=true`)
rebuilds all of this every night with `php artisan demo:rebuild`. On a demo server each visitor signing in with these
logins gets a private copy (see docs/DEPLOYMENT.md §12), deleted after an hour without activity.

**QA server:** `DB_DATABASE=pharmacy_qa MAIL_MAILER=log QUEUE_CONNECTION=sync php artisan serve --host=127.0.0.1 --port=8091`
then open http://127.0.0.1:8091/admin/login

| Who | Email | Password | What they can do |
|---|---|---|---|
| **Owner** (owns all three businesses) | `demo.owner@example.test` | `DemoPass!2026` | Everything, in every business. Switch business from the menu at the top of the sidebar. |
| Accountant (member of all three) | `demo.accountant@example.test` | `DemoPass!2026` | Books, financial reports, approvals and reimbursements, payments, analytics (custom role "Accountant"). |
| Cashier (member of all three) | `demo.cashier@example.test` | `DemoPass!2026` | POS only (built-in "Sales person" role). |

Older QA logins still work: `qa-owner@example.test` / `QaPass!2026` (QA Pharmacy) and super admin
`qa-admin@example.test` / `QaAdmin!2026`.

## The three businesses: every module each type may have

One business = one business type (2026-11-19): each demo business has every module **its own type** allows and
nothing of another type. The tour per type is in [DEMO_GUIDE.md](DEMO_GUIDE.md).

| Business | Type | Modules switched on |
|---|---|---|
| Demo Pharmacy | Pharmacy | Core, Stock & Purchasing, POS, **Pharmacy Pack**, Warehouses, Customer credit, Supplier credit, Books, Expense, Advanced Reporting |
| Demo Inventory Store | Retail & Inventory | Core, Stock & Purchasing, POS, Warehouses, Customer credit, Supplier credit, Books, Expense, Advanced Reporting |
| Demo B2B Distributors | Wholesale & Distribution | Core, Stock & Purchasing, POS (named **Invoice**), Warehouses, Customer credit, Supplier credit, **B2B Sales, B2B Purchasing**, Books, Expense, Advanced Reporting |

FBR Integration is not offered yet (Roadmap step 6). The lists per type live in `config/modules.php` → `types`.

## Email: each business has its own SMTP settings

Every business email (team invites, stock alerts, weekly summary, expense notifications) is sent through **that
business's own account** (Settings → Email & branding, while working in that business). The demo businesses come with
their own sender identity; the SMTP account details (host, username, password) must be entered once per business by
the owner, because real mailbox passwords can't be created by a seeder. Until then that business falls back to the
platform mailer under its own name. Use **Send test email** on the same screen to check each one.

| Business | Sender name | Sender / reply-to address |
|---|---|---|
| Demo Pharmacy | Demo Pharmacy | `pharmacy@demo.example.test` |
| Demo Inventory Store | Demo Inventory Store | `store@demo.example.test` |
| Demo B2B Distributors | Demo B2B Distributors | `sales@demo.example.test` |

## What is already in each business (dated over the last ~6 weeks)

Every record was made through the real screens' services, so each flow can be followed from start to end:

1. **Setup:** PKR currency, GST 18 %, 2 suppliers (with terms and an opening balance), 3 customers (terms, credit
   limits, one opening balance), 5 items with packaging (e.g. Box → Strip → Tablet), "Main warehouse" + "Branch store",
   opening bank balance (manual journal), team of three.
2. **Stock in:** two purchases (batches with expiry for pharmacy / B2B goods, none for stationery): the first paid,
   the second **on credit** with 40 % of its bill paid a week later (Supplier accounts).
3. **Inventory Pro:** a transfer Main → Branch, a damaged-stock adjustment, the POS sells from the Branch.
4. **POS:** 9 sales and 1 return. Pharmacy and Retail: two of them to saved customers **on credit** (one part paid,
   one unpaid; Customer accounts). Wholesale: every sale is for a saved customer (Invoice screen).
5. **B2B purchasing (Wholesale only):** purchase order → goods received (part of one line short) → bill → 60 % paid → vendor credit;
   a second purchase order still waiting for delivery.
6. **B2B sales (Wholesale only):** quotation → accepted → sales order → invoice (now **overdue**, half paid by cheque,
   **shipped** with TCS Courier and delivered: delivery note on the invoice); an invoice paid
   in full then partly credited (credit note kept as customer credit); an open invoice not yet due; a sent quotation;
   a declined quotation.
7. **Expenses:** rent paid by the business (approved by the accountant), fuel paid by the accountant (approved and
   reimbursed by the owner), an electricity bill waiting for approval, a rejected one (no receipt).
8. **Books:** every step above posted automatically; trial balance and balance sheet balance.
9. **Analytics:** weekly summary email on.

## Try these flows

- Open **Selling → Sales documents → Sales invoices**, click the overdue invoice (it opens on the right), *Record
  payment* for the rest → it turns *Paid*; see the journal under **Finance → Accounting → Journals**.
- **Buying → Purchase orders**, open the waiting order → *Receive goods* → *Create bill* → *Enter bill* → pay it.
- **Finance → Expenses**, sign in as the accountant to approve the electricity bill (the owner can't approve their own
  unless the policy allows it).
- **Analytics** for 30 / 90 days, *Export CSV*.
- Switch to the cashier: only the POS is reachable.

## Re-seeding

The seeder skips a business that already exists for the demo owner. For a fresh copy, restore an empty QA backup (or
create a new QA database), migrate, then:

```
DB_DATABASE=pharmacy_qa php artisan migrate --force
DB_DATABASE=pharmacy_qa QUEUE_CONNECTION=sync MAIL_MAILER=log php artisan db:seed --class=DemoDataSeeder --force
```
