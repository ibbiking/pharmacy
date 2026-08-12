<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Business;
use App\Models\Currency\GlobalCurrency;
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
        $currencies = GlobalCurrency::visibleTo($business->id)->orderBy('currency_code')->get();
        return view('admin.business.settings', compact('title', 'business', 'globalMinStock', 'currencies'));
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'note' => 'nullable|string',
            'global_min_indicated_qty' => 'nullable|numeric|min:0',
            'invoice_source' => 'required|in:local,fbr',
            'fbr_business_name' => 'nullable|string|max:255',
            'fbr_ntn' => 'required_if:invoice_source,fbr|nullable|string|max:50',
            'fbr_strn' => 'nullable|string|max:50',
            'fbr_pos_registration_no' => 'required_if:invoice_source,fbr|nullable|string|max:100',
            'fbr_environment' => 'nullable|in:sandbox,production',
            'fbr_api_token' => 'nullable|string',
            'currency_id' => 'nullable|exists:currencies,id',
        ]);

        $business = Business::findOrFail(session('business_id'));

        // A business may only select a global currency or one of its own —
        // never another business's custom currency.
        if ($request->filled('currency_id')) {
            $currency = GlobalCurrency::visibleTo($business->id)->find($request->currency_id);
            abort_unless($currency, 422, 'Invalid currency selection.');
        }

        $business->name = $request->name;
        $business->address = $request->address;
        $business->phone = $request->phone;
        $business->note = $request->note;
        $business->currency_id = $request->currency_id ?: null;
        $business->invoice_source = $request->invoice_source;
        $business->fbr_business_name = $request->fbr_business_name;
        $business->fbr_ntn = $request->fbr_ntn;
        $business->fbr_strn = $request->fbr_strn;
        $business->fbr_pos_registration_no = $request->fbr_pos_registration_no;
        $business->fbr_environment = $request->fbr_environment ?: 'sandbox';

        // Leave the encrypted token untouched unless a new one was typed.
        if ($request->filled('fbr_api_token')) {
            $business->fbr_api_token = $request->fbr_api_token;
        }

        if ($request->invoice_source === 'fbr' && $business->hasFbrCredentials() && !$business->fbr_linked_at) {
            $business->fbr_linked_at = now();
        }

        $business->save();

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
