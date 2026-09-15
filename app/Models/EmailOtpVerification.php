<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailOtpVerification extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'otp_hash',
        'expires_at',
        'attempt_count',
        'send_count',
        'last_sent_at',
        'verified_at',
        'consumed_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'otp_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
