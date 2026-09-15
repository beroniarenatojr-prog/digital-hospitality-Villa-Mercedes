<?php

namespace App\Models;

use App\Enums\RateType;
use App\Enums\RateUnit;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rate extends Model
{
    /** @use HasFactory<\Database\Factories\RateFactory> */
    use HasFactory;

    protected $fillable = [
        'facility_id',
        'name',
        'type',
        'amount',
        'unit',
        'max_occupancy',
        'effective_from',
        'effective_until',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RateType::class,
            'amount' => 'decimal:2',
            'max_occupancy' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected function unit(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): string => RateUnit::normalize($value),
            set: fn (string $value): string => RateUnit::from(RateUnit::normalize($value))->value,
        );
    }

    /** @return BelongsTo<Facility, $this> */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
