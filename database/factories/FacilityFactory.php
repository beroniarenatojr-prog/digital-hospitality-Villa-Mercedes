<?php

namespace Database\Factories;

use App\Enums\FacilityStatus;
use App\Models\FacilityType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Facility>
 */
class FacilityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);
        
        return [
            'facility_type_id' => FacilityType::factory(),
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => $this->faker->paragraph(),
            'capacity' => $this->faker->numberBetween(2, 250),
            'status' => $this->faker->randomElement(FacilityStatus::values()),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
