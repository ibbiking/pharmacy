<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Business;
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

        return redirect()->route('dashboard')->with('success', 'Business created successfully!');
    }
}
