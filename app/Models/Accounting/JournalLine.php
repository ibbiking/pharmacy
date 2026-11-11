<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class JournalLine extends Model
{
    use \App\Traits\BelongsToBusiness;
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = ['journal_entry_id', 'account_id', 'debit', 'credit', 'description', 'contact_type', 'contact_id', 'date'];

    protected $casts = ['debit' => 'float', 'credit' => 'float', 'date' => 'date'];

    public function entry() { return $this->belongsTo(JournalEntry::class, 'journal_entry_id'); }
    public function account() { return $this->belongsTo(Account::class)->withTrashed(); }
}
