<?php

namespace App\Services;

use App\Models\Reservation;
use App\Notifications\Reservation\ReviewInvitation;
use App\Notifications\Reservation\ReviewInvitationMail;
use App\Support\LayoutBadges;
use App\Support\OutboundMail;
use Illuminate\Support\Facades\DB;

class ReviewInvitationService
{
    public function invite(Reservation $reservation): void
    {
        DB::transaction(function () use ($reservation) {
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if (! $locked->canReview() || $locked->review_invited_at || ! $locked->user) {
                return;
            }
            $locked->user->notify(new ReviewInvitation($locked, 'database'));
            $locked->update(['review_invited_at' => now()]);
            LayoutBadges::forgetUnread($locked->user_id);
            // The in-app invitation above is the durable record, so a broken
            // mailer must not undo the check-out that triggered it.
            if (OutboundMail::isConfigured() && config('mail.default') !== 'array') {
                DB::afterCommit(fn () => OutboundMail::safely(
                    'Review invitation email could not be dispatched.',
                    fn () => $locked->user->notify(new ReviewInvitationMail($locked)),
                    [
                        'reservation_id' => $locked->id,
                        'notification' => ReviewInvitationMail::class,
                    ],
                ));
            }
        }, 5);
    }
}
