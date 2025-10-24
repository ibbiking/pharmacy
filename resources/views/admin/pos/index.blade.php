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
                <input type="number" id="invoiceDiscount" class="form-control" style="width:150px;" value="0">
            </div>
            <h4>Total: <span id="grandTotal">0.00</span></h4>
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
                <strong>Grand Total: <span id="receiptTotal">0.00</span></strong>
            </div>
        </div>
        <button class="btn btn-success btn-block mt-3" id="printReceipt"><i class="fa fa-print"></i> Print</button>
    </div>
</div>
@endsection

@push('page-js')
<script>
    console.log("POS script initialized ✅");
// Ensure jQuery runs safely
jQuery(function ($) {
    console.log("POS script initialized ✅");

    let cart = [];

    // Focus on search field when page loads
    $('#searchProduct').focus();

    let searchResults = [];
let selectedIndex = 0;

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

    // ✅ Normalize response: if it's a single object, wrap it in an array
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
    $('#invoiceDiscount').on('input', recalcTotals);

    // Fetch product details
    function fetchProduct(query) {
        console.log('Searching for:', query);
        $.ajax({
            url: {!! json_encode(route('products.search')) !!},
            method: 'GET',
            data: { q: query },
            success: function (data) {
                console.log('Product response:', data);
                if (data && data.id) {
                    addToCart(data);
                } else {
                    alert('Product not found');
                }
            },
            error: function (xhr) {
                console.error('Error:', xhr.responseText);
                alert('Error fetching product');
            }
        });
    }

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
                    renderCart();
                }
            },
            error: function(xhr) {
                console.error('Error fetching category price:', xhr.responseText);
                alert('Error updating price for selected category');
            }
        });
    }

    // Add or update cart
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
                    // Keep previous quantity, don’t increase
                    renderCart();
                } else {
                    // Safe to increase
                    cart[existingIndex].qty = newQty;
                    renderCart();
                }
            },
            error: function() {
                alert('Error checking stock availability');
            }
        });
    } else {
        // Add new product to cart
        const strengthName = (product.strength && product.strength.name)
            ? product.strength.name
            : '';

        cart.push({
    id: product.id,
    name: `${product.product_name} - ${strengthName}`,
    price: parseFloat(product.price || 0),
    qty: 1,
    category_id: product.default_category_id || '',
    discount: parseFloat(product.discount || 0), // 🆕 set product discount
    max_discount: parseFloat(product.discount || 0), // 🆕 store as max ref
    lock_max_discount: !!product.lock_max_discount, // 🆕 true/false
    categories: product.categories || []
});

        renderCart();
    }
}

    // Render cart table with debounced quantity input
    function renderCart() {
        let html = '';
        if (cart.length === 0) {
            html = `<tr><td colspan="7" class="text-center">No products added yet</td></tr>`;
        } else {
            cart.forEach((p, i) => {
                const total = (p.price * p.qty) - p.discount;
                html += `
<tr>
    <td>${i + 1}</td>
    <td>${p.name}</td>
    <td><input type="number" class="form-control price" data-index="${i}" value="${p.price.toFixed(2)}"></td>
    <td><input type="number" class="form-control qty" data-index="${i}" value="${p.qty}"></td>
    <td>
        <select class="form-select category-select" data-index="${i}">
            ${categoryOptionsHtml(p.categories, p.category_id)}
        </select>
    </td>
    <td><input type="number" class="form-control discount" data-index="${i}" value="${p.discount}"></td>
    <td>${((p.price * p.qty) - p.discount).toFixed(2)}</td>
    <td class="text-center">
        <button class="btn btn-danger btn-sm remove-item" data-index="${i}">
            <i class="fa fa-trash"></i>
        </button>
    </td>
</tr>`;
            });
        }
        $('#cartBody').html(html);
        
        // Rebind events to dynamic inputs
        $('.price').off('input').on('input', function() {
    const i = $(this).data('index');
    cart[i].price = parseFloat($(this).val()) || 0;
    recalcTotals();
});

$('.discount').off('input').on('input', function() {
    const i = $(this).data('index');
    let entered = parseFloat($(this).val()) || 0;
    const product = cart[i];
    // 🧠 Step 1: Get latest discount policy from server
    $.get(`/admin/pos/product-discount-info/${product.id}`, function(res) {
        if (res.error) {
            alert(res.error);
            return;
        }

        // 🧠 Step 2: Update cart info with fresh data
        product.max_discount = parseFloat(res.discount || 0);
        product.lock_max_discount = !!res.lock_max_discount;

        // 🧠 Step 3: Apply restriction
        if (product.lock_max_discount && entered > product.max_discount) {
            alert(`Discount cannot exceed ${product.max_discount.toFixed(2)} for this item.`);
            entered = product.max_discount;
            $(`.discount[data-index="${i}"]`).val(entered.toFixed(2));
        }

        // ✅ Step 4: Save and recalc totals
        product.discount = entered;
recalcTotals();

// 🧩 Update only the total cell of that row
const total = (product.price * product.qty) - product.discount;
$(`#cartBody tr:eq(${i}) td:last`).text(total.toFixed(2));
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
    // comment these lines if don't want to reset quantity to 1 on category change
    cart[i].qty = 1;
    renderCart();
    getCategoryPrice(productId, categoryId, i);
    checkStockAvailability(i, 1, 1);
    // comment these lines if don't want to reset quantity to 1 on category change
    
    // unComment these lines if don't want to reset quantity to 1 on category change
    // getCategoryPrice(productId, categoryId, i);
    // const enteredQuantity = cart[i].qty;
    // checkStockAvailability(i, enteredQuantity, enteredQuantity);
    // unComment these lines if don't want to reset quantity to 1 on category change
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

    // Separate function for stock checking
    function checkStockAvailability(index, enteredQuantity, originalValue) {
        const productId = cart[index].id;
        const categoryId = cart[index].category_id;

        console.log('Checking stock for product:', productId, 'quantity:', enteredQuantity, 'category:', categoryId);

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
                        const availableMatch = response.message.match(/Available:\s*([\d.]+)/);
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
                        
                        // Remove any existing split rows for this product
                        cart = cart.filter(item => item.id !== productId || item === cart[index]);
                        
                        // Add additional split rows if any
                        if (response.rows.length > 1) {
                            for (let j = 1; j < response.rows.length; j++) {
                                cart.push({
                                    id: response.rows[j].product_id,
                                    name: cart[index].name,
                                    qty: response.rows[j].quantity,
                                    price: response.rows[j].unit_price,
                                    discount: 0,
                                    category_id: cart[index].category_id,
                                    categories: cart[index].categories
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
                    const errors = xhr.responseJSON.errors;
                    let errorMsg = 'Validation error: ';
                    for (let field in errors) {
                        errorMsg += errors[field][0] + ' ';
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
        cart.forEach(p => subtotal += (p.price * p.qty) - p.discount);

        const invoiceDiscount = parseFloat($('#invoiceDiscount').val()) || 0;
        const grandTotal = Math.max(0, subtotal - invoiceDiscount);

        $('#grandTotal').text(grandTotal.toFixed(2));

        let rhtml = '';
        if (cart.length === 0) {
            rhtml = `<tr><td colspan="6" class="text-center">No items</td></tr>`;
        } else {
            cart.forEach((p, i) => {
                const total = (p.price * p.qty) - p.discount;
                rhtml += `
                    <tr>
                        <td>${i + 1}</td>
                        <td>${p.name}</td>
                        <td>${p.price.toFixed(2)}</td>
                        <td>${p.qty}</td>
                        <td>${p.discount.toFixed(2)}</td>
                        <td>${total.toFixed(2)}</td>
                    </tr>`;
            });
        }

        $('#receiptBody').html(rhtml);
        $('#receiptTotal').text(grandTotal.toFixed(2));
    }

    // Print receipt functionality
    $('#printReceipt').on('click', function() {
        window.print();
    });
});
</script>
@endpush