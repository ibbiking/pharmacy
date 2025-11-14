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

    // Live search with dropdown (unchanged)
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

    // Invoice discount recalculation
    $('#invoiceDiscountValue').on('input', recalcTotals);

    function categoryOptionsHtml(categories, selectedId) {
        if (!categories || categories.length === 0) {
            return `<option value="">No Categories</option>`;
        }
        return categories.map(cat =>
            `<option value="${cat.id}" ${cat.id == selectedId ? 'selected' : ''}>${cat.name}</option>`
        ).join('');
    }

    function getCategoryPrice(productId, categoryId, index) {
        $.ajax({
            url: '/admin/products/category-price',
            method: 'GET',
            data: {
                product_id: productId,
                category_id: categoryId
            },
            success: function(response) {
                if (response.out_of_stock) {
                    alert('Product is out of stock');
                    return;
                }

                if (cart[index] && cart[index].id === productId) {
                    cart[index].price = parseFloat(response.price) || 0;
                    recalcTotals(); // instead of full renderCart()
                    $(`#cartBody tr[data-index="${index}"] .price`).val(cart[index].price.toFixed(2));
                    $(`#cartBody tr[data-index="${index}"] .row-total`).text((cart[index].price * cart[index].qty).toFixed(2));
                }
            },
            error: function(xhr) {
                console.error('Error fetching category price:', xhr.responseText);
                alert('Error updating price for selected category');
            }
        });
    }

    // Add or update cart (updated to include discount logic)
    function addToCart(product) {
        const existingIndex = cart.findIndex(p => p.id === product.id);

        if (existingIndex !== -1) {
            // Product already in cart, check stock before increasing
            const currentQty = cart[existingIndex].qty;
            const newQty = currentQty + 1;
            const categoryId = cart[existingIndex].category_id;

            $.ajax({
                url: '/admin/products/pos/check-stock',
                type: 'POST',
                data: {
                    product_id: product.id,
                    quantity: newQty,
                    category_id: categoryId,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.status === 'error') {
                        alert(response.message);
                        // Keep previous quantity, don't increase
                        renderCart();
                    } else {
                        // Safe to increase
                        cart[existingIndex].qty = newQty;

                        // Recalculate discount and total immediately
                        const base = cart[existingIndex].price * newQty;
                        if (cart[existingIndex].discount_selected_type === 'percent') {
                            const d = (parseFloat(cart[existingIndex].discount_percent) || 0) / 100;
                            cart[existingIndex].rowDiscount = base * d;
                        } else {
                            cart[existingIndex].rowDiscount = parseFloat(cart[existingIndex].discount_amount) || 0;
                        }
                        renderCart();
                    }
                },
                error: function() {
                    alert('Error checking stock availability');
                }
            });
            return;
        }

        // Add new product to cart with discount defaults and locks
        const strengthName = (product.strength && product.strength.name)
            ? product.strength.name
            : '';

        // Determine default discount type and values per your rules:
        // - if discount_percent > 0 -> select percent by default and put percent value in discount field
        // - else if discount (amount) > 0 -> select RS by default and put discount amount in discount field
        // - else -> show 0 with % selected by default (as requested)
        let defaultDiscountType = 'percent';
        let discount_percent = parseFloat(product.discount_percent || 0);
        let discount_amount = parseFloat(product.discount || 0);

        if (discount_percent > 0) {
            defaultDiscountType = 'percent';
        } else if (discount_amount > 0) {
            defaultDiscountType = 'amount';
        } else {
            defaultDiscountType = 'percent';
            discount_percent = 0;
            discount_amount = 0;
        }

        cart.push({
            id: product.id,
            name: `${product.product_name} - ${strengthName}`,
            price: parseFloat(product.price || 0),
            qty: 1,
            category_id: product.default_category_id || '',
            // keep both values; active input value depends on discount_selected_type
            discount_percent: discount_percent,
            discount_amount: discount_amount,
            discount_selected_type: defaultDiscountType, // 'percent' or 'amount'
            lock_max_discount: !!product.lock_max_discount,
            // store max caps (if any) to enforce lock rules
            max_discount_percent: parseFloat(product.discount_percent || 0),
            max_discount_amount: parseFloat(product.discount || 0),
            categories: product.categories || []
        });

        renderCart();
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
            recalcTotals();
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

            // FIX: Update row total immediately after toggle
            const base = (product.price * product.qty) || 0;
            let rowDiscount = 0;

            if (type === 'percent') {
                rowDiscount = base * ((parseFloat(product.discount_percent) || 0) / 100);
            } else {
                rowDiscount = parseFloat(product.discount_amount) || 0;
            }

            $(`#cartBody tr[data-index="${i}"] .row-total`).text((base - rowDiscount).toFixed(2));

            // Recalculate overall totals
            recalcTotals();
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
            // comment these lines if don't want to reset quantity to 1 on category change
            cart[i].qty = 1;
            renderCart();
            getCategoryPrice(productId, categoryId, i);
            checkStockAvailability(i, 1, 1);
        });

        $('.remove-item').off('click').on('click', function() {
            const i = $(this).data('index');
            if (confirm('Are you sure you want to remove this item?')) {
                cart.splice(i, 1);
                renderCart();
            }
        });

        recalcTotals();
    }

    // Separate function for stock checking (unchanged)
    function checkStockAvailability(index, enteredQuantity, originalValue) {
        const productId = cart[index].id;
        const categoryId = cart[index].category_id;

        console.log('Checking stock for product:', productId, 'quantity:', enteredQuantity, 'category:', categoryId);
        if (enteredQuantity <= 0) {
            cart[index].qty = 1;
            renderCart();
            return;
        }

        $.ajax({
            url: '/admin/products/pos/check-stock',
            type: 'POST',
            data: {
                product_id: productId,
                quantity: enteredQuantity,
                category_id: categoryId,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                console.log('Stock check response:', response);

                if (response.status === 'error') {
                    // Use the available_quantity from response if available
                    let availableQty = 1;
                    if (response.available_quantity !== undefined) {
                        availableQty = parseFloat(response.available_quantity);
                    } else {
                        // Fallback to parsing from message
                        const availableMatch = (response.message || '').match(/Available:\s*([\d.]+)/);
                        if (availableMatch && availableMatch[1]) {
                            availableQty = parseFloat(availableMatch[1]);
                        }
                    }

                    // Auto-set to available quantity and show message
                    cart[index].qty = availableQty;
                    alert(response.message + "\n\nQuantity automatically set to " + availableQty);
                    renderCart();
                } else {
                    // Handle successful stock check
                    if (response.rows && response.rows.length > 0) {
                        // Update the current product with first row data
                        cart[index].qty = response.rows[0].quantity;
                        cart[index].price = response.rows[0].unit_price;

                        // Remove any existing split rows for this product (preserve the current one)
                        const baseItem = cart[index];
                        cart = cart.filter((item, idx) => {
                            return !(item.id === productId && idx !== index);
                        });

                        // Add additional split rows if any
                        if (response.rows.length > 1) {
                            for (let j = 1; j < response.rows.length; j++) {
                                cart.push({
                                    id: response.rows[j].product_id,
                                    name: baseItem.name,
                                    qty: response.rows[j].quantity,
                                    price: response.rows[j].unit_price,
                                    discount_percent: 0,
                                    discount_amount: 0,
                                    discount_selected_type: baseItem.discount_selected_type,
                                    lock_max_discount: baseItem.lock_max_discount,
                                    max_discount_percent: baseItem.max_discount_percent,
                                    max_discount_amount: baseItem.max_discount_amount,
                                    category_id: baseItem.category_id,
                                    categories: baseItem.categories
                                });
                            }
                        }
                    }
                    renderCart();
                }
            },
            error: function(xhr, status, error) {
                console.error('Stock check error:', xhr.responseText, status, error);

                // Check if it's a 404 error (Product not found)
                if (xhr.status === 404) {
                    alert('Product not found in the system. Please refresh the page and try again.');
                } else if (xhr.status === 422) {
                    // Validation error
                    const errors = xhr.responseJSON.errors || {};
                    let errorMsg = 'Validation error: ';
                    for (let field in errors) {
                        errorMsg += (errors[field][0] || '') + ' ';
                    }
                    alert(errorMsg);
                } else {
                    alert('Error checking stock availability. Please try again.');
                }

                // Reset to original value on error
                cart[index].qty = originalValue;
                renderCart();
            }
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

        submitToPrintRoute({!! json_encode(route('pos.print-receipt')) !!}, cartData, invoiceDiscountValue, invoiceDiscountType);
    });

    // ------------------------------
    // Save & Print
    $('#saveAndPrint').on('click', function() {
        if (cart.length === 0) {
            alert('No items in cart to save and print!');
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

        $.ajax({
            url: {!! json_encode(route('pos.save-invoice')) !!},
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                cart: cartData,
                invoice_discount_value: invoiceDiscountValue,
                invoice_discount_type: invoiceDiscountType
            },
            success: function(response) {
                // after saving, open print tab using saved invoice id
                submitToPrintRoute(
                    {!! json_encode(route('pos.print-receipt')) !!},
                    cartData,
                    invoiceDiscountValue,
                    invoiceDiscountType,
                    { invoice_id: response.invoice_id }
                );

                alert('Invoice saved successfully!');
                cart = [];
                renderCart();
                recalcTotals();
            },
            error: function() {
                alert('Error saving invoice!');
            }
        });
    });

    // Initialize on page load
    initInvoiceDiscountToggle();
});
</script>
@endpush