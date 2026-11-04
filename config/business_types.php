<?php

/*
| Display wording per business type (Pharmacy / Inventory / Inventory B2B). Screens never hard-code
| "Medicine" or "Item"; they read App\\Support\\BusinessWording::for($type), shared to React as the
| `wording` Inertia prop. Every type must define the same keys.
*/
return array (
  'pharmacy' => 
  array (
    'product' => 'Medicine',
    'products' => 'Medicines',
    'category' => 'Packaging',
    'categories' => 'Packaging',
    'company' => 'Manufacturer',
    'companies' => 'Manufacturers',
    'formula' => 'Formula',
    'formulas' => 'Formulas',
    'strength' => 'Strength',
    'strengths' => 'Strengths',
    'product_type' => 'Dosage Form',
    'product_types' => 'Dosage Forms',
    'generic_catalog' => 'Generic Medicines',
    'batch' => 'Batch',
    'expiry' => 'Expiry',
    'supplier' => 'Supplier',
    'suppliers' => 'Suppliers',
    'customer' => 'Patient / Customer',
    'store' => 'Pharmacy',
  ),
  'inventory' => 
  array (
    'product' => 'Item',
    'products' => 'Items',
    'category' => 'Unit of Measure',
    'categories' => 'Units of Measure',
    'company' => 'Brand',
    'companies' => 'Brands',
    'formula' => 'Specification',
    'formulas' => 'Specifications',
    'strength' => 'Variant',
    'strengths' => 'Variants',
    'product_type' => 'Item Type',
    'product_types' => 'Item Types',
    'generic_catalog' => 'Item Catalogue',
    'batch' => 'Batch',
    'expiry' => 'Expiry',
    'supplier' => 'Supplier',
    'suppliers' => 'Suppliers',
    'customer' => 'Customer',
    'store' => 'Store',
  ),
  'inventory_b2b' => 
  array (
    'product' => 'Item',
    'products' => 'Items',
    'category' => 'Unit of Measure',
    'categories' => 'Units of Measure',
    'company' => 'Brand',
    'companies' => 'Brands',
    'formula' => 'Specification',
    'formulas' => 'Specifications',
    'strength' => 'Variant',
    'strengths' => 'Variants',
    'product_type' => 'Item Type',
    'product_types' => 'Item Types',
    'generic_catalog' => 'Item Catalogue',
    'batch' => 'Batch',
    'expiry' => 'Expiry',
    'supplier' => 'Vendor',
    'suppliers' => 'Vendors',
    'customer' => 'Customer Account',
    'store' => 'Warehouse',
  ),
);
