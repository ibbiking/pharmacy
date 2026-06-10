<?php

namespace App\Modules\BulkEmail\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $table = 'bec_campaigns';
    protected $fillable = ['name', 'from_name', 'contact_list_id', 'template_id', 'signature_id', 'subject', 'scheduled_at', 'status', 'attachments'];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'attachments' => 'array'
    ];

    public function contactList()
    {
        return $this->belongsTo(ContactList::class, 'contact_list_id');
    }

    public function template()
    {
        return $this->belongsTo(EmailTemplate::class, 'template_id');
    }

    public function signature()
    {
        return $this->belongsTo(Signature::class, 'signature_id');
    }

    public function logs()
    {
        return $this->hasMany(CampaignLog::class, 'campaign_id');
    }
}
