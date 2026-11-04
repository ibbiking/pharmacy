<?php

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use BelongsToBusiness;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'changes' => 'array',
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subject()
    {
        return $this->morphTo();
    }
}
