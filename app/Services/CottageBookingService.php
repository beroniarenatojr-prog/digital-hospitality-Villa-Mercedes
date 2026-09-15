<?php

namespace App\Services;

use App\DTOs\PriceBreakdown;
use App\Exceptions\Pricing\InvalidPricingPeriodException;
use App\Models\Facility;
use App\Models\SwimmingSession;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class CottageBookingService
{
    /** @return array{session: SwimmingSession, check_in: Carbon, check_out: Carbon} */
    public function resolve(Facility $facility, mixed $sessionId, mixed $visitDate): array
    {
        if (! $facility->isCottage()) {
            throw ValidationException::withMessages(['facility_id' => 'This booking is not a cottage reservation.']);
        }

        $session = $this->session($sessionId);
        if (! $session) {
            throw ValidationException::withMessages(['swimming_session_id' => 'Choose an available swimming session.']);
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', (string) $visitDate)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['visit_date' => 'Choose a valid visit date.']);
        }

        [$checkIn, $checkOut] = $session->periodFor($date->toDateString());
        if ($checkIn->isPast()) {
            throw ValidationException::withMessages(['visit_date' => 'Choose a future swimming session.']);
        }

        return ['session' => $session, 'check_in' => $checkIn, 'check_out' => $checkOut];
    }

    public function price(Facility $facility, mixed $sessionId, Carbon $checkIn, Carbon $checkOut): PriceBreakdown
    {
        $session = $this->session($sessionId);
        if (! $facility->isCottage() || ! $session) {
            throw new InvalidPricingPeriodException('The selected cottage session is no longer available.');
        }

        [$expectedIn, $expectedOut] = $session->periodFor($checkIn->toDateString());
        if (! $checkIn->equalTo($expectedIn) || ! $checkOut->equalTo($expectedOut)) {
            throw new InvalidPricingPeriodException('Cottage times must match the selected swimming session.');
        }

        $amount = (int) round(((float) $session->cottage_fee) * 100);

        return new PriceBreakdown($amount, 0, 0, 0, $amount, $session->name.' cottage fee');
    }

    private function session(mixed $sessionId): ?SwimmingSession
    {
        if ($sessionId instanceof SwimmingSession) {
            return $sessionId->is_active ? $sessionId : null;
        }

        return SwimmingSession::query()->whereKey($sessionId)->where('is_active', true)->first();
    }
}
