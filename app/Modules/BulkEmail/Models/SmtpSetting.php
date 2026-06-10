<?php

namespace App\Modules\BulkEmail\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SmtpSetting extends Model
{
    protected $table = 'bec_smtp_settings';
    protected $fillable = ['host', 'port', 'username', 'password', 'encryption', 'from_email', 'from_name', 'is_active'];

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Crypt::encryptString($value);
    }

    public function getPasswordAttribute($value)
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return $value;
        }
    }
}
