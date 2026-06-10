<?php

namespace App\Modules\BulkEmail\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $table = 'bec_email_templates';
    protected $fillable = ['name', 'subject', 'body', 'contact_list_id', 'attachments'];
    protected $casts = [
        'attachments' => 'array'
    ];

    public function contactList()
    {
        return $this->belongsTo(ContactList::class, 'contact_list_id');
    }
}
