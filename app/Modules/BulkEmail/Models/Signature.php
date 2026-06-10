<?php

namespace App\Modules\BulkEmail\Models;

use Illuminate\Database\Eloquent\Model;

class Signature extends Model
{
    protected $table = 'bec_signatures';
    protected $fillable = ['name', 'designation', 'company', 'phone', 'website', 'email', 'address', 'logo', 'social_links', 'is_default'];
    protected $casts = [
        'social_links' => 'array',
        'is_default' => 'boolean',
    ];
}
