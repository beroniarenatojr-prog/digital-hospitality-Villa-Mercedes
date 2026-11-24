<?php

namespace App\DTOs;

class PriceBreakdown
{
    public function __construct(
        public readonly int $baseAmount,
        public readonly int $earlyCheckInCharge,
        public readonly int $lateCheckOutCharge,
        public readonly int $extraPersonCharge,
        public readonly int $totalAmount,
        public readonly string $appliedRateName,
        public readonly int $hallExtensionCharge = 0
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'base_amount' => $this->baseAmount,
            'early_check_in_charge' => $this->earlyCheckInCharge,
            'late_check_out_charge' => $this->lateCheckOutCharge,
            'hall_extension_charge' => $this->hallExtensionCharge,
            'extra_person_charge' => $this->extraPersonCharge,
            'additional_charges' => $this->earlyCheckInCharge + $this->lateCheckOutCharge + $this->extraPersonCharge + $this->hallExtensionCharge,
            'total_amount' => $this->totalAmount,
            'applied_rate_name' => $this->appliedRateName,
        ];
    }
}
