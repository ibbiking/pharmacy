<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Currency\GlobalCurrency;
use App\Models\Preference;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Business settings (Roadmap 5.11a, Inertia). Name, contact, receipt note, currency (paged picker) and the
 * business-wide stock alert level. B-1: FBR invoicing is refused until the FBR integration (step 6) is real —
 * FbrInvoiceService is still a stub, so switching to it would make every sale fail.
 */
class BusinessController extends Controller
{
    private function business(): Business
    {
        return Business::findOrFail(session('impersonate_business_id') ?? session('business_id'));
    }

    private static function currencyLabel(GlobalCurrency $c): string
    {
        return "{$c->currency_code} — {$c->name} ({$c->symbol})";
    }

    public function settings()
    {
        $business = $this->business();
        $currency = $business->currency_id ? GlobalCurrency::visibleTo($business->id)->find($business->currency_id) : null;
        $alert = Preference::where('business_id', $business->id)->where('slug', 'global_min_indicated_qty')->value('preference');

        return Inertia::render('Settings/Business', [
            'settings' => [
                'name' => $business->name,
                'address' => $business->address,
                'phone' => $business->phone,
                'note' => $business->note,
                'currency' => $currency ? ['id' => $currency->id, 'label' => self::currencyLabel($currency)] : null,
                'global_min_indicated_qty' => is_numeric($alert) ? (float) $alert : null,
                'invoice_source' => $business->invoice_source ?: 'local',
            ],
        ]);
    }

    /** Paged currency picker: global currencies + this business's own (never another business's). */
    public function currencies(Request $request)
    {
        $term = trim((string) $request->input('q', ''));
        $page = GlobalCurrency::visibleTo($this->business()->id)
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('currency_code', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%")))
            ->orderByRaw("currency_code = 'PKR' desc")->orderBy('currency_code')->orderBy('id')
            ->paginate(20, ['id', 'currency_code', 'name', 'symbol']);

        return response()->json([
            'data' => collect($page->items())->map(fn ($c) => ['id' => $c->id, 'label' => self::currencyLabel($c)]),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
            'total' => $page->total(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $business = $this->business();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:500'],
            'global_min_indicated_qty' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            // B-1: only "local" until the FBR integration (step 6) exists.
            'invoice_source' => ['sometimes', Rule::in(['local'])],
            'currency_id' => ['nullable', 'integer', Rule::exists('currencies', 'id')->whereNull('deleted_at')
                ->where(fn ($q) => $q->whereNull('business_id')->orWhere('business_id', $business->id))],
        ], [
            'invoice_source.in' => 'FBR invoicing comes with the FBR integration (roadmap step 6). Until then invoices use local numbers.',
            'currency_id.exists' => 'Pick a currency from the list.',
        ]);

        $business->fill([
            'name' => trim($data['name']),
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'note' => $data['note'] ?? null,
            'currency_id' => $data['currency_id'] ?? null,
        ])->save();

        if (isset($data['global_min_indicated_qty'])) {
            // Reuse an archived row instead of piling up a new one each time the level is cleared and set again.
            $pref = Preference::withTrashed()->firstOrNew(['business_id' => $business->id, 'type' => 'business', 'slug' => 'global_min_indicated_qty']);
            $pref->preference = $data['global_min_indicated_qty'];
            $pref->deleted_at = null;
            $pref->save();
        } else {
            Preference::where('business_id', $business->id)->where('slug', 'global_min_indicated_qty')->delete();
        }

        return back()->with('success', 'Business settings saved.');
    }
}
