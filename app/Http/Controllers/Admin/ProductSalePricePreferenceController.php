<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Preference;
use Illuminate\Http\Request;

class ProductSalePricePreferenceController extends Controller
{
    /**
     * Show the form to select or update sale price preference for a product.
     */
    public function create($productId)
    {
        $product = Product::findOrFail($productId);

        // Only sale_price preferences
        $preferences = Preference::where('type', 'sale_price')->get();

        $title = 'Select Sale Price Preference';

        return view('admin.products.sale_price_preference.create', compact(
            'title',
            'product',
            'preferences'
        ));
    }

    /**
     * Store or update sale price preference for a product.
     */
    public function store(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);

        $request->validate([
            'sale_price_preference_id' => 'required|exists:preferences,id',
        ]);

        // save or update preference
        $product->update([
            'sale_price_preference_id' => $request->sale_price_preference_id,
        ]);

        $notification = notify('Sale price preference saved successfully.');

        return redirect()->route('products.index')->with($notification);
    }

    /**
     * Edit product sale price preference.
     */
    public function edit($productId)
    {
        $product = Product::findOrFail($productId);

        $preferences = Preference::where('type', 'sale_price')->get();

        $title = 'Edit Sale Price Preference';

        return view('admin.products.sale_price_preference.edit', compact(
            'title',
            'product',
            'preferences'
        ));
    }

    /**
     * Update the product sale price preference.
     */
    public function update(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);

        $request->validate([
            'sale_price_preference_id' => 'required|exists:preferences,id',
        ]);

        $product->update([
            'sale_price_preference_id' => $request->sale_price_preference_id,
        ]);

        $notification = notify('Sale price preference updated successfully.');

        return redirect()->route('products.index')->with($notification);
    }
}