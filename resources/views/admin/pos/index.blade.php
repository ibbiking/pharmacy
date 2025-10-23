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
</style>
@endpush

@section('content')
<div class="pos-wrapper">
    <!-- LEFT SIDE (MAIN POS) -->
    <div class="pos-left">
        <input type="text" id="searchProduct" class="fast-search"
            placeholder="Search Product by Name or Barcode (Press Enter)" autofocus>

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

    // Enter key triggers product search
    $('#searchProduct').on('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const query = $(this).val().trim();
            if (query.length > 1) {
                fetchProduct(query);
                $(this).val('');
            }
        }
    });

    // Invoice discount recalculation
    $('#invoiceDiscount').on('input', recalcTotals);

    // Fetch product details
    function fetchProduct(query) {
        console.log('Searching for:', query);
        $.ajax({
            url: {!! json_encode(route('products.search')) !!}, // ✅ safer Blade syntax for JS
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
                // Remove from cart or handle accordingly
                return;
            }
            
            // Update the price for the specific product in cart
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
        const exists = cart.find(p => p.id === product.id);
        if (exists) {
            exists.qty += 1;
        } else {
            const strengthName = (product.strength && product.strength.name)
                ? product.strength.name
                : '';
            cart.push({
    id: product.id,
    name: `${product.product_name} - ${strengthName}`,
    price: parseFloat(product.price || 0),
    qty: 1,
    category_id: product.default_category_id || '',
    discount: 0,
    categories: product.categories || []
});
        }

        renderCart();
    }

    // Render cart table
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
                <td>${total.toFixed(2)}</td>
            </tr>`;
        });
    }
    $('#cartBody').html(html);
    
    // Rebind events to dynamic inputs
    $('.qty, .price, .discount').off('input').on('input', function() {
        const i = $(this).data('index');
        const val = parseFloat($(this).val()) || 0;
        const key = $(this).hasClass('qty') ? 'qty' : 
                   $(this).hasClass('price') ? 'price' : 'discount';
        cart[i][key] = val;
        recalcTotals();
    });
    
    // Category change event - FIXED VERSION
    $('.category-select').off('change').on('change', function() {
        const i = $(this).data('index');
        const productId = cart[i].id;
        const categoryId = $(this).val();
        
        cart[i].category_id = categoryId;
        
        // Get updated price for this category
        getCategoryPrice(productId, categoryId, i);
    });
    
    recalcTotals();
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
});
</script>
@endpush