@extends('admin.layouts.app')

@push('page-css')
<style>
    /* POS Layout */
    .pos-wrapper {
        display: grid;
        grid-template-columns: 70% 30%;
        gap: 10px;
    }

    .pos-left,
    .pos-right {
        background: #fff;
        border-radius: 10px;
        padding: 10px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
    }

    .fast-search {
        width: 100%;
        font-size: 18px;
        padding: 10px;
        border: 2px solid #3490dc;
        border-radius: 8px;
    }

    .cart-table th,
    .cart-table td {
        vertical-align: middle !important;
    }

    .receipt {
        font-family: 'Courier New', monospace;
        font-size: 12px;
        width: 100%;
        border: 1px dashed #000;
        padding: 5px;
    }

    .receipt-header {
        text-align: center;
        border-bottom: 1px dashed #000;
        margin-bottom: 5px;
    }

    .receipt-table th,
    .receipt-table td {
        font-size: 11px;
        text-align: left;
        padding: 2px 0;
    }

    .receipt-total {
        border-top: 1px dashed #000;
        margin-top: 5px;
        padding-top: 5px;
    }

    #searchResults {
        max-height: 250px;
        overflow-y: auto;
        border-radius: 6px;
    }

    #searchResults .list-group-item.active {
        background-color: #3490dc;
        color: #fff;
    }

    .remove-item {
        padding: 4px 8px;
        font-size: 12px;
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
</style>
@endpush

@section('content')
<div class="pos-wrapper">
    <!-- LEFT SIDE (MAIN POS) -->
    <div class="pos-left">
        <input type="text" id="searchProduct" class="fast-search"
            placeholder="Search Product by Name or Barcode (Press Enter)" autofocus>
        <div id="searchResults" class="list-group position-absolute w-50" style="z-index:1000;"></div>

        <table class="table table-bordered cart-table mt-3">
            <thead class="bg-primary text-white">
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
                    <td colspan="7" class="text-center">No products added yet</td>
                </tr>
            </tbody>
        </table>

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
        <div id="receiptArea" class="receipt">
            <div class="receipt-header">
                <strong>{{ settings('pharmacy_name', 'Your Pharmacy Name') }}</strong><br>
                <small>{{ settings('pharmacy_address', 'Address here...') }}</small>
            </div>

            <table class="receipt-table" width="100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>U.Price</th>
                        <th>Qty</th>
                        <th>Disc</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody id="receiptBody">
                    <tr>
                        <td colspan="6" class="text-center">No items</td>
                    </tr>
                </tbody>
            </table>

            <div class="receipt-total">
                <div>Subtotal: <span id="receiptSubtotal">0.00</span></div>
                <div>Invoice Disc: -<span id="receiptDiscount">0.00</span></div>
                <strong>Grand Total: <span id="receiptTotal">0.00</span></strong>
                <div class="mt-2">Cash Received: <span id="cashReceivedDisplay">0.00</span></div>
                <div>Change Return: <span id="cashChangeDisplay">0.00</span></div>
            </div>
        </div>
        <button class="btn btn-success btn-block mt-3" id="printReceipt"><i class="fa fa-print"></i> Print</button>
        <button class="btn btn-primary btn-block mt-2" id="saveAndPrint"><i class="fa fa-save"></i> Save &
            Print</button>
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

    // Invoice discount type state
    let invoiceDiscountType = 'amount'; // Default to amount

    // Utility: Save POS cart to server session
    function saveCartToSession(excludeIndex = null) {
        return new Promise((resolve, reject) => {
            const cartForSession = cart
                .map((item, idx) => ({
                    product_id: item.id,
                    category_id: item.category_id || null,
                    base_stock_sale_price_id: item.base_stock_sale_price_id || null,
                    unit_price: item.price,
                    base_qty: item.base_qty || 0,
                    quantity: item.qty,
                    price_group: item.price_group || (item.price ? parseFloat(item.price).toFixed(2) : null)
                }))
                // exclude edited row if requested (important to avoid double counting)
                .filter((_, idx) => excludeIndex === null ? true : idx !== excludeIndex);

            $.ajax({
                url: '/admin/pos/save-cart-session',
                type: 'POST',
                data: {
                    cart: JSON.stringify(cartForSession),
                    _token: '{{ csrf_token() }}'
                },
                success: function() {
                    resolve();
                },
                error: function(err) {
                    console.error('Error saving cart session', err);
                    // resolve anyway so checks can continue (backend will treat missing session as empty)
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
        if (query.length < 2) {
            $('#searchResults').hide();
            return;
        }

        $.ajax({
            url: {!! json_encode(route('products.search')) !!},
            method: 'GET',
            data: { q: query },
            success: function(data) {
                $('#searchResults').empty();

                // Normalize response: if it's a single object, wrap it in an array
                if (data && !Array.isArray(data)) {
                    data = [data];
                }

                if (Array.isArray(data) && data.length > 0) {
                    searchResults = data;
                    selectedIndex = 0;

                    data.forEach((product, i) => {
                        const item = $(`
                            <a href="#" class="list-group-item list-group-item-action ${
                                i === 0 ? 'active' : ''
                            }" data-index="${i}">
                                ${product.product_name}
                                ${product.strength?.name ? ` - ${product.strength.name}` : ''}
                            </a>
                        `);
                        $('#searchResults').append(item);
                    });

                    $('#searchResults').show();
                } else {
                    $('#searchResults')
                        .html('<div class="list-group-item text-muted">No products found</div>')
                        .show();
                }
            },
            error: function() {
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
            }
            return;
        } else {
            return; // other keys handled by input event
        }

        // Update visual highlight
        items.removeClass('active').eq(selectedIndex).addClass('active');
    });

    // Click selection from dropdown
    $(document).on('click', '#searchResults .list-group-item', function(e) {
        e.preventDefault();
        const i = $(this).data('index');
        const product = searchResults[i];
        if (product) {
            addToCart(product);
            $('#searchProduct').val('');
            $('#searchResults').hide();
        }
    });

    // Helper to render categories HTML
    function categoryOptionsHtml(categories, selectedId) {
        if (!categories || categories.length === 0) {
            return `<option value="">No Categories</option>`;
        }
        return categories.map(cat =>
            `<option value="${cat.id}" ${cat.id == selectedId ? 'selected' : ''}>${cat.name}</option>`
        ).join('');
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
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if ((response.status === 'error' || response.status === 'partial') && response.rows && response.rows.length) {
                    // If partial with rows: add returned rows (they represent price-grouped allocation)
                    const baseItemName = `${product.product_name}${product.strength?.name ? ' - ' + product.strength.name : ''}`;

                    response.rows.forEach(row => {
                        cart.push({
                            id: product.id,
                            name: baseItemName,
                            qty: parseFloat(row.quantity),
                            price: parseFloat(row.unit_price),
                            category_id: selectedCategoryId,
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
                    const baseItemName = `${product.product_name}${product.strength?.name ? ' - ' + product.strength.name : ''}`;

                    response.rows.forEach(row => {
                        cart.push({
                            id: product.id,
                            name: baseItemName,
                            qty: parseFloat(row.quantity),
                            price: parseFloat(row.unit_price),
                            category_id: selectedCategoryId,
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
                    name: `${product.product_name}${product.strength?.name ? ' - ' + product.strength.name : ''}`,
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
        <select class="form-select category-select" data-index="${i}">
            ${categoryOptionsHtml(p.categories, p.category_id)}
        </select>
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
    <td class="row-total" data-index="${i}">${(base - rowDiscount).toFixed(2)}</td>
    <td class="text-center">
        <button class="btn btn-danger btn-sm remove-item" data-index="${i}">
            <i class="fa fa-trash"></i>
        </button>
    </td>
</tr>`;
            });
        }
        $('#cartBody').html(html);

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
            const i = $(this).data('index');
            const productId = cart[i].id;
            const categoryId = $(this).val();

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
                        _token: '{{ csrf_token() }}'
                    },
                    success: function (response) {
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
                                    category_id: categoryId,
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
                            cart.splice(i, 1);
                            response.rows.forEach(row => {
                                cart.push({
                                    id: row.product_id,
                                    name: baseItem.name,
                                    qty: parseFloat(row.quantity),
                                    price: parseFloat(row.unit_price),
                                    category_id: categoryId,
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

                        if (response.status === 'error' && (!response.rows || response.rows.length === 0)) {
                            // revert to original quantity and show message
                            cart[i].qty = originalValue;
                            alert(response.message || 'Insufficient stock for selected category');
                            saveCartToSession(null).then(() => renderCart());
                            return;
                        }
                    },
                    error: function() {
                        cart[i].qty = originalValue;
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

        // ✅ SEND COMPLETE CART CONTEXT TO BACKEND
        const cartForCheck = cart
            .filter((_, idx) => idx !== index) // exclude current row being edited
            .map((item) => ({
                product_id: item.id,
                category_id: item.category_id || null,
                base_stock_sale_price_id: item.base_stock_sale_price_id || null,
                unit_price: item.price,
                base_qty: item.base_qty || 0,
                quantity: item.qty,
                price_group: item.price_group || (item.price ? parseFloat(item.price).toFixed(2) : null)
            }));

        $.ajax({
            url: '/admin/products/pos/check-stock',
            type: 'POST',
            data: {
                product_id: productId,
                quantity: enteredQuantity,
                category_id: categoryId,
                from_base_stock_sale_price_id: oldStockRowId,
                current_cart: JSON.stringify(cartForCheck), // SEND CART CONTEXT
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
                            category_id: categoryId || null,
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
                                current_cart: JSON.stringify(cartForCheck),
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
                                            qty: parseFloat(row.quantity),
                                            price: parseFloat(row.unit_price),
                                            category_id: categoryId || null,
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

        const grandTotal = Math.max(0, parseFloat(subtotal.toFixed(2)) - invoiceDiscount);

        // Update main display
        $('#subtotalAmount').text(subtotal.toFixed(2));
        $('#invoiceDiscountDisplay').text(invoiceDiscount.toFixed(2));
        $('#grandTotal').text(grandTotal.toFixed(2));

        // Update receipt display
        let rhtml = '';
        if (cart.length === 0) {
            rhtml = `<tr><td colspan="6" class="text-center">No items</td></tr>`;
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
                        <td>${i + 1}</td>
                        <td>${p.name}</td>
                        <td>${p.price.toFixed(2)}</td>
                        <td>${p.qty}</td>
                        <td>${(p.discount_selected_type === 'percent' ? (parseFloat(p.discount_percent) || 0).toFixed(2) + '%' : (parseFloat(p.discount_amount) || 0).toFixed(2) + ' RS')}</td>
                        <td>${rowTotal.toFixed(2)}</td>
                    </tr>`;
            });
        }

        $('#receiptBody').html(rhtml);

        // Update receipt totals
        $('#receiptSubtotal').text(subtotal.toFixed(2));
        $('#receiptDiscount').text(invoiceDiscount.toFixed(2));
        $('#receiptTotal').text(grandTotal.toFixed(2));
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
    function submitToPrintRoute(url, cartData, invoiceDiscountValue, invoiceDiscountType, extra = {}) {
        const form = $('<form>', {
            method: 'POST',
            action: url,
            target: '_blank' // opens in new tab for print popup
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
    // Print Only
    $('#printReceipt').on('click', function() {
        if (cart.length === 0) {
            alert('No items in cart to print!');
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

        const cartData = cart.map(item => ({
            id: item.id,
            name: item.name,
            price: parseFloat(item.price) || 0,
            qty: parseFloat(item.qty) || 0,
            discount_selected_type: item.discount_selected_type,
            discount_percent: parseFloat(item.discount_percent) || 0,
            discount_amount: parseFloat(item.discount_amount) || 0,
            category_id: item.category_id
        }));

        const invoiceDiscountValue = parseFloat($('#invoiceDiscountValue').val()) || 0;
        const changeReturn = parseFloat($('#changeReturn').text()) || 0;

        submitToPrintRoute({!! json_encode(route('pos.print-receipt')) !!}, cartData, invoiceDiscountValue, invoiceDiscountType,
            { cash_received: cashReceived, change_return: changeReturn }
        );
    });

    // ------------------------------
    // Save & Print
    $('#saveAndPrint').on('click', function() {

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
                // clear session too
                saveCartToSession(null).then(() => {
                    renderCart();
                    recalcTotals();
                });
            },
            error: function(err) {
                console.log(err.responseText);
                alert('Error saving invoice!');
            }
        });

    });

    // Initialize on page load
    initInvoiceDiscountToggle();
});
</script>
@endpush