<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Business;
use App\Models\Preference;
use Illuminate\Support\Facades\Auth;

class BusinessController extends Controller
{
    public function setup()
    {
        $title = 'Setup Business';
        return view('admin.business.setup', compact('title'));
    }

    public function storeSetup(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'note' => 'nullable|string',
        ]);

        $business = Business::create([
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
            'note' => $request->note,
            'created_by' => Auth::id(),
        ]);

        Auth::user()->businesses()->attach($business->id, ['role' => 'owner']);
        
        session(['business_id' => $business->id]);

        return redirect()
            ->route('generic_products.index')
            ->with(notify('Business created successfully! You can import generic products from here.'));
    }
    public function settings()
    {
        $title = 'Business Settings';
        $business = Business::findOrFail(session('business_id'));
        $globalMinStockPref = Preference::where('business_id', $business->id)->where('slug', 'global_min_indicated_qty')->first();
        $globalMinStock = $globalMinStockPref ? $globalMinStockPref->preference : '';
        return view('admin.business.settings', compact('title', 'business', 'globalMinStock'));
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'note' => 'nullable|string',
            'global_min_indicated_qty' => 'nullable|numeric|min:0',
        ]);

        $business = Business::findOrFail(session('business_id'));
        
        $business->update([
            'name' => $request->name,
            'address' => $request->address,
            'phone' => $request->phone,
            'note' => $request->note,
        ]);

        if ($request->filled('global_min_indicated_qty')) {
            Preference::updateOrCreate(
                ['business_id' => $business->id, 'type' => 'business', 'slug' => 'global_min_indicated_qty'],
                ['preference' => $request->global_min_indicated_qty]
            );
        } else {
            Preference::where('business_id', $business->id)->where('slug', 'global_min_indicated_qty')->delete();
        }

        return redirect()->route('business.settings')->with('success', 'Business settings updated successfully!');
    }
}
