<?php

namespace App\Modules\BulkEmail\Models;

use Illuminate\Database\Eloquent\Model;

class ContactListColumn extends Model
{
    protected $table = 'bec_contact_list_columns';
    protected $fillable = ['contact_list_id', 'column_name', 'ui_label'];

    public function contactList()
    {
        return $this->belongsTo(ContactList::class, 'contact_list_id');
    }
}
