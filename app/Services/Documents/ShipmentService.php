<?php

namespace App\Services\Documents;

use App\Models\Documents\Document;
use App\Models\Documents\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Shipping for Wholesale & Distribution: packages / shipments of a confirmed sales order or an issued sales invoice,
 * moving packed → shipped → delivered. Stock is not touched here (the invoice takes it).
 */
class ShipmentService
{
    public function create(Document $doc, array $input, User $user): Shipment
    {
        if (! in_array($doc->type, ['sales_order', 'sales_invoice'], true) || $doc->origin !== null) {
            throw ValidationException::withMessages(['shipment' => 'Only sales orders and sales invoices are shipped.']);
        }
        if (in_array($doc->status, ['draft', 'void', 'cancelled'], true)) {
            throw ValidationException::withMessages(['shipment' => 'Confirm the order (or issue the invoice) before shipping it.']);
        }
        $data = validator($input, [
            'carrier' => ['nullable', 'string', 'max:100'], 'tracking_no' => ['nullable', 'string', 'max:100'],
            'packages' => ['nullable', 'integer', 'min:1', 'max:100000'], 'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'notes' => ['nullable', 'string', 'max:500'], 'shipped' => ['nullable', 'boolean'],
        ])->validate();

        return DB::transaction(function () use ($doc, $data, $user) {
            $shipped = (bool) ($data['shipped'] ?? false);
            $s = Shipment::create([
                'document_id' => $doc->id, 'number' => 'PENDING', 'carrier' => $data['carrier'] ?? null, 'tracking_no' => $data['tracking_no'] ?? null,
                'packages' => (int) ($data['packages'] ?? 1), 'weight_kg' => $data['weight_kg'] ?? null, 'notes' => $data['notes'] ?? null,
                'status' => $shipped ? 'shipped' : 'packed', 'shipped_at' => $shipped ? now() : null, 'created_by' => $user->id,
            ]);
            $s->update(['number' => 'SHP-'.str_pad((string) $s->id, 6, '0', STR_PAD_LEFT)]);

            return $s;
        });
    }

    /** packed → shipped → delivered (one step at a time, never back). */
    public function advance(Shipment $s, string $to): Shipment
    {
        $next = ['packed' => 'shipped', 'shipped' => 'delivered'][$s->status] ?? null;
        if ($next !== $to) {
            throw ValidationException::withMessages(['shipment' => "A {$s->status} shipment can’t be marked {$to}."]);
        }
        $s->update(['status' => $to, $to === 'shipped' ? 'shipped_at' : 'delivered_at' => now()]);

        return $s;
    }

    public function delete(Shipment $s): void
    {
        if ($s->status === 'delivered') {
            throw ValidationException::withMessages(['shipment' => 'A delivered shipment is kept as a record.']);
        }
        $s->delete();
    }
}
