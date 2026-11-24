<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\Reservation;
use App\Services\BookingResumeLink;
use App\Support\BookingDraft;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The other end of the email: a link that puts a guest back on the confirm step.
 *
 * Every way this can go wrong is a sentence, not a stack trace, because the guest
 * arrived here from an inbox and has no idea what the state of the booking is:
 * the link expired, the stay has passed, the space is gone, or — the one worth
 * getting right — they finished the booking in another tab after the email was
 * sent, in which case they are shown what they made instead of being offered it
 * again.
 */
class BookingResumeController extends Controller
{
    public function __invoke(Request $request, string $token, BookingResumeLink $links): RedirectResponse
    {
        $user = $request->user();
        $payload = $links->resolve($token);

        if ($payload === null) {
            return redirect()
                ->route('customer.reservations.new')
                ->with('error', BookingResumeLink::EXPIRED_MESSAGE);
        }

        // A link is a draft for one account. Someone else's link is not an expired
        // link — it is a link that should not have been opened here.
        abort_if((int) ($payload['user_id'] ?? 0) !== (int) $user->id, 403);

        $userId = (int) $user->id;
        $facilityId = (int) ($payload['facility_id'] ?? 0);
        $draft = is_array($payload['draft'] ?? null) ? $payload['draft'] : [];
        $facility = Facility::query()->find($facilityId);

        $completed = $links->completedReservationFor($userId, $facilityId, $draft);

        if ($completed instanceof Reservation) {
            return redirect()
                ->route('customer.reservations.show', $completed)
                ->with('success', BookingResumeLink::COMPLETED_MESSAGE);
        }

        if (! $facility instanceof Facility || ! $facility->is_active || ! $facility->isOnlineBookable()) {
            return redirect()
                ->route('customer.reservations.new')
                ->with('error', BookingResumeLink::SPACE_GONE_MESSAGE);
        }

        if (! BookingDraft::isResumable($draft)) {
            return redirect()
                ->route('customer.reservations.new')
                ->with('error', BookingResumeLink::STALE_MESSAGE);
        }

        BookingDraft::restore($facility, $draft);

        return redirect()
            ->route('customer.booking.create', $facility)
            ->with('success', BookingResumeLink::RESTORED_MESSAGE);
    }
}
