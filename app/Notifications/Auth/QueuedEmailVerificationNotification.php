<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedEmailVerificationNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;
}
