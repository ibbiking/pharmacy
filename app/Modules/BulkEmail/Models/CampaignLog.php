<?php

namespace App\Modules\BulkEmail\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignLog extends Model
{
    protected $table = 'bec_campaign_logs';
    protected $fillable = ['campaign_id', 'contact_id', 'email', 'status', 'error', 'opened_at', 'clicked_at', 'tracking_id'];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}
