<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency\GlobalCurrency;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\DataTables;

class CurrencyController extends Controller
{
    public function index(Request $request)
    {
        $title = 'Currencies';
        $businessId = session('business_id');

        if ($request->ajax()) {
            $currencies = GlobalCurrency::visibleTo($businessId);

            return DataTables::of($currencies)
                ->addIndexColumn()
                ->addColumn('scope', function ($currency) {
                    return $currency->isGlobal()
                        ? '<span class="badge badge-secondary">Global</span>'
                        : '<span class="badge badge-info">Your Business</span>';
                })
                ->addColumn('action', function ($row) use ($businessId) {
                    $canManage = !$row->isGlobal() && $row->business_id == $businessId
                        || (auth()->check() && auth()->user()->hasRole('super-admin'));

                    if (!$canManage) {
                        return '<span class="text-muted small">Global — not editable</span>';
                    }

                    $editbtn = '<a href="' . route('currencies.edit', $row->id) . '" class="editbtn"><button class="btn btn-info"><i class="fas fa-edit"></i></button></a>';
                    $deletebtn = '<a data-id="' . $row->id . '" data-route="' . route('currencies.destroy', $row->id) . '" href="javascript:void(0)" id="deletebtn"><button class="btn btn-danger"><i class="fas fa-trash"></i></button></a>';

                    return $editbtn . ' ' . $deletebtn;
                })
                ->rawColumns(['scope', 'action'])
                ->make(true);
        }

        return view('admin.currencies.index', compact('title'));
    }

    public function create()
    {
        $title = 'Add Currency';
        return view('admin.currencies.create', compact('title'));
    }

    public function store(Request $request)
    {
        $businessId = session('business_id');

        $request->validate([
            'currency_code' => [
                'required', 'string', 'max:10',
                Rule::unique('currencies')->where(
                    fn ($query) => $businessId ? $query->where('business_id', $businessId) : $query->whereNull('business_id')
                ),
            ],
            'name' => 'required|string|max:100',
            'symbol' => 'required|string|max:20',
            'exchange_rate' => 'required|numeric|min:0',
        ]);

        GlobalCurrency::create([
            'business_id' => $businessId,
            'currency_code' => strtoupper($request->currency_code),
            'name' => $request->name,
            'symbol' => $request->symbol,
            'exchange_rate' => $request->exchange_rate,
        ]);

        return redirect()->route('currencies.index')->with('success', 'Currency has been added for your business.');
    }

    public function edit(GlobalCurrency $currency)
    {
        $this->authorizeManage($currency);

        $title = 'Edit Currency';
        return view('admin.currencies.edit', compact('title', 'currency'));
    }

    public function update(Request $request, GlobalCurrency $currency)
    {
        $this->authorizeManage($currency);

        $businessId = $currency->business_id;

        $request->validate([
            'currency_code' => [
                'required', 'string', 'max:10',
                Rule::unique('currencies')->ignore($currency->id)->where(
                    fn ($query) => $businessId ? $query->where('business_id', $businessId) : $query->whereNull('business_id')
                ),
            ],
            'name' => 'required|string|max:100',
            'symbol' => 'required|string|max:20',
            'exchange_rate' => 'required|numeric|min:0',
        ]);

        $currency->update([
            'currency_code' => strtoupper($request->currency_code),
            'name' => $request->name,
            'symbol' => $request->symbol,
            'exchange_rate' => $request->exchange_rate,
        ]);

        return redirect()->route('currencies.index')->with('success', 'Currency has been updated.');
    }

    public function destroy(Request $request)
    {
        $currency = GlobalCurrency::findOrFail($request->id);
        $this->authorizeManage($currency);

        return $currency->delete();
    }

    /**
     * Only the owning business can manage its own custom currency; global
     * (business_id null) currencies are only manageable by a super-admin.
     */
    private function authorizeManage(GlobalCurrency $currency): void
    {
        $isOwnCustomCurrency = !$currency->isGlobal() && $currency->business_id == session('business_id');
        $isSuperAdmin = auth()->check() && auth()->user()->hasRole('super-admin');

        abort_unless($isOwnCustomCurrency || $isSuperAdmin, 403, 'You cannot manage this currency.');
    }
}
