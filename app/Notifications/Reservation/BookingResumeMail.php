<?php

namespace App\Notifications\Reservation;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "You were one step away."
 *
 * Sent only to a guest who reached the confirm step of a booking and did not
 * finish it, and only after they stopped working on it — see
 * `App\Jobs\SendBookingResumeLink`. It carries what the booking was (space,
 * stay, party) and the one link back to it, and it says plainly that nothing is
 * held, because the space genuinely is not.
 */
class BookingResumeMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $facilityName,
        public readonly string $guestName,
        public readonly string $url,
        public readonly string $checkIn,
        public readonly string $checkOut,
        public readonly int $guestCount,
        public readonly int $expireHours,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Finish your '.config('resort.short_name').' booking')
            ->markdown('mail.booking-resume', [
                'guestName' => $this->guestName,
                'facility' => $this->facilityName,
                'url' => $this->url,
                'checkIn' => $this->checkIn,
                'checkOut' => $this->checkOut,
                'guestCount' => $this->guestCount,
                'expireHours' => $this->expireHours,
                'resortPhone' => config('resort.phone'),
                'resortPhoneHref' => config('resort.phone_href'),
            ]);
    }
}
