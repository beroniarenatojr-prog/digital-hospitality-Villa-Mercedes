<?php

namespace Database\Factories;

use App\Enums\TrainingBatchStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrainingBatch>
 */
class TrainingBatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('+1 week', '+2 months');
        $end = (clone $start)->modify('+30 days');

        return [
            'program' => $this->faker->randomElement(['Housekeeping NC II', 'Barista NC II']),
            'cohort_code' => strtoupper(Str::random(3)) . '-' . now()->year . '-' . $this->faker->unique()->numberBetween(1, 999),
            'start_date' => $start,
            'end_date' => $end,
            'daily_start_time' => '08:00:00',
            'daily_end_time' => '17:00:00',
            'trainee_capacity' => $this->faker->numberBetween(10, 25),
            'status' => TrainingBatchStatus::Planned->value,
            'notes' => null,
        ];
    }

    public function fullDayHold(): static
    {
        return $this->state(fn () => [
            'daily_start_time' => null,
            'daily_end_time' => null,
        ]);
    }

    public function ongoing(): static
    {
        return $this->state(fn () => [
            'status' => TrainingBatchStatus::Ongoing->value,
            'start_date' => now()->subWeek(),
            'end_date' => now()->addWeeks(3),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => TrainingBatchStatus::Completed->value,
        ]);
    }
}
