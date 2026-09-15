<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;

class PaymentDestination extends Model
{
    protected $fillable = ['method', 'account_name', 'account_number', 'bank', 'qr_path', 'is_active', 'updated_by'];

    protected function casts(): array
    {
        return ['method' => PaymentMethod::class, 'is_active' => 'boolean'];
    }

    public function snapshot(): array
    {
        return $this->only(['account_name', 'account_number', 'bank', 'qr_path']);
    }
}
