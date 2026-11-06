<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Pharmacies / branches of a business (Pharmacy Pack), Inertia since Roadmap 13 prep (2026-11-06).
 * Name + address + phone + note, unique name per business ignoring archived rows; archive and restore.
 * Fixes P-1: the old destroy took the id from the request body and the list loaded through DataTables.
 */
class PharmacyController extends Controller
{
    private const SORTABLE = ['name', 'phone', 'created_at'];
    private const PER_PAGE = [10, 20, 50];

    public function index(Request $request)
    {
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'sort' => in_array($request->input('sort'), self::SORTABLE, true) ? $request->input('sort') : 'name',
            'dir' => $request->input('dir') === 'desc' ? 'desc' : 'asc',
            'per_page' => in_array($request->integer('per_page'), self::PER_PAGE, true) ? $request->integer('per_page') : 20,
            'status' => $request->input('status') === 'archived' ? 'archived' : '',
        ];

        $rows = ($filters['status'] === 'archived' ? Pharmacy::onlyTrashed() : Pharmacy::query())
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$filters['q']}%")
                ->orWhere('address', 'like', "%{$filters['q']}%")->orWhere('phone', 'like', "%{$filters['q']}%")))
            ->orderBy($filters['sort'], $filters['dir'])->orderBy('id')
            ->paginate($filters['per_page'])->withQueryString()
            ->through(fn (Pharmacy $p) => [
                'id' => $p->id, 'name' => $p->name, 'address' => $p->address, 'phone' => $p->phone, 'note' => $p->note,
                'created_at' => $p->created_at?->format('d M Y'), 'archived' => $p->trashed(),
            ]);

        return Inertia::render('Settings/Pharmacies', [
            'rows' => $rows,
            'filters' => $filters,
            'stats' => fn () => ['active' => Pharmacy::count(), 'archived' => Pharmacy::onlyTrashed()->count()],
            'open' => $request->input('open') === 'create' ? 'create' : ($request->integer('open') ?: null),
        ]);
    }

    public function create()
    {
        return redirect()->route('pharmacies.index', ['open' => 'create']);
    }

    public function edit(int $pharmacy)
    {
        return redirect()->route('pharmacies.index', ['open' => $pharmacy]);
    }

    private function validated(Request $request, ?int $ignore = null): array
    {
        $businessId = (int) (session('impersonate_business_id') ?? session('business_id'));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191', Rule::unique('pharmacies', 'name')->where('business_id', $businessId)->whereNull('deleted_at')->ignore($ignore)],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:150'],
        ], ['name.unique' => 'You already have a pharmacy with this name.']);
        $data['name'] = trim($data['name']);

        return $data;
    }

    public function store(Request $request)
    {
        $p = Pharmacy::create($this->validated($request));

        return back()->with('success', "“{$p->name}” added.");
    }

    public function update(Request $request, Pharmacy $pharmacy)
    {
        $pharmacy->update($this->validated($request, $pharmacy->id));

        return back()->with('success', "“{$pharmacy->name}” saved.");
    }

    public function destroy(Pharmacy $pharmacy)
    {
        $pharmacy->delete();

        return back()->with('success', "“{$pharmacy->name}” archived. You can restore it from the Archived filter.");
    }

    public function restore(int $id)
    {
        $p = Pharmacy::onlyTrashed()->findOrFail($id);
        if (Pharmacy::where('name', $p->name)->exists()) {
            return back()->with('error', "An active “{$p->name}” already exists, so this one can’t be restored.");
        }
        $p->restore();

        return back()->with('success', "“{$p->name}” restored.");
    }
}
