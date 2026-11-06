<?php

namespace App\Http\Controllers\Admin\Catalogue;

use App\Http\Controllers\Controller;
use App\Models\GenericProduct;
use App\Services\Catalogue\GenericEntryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Generic catalogue entries (Roadmap 5.6b): suggest / add, edit (details + packaging together) and review.
 * Every permission rule lives in GenericEntryService; this controller only shapes requests and responses.
 */
class GenericEntryController extends Controller
{
    public function __construct(private readonly GenericEntryService $entries)
    {
    }

    public function create(Request $request)
    {
        return Inertia::render('Generic/Edit', ['entry' => null, 'admin' => $this->entries->isAdmin($request->user())]);
    }

    public function store(Request $request)
    {
        $entry = $this->entries->save(null, $request->all(), $request->user());

        return redirect()->route('generic_products.index', $entry->status === 'pending' ? ['status' => 'pending'] : [])->with('success', $entry->status === 'approved'
            ? "“{$entry->product_name}” added to the catalogue."
            : "Thanks: “{$entry->product_name}” was sent for review. You can still change it until it is approved.");
    }

    public function edit(Request $request, $id)
    {
        $entry = GenericProduct::query()->with(['genericCompany', 'genericType'])->findOrFail($id);
        abort_unless($this->entries->canEdit($entry, $request->user()), 403, 'Approved catalogue entries can only be changed by a platform admin.');

        return Inertia::render('Generic/Edit', ['entry' => $this->entries->present($entry), 'admin' => $this->entries->isAdmin($request->user())]);
    }

    public function update(Request $request, $id)
    {
        $entry = $this->entries->save(GenericProduct::query()->findOrFail($id), $request->all(), $request->user());

        return redirect()->route('generic_products.index', $entry->status === 'pending' ? ['status' => 'pending'] : [])->with('success', "“{$entry->product_name}” saved.");
    }

    /** Approve or reject one or many entries (POST; GET links could be forged, G-8). */
    public function review(Request $request)
    {
        $data = $request->validate(['action' => ['required', 'in:approve,reject'], 'ids' => ['required', 'array', 'min:1', 'max:500'], 'ids.*' => ['integer']]);
        $count = $data['action'] === 'approve'
            ? $this->entries->approve($data['ids'], $request->user())
            : $this->entries->reject($data['ids'], $request->user());

        return back()->with($count ? 'success' : 'error', $count
            ? ($data['action'] === 'approve' ? "{$count} entr".($count === 1 ? 'y' : 'ies').' approved: businesses can import them now.' : "{$count} suggestion(s) rejected.")
            : 'Nothing changed: those entries were already reviewed.');
    }

    /** Old suggestions screen: the catalogue page's "Waiting for review" filter. */
    public function suggestions()
    {
        return redirect()->route('generic_products.index', ['status' => 'pending']);
    }
}
