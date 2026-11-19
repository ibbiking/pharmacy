# Work summary: 2026-11-19 (business types, credit, Books sync)

Branch: `feature/fixed-business-type-credit-books`, pushed. The full log with every decision is in
[BUSINESS_TYPES_AND_SERVICES.md](BUSINESS_TYPES_AND_SERVICES.md) §8.

| Commit | Phase |
|---|---|
| `3eade981` | Requirements (docs) |
| `68e5bb4a` | Phases 0–2: one business = one type |
| `42aea59b` | Phases 3–4: credit in every type |
| `bf8596de` | Phase 5: Wholesale & Distribution |
| `92fca64f` | Phase 6: Books sync |
| `c4a7be82` | Phases 7–8: demo guide, reference-app candidates |

All phases are done except phase 7, which is a list waiting for the owner's approval (nothing built from it).
Screens were not checked in a browser; automated tests cover the flows.

## What changed

1. **One business, one type** (no more "mixing"):
   - Types: Pharmacy, Retail & Inventory, Wholesale & Distribution (B2B).
   - On first login the create-business wizard warns "This is your final choice"; saving needs a confirm tick.
   - The type can never be changed later. A module of another type is refused everywhere: Modules page, wizard,
     Billing and the menu.
   - Existing businesses had modules of other types switched off; their data is kept.
2. **Micro services:** Warehouses, Books, Expense, Customer credit and Supplier credit are on from the start in all
   three types and can be switched off. B2B modules are on by default, Wholesale only.
3. **Credit in every type:**
   - **POS:** pick a saved customer and switch on "Rest on account" to take part of the money, or none. The credit
     limit is checked; walk-in customers can't buy on credit.
   - **Returns:** returning a credit sale creates a credit note instead of a cash refund.
   - **Purchases:** paid in full, partly paid or on credit; anything not paid in full gets its bill automatically.
   - **Payments:** payments, statements and ageing under the new "Customer accounts" and "Supplier accounts" menus.
4. **Wholesale & Distribution:**
   - The POS is called **Invoice**; the customer must be picked before adding items.
   - Issuing an invoice or bill asks what was paid now: nothing, in full, or part.
   - "Enter a supplier bill (stock in)" enters a bill without a purchase order.
   - **Shipping:** carrier, tracking, packed → shipped → delivered, printable delivery note.
   - Estimates, sales orders and purchase orders were already built.
5. **Books sync:** switching Books on converts all past data in the background; every screen shows
   "Your data is being converted…".
   - New sales and purchases save normally; their Books entries wait and are added in order once the conversion
     finishes.
   - A check every 5 minutes (`accounting:drain-pending`) finishes anything left if a conversion stops partway.
6. **Demo guide:**
   - `/guide` has a **"Demo tour per type"**: a tab per type, allowed modules as blocks, every step in order.
   - Full version for client demos: [DEMO_GUIDE.md](DEMO_GUIDE.md).
   - Demo data now includes credit sales, purchases on credit and a delivered shipment.

## Tests

25 new tests (BusinessTypeRuleTest, CreditFlowsTest, WholesaleFlowsTest, BooksSyncTest) plus updates to older ones.
Last full run: 316 passed, 3 failed. One was real (soft-delete rule for new tables), fixed and passing since; the
whole suite was not re-run after that fix. The other 2 fail only on MariaDB 10.4 (office laptop runs MySQL 8):
`SuperAdminScreensTest` (window function) and `BillingCatalogTest` (JSON collation).

## Databases (home laptop)

- `pharmacy_laravel13` backed up to `F:\Projects\db_backups\pharmacy_laravel13_before_business_types_20261119.sql`,
  then migrated.
- `pharmacy_qa` (demo) rebuilt with the new demo data; demo login unchanged (see [DEMO_ACCOUNTS.md](DEMO_ACCOUNTS.md)).
- On another machine after pulling: `php artisan migrate`, `php artisan optimize:clear`,
  `php artisan permission:cache-reset`, `npm run build`.

## Waiting for the owner

1. **Security:** revoke the GitHub token pasted in chat; change the mail app password and super-admin password that
   were pasted earlier. Nothing secret is stored in any file.
2. **Phase 7:** which reference Books / Inventory / Expense features to build. List with recommendations:
   [REFERENCE_APPS_MODULES.md](REFERENCE_APPS_MODULES.md). Recommended first set:
   - applying or refunding a customer's leftover credit;
   - advance payments from customers and to suppliers;
   - expense advances and expense claims;
   - fiscal year closing in Books;
   - importing customers, suppliers and items from a spreadsheet.
3. **Branches / sub-businesses:** not built. Warehouses already cover multiple locations; branches with their own
   staff and POS need their own design first. Noted as a future idea.
4. **Merge:** after a browser check, merge this branch into `upgrade/php83-laravel13`.
