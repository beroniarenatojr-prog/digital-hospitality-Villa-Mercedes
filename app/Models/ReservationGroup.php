<?php

namespace App\Models;

use App\Enums\GuestGender;
use App\Enums\GuestIdType;
use App\Enums\ReservationGroupStatus;
use App\Enums\ReservationSource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReservationGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'booking_workflow',
        'group_code',
        'source',
        'status',
        'guest_name',
        'guest_email',
        'guest_phone',
        'guest_place_of_origin',
        'guest_nationality',
        'guest_company',
        'guest_valid_id',
        'guest_gender',
        'total_amount',
        'deposit_required',
        'deposit_due_at',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejection_reason',
        'cancelled_at',
        'cancellation_reason',
        'completed_at',
        'special_requests',
        'admin_notes',
        'policies_accepted_at',
        'policy_version',
        'requires_quote',
        'quote_requested_at',
        'quoted_at',
        'quoted_by',
        'refund_status',
        'refund_amount',
        'refund_notes',
        'refunded_at',
        'refunded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'booking_workflow' => 'array',
            'source' => ReservationSource::class,
            'status' => ReservationGroupStatus::class,
            'guest_gender' => GuestGender::class,
            'guest_valid_id' => GuestIdType::class,
            'total_amount' => 'decimal:2',
            'deposit_required' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'requires_quote' => 'boolean',
            'quote_requested_at' => 'datetime',
            'quoted_at' => 'datetime',
            'refunded_at' => 'datetime',
            'deposit_due_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
            'policies_accepted_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * The service lines within this reservation group.
     *
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'reservation_group_id');
    }

    /**
     * Group-level direct payments.
     *
     * @return HasMany<Payment, $this>
     */
    public function directPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'reservation_group_id');
    }

    /**
     * Payments made against all reservations in this group or directly to the group.
     *
     * @return HasMany<Payment, $this>
     */
    public function allPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'reservation_group_id');
    }

    /**
     * Payments made against all reservations in this group.
     *
     * @return HasManyThrough<Payment, Reservation, $this>
     */
    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Reservation::class, 'reservation_group_id', 'reservation_id');
    }

    public function generateGroupCode(): string
    {
        $prefix = 'GRP-' . date('Ymd') . '-';
        $random = strtoupper(\Illuminate\Support\Str::random(6));

        while (static::where('group_code', $prefix . $random)->exists()) {
            $random = strtoupper(\Illuminate\Support\Str::random(6));
        }

        return $prefix . $random;
    }

    public function isPending(): bool
    {
        return $this->status === ReservationGroupStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === ReservationGroupStatus::Approved;
    }

    public function isRejected(): bool
    {
        return $this->status === ReservationGroupStatus::Rejected;
    }

    public function isCancelled(): bool
    {
        return $this->status === ReservationGroupStatus::Cancelled;
    }

    public function isCompleted(): bool
    {
        return $this->status === ReservationGroupStatus::Completed;
    }

    public function verifiedPaymentsTotal(): float
    {
        $linePayments = (float) $this->payments()->where('payments.status', \App\Enums\PaymentStatus::Verified->value)->sum('payments.amount');
        $groupPayments = (float) $this->directPayments()->where('status', \App\Enums\PaymentStatus::Verified->value)->whereNull('reservation_id')->sum('amount');

        return $linePayments + $groupPayments;
    }

    public function pendingPaymentsTotal(): float
    {
        $linePayments = (float) $this->payments()->where('payments.status', \App\Enums\PaymentStatus::Pending->value)->sum('payments.amount');
        $groupPayments = (float) $this->directPayments()->where('status', \App\Enums\PaymentStatus::Pending->value)->whereNull('reservation_id')->sum('amount');

        return $linePayments + $groupPayments;
    }

    public function outstandingBalance(): float
    {
        if (in_array($this->booking_workflow['refund']['status'] ?? null, ['approved', 'returned'], true)) return 0.0;
        return max(0.0, (float) $this->total_amount - $this->verifiedPaymentsTotal());
    }

    public function earliestCheckIn(): ?Carbon
    {
        $min = $this->reservations()->min('check_in');
        return $min ? Carbon::parse($min) : null;
    }

    public function latestCheckOut(): ?Carbon
    {
        $max = $this->reservations()->max('check_out');
        return $max ? Carbon::parse($max) : null;
    }

    public function recalculateTotals(): void
    {
        $total = (float) $this->reservations()->sum('total_price');
        $this->total_amount = $total;
        if ($this->deposit_required === null) {
            $this->deposit_required = ceil((int) round($total * 100) / 2) / 100;
        }

        // Update overall group status if all lines are completed, cancelled, or checked in
        $statuses = $this->reservations()->pluck('status')->map(fn($s) => $s instanceof \App\Enums\ReservationStatus ? $s->value : $s)->all();
        if (! empty($statuses)) {
            if (collect($statuses)->every(fn($s) => $s === \App\Enums\ReservationStatus::Completed->value)) {
                $this->status = ReservationGroupStatus::Completed;
                $this->completed_at = now();
            } elseif (collect($statuses)->every(fn($s) => in_array($s, [\App\Enums\ReservationStatus::Cancelled->value, \App\Enums\ReservationStatus::Rejected->value], true))) {
                $this->status = ReservationGroupStatus::Cancelled;
                $this->cancelled_at = now();
            }
        }

        $this->save();
    }
}
