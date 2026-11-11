<?php

namespace App\Models\Documents;

use Illuminate\Database\Eloquent\Model;

/** Batches a document line moved (append-only; archived with its document). */
class DocumentLineLot extends Model
{
    use \App\Traits\BelongsToBusiness;

    protected $fillable = ['document_line_id', 'lot_id', 'purchase_id', 'base_qty', 'returned_base_qty', 'base_unit_cost'];

    protected $casts = ['base_qty' => 'float', 'returned_base_qty' => 'float', 'base_unit_cost' => 'float'];
}
