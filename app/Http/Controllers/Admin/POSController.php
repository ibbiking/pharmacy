<?php

namespace App\Http\Controllers\Admin;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Category;
use App\Models\Company;
use App\Models\Farmula;
use App\Models\ProductParameter;
use App\Models\ProductStock;
use App\Models\ProductPreference;
use App\Models\Preference;
use App\Http\Controllers\Admin\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Yajra\DataTables\DataTables;
use App\Http\Controllers\Controller;
use QCod\AppSettings\Setting\AppSettings;

class POSController extends Controller
{
    private $productController;

    public function __construct()
    {
        $this->productController = new ProductController();
    }

    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return view('admin.pos.index');
    }

    public function getProductDiscountInfo($id)
    {
        $product = Product::select('id', 'discount', 'lock_max_discount')->find($id);

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        return response()->json([
            'discount' => (float) $product->discount,
            'lock_max_discount' => (bool) $product->lock_max_discount,
        ]);
    }

    public function printReceipt(Request $request)
    {
        try {
            // Get and validate cart data
            $cartJson = $request->input('cart', '[]');
            $cartData = json_decode($cartJson, true);

            // Check if JSON decoding was successful
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid cart data format');
            }

            if (!is_array($cartData)) {
                throw new \Exception('Cart data must be an array');
            }

            $invoiceDiscountValue = floatval($request->input('invoice_discount_value', 0));
            $invoiceDiscountType = $request->input('invoice_discount_type', 'amount');
            $cashReceived = floatval($request->input('cash_received', 0));
            $changeReturn = floatval($request->input('change_return', 0));

            // Calculate totals
            $subtotal = 0;
            $cartItems = [];

            foreach ($cartData as $index => $item) {
                // Validate required fields
                if (!isset($item['name']) || !isset($item['price']) || !isset($item['qty'])) {
                    continue; // Skip invalid items
                }

                $price = floatval($item['price']);
                $qty = floatval($item['qty']);
                $discountSelectedType = $item['discount_selected_type'] ?? 'percent';
                $discountPercent = floatval($item['discount_percent'] ?? 0);
                $discountAmount = floatval($item['discount_amount'] ?? 0);

                // Validate numeric values
                if ($price < 0 || $qty < 0 || $discountPercent < 0 || $discountAmount < 0) {
                    continue; // Skip items with negative values
                }

                $base = $price * $qty;
                $discount = $discountSelectedType === 'percent'
                    ? $base * ($discountPercent / 100)
                    : $discountAmount;

                // Ensure discount doesn't exceed base amount
                $discount = min($discount, $base);
                $total = max(0, $base - $discount);

                $cartItems[] = [
                    'name' => $item['name'],
                    'price' => $price,
                    'qty' => $qty,
                    'discount_selected_type' => $discountSelectedType,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'total' => $total
                ];

                $subtotal += $total;
            }

            // Calculate invoice discount
            $invoiceDiscount = 0;
            if ($invoiceDiscountType === 'percent') {
                $invoiceDiscount = $subtotal * ($invoiceDiscountValue / 100);
            } else {
                $invoiceDiscount = min($invoiceDiscountValue, $subtotal);
            }

            $grandTotal = max(0, $subtotal - $invoiceDiscount);

            return view('admin.pos.receipt-print', [
                'cartItems' => $cartItems,
                'subtotal' => $subtotal,
                'invoiceDiscountValue' => $invoiceDiscountValue,
                'invoiceDiscountType' => $invoiceDiscountType,
                'invoiceDiscount' => $invoiceDiscount,
                'grandTotal' => $grandTotal,
                'cashReceived' => $cashReceived,
                'changeReturn' => $changeReturn
            ]);
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Print receipt error: ' . $e->getMessage());

            // Return empty receipt with error message
            return view('admin.pos.receipt-print', [
                'cartItems' => [],
                'subtotal' => 0,
                'invoiceDiscountValue' => 0,
                'invoiceDiscountType' => 'amount',
                'invoiceDiscount' => 0,
                'grandTotal' => 0,
                'cashReceived' => $cashReceived,
                'changeReturn' => $changeReturn,
                'error' => 'Failed to generate receipt: ' . $e->getMessage()
            ]);
        }
    }

    public function saveInvoice(Request $request)
    {
        try {

            // Handle cart input (old logic)
            $cartInput = $request->input('cart', []);
            $cartData = is_array($cartInput) ? $cartInput : json_decode($cartInput, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json(['error' => 'Invalid cart data'], 400);
            }

            if (empty($cartData)) {
                return response()->json(['error' => 'Cart is empty'], 400);
            }

            // --- Create invoice ---
            $invoice = \App\Models\Invoice::create([
                'invoice_no'              => 'INV-' . now()->format('YmdHis'),
                'subtotal'                => $request->subtotal ?? 0,
                'discount'                => 0, // updated later
                'total'                   => $request->total ?? 0,

                // NEW FIELDS
                'invoice_discount_type'   => $request->invoice_discount_type,
                'invoice_discount_value'  => $request->invoice_discount_value,
                'invoice_discount_amount' => $request->invoice_discount_amount,
                'grand_total'             => $request->grand_total,
                'cash_received'           => $request->cash_received,
                'change_return'           => $request->change_return,
            ]);

            $subbTotol = 0;
            $totalItemDiscount = 0;

            // --- Insert items (old logic + new fields) ---
            foreach ($cartData as $item) {

                [$baseCategoryId, $baseQty] = calculateBaseStock($item['id'], $item['category_id'], $item['qty']);
                $preferenceInfo = $this->productController->getSalePricePreference($item['id']);
                $baseCategoryPrice = $this->productController->calculateSalePrice($item['id'], $baseCategoryId, $preferenceInfo);
                $preference = $preferenceInfo['preference'];
                $includingTax = $preferenceInfo['including_tax'];
                $base = $item['price'] * $item['qty'];

                if ($item['discount_selected_type'] === 'percent') {
                    $discountAmount = $base * (($item['discount_percent'] ?? 0) / 100);
                } else {
                    $discountAmount = $item['discount_amount'] ?? 0;
                }

                $discountAmount = min($discountAmount, $base);
                $totalItemDiscount += $discountAmount;

                \App\Models\InvoiceItem::create([
                    'invoice_id'      => $invoice->id,
                    'product_id'      => $item['id'],
                    'category_id'     => $item['category_id'],
                    'name'            => $item['name'],
                    'qty'             => $item['qty'],
                    'price'           => $item['price'],
                    'base_category_id'           => $baseCategoryId,
                    'base_quantity'           => $baseQty,
                    'base_category_price'           => $baseCategoryPrice,
                    'sale_preference_slug'           => $preference->slug,
                    'is_tax_included'           => $includingTax,

                    // OLD DISCOUNT
                    'discount_type'   => $item['discount_selected_type'],
                    'discount_value'  => $item['discount_selected_type'] === 'percent'
                        ? ($item['discount_percent'] ?? 0)
                        : ($item['discount_amount'] ?? 0),
                    'discount_amount' => $discountAmount,

                    // NEW FIELDS
                    'price_before_discount' => $item['price_before_discount'] ?? $item['price'],
                    'max_discount_percent'  => $item['max_discount_percent'] ?? 0,
                    'max_discount_amount'   => $item['max_discount_amount'] ?? 0,
                    'row_total'             => $base - $discountAmount,
                ]);

                $subbTotol += $base;
            }

            // ---- Update invoice with final totals ----
            $invoice->update([
                'subtotal' => $subbTotol,
                'discount' => $totalItemDiscount,
                'total'    => $subbTotol - $totalItemDiscount
            ]);

            return response()->json([
                'success'    => true,
                'invoice_id' => $invoice->id,
                'message'    => 'Invoice saved successfully.'
            ]);
        } catch (\Exception $e) {
            \Log::error('Save invoice error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to save invoice.'], 500);
        }
    }
}
