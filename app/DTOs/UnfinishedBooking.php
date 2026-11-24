<?php

namespace App\DTOs;

use App\Models\Facility;
use App\Support\BookingDraft;
use Carbon\Carbon;

/**
 * A booking a guest started and did not finish.
 *
 * Deliberately not a `Reservation`: nothing has been submitted, nothing is held
 * and no deposit is owed, so the guest portal must not dress it up as one. What
 * it carries is what the guest would recognise as their own work — the space, the
 * stay, the party, and how far they got.
 *
 * The step numbers are the wizard's own (`BookingDraft` records them), so the card
 * can tell the guest they were "ready to confirm" in the same words the wizard
 * used when they were standing on it.
 */
final readonly class UnfinishedBooking
{
    /** The step the wizard calls "Confirm" — the last one before a booking exists. */
    public const CONFIRM_STEP = 3;

    public function __construct(
        public Facility $facility,
        public Carbon $checkIn,
        public Carbon $checkOut,
        public int $guestCount,
        public int $step,
        public int $companionCount,
        public ?Carbon $savedAt,
        public ?string $linkToken = null,
    ) {}

    /**
     * @param  array<string, mixed>  $draft
     */
    public static function fromDraft(Facility $facility, array $draft, ?string $linkToken = null): self
    {
        $companions = is_array($draft['other_guest_names'] ?? null) ? $draft['other_guest_names'] : [];
        $savedAt = $draft['saved_at'] ?? null;

        return new self(
            facility: $facility,
            checkIn: Carbon::parse((string) $draft['check_in']),
            checkOut: Carbon::parse((string) $draft['check_out']),
            guestCount: (int) ($draft['guest_count'] ?? 1),
            step: max(1, min(self::CONFIRM_STEP, (int) ($draft['step'] ?? 1))),
            companionCount: count(array_filter($companions, static fn ($guest): bool => is_array($guest) && filled($guest['name'] ?? null))),
            savedAt: is_string($savedAt) && trim($savedAt) !== '' ? Carbon::parse($savedAt) : null,
            linkToken: filled($linkToken) ? $linkToken : null,
        );
    }

    /**
     * How far the guest got, in the wizard's own vocabulary.
     */
    public function progressLabel(): string
    {
        return match ($this->step) {
            self::CONFIRM_STEP => 'Ready to confirm',
            2 => 'Guest details to finish',
            default => 'Dates and guests to finish',
        };
    }

    public function isReadyToConfirm(): bool
    {
        return $this->step >= self::CONFIRM_STEP;
    }

    /**
     * Where the guest resumes it.
     *
     * Usually the wizard itself, which reads this browser's draft back and opens on
     * the step they left. When the draft only exists behind a resume link — the
     * browser that made it is gone — the link is the way in, because it is what
     * puts the draft back into this session.
     */
    public function resumeUrl(): string
    {
        return $this->linkToken !== null
            ? route('customer.booking.resume', ['token' => $this->linkToken])
            : route('customer.booking.create', $this->facility);
    }
}
