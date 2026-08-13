<?php

namespace App\Services;

use App\DTOs\PriceBreakdown;
use App\Enums\RateType;
use App\Exceptions\Pricing\InvalidPricingPeriodException;
use App\Exceptions\Pricing\NoApplicableRateException;
use App\Exceptions\Pricing\OverlappingSeasonalRatesException;
use App\Models\Facility;
use App\Models\Rate;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PricingService
{
    /**
     * Calculate the expected price of a reservation before creation.
     *
     * @param Facility $facility
     * @param Carbon $checkIn
     * @param Carbon $checkOut
     * @param int $earlyCheckInHours
     * @param int $lateCheckOutHours
     * @return PriceBreakdown
     * 
     * @throws InvalidPricingPeriodException
     * @throws OverlappingSeasonalRatesException
     * @throws NoApplicableRateException
     */
    public function calculate(
        Facility $facility,
        Carbon $checkIn,
        Carbon $checkOut,
        int $earlyCheckInHours = 0,
        int $lateCheckOutHours = 0,
        ?int $guestCount = null
    ): PriceBreakdown {
        $this->validateInputs($checkIn, $checkOut, $earlyCheckInHours, $lateCheckOutHours);

        $activeRates = $facility->rates()->where('is_active', true)->get();

        $applicableRate = $this->determineApplicableRate($activeRates, $checkIn);
        
        $baseAmount = $this->calculateBaseAmount($applicableRate, $checkIn, $checkOut);
        
        $earlyCharge = $this->calculateAdditionalCharge($activeRates, 'Early Check-in', $earlyCheckInHours);
        $lateCharge = $this->calculateAdditionalCharge($activeRates, 'Late Check-out', $lateCheckOutHours);

        $extraPersonCharge = 0;
        if ($guestCount !== null && $guestCount > $facility->capacity) {
            $extraGuests = $guestCount - $facility->capacity;
            $extraPersonCharge = $this->calculateExtraPersonCharge($activeRates, $extraGuests, $applicableRate->unit, $checkIn, $checkOut);
        }

        $totalAmount = $baseAmount + $earlyCharge + $lateCharge + $extraPersonCharge;

        return new PriceBreakdown(
            baseAmount: $baseAmount,
            earlyCheckInCharge: $earlyCharge,
            lateCheckOutCharge: $lateCharge,
            extraPersonCharge: $extraPersonCharge,
            totalAmount: $totalAmount,
            appliedRateName: $applicableRate->name
        );
    }

    /**
     * @throws InvalidPricingPeriodException
     */
    private function validateInputs(Carbon $checkIn, Carbon $checkOut, int $early, int $late): void
    {
        if ($checkOut->lessThanOrEqualTo($checkIn)) {
            throw new InvalidPricingPeriodException('Check-out must be after check-in.');
        }

        if ($early < 0 || $late < 0) {
            throw new InvalidPricingPeriodException('Additional hours cannot be negative.');
        }
    }

    /**
     * @param Collection<int, Rate> $activeRates
     * @throws OverlappingSeasonalRatesException
     * @throws NoApplicableRateException
     */
    private function determineApplicableRate(Collection $activeRates, Carbon $checkIn): Rate
    {
        $seasonalRates = $activeRates->filter(function (Rate $rate) use ($checkIn) {
            if ($rate->type !== RateType::Seasonal) {
                return false;
            }

            $from = $rate->effective_from ? Carbon::parse($rate->effective_from)->startOfDay() : null;
            $until = $rate->effective_until ? Carbon::parse($rate->effective_until)->endOfDay() : null;

            if ($from && $checkIn->lessThan($from)) {
                return false;
            }

            if ($until && $checkIn->greaterThan($until)) {
                return false;
            }

            return true;
        });

        if ($seasonalRates->count() > 1) {
            throw new OverlappingSeasonalRatesException('Multiple active seasonal rates apply to this check-in date.');
        }

        if ($seasonalRates->count() === 1) {
            return $seasonalRates->first();
        }

        $baseRate = $activeRates->firstWhere('type', RateType::Base);

        if (! $baseRate) {
            throw new NoApplicableRateException('No active base or seasonal rate found for this facility.');
        }

        return $baseRate;
    }

    private function calculateBaseAmount(Rate $rate, Carbon $checkIn, Carbon $checkOut): int
    {
        // Decimal stored in DB to integer cents
        $ratePerUnitInCents = (int) round($rate->amount * 100);

        if ($rate->unit === 'per night') {
            $nights = $checkIn->copy()->startOfDay()->diffInDays($checkOut->copy()->startOfDay());
            $nights = max(1, (int) $nights); // Ensure at least 1 night is charged
            
            return $ratePerUnitInCents * $nights;
        }

        // Default behavior for "per event", packages, or undefined units
        return $ratePerUnitInCents;
    }

    /**
     * @param Collection<int, Rate> $activeRates
     */
    private function calculateAdditionalCharge(Collection $activeRates, string $chargeName, int $hours): int
    {
        if ($hours <= 0) {
            return 0;
        }

        $chargeRate = $activeRates->first(function (Rate $rate) use ($chargeName) {
            return $rate->type === RateType::AdditionalCharge && $rate->name === $chargeName;
        });

        if (! $chargeRate) {
            return 0;
        }

        $ratePerUnitInCents = (int) round($chargeRate->amount * 100);

        return $ratePerUnitInCents * $hours;
    }

    /**
     * @param Collection<int, Rate> $activeRates
     */
    private function calculateExtraPersonCharge(Collection $activeRates, int $extraGuests, string $baseRateUnit, Carbon $checkIn, Carbon $checkOut): int
    {
        if ($extraGuests <= 0) {
            return 0;
        }

        $extraPersonRate = $activeRates->firstWhere('type', RateType::ExtraPerson);

        if (! $extraPersonRate) {
            throw new NoApplicableRateException('An extra person charge is required but no active extra person rate is configured for this facility.');
        }

        $ratePerPersonInCents = (int) round($extraPersonRate->amount * 100);
        $totalExtraCharge = $ratePerPersonInCents * $extraGuests;

        if ($baseRateUnit === 'per night') {
            $nights = $checkIn->copy()->startOfDay()->diffInDays($checkOut->copy()->startOfDay());
            $nights = max(1, (int) $nights); // Ensure at least 1 night is charged
            $totalExtraCharge *= $nights;
        }

        return $totalExtraCharge;
    }
}
