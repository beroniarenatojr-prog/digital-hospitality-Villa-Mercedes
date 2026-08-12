<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'amount' => $this->faker->randomFloat(2, 2000, 10000),
            'method' => $this->faker->randomElement(PaymentMethod::values()),
            'reference_number' => $this->faker->optional()->bothify('REF-########'),
            'payment_date' => $this->faker->date(),
            'status' => PaymentStatus::Pending->value,
        ];
    }
}
