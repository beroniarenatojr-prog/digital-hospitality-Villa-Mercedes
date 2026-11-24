<?php

namespace App\Services;

use App\Models\Facility;
use App\Support\ResortTime;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/** The resort's bookable schedules; actual staff-recorded overstay is separate. */
class ReservationSchedule
{
    public function configuration(Facility $facility): array
    {
        $mode = match (true) {
            $facility->isRoom() => 'room',
            $facility->isFlyerPackage() => 'package',
            $facility->isFunctionHall() && $facility->slug === 'opal-hall' => 'opal',
            $facility->isFunctionHall() => 'hall',
            default => 'other',
        };

        return [
            'mode' => $mode,
            'start' => $mode === 'package' ? $facility->stayStartTimeString() : config('resort.stay.overnight_hours.check_in'),
            'end' => $mode === 'package' ? $facility->stayEndTimeString() : config('resort.stay.overnight_hours.check_out'),
            'overnight' => $mode === 'package' ? (bool) $facility->stay_spans_midnight : $mode === 'room',
            'label' => $mode === 'package' ? $facility->stay_category->label() : null,
            'today' => ResortTime::today()->toDateString(),
            'defaultDate' => ResortTime::today()->addDay()->toDateString(),
            'maxEarlyHours' => $mode === 'room' ? (int) config('resort.stay.room_max_early_hours') : 0,
            'hallOpen' => config('resort.stay.hall.open'),
            'hallClose' => config('resort.stay.hall.close'),
            'standardHours' => (int) config('resort.stay.hall.standard_hours'),
            'slots' => config('resort.stay.opal_slots'),
            'extensionHourCents' => $this->extensionRateCents($facility),
        ];
    }

    public function extensionRateCents(Facility $facility): int
    {
        if (! $facility->isFunctionHall() || ! array_key_exists($facility->slug, config('resort.stay.hall.extension_rates', []))) {
            return 0;
        }

        $amount = app(PricingService::class)->hourlyChargeAmount($facility, PricingService::HALL_EXTENSION_RATE_NAME);

        return (int) round(($amount ?? 0) * 100);
    }

    public function extensionHours(Facility $facility, Carbon $start, Carbon $end): int
    {
        if (! $facility->isFunctionHall() || $facility->slug === 'opal-hall') {
            return 0;
        }

        return max(0, (int) ceil($start->diffInMinutes($end) / 60) - (int) config('resort.stay.hall.standard_hours'));
    }

    public function validate(Facility $facility, Carbon $start, Carbon $end, int $early = 0, int $late = 0, bool $customer = true): void
    {
        $errors = [];
        $mode = $this->configuration($facility)['mode'];
        if ($end->lte($start)) {
            $errors['check_out'] = 'Check out must be after check in.';
        }
        if ($customer && $late !== 0) {
            $errors['late_check_out_hours'] = 'Request a late check out in notes. Staff approval and an additional fee may apply.';
        }
        if ($customer && ($early < 0 || $early > ($mode === 'room' ? config('resort.stay.room_max_early_hours') : 0))) {
            $errors['early_check_in_hours'] = $mode === 'room'
                ? 'Room early check-in is limited to two hours, subject to availability.'
                : 'Early check-in is available for rooms only.';
        }
        if ($mode === 'room' && $start->isSameDay($end)) {
            if ($early !== 0 || $late !== 0) {
                $errors['early_check_in_hours'] = 'Same-day rooms use one full base rate without automatic early or late fees.';
            }
        } elseif ($mode === 'room') {
            if ($start->format('H:i:s') !== config('resort.stay.overnight_hours.check_in').':00') {
                $errors['check_in'] = 'Room check-in is fixed at 2 PM. Use early check-in to request up to two hours earlier.';
            }
            if ($end->format('H:i:s') !== config('resort.stay.overnight_hours.check_out').':00' || $end->toDateString() <= $start->toDateString()) {
                $errors['check_out'] = 'Room checkout is fixed at 12 PM (noon), at least one day after check-in.';
            }
        } elseif ($mode === 'opal') {
            $valid = collect(config('resort.stay.opal_slots'))->contains(fn ($slot) =>
                $start->isSameDay($end) && $start->format('H:i:s') === $slot['start'].':00' && $end->format('H:i:s') === $slot['end'].':00'
            );
            if (! $valid) {
                $errors['check_in'] = 'Choose Opal Hall from 9 AM to 5 PM or 5 PM to 10 PM on the same day.';
            }
        } elseif ($mode === 'hall') {
            $minutes = $start->diffInMinutes($end);
            $standard = (int) config('resort.stay.hall.standard_hours') * 60;
            if (! $start->isSameDay($end) || $start->format('H:i:s') < config('resort.stay.hall.open').':00' || $end->format('H:i:s') > config('resort.stay.hall.close').':00') {
                $errors['check_out'] = 'The entire hall event must be within 9 AM to 10 PM on the same day.';
            } elseif ($minutes < $standard || fmod($minutes - $standard, 60) !== 0.0) {
                $errors['check_out'] = 'Choose four standard hours plus whole extension hours.';
            } elseif ($minutes > $standard && $this->extensionRateCents($facility) <= 0) {
                $errors['check_out'] = 'Extensions are not available for this hall. Please contact the resort.';
            }
        } elseif ($mode === 'package') {
            [$expectedStart, $expectedEnd] = $facility->occupancyWindowForDate($start, $end);
            if ($start->format('Y-m-d H:i:s') !== $expectedStart->format('Y-m-d H:i:s') || $end->format('Y-m-d H:i:s') !== $expectedEnd->format('Y-m-d H:i:s')) {
                $errors['check_in'] = 'This package uses its published schedule: '.$facility->stayScheduleLabel().'.';
            }
        }

        $lateRoomBooking = $mode === 'room' && $early === 0
            && $start->toDateString() === ResortTime::today()->toDateString()
            && $end->toDateString() > $start->toDateString();
        if ($customer && ! $lateRoomBooking && $start->copy()->subHours($early)->format('Y-m-d H:i:s') <= ResortTime::now()->format('Y-m-d H:i:s')) {
            $errors['check_in'] = 'Choose a check in time that has not passed in Philippine time.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** Defaults for date-only searches. Explicit event times are validated, never silently changed. */
    public function windowForDates(Facility $facility, Carbon $startDate, ?Carbon $endDate = null): array
    {
        if ($facility->isFlyerPackage()) {
            return $facility->occupancyWindowForDate($startDate, $endDate);
        }
        if ($facility->isFunctionHall()) {
            $start = $startDate->copy()->setTimeFromTimeString(config('resort.stay.hall.open'));
            $end = $facility->slug === 'opal-hall'
                ? $start->copy()->setTimeFromTimeString(config('resort.stay.opal_slots.day.end'))
                : $start->copy()->addHours(config('resort.stay.hall.standard_hours'));

            return [$start, $end];
        }
        if ($facility->isRoom() && $endDate && $endDate->isSameDay($startDate)) {
            return [$startDate->copy()->setTime(9, 0), $endDate->copy()->setTime(17, 0)];
        }
        $endDate = $endDate && $endDate->toDateString() > $startDate->toDateString() ? $endDate->copy() : $startDate->copy()->addDay();

        return [$startDate->copy()->setTimeFromTimeString(config('resort.stay.overnight_hours.check_in')), $endDate->setTimeFromTimeString(config('resort.stay.overnight_hours.check_out'))];
    }
}
