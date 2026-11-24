<?php

namespace App\Jobs;

use App\Services\BookingResumeLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * The email that asks a guest to come back and finish a booking.
 *
 * This is a job rather than a queued notification for one reason: the decision
 * cannot be made when the guest leaves (a request-response app never sees them
 * go) and cannot be made when they reach the confirm step either (they may be
 * about to confirm, and an email telling someone mid-checkout that they left is
 * worse than no email at all). It can only be made *later* — so the work is
 * queued, and when the queue arrives it asks the draft whether it is still
 * unfinished. A delayed notification composes its content in the worker, but it
 * cannot decide not to arrive.
 *
 * Nothing here is trusted from the queue row beyond identifiers: the draft, the
 * account and the space are read again at send time.
 */
final class SendBookingResumeLink implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public readonly int $userId,
        public readonly int $facilityId,
        public readonly string $token,
    ) {}

    public function handle(BookingResumeLink $links): void
    {
        // Returns without sending when the guest confirmed, changed their dates,
        // started over, or asked for the link themselves in the meantime. A
        // mailer outage is logged and swallowed inside — the queue must not retry
        // its way into an inbox.
        $links->deliverPending($this->userId, $this->facilityId, $this->token);
    }
}
