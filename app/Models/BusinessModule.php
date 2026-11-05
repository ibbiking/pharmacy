<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A module switched on for a business (not business-scoped via session: always queried by business_id). */
class BusinessModule extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    use \App\Traits\Auditable;

    protected $guarded = [];

    protected $casts = ['activated_at' => 'datetime', 'expires_at' => 'datetime'];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
