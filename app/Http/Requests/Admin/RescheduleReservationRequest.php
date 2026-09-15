<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\PreparesCottageBooking;
use App\Http\Requests\Customer\RescheduleReservationRequest as CustomerRescheduleRequest;

class RescheduleReservationRequest extends CustomerRescheduleRequest
{
    use PreparesCottageBooking;

    protected function prepareForValidation(): void
    {
        $this->prepareCottageBooking();
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'visit_date' => ['nullable', 'date_format:Y-m-d'],
            'swimming_session_id' => ['nullable', 'integer', 'exists:swimming_sessions,id'],
        ];
    }
}
