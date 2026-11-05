<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SignupRequest extends Model
{
    use HasFactory;
    
    protected $fillable = ['name', 'email', 'business_type', 'token', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
