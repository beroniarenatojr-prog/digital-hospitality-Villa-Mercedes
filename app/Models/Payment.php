<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Carbon\Carbon;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reservation_id',
        'reservation_group_id',
        'amount',
        'method',
        'reference_number',
        'payment_date',
        'proof_path',
        'status',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'payment_destination_id', 'destination_snapshot', 'source', 'collected_at', 'reconciliation_note', 'proof_hash',
    ];

    public function paymentDestination(): BelongsTo
    {
        return $this->belongsTo(PaymentDestination::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'destination_snapshot' => 'array',
            'collected_at' => 'datetime',
            'method' => PaymentMethod::class,
            'payment_date' => 'date',
            'status' => PaymentStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * @return BelongsTo<ReservationGroup, $this>
     */
    public function reservationGroup(): BelongsTo
    {
        return $this->belongsTo(ReservationGroup::class, 'reservation_group_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Guest proofs staff have not settled: the Payment review queue, and what
     * its sidebar badge counts. Desk collections are recorded already verified,
     * so they never join this queue.
     *
     * @param  Builder<Payment>  $query
     */
    public function scopeAwaitingReview(Builder $query): void
    {
        $query->where('source', 'customer_deposit')->where('status', PaymentStatus::Pending->value);
    }

    /**
     * The wait the review screen calls overdue: longer than the review window
     * guests are promised (`payments.review_hours`).
     *
     * @param  Builder<Payment>  $query
     */
    public function scopeOverdueForReview(Builder $query): void
    {
        $query->where('created_at', '<=', Carbon::now()->subHours((int) config('payments.review_hours')));
    }

    /**
     * The same rule for one proof, which the review panel states in words.
     */
    public function isOverdueForReview(): bool
    {
        return $this->source === 'customer_deposit'
            && $this->status === PaymentStatus::Pending
            && $this->created_at->lte(Carbon::now()->subHours((int) config('payments.review_hours')));
    }

    public function proofExtension(): ?string
    {
        if (! is_string($this->proof_path) || $this->proof_path === '') {
            return null;
        }

        $extension = strtolower((string) pathinfo($this->proof_path, PATHINFO_EXTENSION));

        return $extension !== '' ? $extension : null;
    }

    public function isImageProof(): bool
    {
        return in_array($this->proofExtension(), ['jpg', 'jpeg', 'png'], true);
    }

    public function isPdfProof(): bool
    {
        return $this->proofExtension() === 'pdf';
    }

    public function canPreviewProof(): bool
    {
        return $this->isImageProof() || $this->isPdfProof();
    }
}
