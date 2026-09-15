<?php

namespace App\Models;

use App\Enums\GuestGender;
use App\Enums\GuestIdType;
use App\Enums\GuestPaymentState;
use App\Enums\PaymentStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Support\GuestInformation;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'guest_place_of_origin',
        'guest_nationality',
        'guest_company',
        'guest_valid_id',
        'guest_gender',
        'facility_id',
        'swimming_session_id',
        'reservation_code',
        'reservation_date',
        'check_in',
        'check_out',
        'guest_count',
        'pax_tier',
        'other_guest_names',
        'guest_signature',
        'guest_signed_at',
        'early_check_in_hours',
        'late_check_out_hours',
        'special_requests',
        'policies_accepted_at',
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
        'cancellation_requested_at',
        'cancellation_request_reason',
        'deposit_required', 'deposit_due_at', 'expired_at', 'review_invited_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'deposit_required' => 'decimal:2',
            'deposit_due_at' => 'datetime',
            'expired_at' => 'datetime',
            'review_invited_at' => 'datetime',
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'guest_gender' => GuestGender::class,
            'guest_valid_id' => GuestIdType::class,
            'other_guest_names' => 'array',
            'guest_signed_at' => 'datetime',
            'policies_accepted_at' => 'datetime',
            'pax_tier' => 'integer',
            'early_check_in_hours' => 'integer',
            'late_check_out_hours' => 'integer',
            'base_price' => 'decimal:2',
            'additional_charges' => 'decimal:2',
            'total_price' => 'decimal:2',
            'status' => ReservationStatus::class,
            'reservation_source' => ReservationSource::class,
            'approved_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancellation_requested_at' => 'datetime',
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
        return $this->belongsTo(Facility::class)->withTrashed();
    }

    public function swimmingSession(): BelongsTo
    {
        return $this->belongsTo(SwimmingSession::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return MorphMany<ActivityLog, $this>
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject')->latest();
    }

    /**
     * Front-desk lookup: reservation code (dashes and spaces optional), plus
     * walk-in or account name, email, and phone.
     *
     * @param  Builder<Reservation>  $query
     */
    public function scopeMatchingGuestLookup(Builder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        $like = '%'.$term.'%';
        $normalizedCode = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $term));
        $normalizedPhone = (string) preg_replace('/\D+/', '', $term);

        $query->where(function (Builder $q) use ($like, $normalizedCode, $normalizedPhone) {
            $q->where('reservation_code', 'like', $like)
                ->orWhere('guest_name', 'like', $like)
                ->orWhere('guest_email', 'like', $like)
                ->orWhere('guest_phone', 'like', $like)
                ->orWhereHas('user', function (Builder $uq) use ($like) {
                    $uq->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                });

            if ($normalizedCode !== '') {
                $q->orWhereRaw("REPLACE(UPPER(reservation_code), '-', '') LIKE ?", ['%'.$normalizedCode.'%']);
            }

            if (strlen($normalizedPhone) >= 4) {
                $phoneSql = "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(%s, ''), ' ', ''), '-', ''), '+', ''), '.', '')";

                $q->orWhereRaw(sprintf($phoneSql, 'guest_phone').' LIKE ?', ['%'.$normalizedPhone.'%'])
                    ->orWhereHas('user', function (Builder $uq) use ($phoneSql, $normalizedPhone) {
                        $uq->whereRaw(sprintf($phoneSql, 'phone').' LIKE ?', ['%'.$normalizedPhone.'%']);
                    });
            }
        });
    }

    /**
     * Guests expected on a calendar day who have not been checked in yet.
     *
     * @param  Builder<Reservation>  $query
     */
    public function scopeArrivingOn(Builder $query, CarbonInterface $day): void
    {
        $query->whereIn('status', [
            ReservationStatus::Pending->value,
            ReservationStatus::Approved->value,
        ])
            ->whereNull('checked_in_at')
            ->whereDate('check_in', $day->toDateString())
            ->orderBy('check_in');
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
     * @return list<string>
     */
    public static function customerTabKeys(): array
    {
        return ['upcoming', 'pending', 'current', 'completed', 'cancelled'];
    }

    /**
     * @param  Builder<Reservation>  $query
     */
    public function scopeForCustomerTab(Builder $query, string $tab): void
    {
        $now = now();

        match ($tab) {
            'upcoming' => $query->where('status', ReservationStatus::Approved->value)
                ->where('check_in', '>', $now),
            'pending' => $query->where('status', ReservationStatus::Pending->value),
            'current' => $query->where('status', ReservationStatus::Approved->value)
                ->where('check_in', '<=', $now)
                ->where('check_out', '>', $now),
            'completed' => $query->where(function (Builder $inner) use ($now) {
                $inner->where('status', ReservationStatus::Completed->value)
                    ->orWhere(function (Builder $approvedPast) use ($now) {
                        $approvedPast->where('status', ReservationStatus::Approved->value)
                            ->where('check_out', '<=', $now);
                    });
            }),
            'cancelled' => $query->whereIn('status', [
                ReservationStatus::Expired->value,
                ReservationStatus::Cancelled->value,
                ReservationStatus::Rejected->value,
                ReservationStatus::Rescheduled->value,
            ]),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * The moment the facility actually becomes occupied. A guest who paid for
     * two hours of early check-in occupies the space two hours early.
     */
    public function occupancyStart(): Carbon
    {
        return $this->check_in->copy()->subHours((int) $this->early_check_in_hours);
    }

    /**
     * The moment the facility is actually released, including late check-out.
     */
    public function occupancyEnd(): Carbon
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
    public function selfServiceDeadline(int $cutoffHours): Carbon
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
     * Companion names from the Guest Information Sheet (up to five).
     *
     * @return list<string>
     */
    public function otherGuestNames(): array
    {
        return GuestInformation::normalizeOtherGuestNames($this->other_guest_names);
    }

    /**
     * How long a saved arrival-pass link stays valid after the stay ends.
     */
    public function arrivalPassExpiresAt(): Carbon
    {
        $expiresAt = $this->check_out->copy()->addDays(14);

        return $expiresAt->isPast() ? now()->addDays(7) : $expiresAt;
    }

    /**
     * Signed, no-login URL the guest can screenshot or open from email at the desk.
     */
    public function arrivalPassUrl(): string
    {
        return URL::temporarySignedRoute(
            'reservations.arrival-pass',
            $this->arrivalPassExpiresAt(),
            ['reservation' => $this->reservation_code]
        );
    }

    public function canShowArrivalPass(): bool
    {
        if ($this->deposit_required !== null && $this->status === ReservationStatus::Pending) return false;
        return in_array($this->status, [
            ReservationStatus::Pending,
            ReservationStatus::Approved,
        ], true);
    }

    /**
     * Get the total amount of verified payments.
     */
    public function getVerifiedTotalPaid(): float
    {
        if ($this->relationLoaded('payments')) {
            return (float) $this->payments
                ->filter(fn (Payment $payment) => $payment->status === PaymentStatus::Verified)
                ->sum('amount');
        }

        return (float) $this->payments()
            ->where('status', PaymentStatus::Verified->value)
            ->sum('amount');
    }

    /**
     * Get the remaining balance due.
     */
    public function getRemainingBalance(): float
    {
        $balance = (float) $this->total_price - $this->getVerifiedTotalPaid();

        return max(0.0, round($balance, 2));
    }

    /**
     * Amounts submitted but not yet verified (receipts and unfinished checkouts).
     */
    public function getPendingVerificationTotal(): float
    {
        if ($this->relationLoaded('payments')) {
            return (float) $this->payments
                ->filter(fn (Payment $payment) => $payment->status === PaymentStatus::Pending)
                ->sum('amount');
        }

        return (float) $this->payments()
            ->where('status', PaymentStatus::Pending->value)
            ->sum('amount');
    }

    /**
     * Customer payments are limited to a deposit; historical mode retains its original rules.
     */
    public function canAcceptGuestPayment(): bool
    {
        if (config('payments.deposit_workflow')) {
            return in_array($this->status, [ReservationStatus::Pending, ReservationStatus::Approved], true)
                && ($this->deposit_required !== null || $this->status === ReservationStatus::Approved)
                && ! $this->checked_in_at
                && ! $this->cancellation_requested_at
                && (! $this->deposit_due_at || $this->deposit_due_at->isFuture())
                && $this->getDepositAvailableAmount() > 0;
        }
        return $this->status === ReservationStatus::Approved
            && $this->getAvailablePaymentAmount() > 0;
    }

    public function getAvailablePaymentAmount(): float
    {
        return max(0.0, round($this->getRemainingBalance() - $this->getPendingVerificationTotal(), 2));
    }

    public function requiredDepositAmount(): float
    {
        return $this->deposit_required !== null ? (float) $this->deposit_required
            : ceil((int) round((float) $this->total_price * 100) / 2) / 100;
    }

    public function getDepositAvailableAmount(): float
    {
        return max(0, round($this->requiredDepositAmount() - $this->getVerifiedTotalPaid() - $this->getPendingVerificationTotal(), 2));
    }

    public function canReview(): bool
    {
        return $this->status === ReservationStatus::Completed && $this->checked_out_at !== null
            && $this->checked_out_at->lte(now()) && ! $this->user?->hasRole('Administrator');
    }

    public function getOverpaymentAmount(): float
    {
        return max(0.0, round($this->getVerifiedTotalPaid() - (float) $this->total_price, 2));
    }

    public function guestPaymentState(): GuestPaymentState
    {
        if (config('payments.deposit_workflow')) {
            if (in_array($this->status, [ReservationStatus::Cancelled, ReservationStatus::Rejected, ReservationStatus::Expired, ReservationStatus::Rescheduled, ReservationStatus::Completed], true)) {
                return GuestPaymentState::Closed;
            }
            if ($this->getRemainingBalance() <= 0) return GuestPaymentState::FullyPaid;
            if ($this->getPendingVerificationTotal() > 0) return GuestPaymentState::ReceiptPending;
            if ($this->canAcceptGuestPayment()) return GuestPaymentState::PaymentAvailable;
            if ($this->status === ReservationStatus::Approved) return GuestPaymentState::OnSiteBalance;
            return GuestPaymentState::AwaitingApproval;
        }
        if ($this->status === ReservationStatus::Pending) {
            return GuestPaymentState::AwaitingApproval;
        }

        if (in_array($this->status, [
            ReservationStatus::Cancelled,
            ReservationStatus::Rejected,
            ReservationStatus::Completed,
            ReservationStatus::Rescheduled,
        ], true)) {
            return GuestPaymentState::Closed;
        }

        if ($this->getRemainingBalance() <= 0.0) {
            return GuestPaymentState::FullyPaid;
        }

        if ($this->getPendingVerificationTotal() > 0) {
            return GuestPaymentState::ReceiptPending;
        }

        return GuestPaymentState::PaymentAvailable;
    }

    /**
     * Label for the guest's declared ID type, or null when none is on file.
     */
    public function maskedGuestId(): ?string
    {
        return $this->guest_valid_id?->label();
    }

    /**
     * Check if the reservation is fully paid.
     */
    public function isFullyPaid(): bool
    {
        return $this->getRemainingBalance() <= 0.0;
    }
}
