<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\PreparesGuestInformation;
use App\Support\GuestInformation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWalkInReservationRequest extends FormRequest
{
    use PreparesGuestInformation;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guestCount' => array_values(array_filter([
                'required',
                'integer',
                'min:1',
                $this->guestMaxCapacity() ? 'max:'.$this->guestMaxCapacity() : null,
            ])),
            'early_check_in_hours' => ['nullable', 'integer', 'min:0', 'max:'.config('resort.stay.max_additional_hours', 12)],
            'late_check_out_hours' => ['nullable', 'integer', 'min:0', 'max:'.config('resort.stay.max_additional_hours', 12)],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
            ...$this->guestInformationRules(emailRequired: false),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'guestCount' => 'guest count',
            ...GuestInformation::attributes(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $this->validateGuestPartySize($validator);
        });
    }

    protected function prepareForValidation(): void
    {
        $this->prepareGuestInformation();
    }
}
