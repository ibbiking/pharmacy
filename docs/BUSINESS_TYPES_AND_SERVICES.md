# Fixed business type, micro services, credit and Books sync

Branch: `feature/fixed-business-type-credit-books` (from `upgrade/php83-laravel13` @ `1c9d5cae`).
Requested by the product owner on 2026-11-19. Status: **requirements saved, open questions asked, no code yet.**

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
| **7** | Extras from SeeBiz Books / IMS / Expense the owner approves (Q1, Q10). | answers |
| **8** | **Demo guide**: `/guide` rebuilt as a tour per business type (every scenario, allowed / not allowed micro services), `docs/DEMO_GUIDE.md`, demo seed data updated to match. | all |

## 6. Open questions (asked 2026-11-19, answers go here)

See the chat message of 2026-11-19; answers will be copied here when given.
