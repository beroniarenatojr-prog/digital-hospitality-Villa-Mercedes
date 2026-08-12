<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(Role::Administrator->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasRole(Role::Administrator->value) || $user->is($model);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Administrator->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasRole(Role::Administrator->value) || $user->is($model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasRole(Role::Administrator->value) && ! $user->is($model);
    }
}
