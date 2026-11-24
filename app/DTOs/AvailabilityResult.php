<?php

namespace App\DTOs;

class AvailabilityResult
{
    public function __construct(
        public readonly bool $isAvailable,
        public readonly ?string $reason = null,
        public readonly ?int $conflictingReservationId = null,
        public readonly ?\App\Enums\ServiceAvailability $availabilityStatus = null
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function status(): \App\Enums\ServiceAvailability
    {
        return $this->availabilityStatus ?? match (true) {
            $this->isAvailable => \App\Enums\ServiceAvailability::Available,
            $this->conflictingReservationId !== null => \App\Enums\ServiceAvailability::FullyBooked,
            default => \App\Enums\ServiceAvailability::Unavailable,
        };
    }

    public function toArray(): array
    {
        return [
            'is_available' => $this->isAvailable,
            'status' => $this->status()->value,
            'status_label' => $this->status()->label(),
            'reason' => $this->reason,
            'conflicting_reservation_id' => $this->conflictingReservationId,
        ];
    }
}
