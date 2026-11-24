<?php

namespace Database\Factories;

use App\Enums\GuestGender;
use App\Enums\GuestIdType;
use App\Enums\ReservationGroupStatus;
use App\Enums\ReservationSource;
use App\Models\ReservationGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReservationGroup>
 */
class ReservationGroupFactory extends Factory
{
    protected $model = ReservationGroup::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'group_code' => 'GRP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
            'source' => ReservationSource::Online,
            'status' => ReservationGroupStatus::Pending,
            'guest_name' => fake()->name(),
            'guest_email' => fake()->safeEmail(),
            'guest_phone' => '0917' . fake()->numerify('#######'),
            'guest_place_of_origin' => fake()->city(),
            'guest_nationality' => 'Filipino',
            'guest_company' => null,
            'guest_valid_id' => GuestIdType::PhilSys,
            'guest_gender' => GuestGender::Female,
            'total_amount' => 5000.00,
            'deposit_required' => 2500.00,
            'deposit_due_at' => now()->addDays(2),
            'special_requests' => null,
            'admin_notes' => null,
            'policies_accepted_at' => now(),
        ];
    }
}
