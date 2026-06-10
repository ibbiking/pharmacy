<?php

namespace App\Modules\BulkEmail\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $table = 'bec_contacts';
    protected $fillable = ['contact_list_id', 'email', 'data', 'status'];
    protected $casts = [
        'data' => 'array',
    ];

    public function contactList()
    {
        return $this->belongsTo(ContactList::class, 'contact_list_id');
    }
}
