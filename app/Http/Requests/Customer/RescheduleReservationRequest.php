<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'check_in' => ['required', 'date', 'after:now'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'guestCount' => ['required', 'integer', 'min:1'],
            'early_check_in_hours' => ['nullable', 'integer', 'min:0', 'max:2'],
            'late_check_out_hours' => ['nullable', 'integer', 'min:0', 'max:0'],
        ];
    }
}

