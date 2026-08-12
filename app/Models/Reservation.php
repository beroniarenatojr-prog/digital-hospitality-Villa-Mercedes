<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    /** @use HasFactory<\Database\Factories\ReservationFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'facility_id',
        'reservation_code',
        'reservation_date',
        'check_in',
        'check_out',
        'guest_count',
        'special_requests',
        'base_price',
        'additional_charges',
        'total_price',
        'status',
        'reservation_source',
        'admin_notes',
        'approved_at',
        'rejected_at',
        'cancelled_at',
        'completed_at',
        'rejection_reason',
        'cancellation_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'base_price' => 'decimal:2',
            'additional_charges' => 'decimal:2',
            'total_price' => 'decimal:2',
            'status' => ReservationStatus::class,
            'reservation_source' => \App\Enums\ReservationSource::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
