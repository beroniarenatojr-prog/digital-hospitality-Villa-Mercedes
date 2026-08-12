<?php

namespace App\Services\Admin;

use App\Enums\Role;
use App\Exceptions\Admin\UnauthorizedAdminActionException;
use App\Models\User;

class CustomerManagementService
{
    /**
     * Ensure the actor is an Administrator.
     */
    private function authorizeAdmin(User $actor): void
    {
        if (!$actor->hasRole(Role::Administrator->value)) {
            throw new UnauthorizedAdminActionException('Only administrators can access customer information.');
        }
    }

    /**
     * Retrieve a customer by ID securely.
     */
    public function getCustomer(User $actor, int $customerId): User
    {
        $this->authorizeAdmin($actor);
        
        $customer = User::findOrFail($customerId);

        // Security assurance: explicitly prevent password and token exposure
        $customer->makeHidden(['password', 'remember_token']);

        return $customer;
    }

    /**
     * Retrieve a customer with their active reservations securely.
     */
    public function getCustomerWithReservations(User $actor, int $customerId): User
    {
        $this->authorizeAdmin($actor);

        $customer = User::with('reservations.payments')->findOrFail($customerId);
        
        // Security assurance: explicitly prevent password and token exposure
        $customer->makeHidden(['password', 'remember_token']);
        
        return $customer;
    }
}
