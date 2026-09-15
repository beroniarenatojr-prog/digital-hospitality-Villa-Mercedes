<?php

namespace App\Http\Requests\Concerns;

use App\Support\GuestInformation;
use Illuminate\Validation\Validator;

trait PreparesGuestInformation
{
    /**
     * @return array<string, mixed>
     */
    protected function guestInformationRules(bool $emailRequired, bool $fullSheet = true): array
    {
        $maxCapacity = $this->guestMaxCapacity();

        return GuestInformation::rules(
            emailRequired: $emailRequired,
            maxCompanions: GuestInformation::companionLimit($maxCapacity),
            fullSheet: $fullSheet,
        );
    }

    protected function guestMaxCapacity(): ?int
    {
        return GuestInformation::maxCapacityForFacilityId($this->input('facility_id'), $this->input('pax_tier'));
    }

    protected function prepareGuestInformation(): void
    {
        $maxCapacity = $this->guestMaxCapacity();
        $normalized = GuestInformation::mergeNormalizedInput($this->all());

        $this->merge([
            ...$normalized,
            'guestCount' => GuestInformation::syncGuestCount(
                (int) $this->input('guestCount', 1),
                $normalized['other_guest_names'] ?? [],
                $maxCapacity
            ),
        ]);
    }

    protected function validateGuestPartySize(Validator $validator): void
    {
        GuestInformation::validatePartySize(
            $validator,
            (int) $this->input('guestCount', 1),
            $this->guestMaxCapacity()
        );
    }
}
