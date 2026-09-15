<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResortFee extends Model
{
    protected $fillable = ['name', 'amount', 'unit', 'description', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'is_active' => 'boolean'];
    }
}
