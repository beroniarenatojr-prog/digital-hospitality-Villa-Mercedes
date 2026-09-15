<?php

namespace App\Services;

use App\Models\Reservation;
use App\Notifications\Reservation\ReviewInvitation;
use App\Support\OutboundMail;
use App\Support\LayoutBadges;
use Illuminate\Support\Facades\DB;

class ReviewInvitationService
{
    public function invite(Reservation $reservation): void
    {
        DB::transaction(function () use ($reservation) {
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if (! $locked->canReview() || $locked->review_invited_at || ! $locked->user) return;
            $locked->user->notify(new ReviewInvitation($locked, 'database'));
            $locked->update(['review_invited_at' => now()]);
            LayoutBadges::forgetUnread($locked->user_id);
            if (OutboundMail::isConfigured() && ! in_array(config('mail.default'), ['array', 'log'], true)) {
                DB::afterCommit(fn () => $locked->user->notify(new \App\Notifications\Reservation\ReviewInvitationMail($locked)));
            }
        }, 5);
    }
}
