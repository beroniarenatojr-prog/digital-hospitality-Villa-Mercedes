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
        'booking_workflow',
        'reservation_group_id',
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
        'policy_version',
        'base_price',
        'additional_charges',
        'hall_extension_charge',
        'total_price',
        'pricing_breakdown',
        'late_checkout_request',
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
            'booking_workflow' => 'array',
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
            'pricing_breakdown' => 'array',
            'late_checkout_request' => 'array',
            'guest_signed_at' => 'datetime',
            'policies_accepted_at' => 'datetime',
            'pax_tier' => 'integer',
            'early_check_in_hours' => 'integer',
            'late_check_out_hours' => 'integer',
            'base_price' => 'decimal:2',
            'additional_charges' => 'decimal:2',
            'hall_extension_charge' => 'decimal:2',
            'guest_sheet_control_number' => 'integer',
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
     * @return BelongsTo<ReservationGroup, $this>
     */
    public function reservationGroup(): BelongsTo
    {
        return $this->belongsTo(ReservationGroup::class, 'reservation_group_id');
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
        $scheduled = $this->check_in->copy()->subHours((int) $this->early_check_in_hours);
        $physical = $this->checked_in_at?->copy()->timezone(\App\Support\ResortTime::timezone())->shiftTimezone(config('app.timezone', 'UTC'));
        return $physical && $physical->lt($scheduled) ? $physical : $scheduled;
    }

    /**
     * The moment the facility is actually released, including late check-out.
     */
    public function occupancyEnd(): Carbon
    {
        return $this->check_out->copy()->addHours((int) $this->late_check_out_hours);
    }

    /** Itemization uses the saved quote, never today's rates for an old booking. */
    public function priceItems(): array
    {
        $items = ['Base price' => (int) round((float) $this->base_price * 100)];
        if ($this->pricing_breakdown !== null) {
            foreach (['early_check_in_charge' => 'Early check-in', 'late_check_out_charge' => 'Approved late check-out', 'extra_person_charge' => 'Extra guests', 'hall_extension_charge' => 'Hall extension'] as $key => $label) {
                if (($this->pricing_breakdown[$key] ?? 0) > 0) {
                    $items[$label] = (int) $this->pricing_breakdown[$key];
                }
            }
        } else {
            // Old rows did not store separate early/late amounts. Do not invent them.
            $approvedLate = (int) ($this->late_checkout_request['applied_fee_cents'] ?? 0);
            if ($approvedLate > 0) $items['Approved late check-out'] = $approvedLate;
            $historical = (int) round((float) $this->additional_charges * 100) - $approvedLate;
            if ($historical > 0) $items['Previously agreed additional charges (includes any early/late hours)'] = $historical;
        }

        foreach ($this->booking_workflow['adjustments'] ?? [] as $entry) {
            $label = match ($entry['kind']) { 'pet_fee' => 'Pets (one fee per booking)', 'child_charge' => 'Owner-approved child charge', 'arrival_discount' => 'Staff check in discount', 'early_arrival' => 'Actual early check in', default => 'Agreed adjustment' };
            $items[$label] = ($items[$label] ?? 0) + $entry['amount_cents'];
        }
        $difference = (int) round((float) $this->total_price * 100) - array_sum($items);
        if ($difference !== 0) $items['Staff quote / agreed price adjustment'] = $difference;

        return $items;
    }

    public function canRequestLateCheckout(): bool
    {
        return $this->facility?->isRoom()
            && $this->status === ReservationStatus::Approved
            && ! $this->checked_out_at && ! $this->cancellation_requested_at
            && $this->occupancyEnd()->gt(\App\Support\ResortTime::databaseNow())
            && (int) $this->late_check_out_hours < (int) config('resort.stay.max_additional_hours', 12)
            && ($this->late_checkout_request['status'] ?? null) !== 'pending';
    }

    public function frontDeskStatusLabel(): string
    {
        if ($this->status === ReservationStatus::Approved) {
            return $this->checked_in_at ? 'Checked in' : 'Awaiting check-in';
        }

        return $this->status->label();
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
     * Companion names from the Guest Information Sheet.
     *
     * @return list<string>
     */
    public function otherGuestNames(): array
    {
        return GuestInformation::normalizeOtherGuestNames($this->other_guest_names);
    }

    /**
     * Companions with optional valid ID types for desk verification.
     *
     * @return list<array{name: string, valid_id: ?string, valid_id_label: ?string}>
     */
    public function otherGuests(): array
    {
        return array_map(static function (array $guest): array {
            $idType = $guest['valid_id'] !== null
                ? GuestIdType::tryFrom($guest['valid_id'])
                : null;

            return [
                'name' => $guest['name'],
                'valid_id' => $idType?->value,
                'valid_id_label' => $idType?->label(),
            ];
        }, GuestInformation::normalizeOtherGuests($this->other_guest_names));
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

    /**
     * An arrival pass is a confirmed-and-paid document.
     *
     * Confirmation alone is not enough: the desk has to be able to trust the slip
     * it is shown, so the deposit staff asked for must be verified first. A record
     * with no deposit — a booking made before the deposit workflow, a walk-in, a
     * deployment with that workflow switched off — has nothing to verify, so any
     * verified payment is what earns the pass there.
     */
    public function canShowArrivalPass(): bool
    {
        if ($this->status !== ReservationStatus::Approved) {
            return false;
        }

        $group = $this->reservationGroup;
        if ($group && $group->directPayments()->whereNull('reservation_id')->exists()) {
            return $group->isApproved() && $group->verifiedPaymentsTotal() >= (float) $group->deposit_required;
        }

        return $this->deposit_required !== null
            ? $this->getVerifiedTotalPaid() >= $this->requiredDepositAmount()
            : $this->getVerifiedTotalPaid() > 0;
    }

    /**
     * Why this reservation has no arrival pass, in the guest's words.
     *
     * One answer for both places that have to give it — the Arrival tab and the
     * page behind a saved pass link — because a guest who hears one story on the
     * account and another from their own screenshot has been told nothing.
     *
     * @return array{title: string, body: string}
     */
    public function arrivalPassNotice(): array
    {
        return match (true) {
            $this->policy_version === config('resort.policy.confirmed_version') && $this->status === ReservationStatus::Pending => [
                'Not confirmed yet',
                'Staff must verify at least the 50% down payment and approve your request. Proof under review is not verified payment. You may pay the full balance online; any remaining balance is due at physical check-in.',
            ],
            $this->status === ReservationStatus::Approved => [
                'Payment not recorded yet',
                'This booking is confirmed, but the check in pass is issued once your payment is verified at the resort. Staff can already look it up with the code in your confirmation email.',
            ],
            $this->status === ReservationStatus::Pending && $this->deposit_required !== null => [
                'Not confirmed yet',
                'Staff are reviewing this request. We email you the moment it is approved — the 50% deposit QR and proof upload open then — and your reservation code and printable check in pass appear here once that deposit is verified.',
            ],
            $this->status === ReservationStatus::Pending => [
                'Waiting for the resort',
                'This request needs a staff quotation and approval. Your reservation code and printable check in pass appear here as soon as they confirm it.',
            ],
            $this->status === ReservationStatus::Expired => [
                'This hold expired',
                'Its deadline passed before the booking was settled, so the dates were released. If you have already transferred funds, contact the resort — do not send another payment.',
            ],
            $this->status === ReservationStatus::Completed => [
                'This stay is complete',
                'You have already checked out, so there is no check in pass to show.',
            ],
            default => [
                $this->status->label(),
                'This reservation has no active check in pass. Book again when you are ready, or call the resort if something looks wrong.',
            ],
        };
    }

    /**
     * Get the total amount of verified payments.
     */
    public function getVerifiedTotalPaid(): float
    {
        $lineTotal = $this->relationLoaded('payments')
            ? (float) $this->payments
                ->filter(fn (Payment $payment) => $payment->status === PaymentStatus::Verified)
                ->sum('amount')
            : (float) $this->payments()->where('status', PaymentStatus::Verified->value)->sum('amount');

        return round($lineTotal + $this->allocatedGroupPayment(PaymentStatus::Verified), 2);
    }

    /**
     * Get the remaining balance due.
     */
    public function getRemainingBalance(): float
    {
        $flow = $this->reservationGroup?->booking_workflow ?? $this->booking_workflow ?? [];
        if (in_array($flow['refund']['status'] ?? null, ['approved', 'returned'], true)) return 0.0;
        $balance = (float) $this->total_price - $this->getVerifiedTotalPaid();

        return max(0.0, round($balance, 2));
    }

    /**
     * Amounts submitted but not yet verified (receipts and unfinished checkouts).
     */
    public function getPendingVerificationTotal(): float
    {
        $lineTotal = $this->relationLoaded('payments')
            ? (float) $this->payments
                ->filter(fn (Payment $payment) => $payment->status === PaymentStatus::Pending)
                ->sum('amount')
            : (float) $this->payments()->where('status', PaymentStatus::Pending->value)->sum('amount');

        return round($lineTotal + $this->allocatedGroupPayment(PaymentStatus::Pending), 2);
    }

    private function allocatedGroupPayment(PaymentStatus $status): float
    {
        if (! $this->reservation_group_id) return 0.0;

        $group = $this->relationLoaded('reservationGroup') ? $this->reservationGroup : null;
        $sumKey = 'allocation_'.$status->value.'_total';
        $hasSnapshot = $group?->hasAttribute($sumKey) && $group->relationLoaded('reservations');
        $amount = $hasSnapshot
            ? $group->getAttribute($sumKey)
            : Payment::query()
                ->where('reservation_group_id', $this->reservation_group_id)
                ->whereNull('reservation_id')->where('status', $status->value)->sum('amount');
        $groupCents = (int) round((float) $amount * 100);
        if ($groupCents === 0) return 0.0;

        $lines = $hasSnapshot
            ? $group->reservations
            : static::query()->where('reservation_group_id', $this->reservation_group_id)
                ->orderBy('id')->get(['id', 'total_price']);
        $weightTotal = $lines->sum(fn (self $line) => (int) round((float) $line->total_price * 100));
        if ($weightTotal <= 0) return 0.0;

        $allocated = 0;
        foreach ($lines as $index => $line) {
            $share = $index === $lines->count() - 1
                ? $groupCents - $allocated
                : (int) floor($groupCents * ((int) round((float) $line->total_price * 100)) / $weightTotal);
            if ($line->id === $this->id) return $share / 100;
            $allocated += $share;
        }

        return 0.0;
    }

    public function scopeWithPaymentAllocation(Builder $query): void
    {
        $query->with(['payments', 'reservationGroup' => function ($groups): void {
            $groups->with(['reservations' => fn ($lines) => $lines
                ->select('id', 'reservation_group_id', 'total_price')->orderBy('id')]);

            foreach ([PaymentStatus::Verified, PaymentStatus::Pending] as $status) {
                $groups->withSum([
                    'directPayments as allocation_'.$status->value.'_total' => fn ($payments) => $payments
                        ->whereNull('reservation_id')->where('status', $status->value),
                ], 'amount');
            }
        }]);
    }

    /**
     * New-policy payments may cover the full balance; historical bookings
     * retain their approval-before-deposit and deposit-only contract. Grouped
     * checkout receives the first proof and requires verification before approval.
     */
    public function canAcceptGuestPayment(): bool
    {
        $discussion = $this->reservationGroup?->booking_workflow ?? $this->booking_workflow ?? [];
        if (isset($discussion['refund']) || ($discussion['child_charge']['status'] ?? null) === 'disputed') return false;
        if ($this->policy_version === config('resort.policy.confirmed_version')) {
            return in_array($this->status, [ReservationStatus::Pending, ReservationStatus::Approved], true)
                && ! $this->checked_in_at && ! $this->cancellation_requested_at
                && ($this->getVerifiedTotalPaid() >= $this->requiredDepositAmount() || ! $this->deposit_due_at || $this->deposit_due_at->isFuture())
                && $this->getAvailablePaymentAmount() > 0;
        }
        if (config('payments.deposit_workflow')) {
            return $this->status === ReservationStatus::Approved
                && ! $this->checked_in_at
                && ! $this->cancellation_requested_at
                && ($this->policy_version === config('resort.policy.confirmed_version') && $this->getVerifiedTotalPaid() >= $this->requiredDepositAmount()
                    || ! $this->deposit_due_at || $this->deposit_due_at->isFuture())
                && ($this->policy_version === config('resort.policy.confirmed_version') ? $this->getAvailablePaymentAmount() : $this->getDepositAvailableAmount()) > 0;
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
            if ($this->getRemainingBalance() <= 0) {
                return GuestPaymentState::FullyPaid;
            }
            if ($this->getPendingVerificationTotal() > 0) {
                return GuestPaymentState::ReceiptPending;
            }
            if ($this->canAcceptGuestPayment()) {
                return GuestPaymentState::PaymentAvailable;
            }
            if ($this->status === ReservationStatus::Approved) {
                return GuestPaymentState::OnSiteBalance;
            }

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
