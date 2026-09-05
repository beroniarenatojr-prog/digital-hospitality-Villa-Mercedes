<?php

namespace App\DTOs;

/**
 * The consequence of holding or accepting one reservation, expressed in the
 * terms an owner decides with: what it earns, what it freezes, and why.
 */
class DecisionImpact
{
    /**
     * @param list<string> $explanations Plain-language reasons, in display order.
     * @param list<array{name: string, reason: string}> $frozenResources
     * @param list<OccupancyClaim> $conflicts Claims that already overlap this window.
     */
    public function __construct(
        public readonly float $reservationValue,
        public readonly float $opportunityCost,
        public readonly array $explanations,
        public readonly array $frozenResources,
        public readonly array $conflicts,
        public readonly float $unpaidBalance = 0.0,
    ) {}

    /**
     * Value earned minus the list value of the space this booking takes off the
     * market. Negative means the hold costs more than the booking brings in.
     */
    public function netValue(): float
    {
        return $this->reservationValue - $this->opportunityCost;
    }

    public function hasConflicts(): bool
    {
        return $this->conflicts !== [];
    }

    public function isNetNegative(): bool
    {
        return $this->netValue() < 0;
    }
}
