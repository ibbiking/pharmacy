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

    public static function log($action, $model = null, $details = null)
    {
        return self::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model ? $model->id : null,
            'ip_address' => request()->ip(),
            'details' => $details,
        ]);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
