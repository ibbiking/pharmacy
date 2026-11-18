<?php

/*
|--------------------------------------------------------------------------
| Sellable modules (docs/MODULES_AND_PLANS.md)
|--------------------------------------------------------------------------
| key => [
|   name, description, icon, accent,
|   'core'       => always active, cannot be switched off,
|   'depends_on' => modules switched on automatically with this one,
|   'routes'     => route-name patterns this module owns (EnsureModuleActive blocks them when the
|                   module is off for the business). Routes not listed anywhere belong to Core.
|   'available'  => false = shown as "coming soon" (built in a later roadmap step).
| ]
|
| Default bundle per business type: 'bundles'.
*/
return [
    'modules' => [
        'core' => [
            'name' => 'Core',
            'description' => 'Business, users & roles, settings, contacts, products/items catalogue, taxes, currencies, helper pages.',
            'icon' => 'grid-1x2', 'accent' => 'var(--chart-1)',
            'core' => true, 'depends_on' => [], 'available' => true,
            'permissions' => [
                'core.dashboard.view' => ['View dashboard', 'See the business dashboard and its charts.'],
                'core.catalogue.view' => ['View products & master data', 'Browse products, packaging, manufacturers, suppliers and taxes.'],
                'core.catalogue.manage' => ['Manage products & master data', 'Add, edit and archive products, packaging, manufacturers, suppliers and taxes.'],
                'core.settings.manage' => ['Manage business settings', 'Business details, email & branding, numbering, modules.'],
                'core.users.manage' => ['Manage team & roles', 'Invite people, give roles, edit roles and permissions.'],
                'core.billing.manage' => ['Billing & subscription', 'Choose a plan, pay, change or cancel the subscription, see payment history.'],
                'core.customers.view' => ['View customers', 'See customers, their documents and balances.'],
                'core.customers.manage' => ['Manage customers', 'Add, edit and archive customers.'],
            ],
            'routes' => [],
        ],
        'stock' => [
            'name' => 'Stock & Purchasing',
            'description' => 'Purchases, batches with expiry, FEFO / FIFO / any-batch selling, sale-price preferences, stock and expiry reports.',
            'icon' => 'box-seam', 'accent' => 'var(--chart-2)',
            'depends_on' => [], 'available' => true,
            'permissions' => [
                'stock.purchases.view' => ['View purchases', 'See purchases and purchase reports.'],
                'stock.purchases.manage' => ['Record purchases', 'Add, edit and archive purchases (stock in).'],
                'stock.preferences.manage' => ['Sale price preferences', 'Change business-wide and product sale-price preferences.'],
                'stock.reports.view' => ['Stock reports', 'Stock alerts, expiry, out-of-stock and expired lists.'],
                'stock.cost.view' => ['See cost prices', 'See purchase cost and margins on screens and reports.'],
            ],
            'routes' => [
                'purchases.*', 'outstock', 'expired', 'products.quick-stock', 'products.stock-summary',
                'products.sale-price-preferences', 'products.sale-price-preferences.store',
                'global-sale-price-preferences.*', 'reports.stock_indication', 'reports.stock_unaligned', 'reports.expiry',
            ],
        ],
        'pos' => [
            'name' => 'POS & Invoicing',
            'description' => 'Point of sale with live receipt, thermal & A4 receipts, invoices, returns, sales and profit reports.',
            'icon' => 'cart-check', 'accent' => 'var(--chart-3)',
            'depends_on' => ['stock'], 'available' => true,
            'permissions' => [
                'pos.sell' => ['Sell at the POS', 'Use the POS to sell and print receipts.'],
                'pos.discount' => ['Give discounts', 'Give line and invoice discounts at the POS (within the business limits).'],
                'pos.price_change' => ['Change prices at the POS', 'Charge a different price than the system price (if the business allows it).'],
                'pos.invoices.view' => ['View invoices', 'See invoices and reprint receipts.'],
                'pos.returns.manage' => ['Process returns', 'Return items or whole invoices and refund the customer.'],
                'pos.reports.view' => ['Sales reports', 'Sales and returns reports.'],
                'pos.profit.view' => ['Profit & loss', 'See the profit and loss report.'],
            ],
            'routes' => [
                'pos.*', 'invoices.*', 'returns.*', 'sales.*', 'products.pos.*', 'products.search', 'products.category-price',
                'reports.sales', 'reports.returns', 'reports.profit_loss',
            ],
        ],
        'pharmacy' => [
            'name' => 'Pharmacy Pack',
            'description' => 'Generic medicine catalogue & import, formulas, strengths, drug-style wording, FBR-ready receipts.',
            'icon' => 'capsule', 'accent' => 'var(--chart-5)',
            'depends_on' => ['stock'], 'available' => true,
            'permissions' => [
                'pharmacy.generic.view' => ['View generic catalogue', 'Browse the generic medicine catalogue.'],
                'pharmacy.generic.import' => ['Import from generic catalogue', 'Import medicines from the generic catalogue.'],
                'pharmacy.masterdata.manage' => ['Manage formulas & strengths', 'Add, edit and archive formulas and strengths.'],
            ],
            'routes' => ['generic_products.*', 'farmulas.*', 'strengths.*', 'pharmacies.*'],
        ],
        'inventory_pro' => [
            'name' => 'Inventory Pro',
            'description' => 'Multiple warehouses, transfers between them, stock adjustments, stock by warehouse and valuation.',
            'icon' => 'buildings', 'accent' => 'var(--chart-6)',
            'depends_on' => ['stock'], 'available' => true,
            'permissions' => [
                'inventory_pro.view' => ['View warehouses & stock', 'See warehouses, transfers, adjustments, stock by warehouse and valuation.'],
                'inventory_pro.manage' => ['Manage warehouses & stock moves', 'Add warehouses, move stock between them and adjust stock.'],
            ],
            'routes' => ['warehouses.*', 'transfers.*', 'adjustments.*', 'inventory.*'],
        ],
        'b2b_sales' => [
            'name' => 'B2B Sales',
            'description' => 'Quotations, sales orders, invoices on credit, payments received, credit notes, customer statements.',
            'icon' => 'briefcase', 'accent' => 'var(--chart-4)',
            'depends_on' => ['stock'], 'available' => true,
            'permissions' => [
                'b2b_sales.view' => ['View sales documents', 'See quotations, sales orders, invoices, payments and credit notes.'],
                'b2b_sales.manage' => ['Create sales documents', 'Create and send quotations, orders, invoices and credit notes.'],
                'b2b_sales.price_change' => ['Change B2B prices', 'Use a different price than the item’s sale price on a sales document.'],
                'b2b_sales.payments' => ['Record payments received', 'Record customer payments against invoices.'],
            ],
            'routes' => ['selling.*', 'customers.statement', 'reports.ar_aging'],
        ],
        'b2b_purchase' => [
            'name' => 'B2B Purchasing',
            'description' => 'Purchase orders, goods received, bills, bill payments, vendor credits, supplier statements.',
            'icon' => 'truck', 'accent' => 'var(--chart-7)',
            'depends_on' => ['stock'], 'available' => true,
            'permissions' => [
                'b2b_purchase.view' => ['View purchasing documents', 'See purchase orders, bills, payments and vendor credits.'],
                'b2b_purchase.manage' => ['Create purchasing documents', 'Create purchase orders, receive goods, enter bills and vendor credits.'],
                'b2b_purchase.payments' => ['Pay bills', 'Record payments to suppliers against bills.'],
            ],
            'routes' => ['buying.*', 'suppliers.statement', 'reports.ap_aging'],
        ],
        'accounting' => [
            'name' => 'Books / Accounting',
            'description' => 'Chart of accounts, automatic journals from every module, ledger, trial balance, P&L, balance sheet.',
            'icon' => 'journal-text', 'accent' => 'var(--chart-8)',
            'depends_on' => [], 'available' => true,
            'permissions' => [
                'accounting.view' => ['View the books', 'See the chart of accounts, journals and the ledger.'],
                'accounting.manage' => ['Manage the books', 'Add accounts and post manual journals.'],
                'accounting.reports.view' => ['Financial reports', 'Trial balance, profit & loss, balance sheet, ageing, tax summary.'],
            ],
            'routes' => ['accounting.*'],
        ],
        'expense' => [
            'name' => 'Expense',
            'description' => 'Expenses with receipts, approvals (with a self-approval policy) and reimbursements.',
            'icon' => 'wallet2', 'accent' => 'var(--chart-5)',
            'depends_on' => [], 'available' => true,
            'permissions' => [
                'expense.submit' => ['Submit expenses', 'Record own expenses and submit them for approval.'],
                'expense.approve' => ['Approve expenses', 'Approve or reject submitted expenses.'],
                'expense.reimburse' => ['Reimburse expenses', 'Mark approved employee-paid expenses as reimbursed.'],
                'expense.manage' => ['Manage expenses', 'See everyone’s expenses and manage expense categories and the policy.'],
            ],
            'routes' => ['expenses.*', 'expense-categories.*'],
        ],
        'analytics' => [
            'name' => 'Advanced Reporting',
            'description' => 'Cross-module dashboard with period comparison, CSV exports, weekly summary email.',
            'icon' => 'graph-up-arrow', 'accent' => 'var(--chart-2)',
            'depends_on' => [], 'available' => true,
            'permissions' => [
                'analytics.view' => ['Business analytics', 'See the cross-module analytics dashboard and export reports.'],
            ],
            'routes' => ['analytics.*'],
        ],
        'fbr' => [
            'name' => 'FBR Integration',
            'description' => 'FBR POS Integration & Digital Invoicing: guided IRIS setup, fiscal numbers, QR codes.',
            'icon' => 'patch-check', 'accent' => 'var(--chart-3)',
            'depends_on' => ['pos'], 'available' => false, 'routes' => [],
        ],
    ],

    /*
    | Built-in roles every business gets. Owner = every permission of the active modules (not editable).
    */
    'system_roles' => [
        'owner' => ['name' => 'Owner', 'description' => 'Everything in this business.', 'permissions' => '*'],
        'sales-person' => ['name' => 'Sales person', 'description' => 'Sells at the POS.', 'permissions' => ['pos.sell', 'pos.discount', 'pos.invoices.view']],
    ],

    'bundles' => [
        'pharmacy' => ['core', 'stock', 'pos', 'pharmacy'],
        'inventory' => ['core', 'stock', 'pos', 'inventory_pro'],
        'inventory_b2b' => ['core', 'stock', 'pos', 'inventory_pro', 'b2b_sales', 'b2b_purchase', 'accounting', 'expense'],
    ],

    /*
    | Everything a business of each type can buy (the "maximum" combination). FBR stays unavailable until step 6.
    */
    'maximum' => [
        'pharmacy' => ['core', 'stock', 'pos', 'pharmacy', 'inventory_pro', 'b2b_sales', 'b2b_purchase', 'accounting', 'expense', 'analytics'],
        'inventory' => ['core', 'stock', 'pos', 'inventory_pro', 'b2b_sales', 'b2b_purchase', 'accounting', 'expense', 'analytics'],
        'inventory_b2b' => ['core', 'stock', 'pos', 'inventory_pro', 'b2b_sales', 'b2b_purchase', 'accounting', 'expense', 'analytics'],
    ],
];
