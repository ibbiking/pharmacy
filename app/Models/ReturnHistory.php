<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnHistory extends Model
{
    use \App\Traits\BelongsToBusiness;

    use HasFactory;

    protected $fillable = ['return_no', 'invoice_id', 'action', 'description', 'user_id'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
