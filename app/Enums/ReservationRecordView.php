<?php

namespace App\Enums;

use App\Models\Reservation;

/**
 * The facets of one reservation record.
 *
 * A booking answers three different questions — what did I book, what do I do
 * when I arrive, and what do I owe — and every one of them used to be answered
 * in a single scroll: the record stacked the arrival code, the stay, the guest
 * sheet, a pricing card and a payment panel, so a guest reading about the
 * deposit passed the bed count again on the way back. Each facet has its own
 * address inside the same record now, and the money keeps its own page
 * (`customer.payments.index`), so `x-customer.reservation-tabs` can name three
 * things a guest would actually say out loud.
 */
enum ReservationRecordView: string
{
    case Stay = 'stay';
    case Arrival = 'arrival';

    public function label(): string
    {
        return match ($this) {
            self::Stay => 'Your stay',
            self::Arrival => 'Check in',
        };
    }

    /**
     * One sentence saying what this facet is for. Shown under the heading so a
     * guest knows whether they are on the right page before they read it.
     */
    public function description(): string
    {
        return match ($this) {
            self::Stay => 'The dates, guests and extras you reserved, and the guest sheet the front desk records at check-in.',
            self::Arrival => 'What to do on the day: your reservation code, what to bring, and who to call.',
        };
    }

    /**
     * An unknown or missing `view` is not an error — it is the record's own
     * view, so old links and hand-typed URLs land somewhere sensible.
     */
    public static function fromQuery(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::default();
    }

    public static function default(): self
    {
        return self::Stay;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The canonical address of this facet. The default view keeps the bare
     * record URL, so "the reservation" has one link rather than two.
     */
    public function url(Reservation $reservation): string
    {
        return route('customer.reservations.show', [
            'reservation' => $reservation,
            ...($this === self::default() ? [] : ['view' => $this->value]),
        ]);
    }
}
