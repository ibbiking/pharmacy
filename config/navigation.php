<?php

/*
| Sidebar navigation, rendered by the Inertia layout (resources/js/Layouts/AppLayout.tsx).
| - 'label' may be a wording key ("wording:products") resolved per business type.
| - 'can' = Spatie permission required; 'role' = role required; omit for everyone.
| - 'inertia' => true marks screens already moved to Inertia (client-side navigation); others are
|   classic Blade pages opened with a normal page load until they are converted (Roadmap step 5).
*/
return [
    ['group' => 'Main', 'items' => [
        ['label' => 'Dashboard', 'icon' => 'speedometer2', 'route' => 'dashboard'],
        ['label' => 'POS', 'icon' => 'cart-check', 'route' => 'pos.index'],
        ['label' => 'Manage Businesses', 'icon' => 'buildings', 'route' => 'superadmin.businesses', 'role' => 'super-admin'],
        ['label' => 'Plans & pricing', 'icon' => 'tags', 'route' => 'superadmin.billing', 'role' => 'super-admin', 'inertia' => true],
    ]],
    ['group' => 'Catalogue', 'items' => [
        ['label' => 'wording:products', 'icon' => 'capsule', 'can' => 'view-products', 'children' => [
            ['label' => 'All', 'route' => 'products.index'],
            ['label' => 'Add new', 'route' => 'products.create', 'can' => 'create-product'],
            ['label' => 'Drafts', 'route' => 'products.drafts'],
            ['label' => 'Out of stock', 'route' => 'outstock', 'can' => 'view-outstock-products'],
            ['label' => 'Expired', 'route' => 'expired', 'can' => 'view-expired-products'],
        ]],
        ['label' => 'wording:generic_catalog', 'icon' => 'collection', 'route' => 'generic_products.index'],
        ['label' => 'Master data', 'icon' => 'diagram-3', 'can' => 'view-category', 'children' => [
            ['label' => 'wording:categories', 'route' => 'categories.index'],
            ['label' => 'wording:companies', 'route' => 'companies.index'],
            ['label' => 'wording:formulas', 'route' => 'farmulas.index'],
            ['label' => 'wording:product_types', 'route' => 'product-types.index'],
            ['label' => 'wording:strengths', 'route' => 'strengths.index'],
            ['label' => 'Taxes', 'route' => 'taxes.index', 'inertia' => true],
        ]],
    ]],
    ['group' => 'Stock', 'items' => [
        ['label' => 'Purchases', 'icon' => 'box-seam', 'can' => 'view-purchase', 'children' => [
            ['label' => 'All purchases', 'route' => 'purchases.index'],
            ['label' => 'Add purchase', 'route' => 'purchases.create', 'can' => 'create-purchase'],
            ['label' => 'Bulk purchase', 'route' => 'purchases.create-multiple', 'can' => 'create-purchase'],
        ]],
        ['label' => 'wording:suppliers', 'icon' => 'truck', 'route' => 'suppliers.index', 'can' => 'view-supplier'],
    ]],
    ['group' => 'Sales', 'items' => [
        ['label' => 'Invoices', 'icon' => 'receipt', 'route' => 'invoices.index'],
        ['label' => 'Reports', 'icon' => 'graph-up-arrow', 'can' => 'view-reports', 'children' => [
            ['label' => 'Sales', 'route' => 'reports.sales'],
            ['label' => 'Profit & loss', 'route' => 'reports.profit_loss'],
            ['label' => 'Returns', 'route' => 'reports.returns'],
            ['label' => 'Expiry', 'route' => 'reports.expiry'],
            ['label' => 'Stock alerts', 'route' => 'reports.stock_indication'],
            ['label' => 'Stock rules gaps', 'route' => 'reports.stock_unaligned'],
        ]],
    ]],
    ['group' => 'Settings', 'items' => [
        ['label' => 'Business settings', 'icon' => 'gear', 'route' => 'business.settings'],
        ['label' => 'Modules', 'icon' => 'puzzle', 'route' => 'modules.index', 'inertia' => true, 'owner' => true],
        ['label' => 'Email & branding', 'icon' => 'envelope-paper', 'route' => 'settings.email', 'inertia' => true, 'owner' => true],
        ['label' => 'Numbering', 'icon' => '123', 'route' => 'settings.numbering', 'inertia' => true, 'owner' => true],
        ['label' => 'Team', 'icon' => 'people', 'route' => 'settings.team', 'inertia' => true, 'can' => 'core.users.manage'],
        ['label' => 'Roles', 'icon' => 'shield-lock', 'route' => 'settings.roles', 'inertia' => true, 'can' => 'core.users.manage'],
        ['label' => 'Sale preferences', 'icon' => 'sliders', 'route' => 'global-sale-price-preferences.index'],
        ['label' => 'Users & access', 'icon' => 'people', 'can' => 'view-access-control', 'children' => [
            ['label' => 'Users', 'route' => 'users.index', 'can' => 'view-users'],
            ['label' => 'Roles', 'route' => 'roles.index', 'can' => 'view-role'],
            ['label' => 'Permissions', 'route' => 'permissions.index', 'can' => 'view-permission'],
        ]],
        ['label' => 'Backups', 'icon' => 'cloud-arrow-down', 'route' => 'backup.index'],
    ]],
    ['group' => 'Help', 'items' => [
        ['label' => 'Help Center', 'icon' => 'life-preserver', 'route' => 'help.index', 'inertia' => true],
    ]],
];
