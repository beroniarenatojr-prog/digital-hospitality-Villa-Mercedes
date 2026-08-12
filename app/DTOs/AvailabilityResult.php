<?php

namespace App\DTOs;

class AvailabilityResult
{
    public function __construct(
        public readonly bool $isAvailable,
        public readonly ?string $reason = null,
        public readonly ?int $conflictingReservationId = null
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'is_available' => $this->isAvailable,
            'reason' => $this->reason,
            'conflicting_reservation_id' => $this->conflictingReservationId,
        ];
    }
}
