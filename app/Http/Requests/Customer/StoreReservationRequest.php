<?php

namespace App\Http\Requests\Customer;

use App\Enums\GuestGender;
use App\Http\Requests\Concerns\PreparesCottageBooking;
use App\Http\Requests\Concerns\PreparesGuestInformation;
use App\Models\Facility;
use App\Support\GuestInformation;
use App\Support\GuestProfilePrefill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReservationRequest extends FormRequest
{
    use PreparesCottageBooking, PreparesGuestInformation;

    private ?Facility $requestedFacility = null;

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
            'check_in' => ['required', 'date'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guestCount' => array_values(array_filter(['required', 'integer', 'min:1', $this->guestMaxCapacity() ? 'max:'.$this->guestMaxCapacity() : null])),
            'pax_tier' => $this->paxTierRules(),
            'visit_date' => [Rule::requiredIf(fn () => $this->isCottage()), 'nullable', 'date_format:Y-m-d'],
            'swimming_session_id' => [Rule::requiredIf(fn () => $this->isCottage()), 'nullable', 'integer', 'exists:swimming_sessions,id'],
            'early_check_in_hours' => ['nullable', 'integer', 'min:0', 'max:2'],
            'late_check_out_hours' => ['nullable', 'integer', 'min:0', 'max:0'],
            'infants_count' => ['nullable', 'integer', 'min:0', 'max:500'],
            'children_count' => ['nullable', 'integer', 'min:0', 'lte:guestCount'],
            'pets_included' => ['nullable', 'boolean'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'policies_accepted' => ['accepted'],
            ...$this->guestInformationRules(emailRequired: true, fullSheet: ! $this->isCottage()),
        ];

        // Guest identity fields come from the registered account, not the booking form.
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

            // `facility_id` only has to exist; it also has to be something the
            // resort sells on its own. An unlisted facility is inventory-only
            // (the Private Function Hall exists to be reserved with Package I),
            // so a crafted POST must not turn it into a standalone reservation
            // the desk would then have to honour.
            $facility = $this->requestedFacility();

            if ($facility !== null && ! $facility->isOnlineBookable()) {
                $validator->errors()->add(
                    'facility_id',
                    'This space can only be reserved as part of a package. Please choose a package or call the resort to arrange it.'
                );
            }
        });
    }

    private function requestedFacility(): ?Facility
    {
        return $this->requestedFacility ??= Facility::query()->find($this->input('facility_id'));
    }

    protected function prepareForValidation(): void
    {
        $this->prepareCottageBooking();
        $this->prepareGuestInformation();

        // The guest sheet's identity fields are rendered readonly in the booking
        // form, so the account is the only author of those values: the form the
        // guest just read and the reservation the server records have to agree,
        // and a crafted POST must not be able to write someone else's identity
        // onto the sheet the desk verifies at check-in. `other_guest_names` is
        // left out because companions are entered on the form, not on the
        // account.
        $profile = GuestProfilePrefill::forUser($this->user());
        unset($profile['other_guest_names']);

        $this->merge($profile);
    }

    private function isCottage(): bool
    {
        return $this->requestedFacility()?->isCottage() === true;
    }

    /**
     * @return list<mixed>
     */
    private function paxTierRules(): array
    {
        $tiers = $this->requestedFacility()?->paxTiers() ?? [];

        if (count($tiers) < 2) {
            return ['nullable', 'integer'];
        }

        return ['required', 'integer', Rule::in($tiers)];
    }
}
