<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    use \App\Traits\BelongsToBusiness;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = ['number', 'date', 'memo', 'source_type', 'source_id', 'source_event', 'is_manual', 'amount', 'created_by'];

    protected $casts = ['date' => 'date', 'is_manual' => 'boolean', 'amount' => 'float'];

    public function lines() { return $this->hasMany(JournalLine::class); }
    public function creator() { return $this->belongsTo(\App\Models\User::class, 'created_by'); }
}
