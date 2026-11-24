<?php

namespace App\Http\Requests\GroupedBooking;

use App\Enums\GuestGender;
use App\Enums\GuestIdType;
use App\Rules\PersonName;
use App\Rules\PhilippineMobileNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGroupedBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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

            'guest_name' => ['required', 'string', 'max:255', new PersonName],
            'guest_email' => ['required', 'email', 'max:255'],
            'guest_phone' => ['required', 'string', new PhilippineMobileNumber],
            'guest_place_of_origin' => ['required', 'string', 'max:255'],
            'guest_nationality' => ['required', 'string', 'max:100'],
            'guest_company' => ['nullable', 'string', 'max:255'],
            'guest_valid_id' => ['required', Rule::enum(GuestIdType::class)],
            'guest_gender' => ['required', Rule::enum(GuestGender::class)],

            'special_requests' => ['nullable', 'string', 'max:1000'],
            'policies_accepted' => ['accepted'],
            'policy_version' => ['nullable', Rule::in([config('resort.policy.version')])],
            'version_token' => ['nullable', 'string'],

            'payment_destination_id' => ['required', 'integer', 'exists:payment_destinations,id'],
            'payment_method' => ['required', Rule::in(\App\Enums\PaymentMethod::values())],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'payment_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.\App\Support\ResortTime::today()->toDateString()],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'payment_percent' => ['nullable', Rule::in([50, 100])],
            'infants_count' => ['nullable', 'integer', 'min:0', 'max:500'],
            'children_count' => ['nullable', 'integer', 'min:0', 'max:500'],
            'pets_included' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->hasAny(['payment_destination_id', 'payment_method'])) {
                return;
            }

            $destination = \App\Models\PaymentDestination::whereKey($this->input('payment_destination_id'))->where('is_active', true)->first();
            if (! $destination || $destination->method->value !== $this->input('payment_method')) {
                $validator->errors()->add('payment_destination_id', 'Choose an active receiving account that matches the payment method.');
            }
        });
    }
}
