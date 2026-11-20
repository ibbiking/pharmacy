<?php

/*
|--------------------------------------------------------------------------
| FBR integration (docs/FBR_INTEGRATION.md, Roadmap step 6)
|--------------------------------------------------------------------------
| ⚠️ UNVERIFIED. Every URL and field name below comes from public / third-party material, NOT from the official
| FBR / PRAL technical specification, and has never been tested against FBR's sandbox. Before a business goes live,
| a developer must check each value against the current official spec and the business's own IRIS sandbox, and
| correct it here (no code change needed for field renames).
|
| Per-business data (NTN, STRN, POS IDs, token) is stored in fbr_settings / fbr_terminals, never here.
*/

return [

    // http = call FBR; fake = App\Services\Fbr\FakeFbrClient (tests and local demos only: its numbers start with
    // "FAKE-" and a business can never go live with it outside local/testing).
    'driver' => env('FBR_DRIVER', 'http'),

    'timeout' => (int) env('FBR_TIMEOUT', 15),

    // Retry schedule for "Save and retry" mode (seconds), then the submission waits for manual review.
    'backoff' => [1, 5, 30, 120, 600],

    'queue' => env('FBR_QUEUE', 'default'),

    'regimes' => [
        'pos' => [
            'name' => 'FBR POS Integration',
            'for' => 'Retail sales at the counter (Tier-1 retailers): pharmacies, shops.',
            'built' => true,
        ],
        'di' => [
            'name' => 'FBR Digital Invoicing',
            'for' => 'Sales-tax-registered businesses selling to other businesses (wholesale, distribution).',
            'built' => false, // coming next
        ],
    ],

    'pos' => [
        // UNVERIFIED. Third-party guides quote e.g. https://esp.fbr.gov.pk:8244/FBR/v1/api/Live/PostData (sandbox)
        // and https://gw.fbr.gov.pk/imsp/v1/api/Live/PostData (production). Set them only after checking the spec.
        'sandbox_url' => env('FBR_POS_SANDBOX_URL', env('FBR_SANDBOX_URL')),
        'production_url' => env('FBR_POS_PRODUCTION_URL', env('FBR_PRODUCTION_URL')),

        // UNVERIFIED invoice type codes.
        'invoice_type_sale' => 1,
        'invoice_type_return' => 3,
        'payment_mode_cash' => 1,

        // UNVERIFIED request field names (our value => FBR's field).
        'fields' => [
            'pos_id' => 'POSID',
            'usin' => 'USIN',                 // our own invoice number, the unique sale reference
            'ref_usin' => 'RefUSIN',          // original invoice number on a return
            'date_time' => 'DateTime',
            'buyer_name' => 'BuyerName',
            'buyer_ntn' => 'BuyerNTN',
            'buyer_cnic' => 'BuyerCNIC',
            'buyer_phone' => 'BuyerPhoneNumber',
            'total_bill' => 'TotalBillAmount',
            'total_quantity' => 'TotalQuantity',
            'total_sale_value' => 'TotalSaleValue',
            'total_tax' => 'TotalTaxCharged',
            'discount' => 'Discount',
            'further_tax' => 'FurtherTax',
            'payment_mode' => 'PaymentMode',
            'invoice_type' => 'InvoiceType',
            'items' => 'Items',
            'item_code' => 'ItemCode',
            'item_name' => 'ItemName',
            'quantity' => 'Quantity',
            'pct_code' => 'PCTCode',
            'tax_rate' => 'TaxRate',
            'sale_value' => 'SaleValue',
            'total_amount' => 'TotalAmount',
            'tax_charged' => 'TaxCharged',
        ],

        // UNVERIFIED response fields: the FBR invoice number, the success code and its value.
        'response_number' => 'InvoiceNumber',
        'response_code' => 'Code',
        'response_success_code' => '100',
        'response_message' => 'Response',
    ],

    // What the receipt QR code contains (FBR's own invoice number, per the public guides — UNVERIFIED).
    'qr_content' => 'fbr_invoice_no',

    // Official FBR POS logo for receipts (public/ path). FBR supplies it; until the file exists a text badge is printed.
    'logo_path' => 'images/fbr/fbr-pos-logo.png',

    // Shown on the setup page; the super admin should keep these current.
    'links' => [
        'iris' => 'https://iris.fbr.gov.pk',
        'verify' => 'https://www.fbr.gov.pk', // Tax Asaan app / FBR invoice verification
    ],
];
