<?php

namespace App\Services\Customer;

use App\DTOs\UnfinishedBooking;
use App\Models\Facility;
use App\Models\User;
use App\Services\BookingResumeLink;
use App\Support\BookingDraft;
use Illuminate\Support\Collection;

/**
 * The bookings a guest started and did not finish.
 *
 * They live in two places, and a guest should not have to know that. The browser
 * that was used holds the freshest copy of each draft; the emailed link carries a
 * copy that outlives the session, and is the only record left once the browser is
 * gone (another device, an expired session, a closed laptop). This reads both,
 * prefers the browser's copy where they overlap, and answers one question: what can
 * this guest still finish?
 *
 * A draft that can no longer be finished is not listed — see `for()` for what
 * happens to it, which is deliberately not the same for every reason.
 */
final class UnfinishedBookings
{
    public function __construct(
        private readonly BookingResumeLink $links,
    ) {}

    /**
     * @return Collection<int, UnfinishedBooking>
     */
    public function for(User $user): Collection
    {
        $userId = (int) $user->id;
        /** @var array<int, string> $tokens */
        $tokens = [];
        $held = BookingDraft::forAccount($userId);
        $drafts = $held;

        // The armed links describe drafts whose browser may be long gone, so they
        // fill the gaps rather than override what this browser last saw.
        foreach ($this->links->armedFor($userId) as $facilityId => $link) {
            $drafts[$facilityId] ??= $link['draft'];
            $tokens[$facilityId] = $link['token'];
        }

        if ($drafts === []) {
            return collect();
        }

        $facilities = Facility::query()
            ->with('facilityType')
            ->whereIn('id', array_keys($drafts))
            ->get()
            ->keyBy('id');

        $unfinished = collect();

        foreach ($drafts as $facilityId => $draft) {
            $facility = $facilities->get($facilityId);

            if (! $facility instanceof Facility) {
                // The space itself is gone, so there is nothing to come back to —
                // ever. That is the one case where the work is thrown away.
                $this->discard($user, (int) $facilityId);

                continue;
            }

            if (! $this->canBeFinished($facility)) {
                // Not offered, and not thrown away either: a space is taken offline
                // and put back, and a guest's work should survive that.
                continue;
            }

            $unfinished->push(UnfinishedBooking::fromDraft(
                $facility,
                $draft,
                // Only when this browser is *not* holding the draft: if it is, the
                // wizard can read it back directly, and that copy is the fresher
                // one — the link is the way back for a browser that has none.
                array_key_exists($facilityId, $held) ? null : ($tokens[$facilityId] ?? null),
            ));
        }

        return $unfinished
            ->sortByDesc(fn (UnfinishedBooking $booking): string => $booking->savedAt?->toIso8601String() ?? '')
            ->values();
    }

    /**
     * Drop an unfinished booking: the browser's copy and the link to it.
     *
     * Called when the guest discards one, and when the space it is for no longer
     * exists. Both mean the same thing — there is nothing here to finish.
     */
    public function discard(User $user, int $facilityId): void
    {
        BookingDraft::forgetSession($facilityId);
        $this->links->clear((int) $user->id, $facilityId);
    }

    /**
     * Whether the reservation wizard can actually finish this booking.
     *
     * A cottage is booked into a swimming session, which the wizard never asks for
     * — a cottage guest finishes on the resort page instead — so a cottage draft is
     * not something this list can honestly offer a way back to.
     */
    private function canBeFinished(Facility $facility): bool
    {
        return $facility->isOnlineBookable() && ! $facility->isCottage();
    }
}
