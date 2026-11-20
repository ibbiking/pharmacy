<?php

namespace App\Models\Fbr;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Model;

/**
 * One invoice, return or test sent to FBR. Unique per business + kind + our reference, so sending again never creates
 * a second fiscal record (idempotent). Request and response are stored with secrets stripped.
 */
class FbrSubmission extends Model
{
    use \App\Traits\BelongsToBusiness;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    public const STATUSES = ['pending' => 'Waiting', 'sent' => 'Sent', 'failed' => 'Rejected', 'manual_review' => 'Needs attention'];

    protected $fillable = ['business_id', 'kind', 'invoice_id', 'reference', 'original_reference', 'environment', 'terminal_id', 'status', 'attempts',
        'http_status', 'request', 'response', 'fbr_number', 'qr_payload', 'error', 'sent_at'];

    protected $casts = ['request' => 'array', 'response' => 'array', 'sent_at' => 'datetime', 'attempts' => 'integer', 'http_status' => 'integer'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class)->withoutGlobalScopes();
    }
}
