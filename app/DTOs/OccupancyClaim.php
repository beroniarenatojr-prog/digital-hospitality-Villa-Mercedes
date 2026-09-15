<?php

namespace App\DTOs;

use Carbon\Carbon;

/**
 * A single claim on a physical resort resource for a period of time.
 *
 * Guest stays, hall events, and package bundles are all claims. Modelling them
 * uniformly is what lets one calendar show the whole property instead of one
 * calendar per department.
 */
class OccupancyClaim
{
    public const TYPE_RESERVATION = 'reservation';

    public function __construct(
        public readonly string $claimType,
        public readonly int $facilityId,
        public readonly string $facilityName,
        public readonly string $label,
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly string $status,
        public readonly float $value = 0.0,
        public readonly ?string $url = null,
        public readonly ?string $note = null,
        /** True when this bar is a linked package/component hold, not the booked facility itself. */
        public readonly bool $isLinkedHold = false,
    ) {}

    public function overlaps(Carbon $from, Carbon $to): bool
    {
        return $this->from->lessThan($to) && $this->to->greaterThan($from);
    }

    public function durationLabel(): string
    {
        if ($this->from->isSameDay($this->to)) {
            return $this->from->format('M d, g:i A').' – '.$this->to->format('g:i A');
        }

        return $this->from->format('M d, g:i A').' – '.$this->to->format('M d, g:i A');
    }
}
