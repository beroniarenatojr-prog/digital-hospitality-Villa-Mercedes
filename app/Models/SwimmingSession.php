<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SwimmingSession extends Model
{
    protected $fillable = ['name', 'slug', 'starts_at', 'ends_at', 'adult_entrance_fee', 'child_entrance_fee', 'child_qualifier', 'children_allowed', 'cottage_fee', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'adult_entrance_fee' => 'decimal:2',
            'child_entrance_fee' => 'decimal:2',
            'cottage_fee' => 'decimal:2',
            'children_allowed' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public function periodFor(string $date): array
    {
        return [
            Carbon::parse($date.' '.$this->starts_at),
            Carbon::parse($date.' '.$this->ends_at),
        ];
    }
}
