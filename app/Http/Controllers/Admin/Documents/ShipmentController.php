<?php

namespace App\Http\Controllers\Admin\Documents;

use App\Http\Controllers\Controller;
use App\Models\Documents\Document;
use App\Models\Documents\Shipment;
use App\Services\Documents\ShipmentService;
use Illuminate\Http\Request;

/** Shipping of sales orders / invoices (Wholesale & Distribution, B2B Sales module): add, advance, delete, delivery note. */
class ShipmentController extends Controller
{
    public function __construct(private readonly ShipmentService $shipments)
    {
    }

    public function store(Request $request, int $id)
    {
        abort_unless($request->user()->can('b2b_sales.manage'), 403);
        $doc = Document::query()->findOrFail($id);
        $s = $this->shipments->create($doc, $request->all(), $request->user());
        session()->flash('drawer_follow', true);

        return redirect()->route('selling.docs.show', $doc->id)->with('success', "Shipment {$s->number} added.");
    }

    public function advance(Request $request, int $id, string $status)
    {
        abort_unless($request->user()->can('b2b_sales.manage'), 403);
        $s = $this->shipments->advance(Shipment::query()->findOrFail($id), $status);
        session()->flash('drawer_follow', true);

        return redirect()->route('selling.docs.show', $s->document_id)->with('success', "Shipment {$s->number} marked ".strtolower(Shipment::STATUSES[$s->status]).'.');
    }

    public function destroy(Request $request, int $id)
    {
        abort_unless($request->user()->can('b2b_sales.manage'), 403);
        $s = Shipment::query()->findOrFail($id);
        $this->shipments->delete($s);
        session()->flash('drawer_follow', true);

        return redirect()->route('selling.docs.show', $s->document_id)->with('success', "Shipment {$s->number} deleted.");
    }

    /** Printable delivery note: what goes in the parcel, to whom, with the carrier and tracking number. */
    public function deliveryNote(Request $request, int $id)
    {
        $s = Shipment::query()->with(['document.lines.product:id,product_name', 'document.lines.unit:id,name', 'document.customer'])->findOrFail($id);

        return view('documents.delivery-note', [
            'shipment' => $s, 'doc' => $s->document,
            'business' => \App\Models\Business::find(session('impersonate_business_id') ?? session('business_id')),
        ]);
    }
}
