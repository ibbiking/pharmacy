<?php

namespace App\Modules\BulkEmail\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BulkEmail\Models\Signature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SignatureController extends Controller
{
    public function index()
    {
        $signatures = Signature::latest()->paginate(10);
        return view('bulk-email::signatures.index', compact('signatures'));
    }

    public function create()
    {
        return view('bulk-email::signatures.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'designation' => 'nullable|string',
            'company' => 'nullable|string',
            'phone' => 'nullable|string',
            'website' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'logo' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('bulk-email/logos', 'public');
        }

        if ($request->is_default) {
            Signature::where('id', '>', 0)->update(['is_default' => false]);
            $data['is_default'] = true;
        }

        Signature::create($data);

        return redirect()->route('bec.signatures.index')->with('success', 'Signature created.');
    }

    public function edit(Signature $signature)
    {
        return view('bulk-email::signatures.edit', compact('signature'));
    }

    public function update(Request $request, Signature $signature)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'designation' => 'nullable|string',
            'company' => 'nullable|string',
            'phone' => 'nullable|string',
            'website' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'logo' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('bulk-email/logos', 'public');
        }

        if ($request->is_default) {
            Signature::where('id', '!=', $signature->id)->update(['is_default' => false]);
            $data['is_default'] = true;
        }

        $signature->update($data);

        return redirect()->route('bec.signatures.index')->with('success', 'Signature updated.');
    }

    public function destroy(Signature $signature)
    {
        $signature->delete();
        return redirect()->route('bec.signatures.index')->with('success', 'Signature deleted.');
    }
}
