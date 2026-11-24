<?php

namespace App\Services\Admin;

use App\Enums\ActivityActionType;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ActivityLogQueryService
{
    /**
     * @return LengthAwarePaginator<int, ActivityLog>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        $query = ActivityLog::query()
            ->with(['causer', 'subject'])
            ->tap(fn (Builder $builder) => $this->constrainToStaffActions($builder));

        if ($request->filled('staff_id')) {
            $query->where('causer_type', (new User)->getMorphClass())
                ->where('causer_id', $request->integer('staff_id'));
        }

        if ($request->filled('action_type')) {
            $actionType = ActivityActionType::tryFrom((string) $request->input('action_type'));
            if ($actionType instanceof ActivityActionType) {
                $this->constrainToActionType($query, $actionType);
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_until')) {
            $query->whereDate('created_at', '<=', $request->input('date_until'));
        }

        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * @return Collection<int, User>
     */
    public function staffOptions(): Collection
    {
        return User::role(Role::Administrator->value)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @param  Builder<ActivityLog>  $query
     */
    private function constrainToStaffActions(Builder $query): void
    {
        $adminIds = User::role(Role::Administrator->value)->pluck('id');

        $query->where(function (Builder $scope) use ($adminIds) {
            $scope->where(function (Builder $staff) use ($adminIds) {
                $staff->where('causer_type', (new User)->getMorphClass())
                    ->whereIn('causer_id', $adminIds);
            })->orWhere(function (Builder $system) {
                $system->whereNull('causer_id')
                    ->whereIn('event', ActivityActionType::systemEvents());
            });
        });
    }

    /**
     * @param  Builder<ActivityLog>  $query
     */
    private function constrainToActionType(Builder $query, ActivityActionType $actionType): void
    {
        if ($actionType === ActivityActionType::Approve) {
            $query->where(function (Builder $approve) {
                $approve->whereIn('event', ['payment_verified', 'late_checkout_approved'])
                    ->orWhere(function (Builder $reservation) {
                        $reservation->where('event', 'status_changed')
                            ->where(fn (Builder $description) => $description->where('description', 'like', '%to Approved%')->orWhere('description', 'like', '%to Confirmed%'));
                    });
            });

            return;
        }

        $query->whereIn('event', $actionType->events());
    }
}
