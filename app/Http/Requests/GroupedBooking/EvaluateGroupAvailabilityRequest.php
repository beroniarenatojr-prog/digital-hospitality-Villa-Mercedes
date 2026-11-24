<?php

namespace App\Http\Requests\GroupedBooking;

use Illuminate\Foundation\Http\FormRequest;

class EvaluateGroupAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'lines.*.check_in' => ['nullable', 'string'],
            'lines.*.check_out' => ['nullable', 'string'],
            'lines.*.date' => ['nullable', 'date_format:Y-m-d'],
            'lines.*.swimming_session_id' => ['nullable', 'integer', 'exists:swimming_sessions,id'],
            'lines.*.session' => ['nullable', 'string'],
            'lines.*.guest_count' => ['nullable', 'integer', 'min:1'],
            'lines.*.guestCount' => ['nullable', 'integer', 'min:1'],
            'lines.*.infants_count' => ['nullable', 'integer', 'min:0', 'max:500'],
            'lines.*.children_count' => ['nullable', 'integer', 'min:0', 'lte:lines.*.guest_count'],
            'lines.*.pax_tier' => ['nullable', 'integer', 'min:1'],
            'lines.*.early_check_in_hours' => ['nullable', 'integer', 'min:0', 'max:2'],
            'lines.*.late_check_out_hours' => ['nullable', 'integer', 'min:0', 'max:12'],
            'lines.*.special_requests' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
