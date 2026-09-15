<?php

namespace App\Notifications\Reservation;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ReviewInvitationMail extends ReviewInvitation implements ShouldQueue
{
    use Queueable;

    // SMTP delivery has no idempotency key. Do not automatically retry an
    // ambiguous delivery; the in-app invitation remains available.
    public int $tries = 1;

    public function __construct(Reservation $reservation)
    {
        parent::__construct($reservation, 'mail');
        $this->afterCommit();
    }
}
