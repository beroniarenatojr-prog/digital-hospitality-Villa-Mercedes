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
    public const EARLY_CHECK_IN_RATE_NAME = 'Early Check-in';

    public const LATE_CHECK_OUT_RATE_NAME = 'Late Check-out';

    public const HALL_EXTENSION_RATE_NAME = 'Hall Extension';

    /**
     * Calculate the expected price of a reservation before creation.
     *
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
        ?int $guestCount = null,
        ?int $paxTier = null
    ): PriceBreakdown {
        $this->validateInputs($checkIn, $checkOut, $earlyCheckInHours, $lateCheckOutHours);

        $activeRates = $this->activeRates($facility);

        $applicableRate = $this->determineApplicableRate($activeRates, $checkIn, $paxTier);

        $baseAmount = $this->calculateBaseAmount($applicableRate, $checkIn, $checkOut);

        if ($facility->isRoom() && $checkIn->isSameDay($checkOut)) {
            $earlyCheckInHours = 0;
            $lateCheckOutHours = 0;
        }
        if ($facility->isRoom() && ($earlyCheckInHours > 0 || $lateCheckOutHours > 0)) {
            foreach ([self::EARLY_CHECK_IN_RATE_NAME => [$earlyCheckInHours, 20000], self::LATE_CHECK_OUT_RATE_NAME => [$lateCheckOutHours, 30000]] as $name => [$hours, $expected]) {
                if ($hours === 0) continue;
                $rates = $activeRates->filter(fn (Rate $rate) => $rate->type === RateType::AdditionalCharge && $rate->name === $name);
                if ($rates->count() !== 1 || (int) round($rates->first()->amount * 100) !== $expected || $rates->first()->unit !== 'per hour') {
                    throw new NoApplicableRateException('Staff must audit and reconcile room hourly rates before quoting early or late hours.');
                }
            }
        }
        $earlyCharge = $this->calculateAdditionalCharge($activeRates, self::EARLY_CHECK_IN_RATE_NAME, $earlyCheckInHours);
        $lateCharge = $this->calculateAdditionalCharge($activeRates, self::LATE_CHECK_OUT_RATE_NAME, $lateCheckOutHours);

        $extraPersonCharge = 0;
        $included = $paxTier ?? $facility->capacity;
        if ($paxTier === null && $guestCount !== null && $included !== null && $guestCount > $included) {
            $extraGuests = $guestCount - $included;
            $extraPersonCharge = $this->calculateExtraPersonCharge($activeRates, $extraGuests, $applicableRate->unit, $checkIn, $checkOut);
        }

        $schedule = app(ReservationSchedule::class);
        $extensionCharge = $schedule->extensionHours($facility, $checkIn, $checkOut) * $schedule->extensionRateCents($facility);
        $totalAmount = $baseAmount + $earlyCharge + $lateCharge + $extraPersonCharge + $extensionCharge;

        return new PriceBreakdown(
            baseAmount: $baseAmount,
            earlyCheckInCharge: $earlyCharge,
            lateCheckOutCharge: $lateCharge,
            extraPersonCharge: $extraPersonCharge,
            totalAmount: $totalAmount,
            appliedRateName: $applicableRate->name,
            hallExtensionCharge: $extensionCharge
        );
    }

    /**
     * The hourly amount charged for an additional-charge rate such as
     * "Late Check-out", or null when the facility has no such rate configured.
     */
    public function hourlyChargeAmount(Facility $facility, string $chargeName): ?float
    {
        $rate = $facility->rates()
            ->where('is_active', true)
            ->where('type', RateType::AdditionalCharge->value)
            ->where('name', $chargeName)
            ->first();

        return $rate ? (float) $rate->amount : null;
    }

    /**
     * Prefer already-eager-loaded rates so a catalog search does not re-query
     * each facility. Callers that constrain the relation to a subset of types
     * should not reuse that relation for quotes.
     *
     * @return Collection<int, Rate>
     */
    private function activeRates(Facility $facility): Collection
    {
        if ($facility->relationLoaded('rates')) {
            return $facility->rates
                ->filter(fn (Rate $rate): bool => $rate->is_active)
                ->values();
        }

        return $facility->rates()->where('is_active', true)->get();
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
     * @param  Collection<int, Rate>  $activeRates
     *
     * @throws OverlappingSeasonalRatesException
     * @throws NoApplicableRateException
     */
    private function determineApplicableRate(Collection $activeRates, Carbon $checkIn, ?int $paxTier = null): Rate
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

        $baseRates = $activeRates->filter(fn (Rate $rate): bool => $rate->type === RateType::Base)->values();
        $tieredRates = $baseRates->filter(fn (Rate $rate): bool => $rate->max_occupancy !== null)->values();

        if ($paxTier !== null) {
            $tierRate = $tieredRates->first(fn (Rate $rate): bool => (int) $rate->max_occupancy === $paxTier);

            if ($tierRate) {
                return $tierRate;
            }
        }

        if ($tieredRates->count() === 1) {
            return $tieredRates->first();
        }

        if ($tieredRates->count() > 1) {
            throw new NoApplicableRateException('Please select a guest tier for this package.');
        }

        $baseRate = $baseRates->first();

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
     * @param  Collection<int, Rate>  $activeRates
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
     * @param  Collection<int, Rate>  $activeRates
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
