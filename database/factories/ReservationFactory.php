<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkIn = $this->faker->dateTimeBetween('now', '+1 month');
        $checkOut = (clone $checkIn)->modify('+2 days');
        $basePrice = $this->faker->randomFloat(2, 2000, 10000);
        
        return [
            'user_id' => User::factory(),
            'facility_id' => Facility::factory(),
            'reservation_code' => 'VM-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4)),
            'reservation_date' => $this->faker->date(),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guest_count' => $this->faker->numberBetween(1, 5),
            'special_requests' => null,
            'base_price' => $basePrice,
            'additional_charges' => 0,
            'total_price' => $basePrice,
            'status' => ReservationStatus::Pending->value,
            'reservation_source' => \App\Enums\ReservationSource::Online->value,
        ];
    }
}
