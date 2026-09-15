<?php

namespace App\Http\Requests\Concerns;

use App\Models\Facility;
use App\Services\CottageBookingService;

trait PreparesCottageBooking
{
    protected function prepareCottageBooking(): void
    {
        $facility = Facility::query()->find($this->input('facility_id'));
        if (! $facility?->isCottage()) {
            return;
        }

        $resolved = app(CottageBookingService::class)->resolve(
            $facility,
            $this->input('swimming_session_id'),
            $this->input('visit_date')
        );

        $this->merge([
            'swimming_session_id' => $resolved['session']->id,
            'check_in' => $resolved['check_in']->toDateTimeString(),
            'check_out' => $resolved['check_out']->toDateTimeString(),
            'early_check_in_hours' => 0,
            'late_check_out_hours' => 0,
        ]);
    }
}
