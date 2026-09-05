<?php

namespace App\Services;

use App\Enums\TrainingApplicationStatus;
use App\Enums\TrainingBatchStatus;
use App\Exceptions\Training\TrainingApplicationException;
use App\Exceptions\Training\TrainingScheduleConflictException;
use App\Models\Facility;
use App\Models\Reservation;
use App\Models\TrainingApplication;
use App\Models\TrainingBatch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Training batches compete for the same rooms, halls and cafe as paying guests.
 * This service is the gatekeeper for that contention: a cohort cannot claim a
 * space that guests already hold, and guests cannot book a space a cohort holds
 * (enforced on the booking side by AvailabilityService).
 */
class TrainingService
{
    /**
     * @param array<string, mixed> $data
     * @param list<int> $facilityIds
     *
     * @throws TrainingScheduleConflictException
     */
    public function createBatch(User $actor, array $data, array $facilityIds): TrainingBatch
    {
        return DB::transaction(function () use ($actor, $data, $facilityIds) {
            $batch = new TrainingBatch($data);
            $batch->status = $data['status'] ?? TrainingBatchStatus::Planned->value;

            $conflicts = $this->conflictsFor($batch, $facilityIds);

            if ($conflicts !== []) {
                throw new TrainingScheduleConflictException(implode(' ', $conflicts));
            }

            $batch->save();
            $batch->facilities()->sync($facilityIds);

            activity()
                ->performedOn($batch)
                ->causedBy($actor)
                ->event('created')
                ->log("Training batch {$batch->cohort_code} scheduled for {$batch->program}.");

            return $batch;
        });
    }

    /**
     * @param array<string, mixed> $data
     * @param list<int> $facilityIds
     *
     * @throws TrainingScheduleConflictException
     */
    public function updateBatch(User $actor, TrainingBatch $batch, array $data, array $facilityIds): TrainingBatch
    {
        return DB::transaction(function () use ($actor, $batch, $data, $facilityIds) {
            $batch->fill($data);

            $conflicts = $this->conflictsFor($batch, $facilityIds);

            if ($conflicts !== []) {
                throw new TrainingScheduleConflictException(implode(' ', $conflicts));
            }

            $batch->save();
            $batch->facilities()->sync($facilityIds);

            activity()
                ->performedOn($batch)
                ->causedBy($actor)
                ->event('updated')
                ->log("Training batch {$batch->cohort_code} updated.");

            return $batch;
        });
    }

    public function changeStatus(User $actor, TrainingBatch $batch, TrainingBatchStatus $status): TrainingBatch
    {
        $previous = $batch->status;
        $batch->update(['status' => $status->value]);

        activity()
            ->performedOn($batch)
            ->causedBy($actor)
            ->event('status_changed')
            ->log("Training batch {$batch->cohort_code} moved from {$previous->label()} to {$status->label()}.");

        return $batch;
    }

    /**
     * Human-readable reasons this batch cannot claim the requested facilities.
     * Written as sentences because they are shown to staff verbatim.
     *
     * @param list<int> $facilityIds
     * @return list<string>
     */
    public function conflictsFor(TrainingBatch $batch, array $facilityIds): array
    {
        if (! $batch->status->isBlocking() || $facilityIds === []) {
            return [];
        }

        $reasons = [];
        $windows = $batch->occupancyWindows();
        $rangeStart = $batch->occupancyStart();
        $rangeEnd = $batch->occupancyEnd();

        $facilities = Facility::with('facilityType')->whereIn('id', $facilityIds)->get();

        foreach ($facilities as $facility) {
            // A cohort in a room also blocks any package built on that room, so
            // the whole coupled group has to be clear.
            $coupledIds = collect([$facility->id])
                ->merge($facility->contendingFacilities()->pluck('id'))
                ->unique()
                ->all();

            $pad = (int) config('resort.stay.max_additional_hours', 12);

            $reservations = Reservation::with('facility')
                ->whereIn('facility_id', $coupledIds)
                ->whereIn('status', Reservation::blockingStatuses())
                ->where('check_in', '<', $rangeEnd->copy()->addHours($pad))
                ->where('check_out', '>', $rangeStart->copy()->subHours($pad))
                ->get();

            foreach ($reservations as $reservation) {
                foreach ($windows as [$start, $end]) {
                    if ($reservation->occupancyStart()->lessThan($end) && $reservation->occupancyEnd()->greaterThan($start)) {
                        $reasons[] = sprintf(
                            'Reservation %s already holds %s on %s.',
                            $reservation->reservation_code,
                            $reservation->facility->name,
                            $reservation->occupancyStart()->format('M d, Y g:i A')
                        );

                        break 2;
                    }
                }
            }

            $otherBatches = TrainingBatch::with('facilities')
                ->blocking()
                ->when($batch->exists, fn ($q) => $q->where('id', '!=', $batch->id))
                ->whereHas('facilities', fn ($q) => $q->whereIn('facilities.id', $coupledIds))
                ->whereDate('start_date', '<=', $batch->end_date)
                ->whereDate('end_date', '>=', $batch->start_date)
                ->get();

            foreach ($otherBatches as $other) {
                foreach ($windows as [$start, $end]) {
                    if ($other->overlapsWindow($start, $end)) {
                        $reasons[] = sprintf(
                            'Training batch %s already uses %s during this period.',
                            $other->cohort_code,
                            $facility->name
                        );

                        break 2;
                    }
                }
            }
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws TrainingApplicationException
     */
    public function apply(TrainingBatch $batch, array $data): TrainingApplication
    {
        if (! $batch->status->acceptsApplications()) {
            throw new TrainingApplicationException('This training batch is no longer accepting applications.');
        }

        $alreadyApplied = $batch->applications()
            ->where('email', $data['email'])
            ->whereIn('status', [TrainingApplicationStatus::Pending->value, TrainingApplicationStatus::Accepted->value])
            ->exists();

        if ($alreadyApplied) {
            throw new TrainingApplicationException('An application for this batch already exists under that email address.');
        }

        $status = $batch->isFull()
            ? TrainingApplicationStatus::Waitlisted
            : TrainingApplicationStatus::Pending;

        return $batch->applications()->create([
            ...$data,
            'status' => $status->value,
        ]);
    }

    /**
     * @throws TrainingApplicationException
     */
    public function decideApplication(
        User $actor,
        TrainingApplication $application,
        TrainingApplicationStatus $status,
        ?string $notes = null
    ): TrainingApplication {
        $batch = $application->trainingBatch;

        if ($status === TrainingApplicationStatus::Accepted
            && $application->status !== TrainingApplicationStatus::Accepted
            && $batch->isFull()) {
            throw new TrainingApplicationException(
                "{$batch->cohort_code} is already full ({$batch->slotsLabel()}). Increase the capacity or waitlist this applicant."
            );
        }

        $application->update([
            'status' => $status->value,
            'staff_notes' => $notes ?? $application->staff_notes,
            'decided_at' => now(),
        ]);

        activity()
            ->performedOn($application)
            ->causedBy($actor)
            ->event('status_changed')
            ->log("Training application for {$application->name} marked {$status->label()}.");

        return $application;
    }

    /**
     * Batches overlapping a period, used by availability checks and reporting.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, TrainingBatch>
     */
    public function blockingBatchesFor(array $facilityIds, Carbon $from, Carbon $to): \Illuminate\Database\Eloquent\Collection
    {
        return TrainingBatch::with('facilities')
            ->blocking()
            ->whereHas('facilities', fn ($q) => $q->whereIn('facilities.id', $facilityIds))
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->get();
    }
}
