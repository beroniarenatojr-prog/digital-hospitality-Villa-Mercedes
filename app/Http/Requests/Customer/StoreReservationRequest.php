<?php

namespace App\Http\Requests\Customer;

use App\Enums\GuestGender;
use App\Http\Requests\Concerns\PreparesCottageBooking;
use App\Http\Requests\Concerns\PreparesGuestInformation;
use App\Models\Facility;
use App\Support\GuestInformation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReservationRequest extends FormRequest
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
        $rules = [
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'check_in' => ['required', 'date', 'after:now'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guestCount' => array_values(array_filter(['required', 'integer', 'min:1', $this->guestMaxCapacity() ? 'max:'.$this->guestMaxCapacity() : null])),
            'pax_tier' => $this->paxTierRules(),
            'visit_date' => [Rule::requiredIf(fn () => $this->isCottage()), 'nullable', 'date_format:Y-m-d'],
            'swimming_session_id' => [Rule::requiredIf(fn () => $this->isCottage()), 'nullable', 'integer', 'exists:swimming_sessions,id'],
            'early_check_in_hours' => ['nullable', 'integer', 'min:0', 'max:'.config('resort.stay.max_additional_hours', 12)],
            'late_check_out_hours' => ['nullable', 'integer', 'min:0', 'max:'.config('resort.stay.max_additional_hours', 12)],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'policies_accepted' => ['accepted'],
            ...$this->guestInformationRules(emailRequired: true, fullSheet: ! $this->isCottage()),
        ];

        // Gender comes from the registered account, not the booking form.
        $rules['guest_gender'] = ['nullable', Rule::enum(GuestGender::class)];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'guestCount' => 'guest count',
            'special_requests' => 'preferences and additional requests',
            'policies_accepted' => 'policies agreement',
            ...GuestInformation::attributes(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'policies_accepted.accepted' => 'Please agree to the resort policies and cancellation terms to continue.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateGuestPartySize($validator);
        });
    }

    protected function prepareForValidation(): void
    {
        $this->prepareCottageBooking();
        $this->prepareGuestInformation();

        $this->merge([
            'guest_gender' => $this->user()?->gender?->value,
        ]);
    }

    private function isCottage(): bool
    {
        return Facility::query()->find($this->input('facility_id'))?->isCottage() === true;
    }

    /**
     * @return list<mixed>
     */
    private function paxTierRules(): array
    {
        $facility = Facility::query()->find($this->input('facility_id'));
        $tiers = $facility?->paxTiers() ?? [];

        if (count($tiers) < 2) {
            return ['nullable', 'integer'];
        }

        return ['required', 'integer', Rule::in($tiers)];
    }
}
