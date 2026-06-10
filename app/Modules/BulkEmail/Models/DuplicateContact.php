<?php

namespace App\Modules\BulkEmail\Models;

use Illuminate\Database\Eloquent\Model;

class DuplicateContact extends Model
{
    protected $table = 'bec_duplicate_contacts';
    protected $fillable = ['contact_list_id', 'email', 'row_data'];
    protected $casts = ['row_data' => 'array'];

    public function contactList()
    {
        return $this->belongsTo(ContactList::class, 'contact_list_id');
    }
}
