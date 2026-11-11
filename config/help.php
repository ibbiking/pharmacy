<?php

/*
|--------------------------------------------------------------------------
| Helper pages ("How it works", Roadmap 4h)
|--------------------------------------------------------------------------
| Plain-language explanations of each flow and preference. Content follows docs/BUSINESS_LOGIC.md:
| keep them in sync when a rule changes.
|
| topic key => [
|   title, icon, accent, module (shown only when that module is on), summary,
|   steps: [[title, text, icon, route?]]   → interactive flow
|   examples: [[title, text]]              → worked examples
|   faqs: [[q, a]]
| ]
*/
return [
    'getting-started' => [
        'title' => 'Getting started',
        'icon' => 'rocket-takeoff', 'accent' => 'var(--chart-1)', 'module' => 'core',
        'summary' => 'Set up a business, choose its modules, and get ready to sell.',
        'steps' => [
            ['Create your business', 'Name, currency and type (Pharmacy, Inventory or Inventory B2B). The type sets the wording and the recommended modules.', 'building-add', 'business.create'],
            ['Switch on modules', 'Only what you need: Stock, POS, Pharmacy Pack… Free during launch. Switch modules off any time; nothing is deleted.', 'puzzle', 'modules.index'],
            ['Email & branding', 'Connect your email account and upload your logo so invoices and receipts go out under your name.', 'envelope-paper', 'settings.email'],
            ['Numbering', 'Choose how your invoices and returns are numbered (e.g. INV-000001).', '123', 'settings.numbering'],
            ['Add taxes', 'Add the taxes you pay or charge, such as GST, so purchases can carry them.', 'percent', 'taxes.index'],
        ],
        'examples' => [],
        'faqs' => [
            ['Can I run more than one business?', 'Yes. Create another business from the business menu at the top of the sidebar and switch between them any time. Each business has its own stock, invoices, numbering and settings.'],
            ['Who can change these settings?', 'The owner of the business. Sales persons only use the POS.'],
        ],
    ],

    'sale-price-preferences' => [
        'title' => 'Sale price preferences',
        'icon' => 'tags', 'accent' => 'var(--chart-2)', 'module' => 'stock',
        'summary' => 'How the system decides the price of an item at the POS.',
        'steps' => [
            ['Product setting first', 'If a product has its own sale-price preference, that one is used.', 'box'],
            ['Otherwise the business setting', 'If not, the business-wide preference is used (Sale preferences screen).', 'building', 'global-sale-price-preferences.index'],
            ['Otherwise static price', 'If nothing is set, the fixed price from the product setup is used.', 'pin-angle'],
            ['Price for the unit sold', 'The price is converted to the unit you sell: a box costs as many tablets as it holds.', 'boxes'],
        ],
        'examples' => [
            ['Static', 'The fixed sale price you entered in the product setup for each unit, e.g. Box 550, Tablet 40. Purchases do not change it.'],
            ['Stock-wise', 'The price of the batch you are selling from. If the first batch to sell costs 39.30 per tablet, a box of 14 is 550.26.'],
            ['Previous inventory', 'The price of your most recent purchase, even if that batch has already sold out.'],
            ['Sale price including tax', 'When on, the sale tax is already inside the price the customer pays. The tax portion is calculated as price × rate ÷ (100 + rate) and shown to owners only. When off, sale tax is added on top at checkout.'],
        ],
        'faqs' => [
            ['Can a cashier change the price?', 'Only if "Allow cashier to change the sale price" is on (business-wide or for the product). A lowest allowed price can be set too. Every change is recorded in the invoice history and the audit log.'],
            ['What is GST on the receipt?', 'GST is the markup between Unit Cost and Paid Unit Cost on the purchase. It is shown for information only and never changes what the customer pays.'],
        ],
    ],

    'lot-selection' => [
        'title' => 'Which batch is sold first (FEFO / FIFO)',
        'icon' => 'calendar2-check', 'accent' => 'var(--chart-3)', 'module' => 'stock',
        'summary' => 'Every purchase is a batch with its own expiry. This decides which batch the POS sells from.',
        'steps' => [
            ['Earliest expiry first (FEFO)', 'The default. The batch that expires soonest is sold first, so short-dated stock does not expire on the shelf.', 'hourglass-split'],
            ['Oldest purchase first (FIFO)', 'The batch you bought first is sold first, whatever its expiry.', 'clock-history'],
            ['Per line at the POS', 'Each cart line can switch between the two. The batch number and expiry are shown on the line.', 'toggles'],
            ['Expired batches', 'A batch can be sold until the day before its expiry date. Expired batches are never sold.', 'x-octagon'],
        ],
        'examples' => [
            ['Two batches', 'Batch A expires in 2029, batch B in 3 months. FEFO sells B first; FIFO sells A first if it was bought earlier.'],
            ['Returns', 'A returned item goes back into the exact batch it was sold from.'],
        ],
        'faqs' => [
            ['Does the batch change the price?', 'With the stock-wise preference, yes: each batch has its own price. With static or previous-inventory pricing, no.'],
        ],
    ],

    'selling-at-the-pos' => [
        'title' => 'Selling at the POS',
        'icon' => 'cart-check', 'accent' => 'var(--chart-4)', 'module' => 'pos',
        'summary' => 'From adding an item to printing the receipt, and returns.',
        'steps' => [
            ['Find the item', 'Search by name or barcode. Only items with stock that has not expired can be added.', 'search', 'pos.index'],
            ['Quantity and unit', 'Choose the unit (box, strip, tablet…) and quantity. Stock held in other cashiers’ carts is reserved for them.', '123'],
            ['Discounts', 'Per line and per invoice, within the limits set by the owner.', 'percent'],
            ['Save & Print', 'The server recalculates every price, discount and tax, takes stock batch by batch and gives the invoice its number.', 'printer'],
            ['Returns', 'From the invoice: return one item or the whole invoice. Stock goes back to the same batches and any tax added on top is refunded.', 'arrow-counterclockwise', 'invoices.index'],
        ],
        'examples' => [],
        'faqs' => [
            ['Why was my sale refused?', 'The message says why: not enough stock, a price change that is not allowed, a price below the minimum, or a discount above the limit.'],
        ],
    ],

    'master-data' => [
        'title' => 'Master data',
        'icon' => 'diagram-3', 'accent' => 'var(--chart-2)', 'module' => 'core',
        'summary' => 'Manufacturers, dosage forms, strengths and formulas: the lists you pick from on every product.',
        'steps' => [
            ['Add once', 'Add each manufacturer, dosage form, strength or formula once; then pick it on products.', 'plus-circle', 'companies.index'],
            ['Unique names', 'Names are unique within your business. Archived names can be used again.', 'fingerprint'],
            ['See usage', 'Each list shows how many products use an entry. Sort by it to find unused ones.', 'bar-chart'],
            ['Archive safely', 'An entry used by products can’t be archived until those products are changed, so nothing breaks.', 'archive'],
            ['Restore', 'Open the Archived filter to bring an entry back.', 'arrow-counterclockwise'],
        ],
        'examples' => [],
        'faqs' => [
            ['Why can’t I archive this?', 'Products still use it. Open the products, pick another entry, then archive.'],
            ['Is the wording different for shops?', 'Yes. A shop sees Brands, Item types and Variants instead of Manufacturers, Dosage forms and Strengths.'],
        ],
    ],
    'products' => [
        'title' => 'Products & packaging',
        'icon' => 'capsule', 'accent' => 'var(--chart-3)', 'module' => 'core',
        'summary' => 'Add a product, set up its packaging and prices, then buy stock. The list shows live stock from every batch.',
        'steps' => [
            ['Add the product', 'Name, manufacturer, dosage form, strengths and formulas. It is saved as a draft.', 'plus-circle', 'products.create'],
            ['Set up packaging', 'List the units largest first (Box → Strip → Tablet) and how many fit in the unit above. The last unit is the one stock is counted in.', 'boxes'],
            ['Prices per unit', 'Optional fixed purchase and sale prices per unit. The sale price must be higher than the purchase price.', 'tag'],
            ['Buy stock', 'Saving the packaging makes the product ready. Record a purchase to put it in stock.', 'cart-plus', 'purchases.create'],
            ['Watch the list', 'Filter by In stock, Out of stock, Expired stock or Expiring within 90 days. Stock counts only batches that have not expired.', 'funnel', 'products.index'],
        ],
        'examples' => [
            ['Box of 10 strips, 10 tablets each', 'Box (1) → Strip (10) → Tablet (10): one box is 100 tablets; stock is counted in tablets.'],
        ],
        'faqs' => [
            ['Why can’t I change the packaging?', 'The product already has purchases, stock or sales. Those were counted with the current packaging, so it is locked to keep stock correct. Prices can still change.'],
            ['Why is a product with stock shown as out of stock?', 'All its remaining batches have expired. They appear under Expired stock and can’t be sold.'],
        ],
    ],
    'billing' => [
        'title' => 'Billing & plans',
        'icon' => 'credit-card', 'accent' => 'var(--chart-1)', 'module' => 'core',
        'summary' => 'Choose a plan or your own modules, pay securely with Stripe, change or cancel any time.',
        'steps' => [
            ['Pick a plan or modules', 'Plans bundle modules at a set price. Or build your own: pick modules and anything they need is added for you.', 'puzzle', 'billing.index'],
            ['Pay securely', 'You are taken to Stripe\'s checkout page. Card details never touch this app. Coupons are entered there.', 'shield-lock'],
            ['Modules switch on', 'As soon as Stripe confirms the payment, the modules are switched on for this business.', 'toggle-on'],
            ['Change any time', 'Switching plans or adding modules is prorated: you pay or get credit for the difference on the next bill.', 'arrow-left-right'],
            ['Cancel', 'Cancelling keeps everything until the end of the period you paid for. Your data is never deleted.', 'x-circle'],
        ],
        'examples' => [],
        'faqs' => [
            ['What happens if a payment fails?', 'Stripe retries the card automatically for a while. You keep access during that time; update the card from "Payment method & bills". If it still fails, paid modules switch off, and your data is kept.'],
            ['Is it free right now?', 'During launch every module is free. When launch pricing ends, the Billing page shows what to choose.'],
            ['Where are my bills?', 'Under Payment history on the Billing page, with a PDF for each one.'],
        ],
    ],
    'taxes' => [
        'title' => 'Taxes',
        'icon' => 'percent', 'accent' => 'var(--chart-5)', 'module' => 'core',
        'summary' => 'Purchase tax, sale tax and GST: what each one does.',
        'steps' => [
            ['Create taxes', 'Name + rate, e.g. "GST 17%". Names are unique within your business.', 'plus-circle', 'taxes.index'],
            ['Attach to purchases', 'On a purchase, add Purchase Tax (what you paid) and Sale Tax (what the customer pays when that batch is sold).', 'box-seam'],
            ['At the POS', 'Sale tax is added on top of the price, or is already inside it if "Sale price including tax" is on.', 'cart'],
            ['Archive', 'Archiving a tax keeps it on purchases that already use it; it just cannot be picked for new ones.', 'archive'],
        ],
        'examples' => [],
        'faqs' => [],
    ],

    // ---------------------------------------------------------------- Roadmap 7–12 modules
    'inventory-pro' => [
        'title' => 'Warehouses, transfers and adjustments',
        'icon' => 'buildings', 'accent' => 'var(--chart-6)', 'module' => 'inventory_pro',
        'summary' => 'Keep stock in several places, move it between them and correct it, without losing batch cost or expiry.',
        'steps' => [
            ['Warehouses', 'Switching Inventory Pro on creates "Main warehouse" and puts all your stock in it. Add more and pick the default for new purchases.', 'buildings', 'warehouses.index'],
            ['Buy into a warehouse', 'Purchases and goods received go to the warehouse you pick on the form.', 'box-arrow-in-down', 'purchases.create'],
            ['POS warehouse', 'On the Warehouses screen choose which warehouse the POS sells from, or leave it empty to sell from all of them.', 'cart-check', 'warehouses.index'],
            ['Transfer', 'Move stock from one warehouse to another. The earliest-expiry batches move first and keep their cost and expiry.', 'arrow-left-right', 'transfers.create'],
            ['Adjust', 'Remove damaged, expired or lost stock, or add stock found in a count. Removed stock leaves the books at its cost.', 'sliders2-vertical', 'adjustments.create'],
            ['Stock by warehouse', 'See quantity and value at cost per item and warehouse, expired stock shown apart.', 'clipboard-data', 'inventory.valuation'],
        ],
        'examples' => [
            ['Transfer', 'Main holds 10 boxes (batch A expiring in March, batch B in June). Moving 12 boxes… is refused; moving 6 takes all of batch A first.'],
            ['Write-off', 'Removing 4 tablets that cost 6 each records 24 as Inventory adjustments (an expense) when Accounting is on.'],
        ],
        'faqs' => [
            ['Can I edit a purchase after moving part of it?', 'No. Once a batch was moved or adjusted, correct it with another adjustment so the history stays true.'],
            ['Does a transfer change the total stock?', 'No. Only adjustments, purchases, sales and returns do.'],
        ],
    ],

    'b2b-sales' => [
        'title' => 'B2B sales: quotation to payment',
        'icon' => 'briefcase', 'accent' => 'var(--chart-4)', 'module' => 'b2b_sales',
        'summary' => 'Sell on account to businesses: quote, take the order, invoice, get paid, and credit what comes back.',
        'steps' => [
            ['Customer', 'Add the customer with payment terms (e.g. Net 30) and an optional credit limit.', 'person-vcard', 'customers.index'],
            ['Quotation', 'Prices come from the item’s sale price for the unit. Mark it sent, accepted or declined.', 'file-earmark-text', 'selling.quotations.create'],
            ['Sales order', 'Convert an accepted quotation into an order (optional) and confirm it.', 'clipboard-check', 'selling.orders.index'],
            ['Invoice', 'Convert to an invoice and issue it: stock leaves the batches (earliest expiry first) and the customer owes the total by the due date.', 'receipt-cutoff', 'selling.invoices.index'],
            ['Payment', 'Record money received and apply it to one or more invoices. Anything left over stays as the customer’s credit.', 'cash-coin', 'selling.payments.create'],
            ['Credit note', 'Goods came back? Create a credit note from the invoice: the items return to the exact batches they came from.', 'arrow-return-left', 'selling.credit-notes.index'],
        ],
        'examples' => [
            ['Credit limit', 'Limit 50,000, balance 45,000: an invoice of 8,000 is refused until the customer pays.'],
            ['Partial payment', 'Invoice 180, payment 100: the invoice is "Partly paid" with 80 open.'],
        ],
        'faqs' => [
            ['Can the price be changed?', 'Only by roles with "Change B2B prices". Totals and taxes are always recalculated on the server.'],
            ['How do I send it?', 'Open the document → Print / PDF (A4 or thermal) → save as PDF and email it, or print it.'],
            ['Statement?', 'Customer profile → Statement shows opening balance, invoices, payments and credit notes with a running balance.'],
        ],
    ],

    'b2b-purchasing' => [
        'title' => 'B2B purchasing: order to payment',
        'icon' => 'cart-plus', 'accent' => 'var(--chart-7)', 'module' => 'b2b_purchase',
        'summary' => 'Order from suppliers, receive into stock, enter their bill and pay it.',
        'steps' => [
            ['Purchase order', 'Pick the supplier, items and agreed cost. Mark it sent.', 'cart-plus', 'buying.orders.create'],
            ['Receive goods', 'When goods arrive, receive what came (part deliveries are fine) with batch, expiry and sale price. They are sellable at once.', 'box-arrow-in-down', 'buying.orders.index'],
            ['Bill', 'Create the bill from the order: it covers what was received. A different final price is booked as purchase price variance.', 'receipt', 'buying.bills.index'],
            ['Pay', 'Record a bill payment and apply it to one or more bills.', 'cash-coin', 'buying.payments.create'],
            ['Vendor credit', 'Returned goods to the supplier? Create a vendor credit from the bill: stock leaves and what you owe drops.', 'arrow-return-right', 'buying.credits.index'],
        ],
        'examples' => [['Part delivery', 'Order 5 boxes, receive 3: the order is "Partly received" and a bill can cover the 3 now.']],
        'faqs' => [['Do I still use “Add purchase”?', 'Yes, for quick buys without an order. Purchase orders are for suppliers you buy from on account.']],
    ],

    'accounting' => [
        'title' => 'Accounting: the books follow every module',
        'icon' => 'journal-text', 'accent' => 'var(--chart-8)', 'module' => 'accounting',
        'summary' => 'A full double-entry ledger that fills itself from sales, purchases, payments, stock and expenses.',
        'steps' => [
            ['Chart of accounts', 'A default chart (like SeeBiz Books) is created when you switch Accounting on. Add your own accounts any time.', 'list-columns', 'accounting.accounts.index'],
            ['Automatic journals', 'Every sale, return, purchase, bill, payment, stock adjustment and approved expense posts its journal. Past history is posted when you switch it on.', 'magic', 'accounting.journals.index'],
            ['Manual journals', 'Opening balances, depreciation, corrections: debits must equal credits.', 'pencil-square', 'accounting.journals.create'],
            ['Reports', 'Profit & loss, balance sheet, trial balance and the tax summary for any period; open any account’s ledger.', 'clipboard-data', 'accounting.reports'],
        ],
        'examples' => [
            ['POS sale of 110 incl. 10 tax, cost 60', 'Dr Cash 110 · Cr Sales 100 · Cr Sales tax payable 10 · Dr Cost of goods sold 60 · Cr Inventory 60.'],
            ['Bill payment of 165', 'Dr Accounts payable 165 · Cr Cash 165.'],
        ],
        'faqs' => [
            ['Are plain purchases paid or owed?', 'Paid in cash by default. Turn on "Plain purchases are on credit" on the Journals screen to owe them to the supplier instead.'],
            ['Something looks missing?', 'Journals → Re-post history posts everything again; it never doubles anything.'],
        ],
    ],

    'expense' => [
        'title' => 'Expenses and approvals',
        'icon' => 'wallet2', 'accent' => 'var(--chart-5)', 'module' => 'expense',
        'summary' => 'Record spending with receipts, get it approved, and pay back staff who paid themselves.',
        'steps' => [
            ['Record', 'Date, category, amount (tax included), who paid, and a photo of the receipt.', 'receipt', 'expenses.create'],
            ['Submit', 'Send it for approval (or save a draft).', 'send'],
            ['Approve or reject', 'Approvers approve, or reject with a reason so it can be fixed and sent again. By default nobody approves their own.', 'check2-circle', 'expenses.index'],
            ['Reimburse', 'Expenses paid by an employee are marked reimbursed when they are paid back.', 'cash-coin'],
        ],
        'examples' => [['Tax inside the amount', 'Paid 1,160 with 16% tax: 160 is recoverable input tax, 1,000 is the expense.']],
        'faqs' => [['Where does it go in the books?', 'Each category posts to an expense account (Categories screen). Approved: Dr expense, Cr Cash or Employee reimbursements payable.']],
    ],

    'analytics' => [
        'title' => 'Analytics',
        'icon' => 'bar-chart-line', 'accent' => 'var(--chart-2)', 'module' => 'analytics',
        'summary' => 'Every module in one view, with the previous period for comparison.',
        'steps' => [
            ['Pick a period', '7 / 30 / 90 days, 12 months, or your own dates (up to two years).', 'calendar3', 'analytics.index'],
            ['Compare', 'Each figure shows the change against the period just before.', 'arrow-left-right'],
            ['Export', 'Download POS sales, B2B documents, purchases, expenses or payments as CSV.', 'download'],
            ['Weekly email', 'Owners can get last week’s numbers every Monday morning.', 'envelope'],
        ],
        'examples' => [],
        'faqs' => [['Why is cash “Accounting off”?', 'Cash & bank come from the books; switch on Accounting to see them.']],
    ],
];
