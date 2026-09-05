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
        'guest_name',
        'guest_email',
        'guest_phone',
        'facility_id',
        'reservation_code',
        'reservation_date',
        'check_in',
        'check_out',
        'guest_count',
        'early_check_in_hours',
        'late_check_out_hours',
        'special_requests',
        'base_price',
        'additional_charges',
        'total_price',
        'status',
        'reservation_source',
        'admin_notes',
        'approved_at',
        'checked_in_at',
        'checked_out_at',
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
            'early_check_in_hours' => 'integer',
            'late_check_out_hours' => 'integer',
            'base_price' => 'decimal:2',
            'additional_charges' => 'decimal:2',
            'total_price' => 'decimal:2',
            'status' => ReservationStatus::class,
            'reservation_source' => \App\Enums\ReservationSource::class,
            'approved_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
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

    /**
     * Statuses that take a facility off the market.
     *
     * @return list<string>
     */
    public static function blockingStatuses(): array
    {
        return [
            ReservationStatus::Pending->value,
            ReservationStatus::Approved->value,
        ];
    }

    /**
     * The moment the facility actually becomes occupied. A guest who paid for
     * two hours of early check-in occupies the space two hours early.
     */
    public function occupancyStart(): \Carbon\Carbon
    {
        return $this->check_in->copy()->subHours((int) $this->early_check_in_hours);
    }

    /**
     * The moment the facility is actually released, including late check-out.
     */
    public function occupancyEnd(): \Carbon\Carbon
    {
        return $this->check_out->copy()->addHours((int) $this->late_check_out_hours);
    }

    public function isInHouse(): bool
    {
        return $this->checked_in_at !== null && $this->checked_out_at === null;
    }

    /**
     * The moment after which the guest can no longer cancel or reschedule
     * online, counted back from arrival.
     */
    public function selfServiceDeadline(int $cutoffHours): \Carbon\Carbon
    {
        return $this->check_in->copy()->subHours($cutoffHours);
    }

    /**
     * The guest this reservation is for. Walk-ins are encoded by staff, so the
     * account holder is not necessarily the guest.
     */
    public function guestName(): string
    {
        return $this->guest_name ?: ($this->user?->name ?? 'Unknown guest');
    }

    public function guestEmail(): ?string
    {
        return $this->guest_email ?: $this->user?->email;
    }

    public function guestPhone(): ?string
    {
        return $this->guest_phone ?: $this->user?->phone;
    }

    /**
     * Get the total amount of verified payments.
     */
    public function getVerifiedTotalPaid(): float
    {
        return (float) $this->payments()
            ->where('status', \App\Enums\PaymentStatus::Verified->value)
            ->sum('amount');
    }

    /**
     * Get the remaining balance due.
     */
    public function getRemainingBalance(): float
    {
        $balance = (float) $this->total_price - $this->getVerifiedTotalPaid();
        return max(0.0, $balance);
    }

    /**
     * Check if the reservation is fully paid.
     */
    public function isFullyPaid(): bool
    {
        return $this->getRemainingBalance() <= 0.0;
    }
}
