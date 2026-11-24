<?php

namespace App\Http\Requests\Admin;

use App\Enums\GuestGender;
use App\Enums\GuestIdType;
use App\Rules\PersonName;
use App\Rules\PhilippineMobileNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminWalkInGroupRequest extends FormRequest
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
            'pets_included' => ['nullable', 'boolean'],
            'lines.*.infants_count' => ['nullable', 'integer', 'min:0', 'max:500'],
            'lines.*.children_count' => ['nullable', 'integer', 'min:0', 'lte:lines.*.guest_count'],
            'lines.*.pax_tier' => ['nullable', 'integer', 'min:1'],
            'lines.*.early_check_in_hours' => ['nullable', 'integer', 'min:0', 'max:12'],
            'lines.*.late_check_out_hours' => ['nullable', 'integer', 'min:0', 'max:12'],

            'existing_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'guest_name' => ['required', 'string', 'max:255', new PersonName],
            'guest_email' => ['required', 'email', 'max:255'],
            'guest_phone' => ['required', 'string', new PhilippineMobileNumber],
            'guest_place_of_origin' => ['required', 'string', 'max:255'],
            'guest_nationality' => ['required', 'string', 'max:100'],
            'guest_company' => ['nullable', 'string', 'max:255'],
            'guest_valid_id' => ['required', Rule::enum(GuestIdType::class)],
            'guest_gender' => ['required', Rule::enum(GuestGender::class)],

            'admin_notes' => ['nullable', 'string', 'max:1000'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'creation_action' => ['nullable', 'string', 'in:approve,pending'],
            'override_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
