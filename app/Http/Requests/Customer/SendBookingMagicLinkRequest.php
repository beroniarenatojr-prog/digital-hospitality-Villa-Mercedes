<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\Concerns\PreparesCottageBooking;
use App\Http\Requests\Concerns\PreparesGuestInformation;
use App\Models\Facility;
use App\Support\GuestInformation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SendBookingMagicLinkRequest extends FormRequest
{
    use PreparesCottageBooking, PreparesGuestInformation;

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
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'check_in' => ['required', 'date', 'after:now'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guestCount' => array_values(array_filter(['required', 'integer', 'min:1', $this->guestMaxCapacity() ? 'max:'.$this->guestMaxCapacity() : null])),
            'visit_date' => [Rule::requiredIf(fn () => $this->isCottage()), 'nullable', 'date_format:Y-m-d'],
            'swimming_session_id' => [Rule::requiredIf(fn () => $this->isCottage()), 'nullable', 'integer', 'exists:swimming_sessions,id'],
            'early_check_in_hours' => ['nullable', 'integer', 'min:0', 'max:'.config('resort.stay.max_additional_hours', 12)],
            'late_check_out_hours' => ['nullable', 'integer', 'min:0', 'max:'.config('resort.stay.max_additional_hours', 12)],
            ...$this->guestInformationRules(emailRequired: true),
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
        $this->prepareCottageBooking();
        $this->prepareGuestInformation();

        $email = $this->input('email') ?: $this->input('guest_email');

        if (filled($email)) {
            $this->merge([
                'email' => strtolower(trim((string) $email)),
                'guest_email' => strtolower(trim((string) ($this->input('guest_email') ?: $email))),
            ]);
        }
    }

    private function isCottage(): bool
    {
        return Facility::query()->find($this->input('facility_id'))?->isCottage() === true;
    }
}
