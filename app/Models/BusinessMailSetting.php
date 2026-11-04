<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A business's own email account. Passwords/secrets are encrypted at rest and never serialised. */
class BusinessMailSetting extends Model
{
    use \App\Traits\Auditable;

    protected $guarded = [];

    protected $hidden = ['smtp_password', 'mailgun_secret'];

    protected $casts = [
        'smtp_password' => 'encrypted',
        'mailgun_secret' => 'encrypted',
        'last_tested_at' => 'datetime',
        'last_test_ok' => 'boolean',
        'smtp_port' => 'integer',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function usesOwnAccount(): bool
    {
        return in_array($this->driver, ['smtp', 'mailgun'], true);
    }
}
