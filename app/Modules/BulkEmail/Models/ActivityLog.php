<?php

namespace App\Modules\BulkEmail\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ActivityLog extends Model
{
    protected $table = 'bec_activity_logs';
    protected $fillable = ['user_id', 'action', 'model_type', 'model_id', 'details', 'ip_address'];
    protected $casts = [
        'details' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
