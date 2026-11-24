<?php

namespace App\Support;

use App\Models\User;

final class GuestProfilePrefill
{
    /**
     * Prefill booking guest-sheet fields from the signed-in account.
     *
     * @return array{
     *   guest_name: string,
     *   guest_place_of_origin: string,
     *   guest_nationality: string,
     *   guest_company: string,
     *   guest_valid_id: string,
     *   guest_gender: string,
     *   guest_phone: string,
     *   guest_email: string,
     *   other_guest_names: list<array{name: string, valid_id: ?string}>|list<string>
     * }
     */
    public static function forUser(?User $user): array
    {
        return [
            'guest_name' => $user?->name ?? '',
            'guest_place_of_origin' => $user?->address ?? '',
            'guest_nationality' => $user?->nationality ?? '',
            'guest_company' => $user?->company ?? '',
            'guest_valid_id' => $user?->valid_id_type?->value ?? '',
            'guest_gender' => $user?->gender?->value ?? '',
            'guest_phone' => $user?->phone ?? '',
            'guest_email' => $user?->email ?? '',
            'other_guest_names' => [],
        ];
    }
}
