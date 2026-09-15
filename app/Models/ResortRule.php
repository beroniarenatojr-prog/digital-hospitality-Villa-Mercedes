<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResortRule extends Model
{
    protected $fillable = ['text', 'is_active', 'is_public', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_public' => 'boolean'];
    }
}
