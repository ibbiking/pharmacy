<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Currency\GlobalCurrency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Currencies (Roadmap 5.11c, Inertia): the global list (seeded, super admin edits) + this business's own additions.
 * A business adds / edits / archives only its own; a currency any business uses can't be archived (C-2), and a business
 * can't add a second copy of a global code (C-3) — it picks the global one in Business settings instead.
 */
class CurrencyController extends Controller
{
    private const SORTABLE = ['currency_code', 'name', 'symbol'];
    private const PER_PAGE = [10, 20, 50];

    private function businessId(): ?int
    {
        $id = session('impersonate_business_id') ?? session('business_id');

        return $id ? (int) $id : null;
    }

    private function isPlatformAdmin(): bool
    {
        return (bool) auth()->user()?->hasRole('super-admin');
    }

    private function canManage(GlobalCurrency $c): bool
    {
        return $c->isGlobal() ? $this->isPlatformAdmin() : (int) $c->business_id === $this->businessId();
    }

    public function index(Request $request)
    {
        $bid = $this->businessId();
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'sort' => in_array($request->input('sort'), self::SORTABLE, true) ? $request->input('sort') : 'currency_code',
            'dir' => $request->input('dir') === 'desc' ? 'desc' : 'asc',
            'per_page' => in_array($request->integer('per_page'), self::PER_PAGE, true) ? $request->integer('per_page') : 20,
            'scope' => in_array($request->input('scope'), ['global', 'own'], true) ? $request->input('scope') : '',
        ];
        $current = $bid ? Business::whereKey($bid)->value('currency_id') : null;
        $usage = DB::table('businesses')->selectRaw('count(*)')->whereColumn('businesses.currency_id', 'currencies.id')->whereNull('businesses.deleted_at');

        $rows = GlobalCurrency::visibleTo($bid)
            ->select('currencies.*')->selectSub($usage, 'usage')
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('currency_code', 'like', "%{$filters['q']}%")->orWhere('name', 'like', "%{$filters['q']}%")))
            ->when($filters['scope'] === 'global', fn ($q) => $q->whereNull('business_id'))
            ->when($filters['scope'] === 'own', fn ($q) => $q->whereNotNull('business_id'))
            ->orderByRaw('currencies.id = ? desc', [(int) $current])
            ->orderBy($filters['sort'], $filters['dir'])->orderBy('id')
            ->paginate($filters['per_page'])->withQueryString()
            ->through(fn (GlobalCurrency $c) => [
                'id' => $c->id,
                'code' => $c->currency_code,
                'name' => $c->name,
                'symbol' => $c->symbol,
                'exchange_rate' => (float) $c->exchange_rate,
                'global' => $c->isGlobal(),
                'current' => $c->id === (int) $current,
                'usage' => (int) $c->usage,
                'can_manage' => $this->canManage($c),
            ]);

        return Inertia::render('Settings/Currencies', [
            'currencies' => $rows,
            'filters' => $filters,
            'stats' => fn () => [
                'global' => GlobalCurrency::whereNull('business_id')->count(),
                'own' => $bid ? GlobalCurrency::where('business_id', $bid)->count() : 0,
                'current' => $current ? GlobalCurrency::withTrashed()->find($current)?->only('currency_code', 'name', 'symbol') : null,
            ],
            'adding_global' => $bid === null,
            'open' => $request->boolean('create') ? 'create' : ($request->integer('edit') ?: null),
        ]);
    }

    /** Old links (/currencies/create, /currencies/{id}/edit) open the form on the list. */
    public function create()
    {
        return redirect()->route('currencies.index', ['create' => 1]);
    }

    public function edit(GlobalCurrency $currency)
    {
        abort_unless($this->canManage($currency), 403, 'You cannot manage this currency.');

        return redirect()->route('currencies.index', ['edit' => $currency->id]);
    }

    private function rules(?GlobalCurrency $currency, ?int $ownerId): array
    {
        $sameScope = fn ($q) => $ownerId ? $q->where('business_id', $ownerId) : $q->whereNull('business_id');

        return [
            'currency_code' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z]{2,10}$/',
                Rule::unique('currencies')->ignore($currency?->id)->whereNull('deleted_at')->where($sameScope),
                // C-3: a business doesn't duplicate a global code.
                function ($attr, $value, $fail) use ($ownerId) {
                    if ($ownerId && GlobalCurrency::whereNull('business_id')->where('currency_code', strtoupper($value))->exists()) {
                        $fail(strtoupper($value).' is already in the global list: pick it in Business settings.');
                    }
                }],
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['required', 'string', 'max:20'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0', 'max:1000000000'],
        ];
    }

    public function store(Request $request)
    {
        $ownerId = $this->businessId(); // super admin outside a business adds a global currency
        $data = $request->validate($this->rules(null, $ownerId), ['currency_code.unique' => 'This code is already in the list.', 'currency_code.regex' => 'Use letters only, e.g. AED.']);

        GlobalCurrency::create([
            'business_id' => $ownerId,
            'currency_code' => strtoupper($data['currency_code']),
            'name' => trim($data['name']),
            'symbol' => trim($data['symbol']),
            'exchange_rate' => $data['exchange_rate'] ?? 1,
        ]);

        return back()->with('success', 'Currency added.');
    }

    public function update(Request $request, GlobalCurrency $currency)
    {
        abort_unless($this->canManage($currency), 403, 'You cannot manage this currency.');
        $data = $request->validate($this->rules($currency, $currency->business_id ? (int) $currency->business_id : null), ['currency_code.unique' => 'This code is already in the list.', 'currency_code.regex' => 'Use letters only, e.g. AED.']);

        $currency->update([
            'currency_code' => strtoupper($data['currency_code']),
            'name' => trim($data['name']),
            'symbol' => trim($data['symbol']),
            'exchange_rate' => $data['exchange_rate'] ?? $currency->exchange_rate,
        ]);

        return back()->with('success', 'Currency saved.');
    }

    public function destroy(GlobalCurrency $currency)
    {
        abort_unless($this->canManage($currency), 403, 'You cannot manage this currency.');
        // C-2: a business using it would silently lose its currency.
        $users = Business::where('currency_id', $currency->id)->count();
        if ($users) {
            return back()->with('error', $users === 1 && ! $currency->isGlobal()
                ? 'This is your business currency. Pick another one in Business settings first.'
                : "{$users} business(es) use this currency, so it can't be archived.");
        }
        $currency->delete();

        return back()->with('success', 'Currency archived.');
    }
}
