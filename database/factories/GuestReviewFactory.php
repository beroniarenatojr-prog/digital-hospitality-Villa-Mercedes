<?php

namespace Database\Factories;

use App\Models\GuestReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuestReview>
 */
class GuestReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reservation_id' => null,
            'rating' => 5,
            'body' => fake()->paragraph(),
            'stay_label' => 'Overnight stay',
            'is_published' => true,
            'featured_on_home' => false,
            'published_at' => now(),
        ];
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'featured_on_home' => true,
        ]);
    }
}
