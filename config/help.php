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
];
