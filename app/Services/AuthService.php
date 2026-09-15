<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function register(array $data): User
    {
        $user = $this->userRepository->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'gender' => $data['gender'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'address' => $data['address'] ?? null,
            'nationality' => $data['nationality'] ?? null,
            'valid_id_type' => $data['valid_id_type'] ?? null,
            'company' => $data['company'] ?? null,
            'password' => Hash::make($data['password']),
        ]);

        $user->forceFill([
            'email_verified_at' => now(),
            'profile_completed_at' => array_key_exists('profile_completed_at', $data)
                ? $data['profile_completed_at']
                : now(),
            'terms_accepted_at' => ! empty($data['terms_accepted']) ? now() : ($data['terms_accepted_at'] ?? null),
        ])->save();

        $user->assignRole(Role::Customer->value);

        $this->activityLogService->log('user_registered', $user, [
            'email' => $user->email,
        ]);

        return $user;
    }
}
