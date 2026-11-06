<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Roadmap 13d: one "Export my data" request of a business. */
class BusinessExport extends Model
{
    use SoftDeletes;
    use \App\Traits\BelongsToBusiness;

    protected $fillable = ['business_id', 'requested_by', 'status', 'file_path', 'size', 'tables', 'rows', 'error', 'finished_at', 'expires_at'];

    protected $casts = ['finished_at' => 'datetime', 'expires_at' => 'datetime'];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function downloadable(): bool
    {
        return $this->status === 'done' && $this->file_path && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
