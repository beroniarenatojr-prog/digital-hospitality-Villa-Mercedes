<?php

namespace Database\Factories;

use App\Enums\GuestGender;
use App\Enums\GuestIdType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '0917'.fake()->unique()->numerify('#######'),
            'gender' => fake()->randomElement(GuestGender::cases()),
            'address' => fake()->streetAddress().', '.fake()->city(),
            'nationality' => 'Filipino',
            'valid_id_type' => GuestIdType::PhilSys,
            'company' => null,
            'email_verified_at' => now(),
            'profile_completed_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function lightweight(): static
    {
        return $this->state(fn (array $attributes) => [
            'profile_completed_at' => null,
            'phone' => null,
            'address' => null,
            'nationality' => null,
            'valid_id_type' => null,
            'company' => null,
            'gender' => null,
            'name' => Str::before($attributes['email'] ?? 'guest@example.com', '@'),
        ]);
    }
}
