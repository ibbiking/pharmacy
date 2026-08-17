@extends('admin.layouts.app')

@push('page-css')
@include('admin.partials._receipt-styles')
<style>
    /* ============ POS Shell & Frozen Bars ============
       Everything between the top search bar and the bottom detail bar
       (cart + live receipt) scrolls with the normal page scrollbar — one
       shared scroller, nothing gets its own independent overflow container. */
    .pos-shell {
        display: flex;
        flex-direction: column;
        gap: 14px;
        min-height: calc(100vh - 60px);
    }

    .pos-sticky-bar {
        position: sticky;
        top: 60px;
        z-index: 30;
        background: #f1f4f9;
        padding: 14px 0 4px;
        margin: -14px 0 0;
        box-shadow: 0 10px 14px -12px rgba(15, 23, 42, 0.25);
    }

    .pos-sticky-bottom-bar {
        position: sticky;
        bottom: 0;
        z-index: 30;
        background: #f1f4f9;
        padding: 4px 0 14px;
        margin: 0 0 -14px;
        box-shadow: 0 -10px 14px -12px rgba(15, 23, 42, 0.25);
    }

    /* POS Layout */
    .pos-wrapper {
        flex: 1 0 auto;
        display: grid;
        grid-template-columns: 70% 30%;
        gap: 14px;
    }

    .pos-left,
    .pos-right {
        background: #fff;
        border-radius: 14px;
        padding: 16px;
        box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
        border: 1px solid #f1f5f9;
    }

    .pos-search-container {
        border-radius: 50px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }
    .pos-search-container .input-group-text {
        background: transparent;
    }
    .pos-search-container input:focus {
        outline: none;
    }

    /* ---------- Search dropdown ---------- */
    #searchResults {
        max-height: 350px;
        overflow-y: auto;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        box-shadow: 0 14px 28px -10px rgba(15, 23, 42, 0.22), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
        padding: 6px;
    }
    #searchResults .list-group-item {
        transition: all 0.15s ease-in-out;
        background-color: #ffffff;
        border: none !important;
        border-left: 3px solid transparent !important;
        border-radius: 10px !important;
        margin-bottom: 3px;
        color: #1e293b;
    }
    #searchResults .list-group-item:nth-child(even) {
        background-color: #f8fafc;
    }
    #searchResults .list-group-item:hover {
        background: linear-gradient(135deg, #eff6ff, #f0fdfa) !important;
        border-left-color: #3490dc !important;
        color: #0f172a !important;
        transform: translateX(2px);
    }
    /* Deliberately more saturated than plain :hover so the keyboard-selected
       row (arrow-key navigation) reads clearly even with no mouse hover
       happening at the same time. */
    #searchResults .list-group-item.active {
        background: linear-gradient(135deg, #dbeafe, #cffafe) !important;
        border-left-color: #1d4ed8 !important;
        border-left-width: 4px !important;
        color: #0f172a !important;
        box-shadow: 0 2px 8px rgba(29, 78, 216, 0.18);
        transform: translateX(2px);
    }
    #searchResults .list-group-item .formula-text {
        color: #0891b2;
        font-weight: 500;
    }
    #searchResults .list-group-item .product-type-text {
        display: inline-block;
        background: #eef2ff;
        color: #4f46e5;
        font-weight: 700;
        font-size: 0.68em;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 2px 8px;
        border-radius: 20px;
    }
    #searchResults .list-group-item.active .formula-text,
    #searchResults .list-group-item.active .product-type-text {
        color: inherit;
    }

    .pos-stock-dot {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        display: inline-block;
        flex-shrink: 0;
        box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.04);
    }
    .pos-stock-dot--ok { background: #22c55e; }
    .pos-stock-dot--out { background: #ef4444; }

    .pos-price-tag {
        font-weight: 700;
        color: #0f172a;
        font-size: 0.92em;
        white-space: nowrap;
    }

    .btn-stock-summary {
        padding: 5px 10px;
        border-radius: 20px;
        color: #64748b;
        transition: all 0.2s;
        background: #fff;
        border: 1px solid #e2e8f0;
        font-size: 0.75em;
        white-space: nowrap;
    }
    .btn-stock-summary:hover {
        background: #3490dc;
        color: #fff;
        border-color: #3490dc;
        box-shadow: 0 4px 10px rgba(52, 144, 220, 0.3);
    }

    /* ---------- Frozen product detail panel ---------- */
    .pos-detail-panel {
        margin-top: 10px;
        border-radius: 14px;
        background: linear-gradient(135deg, #ffffff, #f8fafc);
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 10px rgba(15, 23, 42, 0.05);
        min-height: 94px;
        display: flex;
        align-items: center;
    }
    .pos-detail-placeholder {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        color: #94a3b8;
        font-size: 0.88em;
        padding: 18px;
        text-align: center;
    }
    .pos-detail-placeholder i { font-size: 1.3em; }

    .pos-detail-content { width: 100%; padding: 14px 18px; }
    .pos-detail-identity {
        display: flex;
        align-items: baseline;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 10px;
    }
    .pos-detail-name { font-size: 1.05em; font-weight: 700; color: #0f172a; }
    .pos-detail-tags { display: flex; gap: 6px; flex-wrap: wrap; }
    .pos-tag {
        font-size: 0.7em;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        padding: 3px 9px;
        border-radius: 20px;
    }
    .pos-tag--type { background: #eef2ff; color: #4f46e5; }
    .pos-tag--formula { background: #ecfeff; color: #0891b2; }

    .pos-detail-blocks {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
        gap: 10px;
    }
    .pos-detail-block {
        border-radius: 10px;
        padding: 8px 12px;
        background: #fff;
        border: 1px solid #eef2f7;
        border-left: 3px solid #cbd5e1;
    }
    .pos-detail-block--price { border-left-color: #3490dc; }
    .pos-detail-block--discount { border-left-color: #22c55e; }
    .pos-detail-block--categories { border-left-color: #17a2b8; }
    .pos-detail-block--stock { border-left-color: #f59e0b; }

    .pos-detail-block-label {
        font-size: 0.68em;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #94a3b8;
        margin-bottom: 3px;
    }
    .pos-detail-block-value {
        font-size: 0.85em;
        font-weight: 600;
        color: #1e293b;
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        align-items: center;
    }

    .pos-chip {
        display: inline-block;
        font-size: 0.85em;
        font-weight: 600;
        padding: 3px 9px;
        border-radius: 20px;
        background: #f1f5f9;
        color: #334155;
    }
    .pos-chip--ok { background: #dcfce7; color: #15803d; }
    .pos-chip--danger { background: #fee2e2; color: #b91c1c; }
    .pos-chip--held { background: #fef3c7; color: #92400e; cursor: help; }
    .pos-chip--held i { margin-right: 3px; }
    .pos-chip-sub { font-weight: 700; opacity: 0.85; }

    /* ---------- Cart table ---------- */
    .cart-table-card {
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #eef2f7;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
    }
    .cart-table { margin-bottom: 0; }
    .cart-table thead th {
        background: linear-gradient(135deg, #1d4ed8, #3490dc);
        color: #fff;
        border: none;
        font-size: 0.72em;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        font-weight: 700;
        padding: 12px 10px;
    }
    .cart-table th,
    .cart-table td {
        vertical-align: middle !important;
        border: none;
        border-bottom: 1px solid #eef2f7;
        padding: 10px;
    }
    .cart-table tbody tr {
        transition: background-color 0.15s ease-in-out;
    }
    .cart-table tbody tr:nth-child(odd) { background-color: #ffffff; }
    .cart-table tbody tr:nth-child(even) { background-color: #f8fafc; }
    .cart-table tbody tr:hover { background-color: #eff6ff; }
    .cart-table tbody tr td:first-child { border-left: 3px solid transparent; }
    .cart-table tbody tr:hover td:first-child { border-left-color: #3490dc; }
    .cart-table .row-total { font-weight: 700; color: #0f172a; }

    /* Rounded, softer fields — applied to every text/number input on the
       POS screen (cart rows AND the invoice-discount/cash-received fields
       below the table), not just the cart table, for a consistent look. */
    .pos-left input.form-control,
    .pos-left input[type="number"],
    .pos-left input[type="text"] {
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #fbfcfe;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out, background-color 0.15s ease-in-out;
    }
    .pos-left input.form-control:focus,
    .pos-left input[type="number"]:focus,
    .pos-left input[type="text"]:focus {
        border-color: #3490dc;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(52, 144, 220, 0.15);
        outline: none;
    }
    .cart-table .input-group .form-control {
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
    }

    /* ---------- Custom category dropdown ----------
       A native <select>'s open popup is mostly OS-rendered chrome — per-
       option colors only half-apply and the rest looks stock. The real
       <select> (built by categoryOptionsHtml in the JS below) is kept in
       the DOM as a hidden data source so every existing .val()/.change()
       call site keeps working untouched; everything below is a purely
       cosmetic, fully custom-styled proxy on top of it. */
    .category-select-wrap {
        position: relative;
    }
    .category-select-display {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 7px 12px;
        border-radius: 8px;
        border: 2px solid #cbd5e1;
        font-weight: 600;
        font-size: 0.92em;
        cursor: pointer;
        user-select: none;
        transition: box-shadow 0.15s ease-in-out;
    }
    .category-select-display:hover {
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.1);
    }
    .category-select-display i {
        font-size: 0.8em;
        opacity: 0.7;
    }
    /* position:fixed (not absolute) is deliberate: .cart-table-card uses
       overflow:hidden to get rounded corners on the table, which clips an
       absolutely-positioned menu to the card's bounds the moment it grows
       past the row it opened from (it visually "hides behind" the next
       row). Fixed positioning is computed from the trigger's
       getBoundingClientRect() in JS on open, so it escapes that clipping
       entirely instead of being constrained by any scrolling/overflow
       ancestor. */
    .category-select-menu {
        display: none;
        position: fixed;
        z-index: 2000;
        min-width: 100%;
        max-height: 220px;
        overflow-y: auto;
        background: #fff;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 12px 24px -8px rgba(15, 23, 42, 0.25), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        padding: 6px;
    }
    .category-select-menu.is-open {
        display: block;
    }
    .category-option {
        padding: 8px 12px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 0.92em;
        cursor: pointer;
        margin-bottom: 3px;
        white-space: nowrap;
        transition: transform 0.1s ease-in-out, box-shadow 0.1s ease-in-out;
    }
    .category-option:hover {
        transform: translateX(2px);
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.15);
    }
    .category-option.is-selected {
        box-shadow: inset 0 0 0 2px rgba(15, 23, 42, 0.25);
    }
    .category-option-empty {
        padding: 8px 12px;
        color: #94a3b8;
        font-size: 0.88em;
    }

    .pos-shortcut-hint {
        font-size: 0.8em;
        opacity: 0.8;
        font-weight: 400;
    }

    .remove-item {
        padding: 0;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: none;
        background: #fef2f2;
        color: #dc2626;
        transition: all 0.15s;
    }
    .remove-item:hover {
        background: #dc2626;
        color: #fff;
        box-shadow: 0 4px 10px rgba(220, 38, 38, 0.3);
    }

    .invoice-discount-type-btn {
        padding: 6px 12px;
        font-size: 12px;
        border: 1px solid #ced4da;
    }

    .invoice-discount-type-btn.active {
        background-color: #3490dc;
        color: white;
        border-color: #3490dc;
    }

    .invoice-discount-type-btn:not(.active):hover {
        background-color: #e9ecef;
    }

    /* Per-row RS/% discount-type toggle inside the cart table — previously
       plain unstyled Bootstrap buttons, now matching the rounded/colored
       treatment used everywhere else on this page. */
    .discount-type-btn {
        border: 1px solid #cbd5e1;
        font-size: 0.78em;
        font-weight: 600;
    }
    .discount-type-btn.active {
        background-color: #3490dc;
        color: #fff;
        border-color: #3490dc;
    }
    .discount-type-btn:not(.active):hover {
        background-color: #eef2f7;
    }
    .cart-table .input-group .discount-type-btn:last-child {
        border-top-right-radius: 8px;
        border-bottom-right-radius: 8px;
    }

    /* ---------- Live receipt ---------- */
    .pos-right .pmx-receipt {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 4px 16px rgba(15, 23, 42, 0.08);
        width: 100%;
    }
    .pos-receipt-live-badge {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.72em;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #16a34a;
        margin-bottom: 8px;
    }
    .pos-receipt-live-badge .pos-live-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #22c55e;
        box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.25);
        animation: pos-pulse 1.6s infinite;
    }
    @keyframes pos-pulse {
        0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.35); }
        70% { box-shadow: 0 0 0 6px rgba(34, 197, 94, 0); }
        100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
    }
</style>
@endpush

@section('content')
<div class="pos-shell">
    <!-- FROZEN TOP BAR: search + product detail panel stay in view while
         the cart/receipt below scroll with the normal page scrollbar. -->
    <div class="pos-sticky-bar" id="posStickyBar">
        <div class="pos-search-container position-relative mb-0">
            <div class="input-group shadow-sm" style="border-radius: 50px; overflow: hidden; border: 2px solid #3490dc; background:#fff;">
                <div class="input-group-prepend">
                    <span class="input-group-text border-0 text-primary" style="padding-left: 20px; padding-right: 15px; height: 100%; display: flex; align-items: center;">
                        <i class="fas fa-search fa-lg"></i>
                    </span>
                </div>
                <input type="text" id="searchProduct" class="form-control border-0"
                    placeholder="Scan Barcode or Search by Medicine Name / Formula (Press Enter)" autofocus style="box-shadow: none; font-size: 18px; padding-left: 5px; height: 50px; background: transparent;">
                <div class="input-group-append" style="display: none;" id="clearSearch">
                    <span class="input-group-text border-0" style="cursor: pointer; padding-right: 20px; padding-left: 15px; height: 100%; display: flex; align-items: center;">
                        <i class="fas fa-times text-muted"></i>
                    </span>
                </div>
            </div>
            <div id="searchResults" class="list-group shadow position-absolute w-100" style="z-index:1000; top: 100%; margin-top: 5px; overflow-y: auto; max-height: 350px; display:none;"></div>
        </div>
    </div>

    <div class="pos-wrapper">
    <!-- LEFT SIDE (MAIN POS) -->
    <div class="pos-left">
        <div class="cart-table-card">
        <table class="table cart-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th>Price</th>
                    <th>Qty</th>
                    <th>Category</th>
                    <th>Disc</th>
                    <th>Total</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="cartBody">
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">No products added yet</td>
                </tr>
            </tbody>
        </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div>
                <label>Invoice Discount:</label>
                <div class="input-group" style="width: 200px;">
                    <input type="number" step="0.01" id="invoiceDiscountValue" class="form-control" value="0" min="0">
                    <div class="input-group-append" style="display: flex;">
                        <button type="button" class="btn btn-outline-secondary invoice-discount-type-btn active"
                            data-type="amount">RS</button>
                        <button type="button" class="btn btn-outline-secondary invoice-discount-type-btn"
                            data-type="percent">%</button>
                    </div>
                </div>
                <small class="form-text text-muted" id="invoiceDiscountHint"></small>
            </div>
            <div class="text-end">
                <div>Subtotal: <span id="subtotalAmount">0.00</span></div>
                <div>Invoice Disc: -<span id="invoiceDiscountDisplay">0.00</span></div>
                <h4>Grand Total: <span id="grandTotal">0.00</span></h4>
            </div>
            <div class="mt-2">
                <label>Cash Received:</label>
                <input type="number" step="0.01" id="cashReceived" class="form-control" placeholder="Enter Cash Amount">
            </div>

            <div class="mt-1">
                <strong>Change Return: <span id="changeReturn">0.00</span></strong>
            </div>
        </div>
    </div>

    <!-- RIGHT SIDE (LIVE RECEIPT) -->
    <div class="pos-right">
        @php
            $businessId = session('business_id');
            $liveReceiptBusiness = $businessId ? \App\Models\Business::find($businessId) : null;
            $liveReceiptName = $liveReceiptBusiness && !empty($liveReceiptBusiness->name)
                ? $liveReceiptBusiness->name
                : settings('app_name', 'Business');
            $liveReceiptAddress = $liveReceiptBusiness && !empty($liveReceiptBusiness->address) && $liveReceiptBusiness->address !== 'N/A'
                ? $liveReceiptBusiness->address
                : settings('company_address', '123 Main Street, City');
            $liveReceiptPhone = $liveReceiptBusiness && !empty($liveReceiptBusiness->phone) && $liveReceiptBusiness->phone !== 'N/A'
                ? $liveReceiptBusiness->phone
                : settings('company_phone', '0300-XXXXXXX');
        @endphp
        <div class="pos-receipt-live-badge"><span class="pos-live-dot"></span> Live Preview</div>
        <div id="receiptArea" class="pmx-receipt">
            <div class="pmx-header">
                <h1 class="pmx-brand">{{ $liveReceiptName }}</h1>
                <div class="pmx-brand-line">{{ $liveReceiptAddress }}</div>
                <div class="pmx-doc-title">Sale Receipt</div>
            </div>

            <div class="pmx-meta">
                <div class="pmx-row">
                    <span class="pmx-label">Invoice #</span>
                    <span class="pmx-value" id="receiptInvoiceNo">{{ $nextInvoiceNo ?? 'Pending (FBR)' }}</span>
                </div>
                <div class="pmx-row">
                    <span class="pmx-label">Date</span>
                    <span class="pmx-value">{{ \Carbon\Carbon::now()->format('d-M-y g:ia') }}</span>
                </div>
            </div>

            <div class="pmx-divider"></div>

            <table class="pmx-items">
                <thead>
                    <tr>
                        <th class="col-sr">#</th>
                        <th class="col-item">Item</th>
                        <th class="col-qty">Qty</th>
                        <th class="col-price">Price</th>
                        <th class="col-disc">Disc</th>
                        <th class="col-total">Total</th>
                    </tr>
                </thead>
                <tbody id="receiptBody">
                    <tr>
                        <td colspan="6" class="pmx-empty">No items</td>
                    </tr>
                </tbody>
            </table>

            <div class="pmx-divider"></div>

            <div class="pmx-totals">
                <div id="receiptSubtotalBlock">
                    <div class="pmx-row">
                        <span class="pmx-label">Subtotal</span>
                        <span class="pmx-value">0.00</span>
                    </div>
                </div>
                <div id="receiptInvoiceDiscountBlock"></div>
                <div id="receiptSalesTaxRows"></div>
                <div class="pmx-row pmx-row--grand">
                    <span class="pmx-label">Grand Total</span>
                    <span class="pmx-value" id="receiptTotal">0.00</span>
                </div>
                <div class="pmx-row" style="margin-top: 6px;">
                    <span class="pmx-label">Cash Received</span>
                    <span class="pmx-value" id="cashReceivedDisplay">0.00</span>
                </div>
                <div class="pmx-row">
                    <span class="pmx-label">Change Return</span>
                    <span class="pmx-value" id="cashChangeDisplay">0.00</span>
                </div>
            </div>

            <div id="receiptGstSection" style="display: none;">
                <div class="pmx-divider"></div>
                <div class="pmx-totals">
                    <div class="pmx-note">GST Disclosure (already included above, not an extra charge)</div>
                    <div id="receiptGstRows"></div>
                </div>
            </div>

            <div class="pmx-divider"></div>

            <div class="pmx-footer">
                @if($liveReceiptBusiness && !empty($liveReceiptBusiness->note))
                <div>{{ $liveReceiptBusiness->note }}</div>
                @endif
                <div class="pmx-thanks">Thank you for your purchase!</div>
                <div>Please visit again</div>
                <div>Contact: {{ $liveReceiptPhone }}</div>
            </div>
        </div>
        <button class="btn btn-primary btn-block mt-3" id="saveAndPrint" title="Shortcut: F9"><i class="fa fa-save"></i> Save &
            Print <span class="pos-shortcut-hint">(F9)</span></button>
    </div>
    </div>

    <!-- FROZEN BOTTOM BAR: product detail panel stays pinned to the bottom
         of the viewport while the cart/receipt above scroll with the
         normal page scrollbar. -->
    <div class="pos-sticky-bottom-bar" id="posStickyBottomBar">
        <div id="productDetailPanel" class="pos-detail-panel">
            <div class="pos-detail-placeholder" id="detailPlaceholder">
                <i class="fas fa-hand-pointer"></i>
                <span>Hover a product in the search results or in the cart below (or use arrow keys in the search box) to see its full details here</span>
            </div>
            <div class="pos-detail-content" id="detailContent" style="display:none;">
                <div class="pos-detail-identity">
                    <span class="pos-detail-name" id="detailName">-</span>
                    <div class="pos-detail-tags">
                        <span class="pos-tag pos-tag--type" id="detailType" style="display:none;"></span>
                        <span class="pos-tag pos-tag--formula" id="detailFormula" style="display:none;"></span>
                    </div>
                </div>
                <div class="pos-detail-blocks">
                    <div class="pos-detail-block pos-detail-block--price">
                        <div class="pos-detail-block-label"><i class="fas fa-tag"></i> Price</div>
                        <div class="pos-detail-block-value" id="detailPrice">-</div>
                    </div>
                    <div class="pos-detail-block pos-detail-block--discount">
                        <div class="pos-detail-block-label"><i class="fas fa-percentage"></i> Discount</div>
                        <div class="pos-detail-block-value" id="detailDiscount">-</div>
                    </div>
                    <div class="pos-detail-block pos-detail-block--categories">
                        <div class="pos-detail-block-label"><i class="fas fa-layer-group"></i> Category Pricing</div>
                        <div class="pos-detail-block-value" id="detailCategories">-</div>
                    </div>
                    <div class="pos-detail-block pos-detail-block--stock">
                        <div class="pos-detail-block-label"><i class="fas fa-warehouse"></i> Stock</div>
                        <div class="pos-detail-block-value" id="detailStock">-</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stock Summary Modal -->
<div class="modal fade" id="stockModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Stock Summary</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="stock-content">Loading...</div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script>
    console.log("POS script initialized");
// Ensure jQuery runs safely
jQuery(function ($) {
    console.log("POS script initialized");

    let cart = [];

    // Focus on search field when page loads
    $('#searchProduct').focus();

    let searchResults = [];
    let selectedIndex = 0;
    // Guards against out-of-order AJAX responses: a request fired for an
    // earlier, longer query can resolve AFTER the box has since been
    // cleared/changed, and would otherwise reopen the dropdown with stale
    // results. Bumped before every request; a response is only rendered if
    // it's still the latest one AND the box still holds the query it was
    // sent for.
    let searchRequestToken = 0;

    // Invoice discount type state
    let invoiceDiscountType = 'amount'; // Default to amount

    // Frozen product-detail panel state (hover over a search result or a
    // cart row). Identity/pricing/discount/categories come from data
    // already in memory (searchResults[]/cart[]); stock availability is
    // fetched fresh every time (see fetchProductDetailStock) since it's
    // live and can change from any sales-person's cart at any moment.
    let currentDetailProductId = null;
    let detailHideTimer = null;

    function getCartPayload(excludeIndex = null) {
        return JSON.stringify(cart
            .map((item, idx) => ({
                product_id: item.id,
                category_id: item.category_id || null,
                base_stock_sale_price_id: item.base_stock_sale_price_id || null,
                unit_price: item.price,
                base_qty: item.base_qty || 0,
                quantity: item.qty,
                price_group: item.price_group || (item.price ? parseFloat(item.price).toFixed(2) : null)
            }))
            .filter((_, idx) => excludeIndex === null ? true : idx !== excludeIndex)
        );
    }

    // Utility: Save POS cart to server session
    function saveCartToSession(excludeIndex = null) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: '/admin/pos/save-cart-session',
                type: 'POST',
                data: {
                    cart: getCartPayload(excludeIndex),
                    _token: '{{ csrf_token() }}'
                },
                success: function() {
                    resolve();
                },
                error: function(err) {
                    console.error('Error saving cart session', err);
                    resolve();
                }
            });
        });
    }

    // Initialize invoice discount toggle buttons
    function initInvoiceDiscountToggle() {
        $('.invoice-discount-type-btn').off('click').on('click', function() {
            const type = $(this).data('type');

            // Update UI
            $('.invoice-discount-type-btn').removeClass('active');
            $(this).addClass('active');

            // Update state
            invoiceDiscountType = type;

            // Update hint text
            updateInvoiceDiscountHint();

            // Recalculate totals
            recalcTotals();
        });

        // Trigger recalc on discount value change
        $('#invoiceDiscountValue').off('input').on('input', function() {
            recalcTotals();
        });

        // Update hint on initial load
        updateInvoiceDiscountHint();
    }

    function updateInvoiceDiscountHint() {
        const hint = invoiceDiscountType === 'percent'
            ? 'Percentage discount applied on subtotal'
            : 'Fixed amount discount';
        $('#invoiceDiscountHint').text(hint);
    }

    // Live search with dropdown
    $('#searchProduct').on('input', function() {
        const query = $(this).val().trim();
        
        if (query.length > 0) {
            $('#clearSearch').show();
        } else {
            $('#clearSearch').hide();
        }

        if (query.length < 2) {
            $('#searchResults').hide();
            return;
        }

        const requestToken = ++searchRequestToken;

        $.ajax({
            url: {!! json_encode(route('products.search')) !!},
            method: 'GET',
            data: { q: query, current_cart: getCartPayload() },
            success: function(data) {
                // Stale response guard: bail out silently if a newer request
                // has since been fired, or the box no longer holds the query
                // this response was fetched for (e.g. cleared or edited while
                // the request was in flight).
                if (requestToken !== searchRequestToken || $('#searchProduct').val().trim() !== query) {
                    return;
                }

                $('#searchResults').empty();

                // Guard against redirected HTML/errors or malformed payloads.
                if (!Array.isArray(data)) {
                    if (data && typeof data === 'object' && data.product_name) {
                        data = [data];
                    } else {
                        data = [];
                    }
                }

                data = data.filter(product => product && product.product_name);

                if (Array.isArray(data) && data.length > 0) {
                    searchResults = data;
                    selectedIndex = 0;

                    data.forEach((product, i) => {
                        const item = $(`
                            <a href="#" class="list-group-item list-group-item-action ${i === 0 ? 'active' : ''}" 
                               data-index="${i}" 
                               style="border-left: none; border-right: none; padding: 12px 20px;">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex flex-column" style="flex: 1;">
                                        <div style="font-size: 1.15em; font-weight: 600; line-height: 1.2;">
                                            ${product.product_name}${product.strength?.name ? `-${product.strength.name}` : ''}
                                        </div>
                                        <div class="d-flex align-items-center mt-1">
                                            ${product.product_type ? `<span class="product-type-text" style="font-size: 0.75em;">${product.product_type}</span>` : ''}
                                            ${product.farmula ? `<small class="formula-text ml-2" style="font-size: 0.85em;">${product.farmula}</small>` : ''}
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center" style="gap: 10px;">
                                        <span class="pos-stock-dot ${product.out_of_stock ? 'pos-stock-dot--out' : 'pos-stock-dot--ok'}" title="${product.out_of_stock ? 'Out of stock' : 'In stock'}"></span>
                                        ${product.price ? `<span class="pos-price-tag">Rs ${parseFloat(product.price).toFixed(2)}</span>` : ''}
                                        <button class="btn-stock-summary show-stock-summary" data-id="${product.id}" title="View Stock Summary">
                                            <i class="fas fa-warehouse mr-1"></i> <small>Stock</small>
                                        </button>
                                    </div>
                                </div>
                            </a>
                        `);
                        $('#searchResults').append(item);
                    });

                    $('#searchResults').show();

                    // The first row is highlighted active by default — show
                    // its details right away instead of waiting for a hover
                    // or an arrow-key press.
                    showProductDetail(searchResults[0], false);
                } else {
                    $('#searchResults')
                        .html('<div class="list-group-item text-muted">No products found</div>')
                        .show();
                }
            },
            error: function() {
                if (requestToken !== searchRequestToken || $('#searchProduct').val().trim() !== query) {
                    return;
                }
                $('#searchResults')
                    .html('<div class="list-group-item text-danger">Error fetching results</div>')
                    .show();
            }
        });
    });

    // Keyboard navigation for dropdown
    $('#searchProduct').on('keydown', function(e) {
        const items = $('#searchResults .list-group-item');
        if (items.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = (selectedIndex + 1) % items.length;
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = (selectedIndex - 1 + items.length) % items.length;
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const selectedProduct = searchResults[selectedIndex];
            if (selectedProduct) {
                addToCart(selectedProduct);
                $('#searchProduct').val('');
                $('#searchResults').hide();
                $('#clearSearch').hide();
            }
            return;
        } else {
            return; // other keys handled by input event
        }

        // Update visual highlight
        items.removeClass('active').eq(selectedIndex).addClass('active');

        // Keep the highlighted row scrolled into view — #searchResults has
        // its own overflow-y:auto, so simply toggling the .active class
        // doesn't bring an off-screen row back into view on its own.
        const activeEl = items.eq(selectedIndex).get(0);
        if (activeEl && activeEl.scrollIntoView) {
            activeEl.scrollIntoView({ block: 'nearest' });
        }

        // Keep the frozen detail panel in sync with keyboard navigation too,
        // not just hover.
        const highlightedProduct = searchResults[selectedIndex];
        if (highlightedProduct) {
            showProductDetail(highlightedProduct, false);
        }
    });

    // Click selection from dropdown
    $(document).on('click', '#searchResults .list-group-item', function(e) {
        // Prevent adding to cart if the stock button was clicked
        if ($(e.target).closest('.show-stock-summary').length) {
            return;
        }

        e.preventDefault();
        const i = $(this).data('index');
        const product = searchResults[i];
        if (product) {
            addToCart(product);
            $('#searchProduct').val('').focus();
            $('#searchResults').hide();
            $('#clearSearch').hide();
        }
    });

    // Stock Summary Click
    $(document).on('click', '.show-stock-summary', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const productId = $(this).data('id');
        showStockSummary(productId);
    });

    function showStockSummary(productId) {
        $('#stock-content').html('<div class="text-center p-3"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><br>Loading summary...</div>');
        $('#stockModal').modal('show');

        $.ajax({
            url: "/admin/products/" + productId + "/stock-summary",
            type: 'GET',
            success: function (res) {
                $('#stockModal .modal-title').text('Stock Summary [' + res.product_name + ']');

                if (res.summary.available.length === 0 && res.summary.expired.length === 0) {
                    $('#stock-content').html('<div class="alert alert-warning text-center mt-3 mb-3">No stock available in any category</div>');
                    return;
                }

                let html = '';

                if (res.summary.available.length > 0) {
                    html += '<h6 class="text-success mt-1 mb-2 font-weight-bold">Available Stock</h6>';
                    html += '<table class="table table-bordered table-sm mb-3">';
                    html += '<thead class="bg-light"><tr><th>Category</th><th>Quantity</th></tr></thead><tbody>';
                    res.summary.available.forEach(function (item) {
                        html += '<tr><td>' + item.category + '</td><td class="font-weight-bold">' + item.quantity + '</td></tr>';
                    });
                    html += '</tbody></table>';
                }

                if (res.summary.expired.length > 0) {
                    html += '<h6 class="text-danger mt-3 mb-2 font-weight-bold">Expired Stock</h6>';
                    html += '<table class="table table-bordered table-sm">';
                    html += '<thead class="bg-danger text-white"><tr><th>Category</th><th>Quantity</th></tr></thead><tbody>';
                    res.summary.expired.forEach(function (item) {
                        html += '<tr><td>' + item.category + '</td><td class="text-danger font-weight-bold">' + item.quantity + '</td></tr>';
                    });
                    html += '</tbody></table>';
                }

                $('#stock-content').html(html);
            },
            error: function (xhr) {
                $('#stock-content').html('<div class="alert alert-danger">Failed to load stock summary. (Status: ' + xhr.status + ')</div>');
            }
        });
    }

    // ------------------------------
    // Frozen product-detail panel: hover a search-result row or a cart row
    // to see stock / discount / category pricing for that product. Identity/
    // pricing/discount/categories come from data already available
    // client-side (the product object from products.search, or the cart
    // line item); stock availability is fetched fresh from the server
    // every time (see fetchProductDetailStock).
    let currentDetailMyCartByCategory = {};

    // Grouped by category NAME (Tablet/Strip/Box, resolved via the cart
    // line's own stored `categories` list) so it can be paired with the
    // matching availability chip in renderDetailStock — a product can have
    // MULTIPLE cart lines for the same category when they come from
    // different purchase batches/prices (mergeSamePriceRows only merges
    // lines sharing the exact same batch/price_group), so this must sum
    // ALL matching lines, not just the first one found.
    function computeMyCartByCategory(productId) {
        const byCategory = {};
        cart.filter(function (item) { return item.id == productId; }).forEach(function (item) {
            const cat = (item.categories || []).find(function (c) { return c.id == item.category_id; });
            const catName = cat ? cat.name : 'Selected unit';
            byCategory[catName] = (byCategory[catName] || 0) + (parseFloat(item.qty) || 0);
        });
        return byCategory;
    }

    // The detail panel only updates on hover/arrow-nav by design (it stays
    // "frozen" showing the last-viewed product while the cart/receipt
    // scroll) — but that means it goes stale the instant the viewer's OWN
    // action (adding another unit, a second batch-priced row, editing qty)
    // changes the very thing it's showing, with no further hover to catch
    // it. Called from renderCart() after every cart mutation so the panel
    // stays live-bound to whichever product it's currently displaying.
    function refreshDetailStockIfShowing() {
        if (!currentDetailProductId) return;
        currentDetailMyCartByCategory = computeMyCartByCategory(currentDetailProductId);
        fetchProductDetailStock(currentDetailProductId);
    }

    function showProductDetail(source, isCartItem) {
        clearTimeout(detailHideTimer);

        const strengthName = (!isCartItem && source.strength && source.strength.name) ? '-' + source.strength.name : '';
        const displayName = isCartItem ? source.name : `${source.product_name}${strengthName}`;
        const productType = source.product_type || '';
        const formula = isCartItem ? '' : (source.farmula || '');
        const price = parseFloat(source.price) || 0;
        const discountPercent = parseFloat(source.discount_percent) || 0;
        const discountAmount = parseFloat(isCartItem ? source.discount_amount : source.discount) || 0;
        const lockMax = !!source.lock_max_discount;
        const categories = source.categories || [];
        const productId = source.id;

        // "In your cart" always reflects the viewer's OWN cart, computed
        // straight from the in-memory `cart` array — not a server call —
        // so it's correct for the self case, not just the "someone else is
        // holding it" case (that part comes from the server, see the
        // "held by" chip below).
        currentDetailMyCartByCategory = computeMyCartByCategory(productId);

        $('#detailPlaceholder').hide();
        $('#detailContent').show();

        $('#detailName').text(displayName || '-');
        $('#detailType').text(productType).toggle(!!productType);
        $('#detailFormula').text(formula).toggle(!!formula);

        $('#detailPrice').text(price ? 'Rs ' + price.toFixed(2) : '-');

        let discountText = 'No discount';
        if (discountPercent > 0) {
            discountText = discountPercent.toFixed(2) + '%';
        } else if (discountAmount > 0) {
            discountText = 'Rs ' + discountAmount.toFixed(2);
        }
        if (lockMax && (discountPercent > 0 || discountAmount > 0)) {
            discountText += ' <span class="pos-chip">max locked</span>';
        }
        $('#detailDiscount').html(discountText);

        if (categories.length) {
            $('#detailCategories').html(categories.map(function (c) {
                const catPrice = (c.price !== undefined && c.price !== null) ? parseFloat(c.price).toFixed(2) : null;
                return `<span class="pos-chip">${c.name}${catPrice !== null ? ': Rs ' + catPrice : ''}</span>`;
            }).join(''));
        } else {
            $('#detailCategories').html('<span class="text-muted">No categories configured</span>');
        }

        if (!productId) {
            $('#detailStock').html('<span class="text-muted">-</span>');
            return;
        }

        // Availability is genuinely live — it changes as soon as anyone
        // (this session or another sales-person) edits a cart, so it is
        // deliberately NOT cached across hovers; a stale cached number here
        // is exactly what would show "1 in cart" as still fully available.
        //
        // Fetched immediately (no artificial debounce delay) — a delay here
        // just widens the window where the PREVIOUSLY hovered product's
        // stock is still on screen while the user has already moved to a
        // different row, reading as "stuck one step behind". Firing right
        // away and letting currentDetailProductId discard any out-of-order
        // response (see fetchProductDetailStock) closes that window instead
        // of trying to time around it.
        currentDetailProductId = productId;
        $('#detailStock').html('<span class="text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</span>');
        fetchProductDetailStock(productId);
    }

    function fetchProductDetailStock(productId) {
        $.ajax({
            url: '/admin/products/' + productId + '/pos-availability',
            type: 'GET',
            success: function (res) {
                // Ignore an out-of-order response for a product that's no
                // longer the one being shown (e.g. the mouse has since
                // moved to a different row).
                if (productId !== currentDetailProductId) return;

                renderDetailStock({
                    summary: res.summary || { available: [], expired: [] },
                    heldBy: res.held_by || []
                });
            },
            error: function () {
                if (productId !== currentDetailProductId) return;
                $('#detailStock').html('<span class="text-danger">Unable to load stock</span>');
            }
        });
    }

    function renderDetailStock(data) {
        const summary = data.summary || { available: [], expired: [] };
        const heldBy = data.heldBy || [];
        const myCartByCategory = Object.assign({}, currentDetailMyCartByCategory);

        let html = '';
        (summary.available || []).forEach(function (item) {
            const myQty = myCartByCategory[item.category] || 0;
            delete myCartByCategory[item.category]; // mark as accounted for
            const inCartNote = myQty > 0
                ? ` <span class="pos-chip-sub">(${myQty} in your cart)</span>`
                : '';
            html += `<span class="pos-chip pos-chip--ok">${item.category}: ${item.quantity}${inCartNote}</span>`;
        });
        (summary.expired || []).forEach(function (item) {
            html += `<span class="pos-chip pos-chip--danger">${item.category}: ${item.quantity} expired</span>`;
        });
        // Any cart quantity in a unit that didn't match one of the
        // available-stock chips above (edge case) still gets shown, rather
        // than silently disappearing.
        Object.keys(myCartByCategory).forEach(function (catName) {
            if (myCartByCategory[catName] > 0) {
                html += `<span class="pos-chip pos-chip--ok">${catName}: <span class="pos-chip-sub">(${myCartByCategory[catName]} in your cart)</span></span>`;
            }
        });

        // "Held by" info is shown via a tooltip on a small inline icon
        // rather than an extra row, so the panel's height never grows.
        if (heldBy.length > 0) {
            const heldByText = heldBy.map(function (h) {
                return `${h.name}: ${parseFloat(h.qty).toFixed(2)}`;
            }).join(', ');
            html += `<span class="pos-chip pos-chip--held" title="Held in other sales-persons' carts: ${heldByText}"><i class="fas fa-user-clock"></i> ${heldByText}</span>`;
        }

        $('#detailStock').html(html || '<span class="text-muted">No stock available</span>');
    }

    function hideProductDetail() {
        clearTimeout(detailHideTimer);
        detailHideTimer = setTimeout(function () {
            $('#detailContent').hide();
            $('#detailPlaceholder').show();
        }, 200);
    }

    // Hover a search-result row
    $('#searchResults').on('mouseenter', '.list-group-item[data-index]', function () {
        const i = $(this).data('index');
        const product = searchResults[i];
        if (product) showProductDetail(product, false);
    });
    $('#searchResults').on('mouseleave', '.list-group-item[data-index]', function () {
        hideProductDetail();
    });

    // Hover a cart row
    $('#cartBody').on('mouseenter', 'tr[data-index]', function () {
        const i = $(this).data('index');
        const item = cart[i];
        if (item) showProductDetail(item, true);
    });
    $('#cartBody').on('mouseleave', 'tr[data-index]', function () {
        hideProductDetail();
    });

    // Custom category dropdown (see categoryMenuItemsHtml/applyCategorySelectColor
    // above for why the real <select> stays hidden). Bound once on the
    // persistent #cartBody container via delegation, so it keeps working
    // across every renderCart() re-render without needing to be re-bound.
    $('#cartBody').on('click', '.category-select-display', function (e) {
        e.stopPropagation();
        const index = $(this).data('index');
        const $menu = $(`.category-select-menu[data-index="${index}"]`);
        const wasOpen = $menu.hasClass('is-open');
        $('.category-select-menu').removeClass('is-open');
        if (!wasOpen) {
            // Positioned here (not in CSS) because it's fixed relative to
            // the viewport, computed fresh from the trigger's current
            // on-screen position every time it opens.
            const rect = this.getBoundingClientRect();
            $menu.css({
                top: rect.bottom + 4,
                left: rect.left,
                minWidth: rect.width
            });
            $menu.addClass('is-open');
        }
    });
    $('#cartBody').on('click', '.category-option', function (e) {
        e.stopPropagation();
        const $menu = $(this).closest('.category-select-menu');
        const index = $menu.data('index');
        const value = $(this).data('value');
        // Routes through the real <select> + its existing 'change' handler
        // — this is the ONLY thing that actually changes the category, the
        // custom menu is purely a prettier input method for the same value.
        $(`.category-select[data-index="${index}"]`).val(value).trigger('change');
        $menu.removeClass('is-open');
    });
    $(document).on('click', function () {
        $('.category-select-menu').removeClass('is-open');
    });
    // Fixed-position menus don't move with the page scroll on their own —
    // simplest correct behavior is to close rather than reposition.
    $(window).on('scroll resize', function () {
        $('.category-select-menu').removeClass('is-open');
    });

    // Clear search manually
    $('#clearSearch').on('click', function() {
        $('#searchProduct').val('').focus();
        $('#searchResults').hide();
        $(this).hide();
    });

    // Helper to render categories HTML
    // A distinct, DETERMINISTIC color per category (keyed by category id,
    // not render order) so the same category always looks the same
    // everywhere a category dropdown appears — purely cosmetic, no effect
    // on the category-select's value/change behavior.
    const CATEGORY_COLOR_PALETTE = [
        { bg: '#dbeafe', text: '#1d4ed8' }, // blue
        { bg: '#ccfbf1', text: '#0f766e' }, // teal
        { bg: '#dcfce7', text: '#15803d' }, // green
        { bg: '#fef3c7', text: '#92400e' }, // amber
        { bg: '#ede9fe', text: '#6d28d9' }, // purple
        { bg: '#fce7f3', text: '#be185d' }, // pink
        { bg: '#cffafe', text: '#0e7490' }, // cyan
        { bg: '#ffedd5', text: '#c2410c' }, // orange
        { bg: '#ffe4e6', text: '#be123c' }, // rose
        { bg: '#e0e7ff', text: '#4338ca' }, // indigo
    ];

    function colorForCategory(catId) {
        const idx = Math.abs(parseInt(catId, 10) || 0) % CATEGORY_COLOR_PALETTE.length;
        return CATEGORY_COLOR_PALETTE[idx];
    }

    function categoryOptionsHtml(categories, selectedId) {
        if (!categories || categories.length === 0) {
            return `<option value="">No Categories</option>`;
        }
        return categories.map(cat => {
            const color = colorForCategory(cat.id);
            return `<option value="${cat.id}" ${cat.id == selectedId ? 'selected' : ''} style="background-color:${color.bg}; color:${color.text};">${cat.name}</option>`;
        }).join('');
    }

    // A native <select>'s OPEN dropdown popup is largely OS-rendered chrome
    // in most browsers — per-option background/text color only partially
    // applies and the rest looks stock, clashing with a custom-styled page.
    // So the real <select> (built by categoryOptionsHtml above) stays in
    // the DOM as the hidden source of truth — every existing call site that
    // reads/sets/triggers it (.val(), .trigger('change'), the change
    // handler below) keeps working completely unchanged — and a fully
    // custom-styled proxy (a colored "button" + an absolutely positioned,
    // colored option list built by categoryMenuItemsHtml) sits on top of it
    // purely for display/interaction.
    function categoryMenuItemsHtml(categories, selectedId) {
        if (!categories || categories.length === 0) {
            return `<div class="category-option-empty">No categories</div>`;
        }
        return categories.map(cat => {
            const color = colorForCategory(cat.id);
            const isSelected = cat.id == selectedId;
            return `<div class="category-option ${isSelected ? 'is-selected' : ''}" data-value="${cat.id}" style="background:${color.bg}; color:${color.text};">${cat.name}</div>`;
        }).join('');
    }

    // Syncs the custom proxy (label text + color) to whatever the hidden
    // <select> currently holds — called on initial render AND from inside
    // the existing change handler, so it stays correct whether the value
    // changed via our custom menu or via the pre-existing programmatic
    // `.val(...).trigger('change')` auto-switch call sites elsewhere in
    // this file.
    function applyCategorySelectColor($select) {
        const color = colorForCategory($select.val());
        const label = $select.find('option:selected').text() || 'Select category';
        const index = $select.data('index');
        const $display = $(`.category-select-display[data-index="${index}"]`);
        $display.css({
            'background-color': color.bg,
            'color': color.text,
            'border-color': color.text
        });
        $display.find('.category-select-display-label').text(label);
    }

    // Add or update cart - updated flow:
    // - Save current cart to session (excluding nothing)
    // - Call check-stock endpoint for requested product + qty (1) so backend returns price-grouped rows
    // - Merge rows into cart (price-grouped)
    async function addToCart(product) {
        // default selected category (coming from product data)
        const selectedCategoryId = product.default_category_id || null;

        // Save current cart to session so backend uses reserved quantities
        await saveCartToSession(null);

        // Call POS stock check endpoint to get price-grouped allocation for qty = 1
        $.ajax({
            url: '/admin/products/pos/check-stock',
            type: 'POST',
            data: {
                product_id: product.id,
                quantity: 1,
                category_id: selectedCategoryId,
                from_base_stock_sale_price_id: null,
                current_cart: getCartPayload(),
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if ((response.status === 'error' || response.status === 'partial') && response.rows && response.rows.length) {
                    // If partial with rows: add returned rows (they represent price-grouped allocation)
                    const baseItemName = `${product.product_name}${product.strength?.name ? '-' + product.strength.name : ''}`;

                    response.rows.forEach(row => {
                        cart.push({
                            id: product.id,
                            name: baseItemName,
                            product_type: product.product_type || '',
                            qty: parseFloat(row.quantity),
                            price: parseFloat(row.unit_price),
                            category_id: row.category_id || selectedCategoryId || null,
                            base_stock_sale_price_id: row.base_stock_sale_price_id || null,
                            base_qty: row.base_qty || row.quantity,
                            price_group: row.price_group || Number(row.unit_price).toFixed(2),

                            discount_percent: parseFloat(product.discount_percent || 0),
                            discount_amount: parseFloat(product.discount || 0),
                            discount_selected_type: (parseFloat(product.discount_percent || 0) > 0) ? 'percent' : ((parseFloat(product.discount || 0) > 0) ? 'amount' : 'percent'),
                            lock_max_discount: !!product.lock_max_discount,
                            max_discount_percent: parseFloat(product.discount_percent || 0),
                            max_discount_amount: parseFloat(product.discount || 0),
                            categories: product.categories || []
                        });
                    });

                    // Merge same-price rows
                    mergeSamePriceRows();

                    // Save updated cart to session
                    saveCartToSession(null).then(() => {
                        renderCart();
                    });

                    if (response.status === 'error') {
                        alert(response.message || 'Insufficient stock - partial rows added.');
                    } else if (response.status === 'partial') {
                        alert(response.message || 'Partial allocation added due to limited stock.');
                    }

                    return;
                }

                if (response.status === 'ok' && response.rows && response.rows.length) {
                    // Normal allocation: add rows returned
                    const baseItemName = `${product.product_name}${product.strength?.name ? '-' + product.strength.name : ''}`;

                    response.rows.forEach(row => {
                        cart.push({
                            id: product.id,
                            name: baseItemName,
                            product_type: product.product_type || '',
                            qty: parseFloat(row.quantity),
                            price: parseFloat(row.unit_price),
                            category_id: row.category_id || selectedCategoryId || null,
                            base_stock_sale_price_id: row.base_stock_sale_price_id || null,
                            base_qty: row.base_qty || row.quantity,
                            price_group: row.price_group || Number(row.unit_price).toFixed(2),

                            discount_percent: parseFloat(product.discount_percent || 0),
                            discount_amount: parseFloat(product.discount || 0),
                            discount_selected_type: (parseFloat(product.discount_percent || 0) > 0) ? 'percent' : ((parseFloat(product.discount || 0) > 0) ? 'amount' : 'percent'),
                            lock_max_discount: !!product.lock_max_discount,
                            max_discount_percent: parseFloat(product.discount_percent || 0),
                            max_discount_amount: parseFloat(product.discount || 0),
                            categories: product.categories || []
                        });
                    });

                    // Merge same-price rows
                    mergeSamePriceRows();

                    // Save updated cart to session
                    saveCartToSession(null).then(() => {
                        renderCart();
                    });

                    return;
                }

                // no rows returned - treat as out of stock
                if (response.status === 'error' && (!response.rows || response.rows.length === 0)) {
                    alert(response.message || 'No stock available for this product');
                    return;
                }

                // fallback: if no structured response, just add a simple row (defensive)
                cart.push({
                    id: product.id,
                    name: `${product.product_name}${product.strength?.name ? '-' + product.strength.name : ''}`,
                    product_type: product.product_type || '',
                    qty: 1,
                    price: parseFloat(product.price || 0),
                    category_id: selectedCategoryId,
                    base_stock_sale_price_id: product.base_stock_sale_price_id || null,
                    base_qty: 1,
                    price_group: Number(product.price || 0).toFixed(2),

                    discount_percent: parseFloat(product.discount_percent || 0),
                    discount_amount: parseFloat(product.discount || 0),
                    discount_selected_type: (parseFloat(product.discount_percent || 0) > 0) ? 'percent' : ((parseFloat(product.discount || 0) > 0) ? 'amount' : 'percent'),
                    lock_max_discount: !!product.lock_max_discount,
                    max_discount_percent: parseFloat(product.discount_percent || 0),
                    max_discount_amount: parseFloat(product.discount || 0),
                    categories: product.categories || []
                });

                saveCartToSession(null).then(() => {
                    renderCart();
                });
            },
            error: function(xhr) {
                console.error('Error checking stock on add', xhr);
                alert('Error adding product - stock check failed');
            }
        });
    }

    // Render cart table
    function renderCart() {
        let html = '';
        if (cart.length === 0) {
            html = `<tr><td colspan="7" class="text-center">No products added yet</td></tr>`;
        } else {
            cart.forEach((p, i) => {
                // compute row discount according to selected type
                const base = (p.price * p.qty) || 0;
                let rowDiscount = 0;
                if (p.discount_selected_type === 'percent') {
                    rowDiscount = base * ((parseFloat(p.discount_percent) || 0) / 100);
                } else {
                    rowDiscount = parseFloat(p.discount_amount) || 0;
                }

                // show discount input value depending on type
                const discountInputValue = p.discount_selected_type === 'percent'
                    ? (parseFloat(p.discount_percent) || 0)
                    : (parseFloat(p.discount_amount) || 0);

                html += `
<tr data-index="${i}">
    <td>${i + 1}</td>
    <td>${p.name}</td>
    <td><input type="number" step="0.01" class="form-control price" data-index="${i}" value="${(p.price || 0).toFixed(2)}"></td>
    <td><input type="number" min="0" class="form-control qty" data-index="${i}" value="${p.qty}"></td>
    <td>
        <div class="category-select-wrap">
            <select class="form-select category-select" data-index="${i}" style="display:none;">
                ${categoryOptionsHtml(p.categories, p.category_id)}
            </select>
            <div class="category-select-display" data-index="${i}">
                <span class="category-select-display-label">-</span>
                <i class="fas fa-chevron-down"></i>
            </div>
            <div class="category-select-menu" data-index="${i}">
                ${categoryMenuItemsHtml(p.categories, p.category_id)}
            </div>
        </div>
    </td>
    <td>
        <div class="input-group">
            <input type="number" step="0.01" class="form-control discount-input" data-index="${i}" value="${Number(discountInputValue).toFixed(2)}">
            <div class="input-group-append" style="display:flex;">
                <button class="btn btn-outline-secondary discount-type-btn ${p.discount_selected_type === 'percent' ? 'active' : ''}" data-index="${i}" data-type="percent">%</button>
                <button class="btn btn-outline-secondary discount-type-btn ${p.discount_selected_type === 'amount' ? 'active' : ''}" data-index="${i}" data-type="amount">RS</button>
            </div>
        </div>
        <small class="form-text text-muted discount-hint" data-index="${i}">
            ${p.lock_max_discount ? 'Max: ' + (p.discount_selected_type === 'percent' ? (p.max_discount_percent || 0) + '%' : (p.max_discount_amount || 0).toFixed(2) + ' RS') : ''}
        </small>
    </td>
    <td class="row-total" data-index="${i}">${Math.max(0, base - rowDiscount).toFixed(2)}</td>
    <td class="text-center">
        <button class="btn btn-danger btn-sm remove-item" data-index="${i}">
            <i class="fa fa-trash"></i>
        </button>
    </td>
</tr>`;
            });
        }
        $('#cartBody').html(html);

        // Tint each category dropdown to match its current selection.
        $('.category-select').each(function () {
            applyCategorySelectColor($(this));
        });

        // Initialize invoice discount toggle
        initInvoiceDiscountToggle();

        // Rebind events

        // Price change
        $('.price').off('input').on('input', function() {
            const i = $(this).data('index');
            cart[i].price = parseFloat($(this).val()) || 0;
            // Keep price_group in sync
            cart[i].price_group = Number(cart[i].price).toFixed(2);
            saveCartToSession().then(() => {
                recalcTotals();
            });
        });

        // Discount type toggle click
        $('.discount-type-btn').off('click').on('click', function (e) {
            e.preventDefault();

            const i = $(this).data('index');
            const type = $(this).data('type'); // 'percent' or 'amount'
            const product = cart[i];

            // Toggle UI
            $(`.discount-type-btn[data-index="${i}"]`).removeClass('active');
            $(this).addClass('active');

            // Change selected type
            product.discount_selected_type = type;

            // Update hint text (if locked)
            const hint = product.lock_max_discount
                ? 'Max: ' + (
                    type === 'percent'
                        ? (product.max_discount_percent || 0) + '%'
                        : (product.max_discount_amount || 0).toFixed(2) + ' RS'
                  )
                : '';
            $(`.discount-hint[data-index="${i}"]`).text(hint);

            // Update discount input value shown (switch to corresponding stored value)
            const input = $(`.discount-input[data-index="${i}"]`);
            if (type === 'percent') {
                input.val((parseFloat(product.discount_percent) || 0).toFixed(2));
            } else {
                input.val((parseFloat(product.discount_amount) || 0).toFixed(2));
            }

            saveCartToSession().then(() => {
                recalcTotals();
            });
        });

        // Discount input change (applies to the currently selected type)
        $('.discount-input').off('input').on('input', function() {
            const i = $(this).data('index');
            let entered = parseFloat($(this).val()) || 0;
            const product = cart[i];

            // Enforce lock_max_discount if applicable:
            if (product.lock_max_discount) {
                if (product.discount_selected_type === 'percent' && product.max_discount_percent > 0 && entered > product.max_discount_percent) {
                    alert(`Discount percentage cannot exceed ${product.max_discount_percent.toFixed(2)}% for this item.`);
                    entered = product.max_discount_percent;
                    $(this).val(parseFloat(entered.toFixed(2)));
                }
                if (product.discount_selected_type === 'amount' && product.max_discount_amount > 0 && entered > product.max_discount_amount) {
                    alert(`Discount amount cannot exceed ${product.max_discount_amount.toFixed(2)} RS for this item.`);
                    entered = product.max_discount_amount;
                    $(this).val(parseFloat(entered.toFixed(2)));
                }
            }

            // Save to correct field
            if (product.discount_selected_type === 'percent') {
                product.discount_percent = entered;
            } else {
                product.discount_amount = entered;
            }

            saveCartToSession().then(() => {
                // Update row total quickly (no AJAX needed)
                const base = (product.price * product.qty) || 0;
                let rowDiscount = 0;
                if (product.discount_selected_type === 'percent') {
                    rowDiscount = base * ((parseFloat(product.discount_percent) || 0) / 100);
                } else {
                    rowDiscount = parseFloat(product.discount_amount) || 0;
                }

                $(`#cartBody tr[data-index="${i}"] .row-total`).text((base - rowDiscount).toFixed(2));
                recalcTotals();
            });
        });

        // Use debounce for quantity input to prevent immediate AJAX calls
        $('.qty').off('input').on('input', function() {
            const i = $(this).data('index');
            const val = parseFloat($(this).val()) || 0;

            // Store the original value before making the AJAX call
            const originalValue = cart[i].qty;
            cart[i].qty = val;

            // Clear any existing timeout
            if (cart[i].timeoutId) {
                clearTimeout(cart[i].timeoutId);
            }

            // Set a new timeout to check stock after user stops typing
            cart[i].timeoutId = setTimeout(() => {
                checkStockAvailability(i, val, originalValue);
            }, 800); // 800ms delay
        });

        // Category change event
        $('.category-select').off('change').on('change', function() {
            applyCategorySelectColor($(this));

            const i = $(this).data('index');
            const productId = cart[i].id;
            const categoryId = $(this).val();

            const originalValue = cart[i].qty;

            cart[i].category_id = categoryId;
            // reset quantity to 1 for the changed row (that's your current behavior)
            cart[i].qty = 1;

            // Save session excluding this edited row (important)
            saveCartToSession(i).then(() => {
                // Ask backend for category-aware allocation for qty = 1
                $.ajax({
                    url: '/admin/products/pos/check-stock',
                    type: 'POST',
                    data: {
                        product_id: productId,
                        quantity: 1,
                        category_id: categoryId,
                        from_base_stock_sale_price_id: null,
                        current_cart: getCartPayload(i),
                        _token: '{{ csrf_token() }}'
                    },
                    success: function (response) {
                        if (!cart[i]) return; // Safety check in case cart changed
                        if ((response.status === 'error' || response.status === 'partial') && response.rows && response.rows.length) {
                            // replace the edited row with returned rows
                            const baseItem = cart[i];
                            cart.splice(i, 1);
                            response.rows.forEach(row => {
                                cart.push({
                                    id: row.product_id,
                                    name: baseItem.name,
                                    qty: parseFloat(row.quantity),
                                    price: parseFloat(row.unit_price),
                                    category_id: row.category_id || categoryId || null,
                                    base_stock_sale_price_id: row.base_stock_sale_price_id || null,
                                    base_qty: row.base_qty || row.quantity,
                                    price_group: row.price_group || Number(row.unit_price).toFixed(2),

                                    discount_percent: baseItem.discount_percent || 0,
                                    discount_amount: baseItem.discount_amount || 0,
                                    discount_selected_type: baseItem.discount_selected_type,
                                    lock_max_discount: baseItem.lock_max_discount,
                                    max_discount_percent: baseItem.max_discount_percent,
                                    max_discount_amount: baseItem.max_discount_amount,
                                    categories: baseItem.categories
                                });
                            });

                            // merge and save session
                            mergeSamePriceRows();
                            saveCartToSession(null).then(() => renderCart());

                            if (response.status === 'partial') {
                                alert(response.message);
                            }
                            return;
                        }

                        if (response.status === 'ok' && response.rows && response.rows.length) {
                            const baseItem = cart[i];
                            if (!baseItem) return;
                            cart.splice(i, 1);
                            response.rows.forEach(row => {
                                cart.push({
                                    id: row.product_id,
                                    name: baseItem.name,
                                    qty: parseFloat(row.quantity),
                                    price: parseFloat(row.unit_price),
                                    category_id: row.category_id || categoryId || null,
                                    base_stock_sale_price_id: row.base_stock_sale_price_id || null,
                                    base_qty: row.base_qty || row.quantity,
                                    price_group: row.price_group || Number(row.unit_price).toFixed(2),

                                    discount_percent: baseItem.discount_percent || 0,
                                    discount_amount: baseItem.discount_amount || 0,
                                    discount_selected_type: baseItem.discount_selected_type,
                                    lock_max_discount: baseItem.lock_max_discount,
                                    max_discount_percent: baseItem.max_discount_percent,
                                    max_discount_amount: baseItem.max_discount_amount,
                                    categories: baseItem.categories
                                });
                            });
                            mergeSamePriceRows();
                            saveCartToSession(null).then(() => renderCart());
                            return;
                        }

                        if ((response.status === 'error' || response.status === 'partial') && (!response.rows || response.rows.length === 0)) {
                            if (response.smart_category_id && response.smart_category_id != categoryId) {
                                alert(response.message || 'Action rejected. Switching category.');
                                $(`.category-select[data-index="${i}"]`).val(response.smart_category_id).trigger('change');
                                return;
                            }
                            
                            // Revert using the DOM elements instead of just the data model to ensure UI is reset
                            if (cart[i]) {
                                cart[i].qty = originalValue;
                            }
                            
                            alert(response.message || 'Insufficient stock for selected category');
                            
                            // Force category dropdown to revert back to what the model has
                            renderCart();
                            return;
                        }
                    },
                    error: function() {
                        if (cart[i]) cart[i].qty = originalValue;
                        alert('Category change stock check failed');
                        renderCart();
                    }
                });
            });
        });

        // Remove item
        $('.remove-item').off('click').on('click', function() {
            const i = $(this).data('index');
            if (confirm('Are you sure you want to remove this item?')) {
                const removed = cart.splice(i, 1);
                // Save updated cart to session
                saveCartToSession(null).then(() => {
                    // After removing, the freed stock will be considered available by backend
                    renderCart();
                });
            }
        });

        recalcTotals();
        refreshDetailStockIfShowing();
    }

    // Check stock availability for edited row (quantity or explicit check)
    // Check stock availability for edited row (quantity or explicit check)
    async function checkStockAvailability(index, enteredQuantity, originalValue) {
        if (!cart[index]) return;

        // ✅ BACKUP BEFORE ANY CART MUTATION
        const backupItem = { ...cart[index] };

        const productId = backupItem.id;
        const categoryId = backupItem.category_id || null;
        const oldStockRowId = backupItem.base_stock_sale_price_id || null;

        // invalid qty
        if (enteredQuantity <= 0) {
            cart[index].qty = 1;
            renderCart();
            return;
        }

        // save cart excluding edited row
        await saveCartToSession(index);

        $.ajax({
            url: '/admin/products/pos/check-stock',
            type: 'POST',
            data: {
                product_id: productId,
                quantity: enteredQuantity,
                category_id: categoryId,
                from_base_stock_sale_price_id: oldStockRowId,
                current_cart: getCartPayload(index), // SEND CART CONTEXT
                _token: '{{ csrf_token() }}'
            },

            success: function (response) {
                // HANDLE SUCCESS WITH ROWS (ok/partial/error-with-rows)
                if (response.rows && response.rows.length) {
                    // Remove only the edited row
                    cart.splice(index, 1);

                    response.rows.forEach(row => {
                        cart.push({
                            id: row.product_id || productId,
                            name: backupItem.name,
                            qty: parseFloat(row.quantity),
                            price: parseFloat(row.unit_price),
                            category_id: row.category_id || categoryId || null,
                            base_stock_sale_price_id: row.base_stock_sale_price_id || null,
                            base_qty: row.base_qty || row.quantity,
                            price_group: (row.price_group || row.unit_price).toString(),

                            discount_percent: backupItem.discount_percent || 0,
                            discount_amount: backupItem.discount_amount || 0,
                            discount_selected_type: backupItem.discount_selected_type,
                            lock_max_discount: backupItem.lock_max_discount,
                            max_discount_percent: backupItem.max_discount_percent,
                            max_discount_amount: backupItem.max_discount_amount,
                            categories: backupItem.categories
                        });
                    });

                    mergeSamePriceRows();

                    saveCartToSession(null).then(() => {
                        renderCart();
                        if (response.status !== 'ok') {
                            alert(response.message);
                        }
                    });

                    return;
                }

                // ✅ FIX: Even when status is 'error', we need to get price-grouped rows for available quantity
                // So let's make another call specifically to get the allocation for available quantity
                if (response.status === 'error') {
                    if (response.smart_category_id && response.smart_category_id != categoryId) {
                        alert(response.message || 'Action rejected. Switching category.');
                        $(`.category-select[data-index="${index}"]`).val(response.smart_category_id).trigger('change');
                        return;
                    }
                    
                    // Extract available quantity from error message or use provided field
                    let availableQty = 0;
                    
                    if (response.available_quantity !== undefined) {
                        availableQty = parseFloat(response.available_quantity);
                    } else {
                        // Parse from message like "Available: 23.00 in selected category"
                        const match = response.message.match(/Available:\s*(\d+\.?\d*)/);
                        if (match) {
                            availableQty = parseFloat(match[1]);
                        }
                    }

                    if (availableQty > 0) {
                        // ✅ Make a NEW request to get price-grouped rows for the available quantity
                        $.ajax({
                            url: '/admin/products/pos/check-stock',
                            type: 'POST',
                            data: {
                                product_id: productId,
                                quantity: availableQty,  // Use available quantity instead of entered
                                category_id: categoryId,
                                from_base_stock_sale_price_id: null, // Start fresh
                                current_cart: getCartPayload(index),
                                _token: '{{ csrf_token() }}'
                            },
                            success: function(availableResponse) {
                                if (availableResponse.rows && availableResponse.rows.length) {
                                    // Remove the edited row
                                    cart.splice(index, 1);
                                    
                                    // Add the price-grouped rows for available quantity
                                    availableResponse.rows.forEach(row => {
                                        cart.push({
                                            id: row.product_id || productId,
                                            name: backupItem.name,
                                            product_type: backupItem.product_type || '',
                                            qty: parseFloat(row.quantity),
                                            price: parseFloat(row.unit_price),
                                            category_id: row.category_id || categoryId || null,
                                            base_stock_sale_price_id: row.base_stock_sale_price_id || null,
                                            base_qty: row.base_qty || row.quantity,
                                            price_group: (row.price_group || row.unit_price).toString(),
                                            discount_percent: backupItem.discount_percent || 0,
                                            discount_amount: backupItem.discount_amount || 0,
                                            discount_selected_type: backupItem.discount_selected_type,
                                            lock_max_discount: backupItem.lock_max_discount,
                                            max_discount_percent: backupItem.max_discount_percent,
                                            max_discount_amount: backupItem.max_discount_amount,
                                            categories: backupItem.categories
                                        });
                                    });
                                    
                                    mergeSamePriceRows();
                                    
                                    saveCartToSession(null).then(() => {
                                        renderCart();
                                        alert(`${response.message}. Quantity adjusted to available stock: ${availableQty}`);
                                    });
                                } else {
                                    // Fallback if no rows returned
                                    cart[index] = {
                                        ...backupItem,
                                        qty: availableQty
                                    };
                                    saveCartToSession(null).then(() => {
                                        renderCart();
                                        alert(`${response.message}. Quantity adjusted to available stock: ${availableQty}`);
                                    });
                                }
                            },
                            error: function() {
                                // Fallback if the second request fails
                                cart[index] = {
                                    ...backupItem,
                                    qty: availableQty
                                };
                                saveCartToSession(null).then(() => {
                                    renderCart();
                                    alert(`${response.message}. Quantity adjusted to available stock: ${availableQty}`);
                                });
                            }
                        });
                    } else {
                        // ✅ NO STOCK AVAILABLE - REMOVE THE ROW
                        cart.splice(index, 1);
                        saveCartToSession(null).then(() => {
                            renderCart();
                            alert(response.message || 'No stock available. Item removed from cart.');
                        });
                    }
                }
            },

            error: function () {
                // ✅ AJAX FAIL → ROLLBACK TO ORIGINAL VALUE
                cart[index] = {
                    ...backupItem,
                    qty: originalValue
                };
                renderCart();
                alert('Stock check failed. Please try again.');
            }
        });
    }

    // Merge rows with same price_group / price
    function mergeSamePriceRows(productId = null, categoryId = null) {
    cart = Object.values(cart.reduce((acc, item) => {

        // Only merge same product + category + price group
        const key = `${item.id}-${item.category_id}-${item.price_group}-${item.base_stock_sale_price_id}`;

        if (!acc[key]) {
            acc[key] = { ...item };
        } else {
            acc[key].qty += parseFloat(item.qty);
            acc[key].base_qty += parseFloat(item.base_qty || 0);
        }

        return acc;

    }, {}));

    // KEEP FIFO ORDER (important)
    cart.sort((a, b) => {
        return (a.base_stock_sale_price_id || 0) - (b.base_stock_sale_price_id || 0);
    });
}

    // Recalculate totals and update receipt
    function recalcTotals() {
        let subtotal = 0;
        let grossSubtotal = 0;
        let totalItemDiscount = 0;

        cart.forEach(p => {
            const base = (p.price * p.qty) || 0;
            let rowDiscount = 0;
            if (p.discount_selected_type === 'percent') {
                rowDiscount = base * ((parseFloat(p.discount_percent) || 0) / 100);
            } else {
                rowDiscount = parseFloat(p.discount_amount) || 0;
            }

            // If lock_max_discount is true, ensure discount doesn't exceed caps
            if (p.lock_max_discount) {
                if (p.discount_selected_type === 'percent' && p.max_discount_percent > 0) {
                    const cap = parseFloat(p.max_discount_percent) || 0;
                    const enteredPercent = parseFloat(p.discount_percent) || 0;
                    if (enteredPercent > cap) {
                        p.discount_percent = cap;
                        rowDiscount = base * (cap / 100);
                    }
                } else if (p.discount_selected_type === 'amount' && p.max_discount_amount > 0) {
                    const capAmount = parseFloat(p.max_discount_amount) || 0;
                    const enteredAmount = parseFloat(p.discount_amount) || 0;
                    if (enteredAmount > capAmount) {
                        p.discount_amount = capAmount;
                        rowDiscount = capAmount;
                    }
                }
            }
            rowDiscount = Math.min(rowDiscount, base); // Cap discount safely

            grossSubtotal += base;
            totalItemDiscount += rowDiscount;
            subtotal += Math.max(0, base - rowDiscount);
        });

        // Calculate invoice discount based on type
        const invoiceDiscountValue = parseFloat($('#invoiceDiscountValue').val()) || 0;

        let invoiceDiscount = 0;
        if (invoiceDiscountType === 'percent') {
            // Percentage discount - calculate from subtotal
            invoiceDiscount = subtotal * (invoiceDiscountValue / 100);
        } else {
            // Fixed amount discount
            invoiceDiscount = Math.min(invoiceDiscountValue, subtotal); // Can't discount more than subtotal
        }

        const preTaxGrandTotal = Math.max(0, parseFloat(subtotal.toFixed(2)) - invoiceDiscount);

        // Update main display
        $('#subtotalAmount').text(subtotal.toFixed(2));
        $('#invoiceDiscountDisplay').text(invoiceDiscount.toFixed(2));

        // Update receipt display
        let rhtml = '';
        if (cart.length === 0) {
            rhtml = `<tr><td colspan="6" class="pmx-empty">No items</td></tr>`;
        } else {
            cart.forEach((p, i) => {
                const base = (p.price * p.qty) || 0;
                let rowDiscount = 0;
                if (p.discount_selected_type === 'percent') {
                    rowDiscount = base * ((parseFloat(p.discount_percent) || 0) / 100);
                } else {
                    rowDiscount = parseFloat(p.discount_amount) || 0;
                }
                const rowTotal = Math.max(0, base - rowDiscount);

                rhtml += `
                    <tr>
                        <td class="col-sr">${i + 1}</td>
                        <td class="col-item">
                            <span class="item-name">${p.name}</span>
                            ${p.product_type ? `<span class="item-type">${p.product_type}</span>` : ''}
                        </td>
                        <td class="col-qty">${p.qty}</td>
                        <td class="col-price">${p.price.toFixed(2)}</td>
                        <td class="col-disc">${(p.discount_selected_type === 'percent' ? (parseFloat(p.discount_percent) || 0).toFixed(2) + '%' : (parseFloat(p.discount_amount) || 0).toFixed(2))}</td>
                        <td class="col-total">${rowTotal.toFixed(2)}</td>
                    </tr>`;
            });
        }

        $('#receiptBody').html(rhtml);

        // Update receipt totals — mirrors the breakdown already shown on the
        // printed sale receipt (receipt-print.blade.php): gross subtotal +
        // product-discount clawback + net subtotal when items carry their
        // own discount, then an annotated invoice-discount row.
        let subtotalBlockHtml;
        if (totalItemDiscount > 0) {
            subtotalBlockHtml = `
                <div class="pmx-row">
                    <span class="pmx-label">Subtotal</span>
                    <span class="pmx-value">${grossSubtotal.toFixed(2)}</span>
                </div>
                <div class="pmx-row pmx-row--muted">
                    <span class="pmx-label">Product Discounts</span>
                    <span class="pmx-value">-${totalItemDiscount.toFixed(2)}</span>
                </div>
                <div class="pmx-row">
                    <span class="pmx-label">Net Subtotal</span>
                    <span class="pmx-value">${subtotal.toFixed(2)}</span>
                </div>`;
        } else {
            subtotalBlockHtml = `
                <div class="pmx-row">
                    <span class="pmx-label">Subtotal</span>
                    <span class="pmx-value">${subtotal.toFixed(2)}</span>
                </div>`;
        }
        $('#receiptSubtotalBlock').html(subtotalBlockHtml);

        let invoiceDiscountBlockHtml = '';
        if (invoiceDiscount > 0) {
            const discountLabelSuffix = invoiceDiscountType === 'percent'
                ? `(${invoiceDiscountValue.toFixed(2)}%)`
                : `(${invoiceDiscountValue.toFixed(2)})`;
            invoiceDiscountBlockHtml = `
                <div class="pmx-row pmx-row--muted">
                    <span class="pmx-label">Invoice Discount ${discountLabelSuffix}</span>
                    <span class="pmx-value">-${invoiceDiscount.toFixed(2)}</span>
                </div>`;
        }
        $('#receiptInvoiceDiscountBlock').html(invoiceDiscountBlockHtml);

        // Grand total needs the additive Sales Tax amount, which can only be
        // resolved server-side (it depends on which purchase batch(es) the
        // sold stock comes from). Apply the last known tax data immediately
        // so the UI never flashes a stale/zero total, then refresh it async.
        applyGrandTotalWithTax(preTaxGrandTotal);
        scheduleCartTaxRefresh();
    }

    // Additive Sale Tax (named taxes from "Sale Information & Sale Tax" on
    // the purchase) and disclosure-only GST, fetched live from the server —
    // see POSController::cartTaxBreakdown(). Sale Tax is added on top of the
    // pre-tax grand total; GST is shown but never added.
    let cartTaxData = { salesTaxes: [], gstTaxes: [], salesTaxTotal: 0, gstTotal: 0 };
    let cartTaxFetchTimer = null;
    let lastPreTaxGrandTotal = 0;

    function applyGrandTotalWithTax(preTaxGrandTotal) {
        lastPreTaxGrandTotal = preTaxGrandTotal;
        const grandTotal = Math.max(0, preTaxGrandTotal + (cartTaxData.salesTaxTotal || 0));

        $('#grandTotal').text(grandTotal.toFixed(2));
        $('#receiptTotal').text(grandTotal.toFixed(2));

        let salesRows = '';
        (cartTaxData.salesTaxes || []).forEach(function (t) {
            salesRows += `<div class="pmx-row"><span class="pmx-label">${t.name} (${t.rate.toFixed(2)}%)</span><span class="pmx-value">+${t.amount.toFixed(2)}</span></div>`;
        });
        $('#receiptSalesTaxRows').html(salesRows);

        let gstRows = '';
        (cartTaxData.gstTaxes || []).forEach(function (t) {
            gstRows += `<div class="pmx-row pmx-row--muted"><span class="pmx-label">${t.name} (${t.rate.toFixed(2)}%)</span><span class="pmx-value">${t.amount.toFixed(2)}</span></div>`;
        });
        $('#receiptGstRows').html(gstRows);
        $('#receiptGstSection').toggle((cartTaxData.gstTaxes || []).length > 0);

        recalcCashSection();
    }

    function scheduleCartTaxRefresh() {
        clearTimeout(cartTaxFetchTimer);

        if (cart.length === 0) {
            cartTaxData = { salesTaxes: [], gstTaxes: [], salesTaxTotal: 0, gstTotal: 0 };
            applyGrandTotalWithTax(lastPreTaxGrandTotal);
            return;
        }

        cartTaxFetchTimer = setTimeout(fetchCartTaxBreakdown, 250);
    }

    function fetchCartTaxBreakdown() {
        const cartForTax = cart.map(function (item) {
            return {
                id: item.id,
                category_id: item.category_id,
                qty: item.qty,
                price: item.price,
                discount_selected_type: item.discount_selected_type,
                discount_percent: item.discount_percent,
                discount_amount: item.discount_amount
            };
        });

        $.ajax({
            url: {!! json_encode(route('pos.cart-tax-breakdown')) !!},
            method: 'POST',
            dataType: 'json',
            data: { _token: '{{ csrf_token() }}', cart: JSON.stringify(cartForTax) },
            success: function (res) {
                cartTaxData = {
                    salesTaxes: res.salesTaxes || [],
                    gstTaxes: res.gstTaxes || [],
                    salesTaxTotal: parseFloat(res.salesTaxTotal) || 0,
                    gstTotal: parseFloat(res.gstTotal) || 0
                };
                applyGrandTotalWithTax(lastPreTaxGrandTotal);
            },
            error: function (xhr) {
                console.error('cart-tax-breakdown request failed:', xhr.status, xhr.responseText);
            }
        });
    }

    // Refresh the cosmetic "next invoice #" preview shown on the live receipt
    // (not reserved — the real number is assigned when the sale is saved).
    function refreshNextInvoiceNo() {
        $.getJSON({!! json_encode(route('pos.next-invoice-number')) !!}, function(res) {
            $('#receiptInvoiceNo').text(res.invoice_no || 'Pending (FBR)');
        });
    }

    // Calculate change automatically when cash received is entered
    $('#cashReceived').on('input', function () {

        let cash = parseFloat($(this).val());

        if (isNaN(cash) || cash <= 0) {
            cash = 0;
        }

        let grandTotal = parseFloat($('#grandTotal').text()) || 0;

        let change = 0;

        if (cash >= grandTotal) {
            change = cash - grandTotal;
        } else {
            change = 0;
        }

        // Update left side
        $('#changeReturn').text(change.toFixed(2));

        // Update receipt area
        $('#cashReceivedDisplay').text(cash.toFixed(2));
        $('#cashChangeDisplay').text(change.toFixed(2));
    });

    // Re-run cash calculation after total changes
    function recalcCashSection() {
        $('#cashReceived').trigger('input');
    }

    // Call again after totals update
    let oldRecalcTotals = recalcTotals;
    recalcTotals = function () {
        oldRecalcTotals();
        recalcCashSection();
    };

    // Utility: submit data to print route in new tab silently
    let pendingPrintWindow = null;
    
    function submitToPrintRoute(url, cartData, invoiceDiscountValue, invoiceDiscountType, extra = {}) {
        // If window wasn't pre-opened on click (eg. pure sync click) open it now
        if (!pendingPrintWindow || pendingPrintWindow.closed) {
            pendingPrintWindow = window.open('', 'PrintReceipt', 'width=800,height=600,scrollbars=yes,resizable=yes');
        }

        const form = $('<form>', {
            method: 'POST',
            action: url,
            target: 'PrintReceipt' // Route the form specifically to the popup window context
        });

        form.append($('<input>', {
            type: 'hidden',
            name: '_token',
            value: '{{ csrf_token() }}'
        }));

        form.append($('<input>', {
            type: 'hidden',
            name: 'cart',
            value: JSON.stringify(cartData)
        }));

        form.append($('<input>', {
            type: 'hidden',
            name: 'invoice_discount_value',
            value: invoiceDiscountValue
        }));

        form.append($('<input>', {
            type: 'hidden',
            name: 'invoice_discount_type',
            value: invoiceDiscountType
        }));

        // append any extra fields (like invoice_id)
        for (const key in extra) {
            form.append($('<input>', {
                type: 'hidden',
                name: key,
                value: extra[key]
            }));
        }

        $('body').append(form);
        form.submit();
        form.remove();
    }

    // ------------------------------
    // Save & Print
    // Validates the cart/cash state and, if OK, asks for confirmation
    // before actually saving+printing. Shared by the button click and the
    // F9 keyboard shortcut below so both go through the same confirmation.
    function requestSaveAndPrint() {
        if (cart.length === 0) {
            alert('No items in cart to save and print!');
            return;
        }
        const grandTotal = parseFloat($("#grandTotal").text()) || 0;
        const cashReceived = parseFloat($("#cashReceived").val()) || 0;

        if (cashReceived <= 0) {
            alert("Cash received must be greater than zero.");
            $('#cashReceived').focus();
            return;
        }

        if (cashReceived < grandTotal) {
            alert("Cash received must be greater than or equal to Grand Total.");
            $('#cashReceived').focus();
            return;
        }

        Swal.fire({
            title: 'Confirm Sale',
            text: 'Save this invoice and print the receipt?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Save & Print',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#3490dc',
            reverseButtons: true
        }).then(function (result) {
            // The bundled SweetAlert2 build is v8 (pre-isConfirmed API) —
            // it resolves confirm as {value: true}, not {isConfirmed: true}.
            if (result.value) {
                doSaveAndPrint();
            }
        });
    }

    $('#saveAndPrint').on('click', function() {
        requestSaveAndPrint();
    });

    // F9 = Save & Print shortcut, from anywhere on the page (including
    // while the barcode/search box has focus). Goes through the same
    // requestSaveAndPrint() confirmation as the button click.
    $(document).on('keydown', function (e) {
        if (e.key === 'F9') {
            e.preventDefault();
            requestSaveAndPrint();
        }
    });

    function doSaveAndPrint() {
        // Open immediately on click to prevent browser block
        pendingPrintWindow = window.open('', 'PrintReceipt', 'width=800,height=600,scrollbars=yes,resizable=yes');

        const cartData = cart.map(item => {

            const base = (parseFloat(item.price) || 0) * (parseFloat(item.qty) || 0);

            const discountAmount = item.discount_selected_type === "percent"
                ? (base * ((parseFloat(item.discount_percent) || 0) / 100))
                : (parseFloat(item.discount_amount) || 0);
            return {
                id: item.id,
                product_id: item.id,
                category_id: item.category_id || null,
                name: item.name,
                price: parseFloat(item.price) || 0,
                qty: parseFloat(item.qty) || 0,
                base_qty: parseFloat(item.base_qty || 0),
                price_group: item.price_group || Number(item.price).toFixed(2),
                base_stock_sale_price_id: item.base_stock_sale_price_id,

                // original discount fields
                discount_selected_type: item.discount_selected_type,
                discount_percent: parseFloat(item.discount_percent) || 0,
                discount_amount: parseFloat(item.discount_amount) || 0,

                // NEW FIELDS
                price_before_discount: parseFloat(item.price) || 0,
                discount_type: item.discount_selected_type,
                discount_value: item.discount_selected_type === "percent"
                    ? (parseFloat(item.discount_percent) || 0)
                    : (parseFloat(item.discount_amount) || 0),
                max_discount_percent: item.max_discount_percent || 0,
                max_discount_amount: item.max_discount_amount || 0,
                row_total: base - discountAmount
            };
        });

        const invoiceDiscountValue = parseFloat($('#invoiceDiscountValue').val()) || 0;
        const invoiceDiscountAmount = parseFloat($('#invoiceDiscountDisplay').text()) || 0;

        const payload = {
            _token: '{{ csrf_token() }}',

            subtotal: parseFloat($("#subtotalAmount").text()) || 0,
            invoice_discount_type: invoiceDiscountType,
            invoice_discount_value: invoiceDiscountValue,
            invoice_discount_amount: invoiceDiscountAmount,
            total: parseFloat($("#subtotalAmount").text()) || 0,
            grand_total: parseFloat($("#grandTotal").text()) || 0,
            cash_received: parseFloat($("#cashReceived").val()) || 0,
            change_return: parseFloat($("#changeReturn").text()) || 0,

            // old name: cart
            cart: cartData,

            // new name expected by new controller, if needed
            items: cartData
        };

        $.ajax({
            url: {!! json_encode(route('pos.save-invoice')) !!},
            method: 'POST',
            data: payload,
            success: function(response) {

                const cashReceived = parseFloat($('#cashReceived').val()) || 0;
                const changeReturn = parseFloat($('#changeReturn').text()) || 0;

                submitToPrintRoute(
                    {!! json_encode(route('pos.print-receipt')) !!},
                    cartData,
                    invoiceDiscountValue,
                    invoiceDiscountType,
                    {
                        invoice_id: response.invoice_id,
                        cash_received: cashReceived,
                        change_return: changeReturn
                    }
                );

                alert('Invoice saved successfully!');
                cart = [];

                // Reset every POS input/display back to its default state for
                // the next sale — cash received/change return are reset both
                // on the input itself and on their live-receipt display spans
                // directly (rather than relying only on the async recalc
                // chain), and the invoice discount type is reset to match the
                // button that's actually left active (amount/RS, the page's
                // default) instead of drifting out of sync with it.
                $('#cashReceived').val('');
                $('#cashReceivedDisplay').text('0.00');
                $('#cashChangeDisplay').text('0.00');
                $('#changeReturn').text('0.00');

                $('#invoiceDiscountValue').val(0);
                invoiceDiscountType = 'amount';
                $('.invoice-discount-type-btn').removeClass('active');
                $('.invoice-discount-type-btn[data-type="amount"]').addClass('active');
                updateInvoiceDiscountHint();

                // clear session too
                saveCartToSession(null).then(() => {
                    renderCart();
                    recalcTotals();
                    refreshNextInvoiceNo();
                });
            },
            error: function(err) {
                if (pendingPrintWindow && !pendingPrintWindow.closed) {
                    pendingPrintWindow.close();
                }

                if (err.responseJSON && err.responseJSON.error === 'stock_exceeded') {
                    const availableQty = err.responseJSON.available_category_qty;
                    alert(`Invoice cannot proceed! Item "${err.responseJSON.product_name}" exceeds stock. Adjusting automatically to maximum available.`);
                    
                    let adjusted = false;
                    for (let i = cart.length - 1; i >= 0; i--) {
                        if (cart[i].id == err.responseJSON.product_id) {
                            if (!adjusted) {
                                cart[i].qty = availableQty;
                                if (cart[i].qty <= 0) cart.splice(i, 1);
                                else {
                                    // Trigger backend full check-stock reload for this row's assignment
                                    checkStockAvailability(i, availableQty, cart[i].qty);
                                }
                                adjusted = true;
                            } else {
                                cart.splice(i, 1);
                            }
                        }
                    }
                    
                    saveCartToSession(null).then(() => {
                        renderCart();
                        recalcTotals();
                    });
                    return;
                }

                let msg = 'Error saving invoice!';
                if (err.responseJSON && err.responseJSON.error) {
                    msg = err.responseJSON.error;
                }
                alert(msg);
            }
        });

    }

    // Initialize on page load
    initInvoiceDiscountToggle();
});
</script>
@endpush