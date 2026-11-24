<?php

namespace App\Support;

use App\Enums\GuestGender;
use App\Enums\GuestIdType;
use App\Models\Facility;
use App\Rules\PhilippineMobileNumber;
use App\Rules\PersonName;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class GuestInformation
{
    /**
     * Companions besides the primary guest. A 10-pax room can list 9 other names.
     */
    public static function companionLimit(?int $maxCapacity): ?int
    {
        if ($maxCapacity === null) {
            return null;
        }

        return max(0, $maxCapacity - 1);
    }

    public static function maxCapacityForFacilityId(mixed $facilityId, mixed $paxTier = null): ?int
    {
        if (! filled($facilityId) || ! is_numeric($facilityId)) {
            return 1;
        }

        $facility = Facility::query()->find((int) $facilityId);

        if ($facility?->isCottage()) {
            return null;
        }

        $max = max(1, (int) ($facility?->effectiveMaxCapacity() ?? 1));

        if (is_numeric($paxTier) && (int) $paxTier > 0) {
            return max(1, min($max, (int) $paxTier));
        }

        return $max;
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(bool $emailRequired = true, ?int $maxCompanions = null, bool $fullSheet = true): array
    {
        $otherGuestRules = ['nullable', 'array'];

        if ($maxCompanions !== null) {
            $otherGuestRules[] = 'max:'.$maxCompanions;
        }

        $sensitive = $fullSheet ? 'required' : 'nullable';

        return [
            'guest_name' => ['required', 'string', 'max:255', new PersonName],
            'guest_place_of_origin' => [$sensitive, 'string', 'max:255'],
            'guest_nationality' => [$sensitive, 'string', 'max:100', new PersonName],
            'guest_company' => ['nullable', 'string', 'max:255'],
            'guest_valid_id' => [$sensitive, Rule::enum(GuestIdType::class)],
            'guest_gender' => [$sensitive, Rule::enum(GuestGender::class)],
            'guest_phone' => ['required', 'string', 'size:11', new PhilippineMobileNumber],
            'guest_email' => [$emailRequired ? 'required' : 'nullable', 'email', 'max:255'],
            'other_guest_names' => $otherGuestRules,
            'other_guest_names.*.name' => ['required', 'string', 'max:255', new PersonName],
            'other_guest_names.*.valid_id' => ['nullable', Rule::enum(GuestIdType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'guest_name' => 'name',
            'guest_place_of_origin' => 'address',
            'guest_nationality' => 'nationality',
            'guest_company' => 'company name/agency',
            'guest_valid_id' => 'valid ID type',
            'guest_gender' => 'gender',
            'guest_phone' => 'contact #',
            'guest_email' => 'email address',
            'other_guest_names' => 'other guest names',
            'other_guest_names.*.name' => 'other guest name',
            'other_guest_names.*.valid_id' => 'other guest valid ID type',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mergeNormalizedInput(array $data): array
    {
        $email = isset($data['guest_email']) ? strtolower(trim((string) $data['guest_email'])) : '';

        return [
            'guest_name' => isset($data['guest_name']) ? trim((string) $data['guest_name']) : null,
            'guest_place_of_origin' => isset($data['guest_place_of_origin']) ? trim((string) $data['guest_place_of_origin']) : null,
            'guest_nationality' => isset($data['guest_nationality']) ? trim((string) $data['guest_nationality']) : null,
            'guest_company' => filled($data['guest_company'] ?? null) ? trim((string) $data['guest_company']) : null,
            'guest_valid_id' => filled($data['guest_valid_id'] ?? null) ? trim((string) $data['guest_valid_id']) : null,
            'guest_phone' => isset($data['guest_phone']) ? PhilippineMobileNumber::normalize(trim((string) $data['guest_phone'])) : null,
            'guest_email' => $email !== '' ? $email : null,
            'other_guest_names' => self::normalizeOtherGuests($data['other_guest_names'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{
     *   guest_name: ?string,
     *   guest_place_of_origin: ?string,
     *   guest_nationality: ?string,
     *   guest_company: ?string,
     *   guest_valid_id: ?string,
     *   guest_gender: ?string,
     *   guest_phone: ?string,
     *   guest_email: ?string,
     *   other_guest_names: list<array{name: string, valid_id: ?string}>
     * }
     */
    public static function fromValidated(array $validated): array
    {
        $idType = $validated['guest_valid_id'] ?? null;
        if ($idType instanceof GuestIdType) {
            $idType = $idType->value;
        }

        $gender = $validated['guest_gender'] ?? null;
        if ($gender instanceof GuestGender) {
            $gender = $gender->value;
        }

        return [
            'guest_name' => $validated['guest_name'] ?? null,
            'guest_place_of_origin' => $validated['guest_place_of_origin'] ?? null,
            'guest_nationality' => $validated['guest_nationality'] ?? null,
            'guest_company' => $validated['guest_company'] ?? null,
            'guest_valid_id' => is_string($idType) && $idType !== '' ? $idType : null,
            'guest_gender' => is_string($gender) && $gender !== '' ? $gender : null,
            'guest_phone' => $validated['guest_phone'] ?? null,
            'guest_email' => $validated['guest_email'] ?? null,
            'other_guest_names' => self::normalizeOtherGuests($validated['other_guest_names'] ?? []),
        ];
    }

    /**
     * @param  list<array{name: string, valid_id: ?string}>|list<string>|array<int, mixed>  $otherNames
     */
    public static function syncGuestCount(int $guestCount, array $otherNames, ?int $maxCapacity): int
    {
        $namedCompanions = count(self::normalizeOtherGuests($otherNames));

        $count = max($guestCount, 1 + $namedCompanions, 1);

        return $maxCapacity === null ? $count : min($maxCapacity, $count);
    }

    public static function validatePartySize(Validator $validator, int $guestCount, ?int $maxCapacity): void
    {
        if ($maxCapacity === null) {
            return;
        }

        $named = count(self::normalizeOtherGuests($validator->getData()['other_guest_names'] ?? []));
        $allowed = self::companionLimit($maxCapacity);

        if ($named > $allowed) {
            $validator->errors()->add(
                'other_guest_names',
                $allowed === 0
                    ? 'This space has no extra guest-name slots besides the primary guest.'
                    : "This room holds up to {$maxCapacity} guests. You can add at most {$allowed} other guest names besides the primary guest."
            );
        }
    }

    /**
     * Companion records for filing: name plus optional valid ID type.
     *
     * Accepts legacy plain-string lists and the newer `{name, valid_id}` shape.
     *
     * @return list<array{name: string, valid_id: ?string}>
     */
    public static function normalizeOtherGuests(mixed $entries, bool $keepEmpty = false): array
    {
        $guests = collect(is_array($entries) ? $entries : [])
            ->map(function ($entry): array {
                if (is_string($entry)) {
                    return [
                        'name' => trim($entry),
                        'valid_id' => null,
                    ];
                }

                if (! is_array($entry)) {
                    return ['name' => '', 'valid_id' => null];
                }

                $validId = $entry['valid_id'] ?? $entry['valid_id_type'] ?? null;
                if ($validId instanceof GuestIdType) {
                    $validId = $validId->value;
                }

                return [
                    'name' => isset($entry['name']) ? trim((string) $entry['name']) : '',
                    'valid_id' => filled($validId) ? trim((string) $validId) : null,
                ];
            });

        if (! $keepEmpty) {
            $guests = $guests->filter(fn (array $guest): bool => $guest['name'] !== '');
        }

        return $guests
            ->map(function (array $guest): array {
                $idType = $guest['valid_id'] !== null
                    ? GuestIdType::tryFrom($guest['valid_id'])
                    : null;

                return [
                    'name' => $guest['name'],
                    'valid_id' => $idType?->value,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function normalizeOtherGuestNames(mixed $names): array
    {
        return array_column(self::normalizeOtherGuests($names), 'name');
    }
}
