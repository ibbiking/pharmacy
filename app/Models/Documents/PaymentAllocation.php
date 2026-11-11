<?php

namespace App\Models\Documents;

use Illuminate\Database\Eloquent\Model;

/** Part of a payment, or of a credit note / vendor credit, that settled a document (append-only). */
class PaymentAllocation extends Model
{
    use \App\Traits\BelongsToBusiness;

    protected $fillable = ['payment_id', 'credit_document_id', 'document_id', 'amount'];

    protected $casts = ['amount' => 'float'];

    public function document() { return $this->belongsTo(Document::class)->withTrashed(); }
    public function payment() { return $this->belongsTo(Payment::class)->withTrashed(); }
}
