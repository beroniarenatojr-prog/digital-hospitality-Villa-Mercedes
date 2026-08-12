<?php

namespace Database\Factories;

use App\Enums\RateType;
use App\Models\Facility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rate>
 */
class RateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'name' => 'Base Rate',
            'type' => RateType::Base->value,
            'amount' => $this->faker->randomFloat(2, 1000, 10000),
            'unit' => 'per night',
            'is_active' => true,
        ];
    }
}
