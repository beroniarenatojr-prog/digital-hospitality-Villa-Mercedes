<?php

namespace App\Enums;

use App\Models\Reservation;

/**
 * The page of one booking that a link into it should open.
 *
 * Every list of bookings — "My reservations", the dashboard, a notification —
 * is a list of links into them, and the useful destination depends on where the
 * booking is: one whose deposit is still owed is about the money, one that is
 * confirmed and still ahead is about the day itself, and one that is over is
 * about what was booked. Each list could read the status and decide for itself,
 * but then every list would decide differently — and a guest who clicks "View
 * details" on two screens would land in two places. So the rule lives here, and
 * the links ask.
 *
 * The three destinations are the three `x-customer.reservation-tabs` names, so a
 * guest who arrives from a card is on a page they can see they are on.
 */
enum ReservationDestination: string
{
    case Stay = 'stay';
    case Arrival = 'arrival';
    case Payments = 'payments';

    /**
     * The page that answers what this guest most likely came to do.
     *
     * A pending booking has not been agreed yet: what staff are deciding, and what
     * is owed for it, are both answered on the money page. A confirmed stay that is
     * not over is about arriving — the code, the times, what to bring. Everything
     * else is history: completed, cancelled, declined, expired, and a confirmed
     * stay whose last night has passed, which a guest is looking back at rather
     * than packing for.
     */
    public static function for(Reservation $reservation): self
    {
        if ($reservation->status === ReservationStatus::Pending) {
            return self::Payments;
        }

        $arriving = $reservation->status === ReservationStatus::Approved
            && $reservation->check_out->isFuture();

        return $arriving ? self::Arrival : self::Stay;
    }

    public function url(Reservation $reservation): string
    {
        return match ($this) {
            self::Stay => ReservationRecordView::Stay->url($reservation),
            self::Arrival => ReservationRecordView::Arrival->url($reservation),
            self::Payments => route('customer.payments.index', $reservation),
        };
    }

    /**
     * The link's own words.
     *
     * A guest should be able to tell where a link goes before following it: the
     * one label every card used to carry, "View details", is what left a pending
     * booking's actual problem — the deposit — two clicks away from the card that
     * was warning about it.
     */
    public function linkLabel(): string
    {
        return match ($this) {
            self::Stay => 'View details',
            self::Arrival => 'Check in details',
            self::Payments => 'Deposit & payments',
        };
    }
}
