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
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Yajra\DataTables\DataTables;
use App\Http\Controllers\Controller;
use QCod\AppSettings\Setting\AppSettings;

class POSController extends Controller
{
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

            $invoiceDiscount = floatval($request->input('invoice_discount', 0));

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

            $grandTotal = max(0, $subtotal - $invoiceDiscount);

            return view('admin.pos.receipt-print', [
                'cartItems' => $cartItems,
                'subtotal' => $subtotal,
                'invoiceDiscount' => $invoiceDiscount,
                'grandTotal' => $grandTotal
            ]);
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Print receipt error: ' . $e->getMessage());

            // Return empty receipt with error message
            return view('admin.pos.receipt-print', [
                'cartItems' => [],
                'subtotal' => 0,
                'invoiceDiscount' => 0,
                'grandTotal' => 0,
                'error' => 'Failed to generate receipt: ' . $e->getMessage()
            ]);
        }
    }
}
