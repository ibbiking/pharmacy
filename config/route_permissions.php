<?php

/*
|--------------------------------------------------------------------------
| Route → permission map (Roadmap 4i-4), enforced by App\Http\Middleware\EnsureRoutePermission
|--------------------------------------------------------------------------
| First matching pattern wins (put specific names before wildcards). Values:
|   'module.area.action' → that permission (Owner and super-admin always pass)
|   '@super-admin'       → super-admin only
|   '@member'            → any active member of the business (listed in 'open')
| tests/Feature/Platform/RoutePermissionsTest fails if an admin route is in neither list.
*/
return [
    'map' => [
        'dashboard' => 'core.dashboard.view',
        'suppliers.statement' => 'supplier_credit.view', // before suppliers.*
        'accounting.reports' => 'accounting.reports.view',
        'accounting.accounts.index' => 'accounting.view',
        'accounting.ledger' => 'accounting.view',
        'accounting.journals.index' => 'accounting.view',
        'accounting.journals.show' => 'accounting.view',
        'accounting.*' => 'accounting.manage',
        'expenses.policy' => 'expense.manage',
        'expenses.*' => 'expense.submit',
        'expense-categories.*' => 'expense.manage',
        'analytics.*' => 'analytics.view',

        // POS & invoicing (before the generic product patterns)
        'pos.*' => 'pos.sell',
        'products.pos.*' => 'pos.sell',
        'products.search' => 'pos.sell',
        'products.category-price' => 'pos.sell',
        'products.price-summary' => 'pos.sell',
        'invoices.return' => 'pos.returns.manage',
        'invoices.return-product' => 'pos.returns.manage',
        'invoices.return-preview' => 'pos.returns.manage',
        'invoices.*' => 'pos.invoices.view',
        'returns.*' => 'pos.invoices.view',
        'reports.sales' => 'pos.reports.view',
        'reports.returns' => 'pos.reports.view',
        'reports.profit_loss' => 'pos.profit.view',
        'sales.*' => 'pos.sell', // legacy sale screen, retired (5.11c): redirects to the POS

        // Stock & purchasing
        'purchases.index' => 'stock.purchases.view',
        'purchases.show' => 'stock.purchases.view',
        'purchases.report' => 'stock.purchases.view',
        'purchases.*' => 'stock.purchases.manage',
        'products.quick-stock' => 'stock.purchases.manage',
        'products.unit-options' => 'stock.purchases.manage', // units of a product, for the purchase forms
        'products.stock-summary' => 'stock.reports.view',
        'products.sale-price-preferences' => 'stock.preferences.manage',
        'products.sale-price-preferences.store' => 'stock.preferences.manage',
        'global-sale-price-preferences.*' => 'stock.preferences.manage',
        'reports.stock_indication' => 'stock.reports.view',
        'reports.stock_unaligned' => 'stock.reports.view',
        'reports.expiry' => 'stock.reports.view',
        'outstock' => 'stock.reports.view',
        'expired' => 'stock.reports.view',

        // Pharmacy pack (super-admin curation actions are also checked inside the controller)
        'generic_products.index' => 'pharmacy.generic.view',
        'generic_products.details' => 'pharmacy.generic.view',
        'generic_products.*' => 'pharmacy.generic.import',
        'farmulas.*' => 'pharmacy.masterdata.manage',
        'strengths.*' => 'pharmacy.masterdata.manage',

        // Catalogue & master data
        'products.index' => 'core.catalogue.view',
        'products.details' => 'core.catalogue.view',
        'products.show' => 'core.catalogue.view',
        'products.drafts' => 'core.catalogue.view',
        'products.autocomplete' => 'core.catalogue.view',
        'products.*' => 'core.catalogue.manage',
        'categories.index' => 'core.catalogue.view',
        'categories.autocomplete' => 'core.catalogue.view',
        'categories.*' => 'core.catalogue.manage',
        'product-categories.index' => 'core.catalogue.view',
        'product-categories.*' => 'core.catalogue.manage',
        'companies.index' => 'core.catalogue.view',
        'companies.autocomplete' => 'core.catalogue.view',
        'companies.*' => 'core.catalogue.manage',
        'product-types.index' => 'core.catalogue.view',
        'product-types.autocomplete' => 'core.catalogue.view',
        'product-types.*' => 'core.catalogue.manage',
        'suppliers.index' => 'core.catalogue.view',
        'suppliers.show' => 'core.catalogue.view',
        'suppliers.autocomplete' => 'core.catalogue.view',
        'suppliers.*' => 'core.catalogue.manage',
        'taxes.index' => 'core.catalogue.view',
        'taxes.*' => 'core.catalogue.manage',

        // Roadmap 7–12 (specific names before wildcards)
        'customers.index' => 'core.customers.view',
        'customers.show' => 'core.customers.view',
        'customers.statement' => 'customer_credit.view',
        'customers.*' => 'core.customers.manage',
        'warehouses.index' => 'inventory_pro.view',
        'warehouses.*' => 'inventory_pro.manage',
        'transfers.index' => 'inventory_pro.view',
        'transfers.show' => 'inventory_pro.view',
        'transfers.*' => 'inventory_pro.manage',
        'adjustments.index' => 'inventory_pro.view',
        'adjustments.show' => 'inventory_pro.view',
        'adjustments.*' => 'inventory_pro.manage',
        'inventory.*' => 'inventory_pro.view',
        // B2B: the controllers check manage / payments permissions per action; view to open anything.
        'selling.payments.*' => 'customer_credit.view',
        'selling.*' => 'b2b_sales.view',
        'reports.ar_aging' => 'customer_credit.view',
        'buying.payments.*' => 'supplier_credit.view',
        'buying.*' => 'b2b_purchase.view',
        'reports.ap_aging' => 'supplier_credit.view',

        // Settings
        'business.settings' => 'core.settings.manage',
        'business.settings.update' => 'core.settings.manage',
        'business.settings.currencies' => 'core.settings.manage',
        'billing.*' => 'core.billing.manage',
        'settings.email*' => 'core.settings.manage',
        'settings.numbering*' => 'core.settings.manage',
        'settings.export*' => 'core.settings.manage', // + owner check in DataExportController
        'modules.activate' => 'core.settings.manage',
        'modules.deactivate' => 'core.settings.manage',
        'currencies.*' => 'core.settings.manage',
        'pharmacies.*' => 'core.settings.manage',
        'settings' => '@super-admin', // app-wide settings (5.11b, SA-3): now only a redirect to the platform portal

        // People
        'settings.team*' => 'core.users.manage',
        'settings.roles*' => 'core.users.manage',
        'users.*' => 'core.users.manage', // legacy URLs, redirect to Team (5.11b)
        // Legacy global roles/permissions URLs redirect to this business's Roles screen.
        'roles.*' => 'core.users.manage',
        'permissions.*' => 'core.users.manage',
    ],

    // Open to every active member (own profile, theme, switching business, help, lookups, …).
    'open' => [
        'business.create', 'business.create.currencies', 'business.store', 'business.setup', 'business.switch',
        'preferences.theme', 'lookup', 'items.units', 'help.index', 'help.show', 'modules.index',
        'profile', 'profile.update', 'update-password', 'mark-as-read', 'read', 'logout',
        'notifications.index', 'notifications.read', 'notifications.read-all',
        // Old super admin addresses: plain redirects to the separate platform portal (which has its own login).
        'superadmin.billing', 'superadmin.billing.subscriptions', 'superadmin.businesses', 'superadmin.businesses.show',
        'superadmin.visits', 'superadmin.users', 'superadmin.roles', 'superadmin.settings', 'backup.index',
    ],
];
