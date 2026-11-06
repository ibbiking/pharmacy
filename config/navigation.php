<?php

/*
| Sidebar navigation, rendered by the Inertia layout (resources/js/Layouts/AppLayout.tsx).
| - 'label' may be a wording key ("wording:products") resolved per business type.
| - Who sees an item follows config/route_permissions.php for its route (the same check as the
|   route itself); 'role' / 'owner' add extra conditions.
| - 'inertia' => true marks screens already moved to Inertia (client-side navigation); others are
|   classic Blade pages opened with a normal page load until they are converted (Roadmap step 5).
*/
return [
    ['group' => 'Main', 'items' => [
        ['label' => 'Dashboard', 'icon' => 'speedometer2', 'route' => 'dashboard', 'inertia' => true],
        ['label' => 'POS', 'icon' => 'cart-check', 'route' => 'pos.index', 'inertia' => true],
    ]],
    // Super admin only (Roadmap 5.11b).
    ['group' => 'Platform', 'items' => [
        ['label' => 'Businesses', 'icon' => 'buildings', 'route' => 'superadmin.businesses', 'role' => 'super-admin', 'inertia' => true],
        ['label' => 'Users', 'icon' => 'person-badge', 'route' => 'superadmin.users', 'role' => 'super-admin', 'inertia' => true],
        ['label' => 'Global roles', 'icon' => 'shield-check', 'route' => 'superadmin.roles', 'role' => 'super-admin', 'inertia' => true],
        ['label' => 'Plans & pricing', 'icon' => 'tags', 'route' => 'superadmin.billing', 'role' => 'super-admin', 'inertia' => true],
        ['label' => 'Subscriptions', 'icon' => 'credit-card-2-front', 'route' => 'superadmin.billing.subscriptions', 'role' => 'super-admin', 'inertia' => true],
        ['label' => 'App settings', 'icon' => 'sliders2', 'route' => 'superadmin.settings', 'role' => 'super-admin', 'inertia' => true],
        ['label' => 'Backups', 'icon' => 'cloud-arrow-down', 'route' => 'backup.index', 'role' => 'super-admin', 'inertia' => true],
    ]],
    ['group' => 'Catalogue', 'items' => [
        ['label' => 'wording:products', 'icon' => 'capsule', 'children' => [
            ['label' => 'All', 'route' => 'products.index', 'inertia' => true],
            ['label' => 'Add new', 'route' => 'products.create', 'inertia' => true],
        ]],
        ['label' => 'wording:generic_catalog', 'icon' => 'collection', 'route' => 'generic_products.index', 'inertia' => true],
        ['label' => 'Master data', 'icon' => 'diagram-3', 'children' => [
            ['label' => 'wording:categories', 'route' => 'categories.index', 'inertia' => true],
            ['label' => 'wording:companies', 'route' => 'companies.index', 'inertia' => true],
            ['label' => 'wording:formulas', 'route' => 'farmulas.index', 'inertia' => true],
            ['label' => 'wording:product_types', 'route' => 'product-types.index', 'inertia' => true],
            ['label' => 'wording:strengths', 'route' => 'strengths.index', 'inertia' => true],
            ['label' => 'Taxes', 'route' => 'taxes.index', 'inertia' => true],
        ]],
    ]],
    ['group' => 'Stock', 'items' => [
        ['label' => 'Purchases', 'icon' => 'box-seam', 'children' => [
            ['label' => 'All purchases', 'route' => 'purchases.index', 'inertia' => true],
            ['label' => 'Add purchase', 'route' => 'purchases.create', 'inertia' => true],
        ]],
        ['label' => 'wording:suppliers', 'icon' => 'truck', 'route' => 'suppliers.index', 'inertia' => true],
    ]],
    ['group' => 'Sales', 'items' => [
        ['label' => 'Invoices', 'icon' => 'receipt', 'route' => 'invoices.index', 'inertia' => true],
        ['label' => 'Reports', 'icon' => 'graph-up-arrow', 'children' => [
            ['label' => 'Sales', 'route' => 'reports.sales', 'inertia' => true],
            ['label' => 'Profit & loss', 'route' => 'reports.profit_loss', 'inertia' => true],
            ['label' => 'Returns', 'route' => 'reports.returns', 'inertia' => true],
            ['label' => 'Expiry', 'route' => 'reports.expiry', 'inertia' => true],
            ['label' => 'Stock alerts', 'route' => 'reports.stock_indication', 'inertia' => true],
            ['label' => 'Stock rules gaps', 'route' => 'reports.stock_unaligned', 'inertia' => true],
        ]],
    ]],
    ['group' => 'Settings', 'items' => [
        ['label' => 'Business settings', 'icon' => 'gear', 'route' => 'business.settings', 'inertia' => true],
        ['label' => 'Modules', 'icon' => 'puzzle', 'route' => 'modules.index', 'inertia' => true, 'owner' => true],
        ['label' => 'Billing', 'icon' => 'credit-card', 'route' => 'billing.index', 'inertia' => true],
        ['label' => 'Email & branding', 'icon' => 'envelope-paper', 'route' => 'settings.email', 'inertia' => true, 'owner' => true],
        ['label' => 'Numbering', 'icon' => '123', 'route' => 'settings.numbering', 'inertia' => true, 'owner' => true],
        ['label' => 'Team', 'icon' => 'people', 'route' => 'settings.team', 'inertia' => true],
        ['label' => 'Roles', 'icon' => 'shield-lock', 'route' => 'settings.roles', 'inertia' => true],
        ['label' => 'Sale preferences', 'icon' => 'sliders', 'route' => 'global-sale-price-preferences.index', 'inertia' => true],
    ]],
    ['group' => 'Help', 'items' => [
        ['label' => 'Help Center', 'icon' => 'life-preserver', 'route' => 'help.index', 'inertia' => true],
    ]],
];
