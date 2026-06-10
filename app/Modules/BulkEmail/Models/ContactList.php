<?php

namespace App\Modules\BulkEmail\Models;

use Illuminate\Database\Eloquent\Model;

class ContactList extends Model
{
    protected $table = 'bec_contact_lists';
    protected $fillable = ['name', 'file_path', 'status', 'total_rows', 'processed_rows', 'failed_rows', 'duplicate_rows', 'error_message'];

    public function columns()
    {
        return $this->hasMany(ContactListColumn::class, 'contact_list_id');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class, 'contact_list_id');
    }

    public function duplicates()
    {
        return $this->hasMany(DuplicateContact::class, 'contact_list_id');
    }
}
