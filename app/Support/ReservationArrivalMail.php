<?php

namespace App\Support;

use App\Models\Reservation;
use Illuminate\Notifications\Messages\MailMessage;

class ReservationArrivalMail
{
    public static function message(Reservation $reservation, string $intro): MailMessage
    {
        $reservation->loadMissing('facility');

        return (new MailMessage)
            ->subject('Your '.config('resort.short_name').' reservation '.$reservation->reservation_code)
            ->markdown('mail.reservation-arrival-pass', [
                'guestName' => $reservation->guestName(),
                'intro' => $intro,
                'code' => $reservation->reservation_code,
                'facility' => $reservation->facility?->name ?? config('resort.short_name'),
                'checkIn' => $reservation->check_in->format('D, M j, Y g:i A'),
                'checkOut' => $reservation->check_out->format('D, M j, Y g:i A'),
                'guestCount' => $reservation->guest_count,
                'earlyHours' => (int) $reservation->early_check_in_hours,
                'lateHours' => (int) $reservation->late_check_out_hours,
                'specialRequests' => $reservation->special_requests,
                'totalPrice' => number_format((float) $reservation->total_price, 2),
                'status' => $reservation->status->label(),
                'passUrl' => $reservation->arrivalPassUrl(),
                'showPass' => $reservation->canShowArrivalPass(),
                'resortPhone' => config('resort.phone'),
                'resortPhoneHref' => config('resort.phone_href'),
            ]);
    }
}
