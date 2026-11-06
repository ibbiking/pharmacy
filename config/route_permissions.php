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
        'sales.*' => 'pos.sell', // legacy single-product sale screen

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

        // Settings
        'business.settings' => 'core.settings.manage',
        'business.settings.update' => 'core.settings.manage',
        'billing.*' => 'core.billing.manage',
        'settings.email*' => 'core.settings.manage',
        'settings.numbering*' => 'core.settings.manage',
        'modules.activate' => 'core.settings.manage',
        'modules.deactivate' => 'core.settings.manage',
        'currencies.*' => 'core.settings.manage',
        'pharmacies.*' => 'core.settings.manage',
        'settings' => 'core.settings.manage',
        'backup.*' => 'core.backup.manage',

        // People
        'settings.team*' => 'core.users.manage',
        'settings.roles*' => 'core.users.manage',
        'users.*' => 'core.users.manage',
        // Legacy Spatie roles/permissions are GLOBAL (shared by every business): super-admin only.
        'roles.*' => '@super-admin',
        'permissions.*' => '@super-admin',
        'superadmin.*' => '@super-admin',
    ],

    // Open to every active member (own profile, theme, switching business, help, lookups, …).
    'open' => [
        'business.create', 'business.create.currencies', 'business.store', 'business.setup', 'business.switch',
        'preferences.theme', 'lookup', 'help.index', 'help.show', 'modules.index',
        'profile', 'profile.update', 'update-password', 'mark-as-read', 'read', 'logout',
    ],
];
