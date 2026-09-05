<?php

namespace Database\Factories;

use App\Enums\TrainingApplicationStatus;
use App\Models\TrainingBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrainingApplication>
 */
class TrainingApplicationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'training_batch_id' => TrainingBatch::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => '09' . $this->faker->numerify('#########'),
            'address' => $this->faker->streetAddress() . ', Ilagan, Isabela',
            'message' => null,
            'status' => TrainingApplicationStatus::Pending->value,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => TrainingApplicationStatus::Accepted->value,
            'decided_at' => now(),
        ]);
    }
}
