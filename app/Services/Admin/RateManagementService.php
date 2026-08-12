<?php

namespace App\Services\Admin;

use App\Enums\RateType;
use App\Enums\Role;
use App\Exceptions\Admin\UnauthorizedAdminActionException;
use App\Models\Rate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RateManagementService
{
    /**
     * Ensure the actor is an Administrator.
     */
    private function authorizeAdmin(User $actor): void
    {
        if (!$actor->hasRole(Role::Administrator->value)) {
            throw new UnauthorizedAdminActionException('Only administrators can manage rates.');
        }
    }

    /**
     * Create a new rate.
     */
    public function createRate(User $actor, array $data): Rate
    {
        $this->authorizeAdmin($actor);

        if (isset($data['amount']) && $data['amount'] <= 0) {
            throw new InvalidArgumentException('Rate amount must be greater than zero.');
        }

        if (isset($data['type']) && $data['type'] === RateType::Seasonal) {
            if (empty($data['effective_from']) || empty($data['effective_until'])) {
                throw new InvalidArgumentException('Seasonal rates require both effective_from and effective_until dates.');
            }
            if ($data['effective_until'] < $data['effective_from']) {
                throw new InvalidArgumentException('Effective until date must be on or after effective from date.');
            }
        }

        return DB::transaction(function () use ($actor, $data) {
            $rate = new Rate();
            $rate->facility_id = $data['facility_id'];
            $rate->name = $data['name'];
            $rate->type = $data['type'] ?? RateType::Base;
            $rate->amount = number_format((float) $data['amount'], 2, '.', '');
            $rate->unit = $data['unit'] ?? 'per_night';
            $rate->effective_from = $data['effective_from'] ?? null;
            $rate->effective_until = $data['effective_until'] ?? null;
            $rate->is_active = $data['is_active'] ?? true;
            
            $rate->save();

            activity()
                ->causedBy($actor)
                ->performedOn($rate)
                ->event('rate_created')
                ->log("Rate '{$rate->name}' was created.");

            return $rate;
        });
    }

    /**
     * Update an existing rate without destroying historical pricing snapshots.
     */
    public function updateRate(User $actor, Rate $rate, array $data): Rate
    {
        $this->authorizeAdmin($actor);

        if (isset($data['amount']) && $data['amount'] <= 0) {
            throw new InvalidArgumentException('Rate amount must be greater than zero.');
        }

        if (isset($data['effective_from']) || isset($data['effective_until'])) {
            $from = $data['effective_from'] ?? $rate->effective_from;
            $until = $data['effective_until'] ?? $rate->effective_until;
            $type = $data['type'] ?? $rate->type;

            if ($type === RateType::Seasonal) {
                if (!$from || !$until) {
                    throw new InvalidArgumentException('Seasonal rates require both effective_from and effective_until dates.');
                }
                if ($until < $from) {
                    throw new InvalidArgumentException('Effective until date must be on or after effective from date.');
                }
            }
        }

        return DB::transaction(function () use ($actor, $rate, $data) {
            if (array_key_exists('name', $data)) {
                $rate->name = $data['name'];
            }
            if (array_key_exists('type', $data) && $data['type'] instanceof RateType) {
                $rate->type = $data['type'];
            }
            if (array_key_exists('amount', $data)) {
                $rate->amount = number_format((float) $data['amount'], 2, '.', '');
            }
            if (array_key_exists('unit', $data)) {
                $rate->unit = $data['unit'];
            }
            if (array_key_exists('effective_from', $data)) {
                $rate->effective_from = $data['effective_from'];
            }
            if (array_key_exists('effective_until', $data)) {
                $rate->effective_until = $data['effective_until'];
            }
            if (array_key_exists('is_active', $data)) {
                $rate->is_active = $data['is_active'];
            }

            $rate->save();

            activity()
                ->causedBy($actor)
                ->performedOn($rate)
                ->event('rate_updated')
                ->log("Rate '{$rate->name}' was updated.");

            return $rate;
        });
    }

    /**
     * Activate a rate explicitly.
     */
    public function activateRate(User $actor, Rate $rate): Rate
    {
        $this->authorizeAdmin($actor);

        $rate->is_active = true;
        $rate->save();

        activity()
            ->causedBy($actor)
            ->performedOn($rate)
            ->event('rate_activated')
            ->log("Rate '{$rate->name}' was activated.");

        return $rate;
    }

    /**
     * Deactivate a rate explicitly.
     */
    public function deactivateRate(User $actor, Rate $rate): Rate
    {
        $this->authorizeAdmin($actor);

        $rate->is_active = false;
        $rate->save();

        activity()
            ->causedBy($actor)
            ->performedOn($rate)
            ->event('rate_deactivated')
            ->log("Rate '{$rate->name}' was deactivated.");

        return $rate;
    }
}
