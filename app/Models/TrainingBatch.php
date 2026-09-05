<?php

namespace App\Models;

use App\Enums\TrainingApplicationStatus;
use App\Enums\TrainingBatchStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingBatch extends Model
{
    /** @use HasFactory<\Database\Factories\TrainingBatchFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'program',
        'cohort_code',
        'start_date',
        'end_date',
        'daily_start_time',
        'daily_end_time',
        'trainee_capacity',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'trainee_capacity' => 'integer',
            'status' => TrainingBatchStatus::class,
        ];
    }

    /**
     * The physical resources this cohort occupies while it runs.
     *
     * @return BelongsToMany<Facility, $this>
     */
    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'training_batch_facility')->withTimestamps();
    }

    /**
     * @return HasMany<TrainingApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(TrainingApplication::class);
    }

    /**
     * @param Builder<TrainingBatch> $query
     */
    public function scopeBlocking(Builder $query): void
    {
        $query->whereIn('status', TrainingBatchStatus::blocking());
    }

    /**
     * @param Builder<TrainingBatch> $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->whereIn('status', TrainingBatchStatus::blocking())
            ->whereDate('end_date', '>=', now()->toDateString());
    }

    /**
     * Outer bound of the cohort, used for coarse date filtering.
     */
    public function occupancyStart(): Carbon
    {
        return $this->start_date->copy()->setTimeFromTimeString($this->daily_start_time ?? '00:00:00');
    }

    public function occupancyEnd(): Carbon
    {
        return $this->end_date->copy()->setTimeFromTimeString($this->daily_end_time ?? '23:59:59');
    }

    /**
     * The precise periods the facilities are held.
     *
     * With session hours set, the cohort holds its rooms only during those hours
     * each day, which leaves evenings sellable. Without session hours the batch
     * is treated as a continuous hold across the whole date range.
     *
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    public function occupancyWindows(): array
    {
        if (! $this->daily_start_time || ! $this->daily_end_time) {
            return [[$this->occupancyStart(), $this->occupancyEnd()]];
        }

        $windows = [];
        $day = $this->start_date->copy()->startOfDay();
        $lastDay = $this->end_date->copy()->startOfDay();

        while ($day->lessThanOrEqualTo($lastDay)) {
            $windows[] = [
                $day->copy()->setTimeFromTimeString($this->daily_start_time),
                $day->copy()->setTimeFromTimeString($this->daily_end_time),
            ];

            $day->addDay();
        }

        return $windows;
    }

    public function overlapsWindow(Carbon $from, Carbon $to): bool
    {
        foreach ($this->occupancyWindows() as [$start, $end]) {
            if ($start->lessThan($to) && $end->greaterThan($from)) {
                return true;
            }
        }

        return false;
    }

    public function acceptedCount(): int
    {
        return $this->applications()
            ->where('status', TrainingApplicationStatus::Accepted->value)
            ->count();
    }

    public function slotsRemaining(): int
    {
        return max(0, $this->trainee_capacity - $this->acceptedCount());
    }

    public function isFull(): bool
    {
        return $this->slotsRemaining() <= 0;
    }

    public function slotsLabel(): string
    {
        return $this->acceptedCount() . ' / ' . $this->trainee_capacity . ' slots filled';
    }

    public function sessionHoursLabel(): string
    {
        if (! $this->daily_start_time || ! $this->daily_end_time) {
            return 'Full-day hold';
        }

        return Carbon::parse($this->daily_start_time)->format('g:i A')
            . ' – ' . Carbon::parse($this->daily_end_time)->format('g:i A');
    }

    public function dateRangeLabel(): string
    {
        return $this->start_date->format('M d, Y') . ' – ' . $this->end_date->format('M d, Y');
    }
}
