<?php

namespace App\Support\Admin;

use App\Support\ResortTime;
use Carbon\Carbon;

final readonly class ReportFilters
{
    public function __construct(
        public ?Carbon $startDate,
        public ?Carbon $endDate,
        public string $grouping = 'month',
        public string $view = 'executive',
        public ?string $facilityType = null,
        public ?string $source = null,
        public ?string $channel = null,
        public ?string $segment = null,
    ) {}

    /**
     * Query values shared by report navigation and exports.
     *
     * @return array<string, string>
     */
    public function query(): array
    {
        return array_filter([
            'start_date' => $this->startDate?->format('Y-m-d'),
            'end_date' => $this->endDate?->format('Y-m-d'),
            'grouping' => $this->grouping,
            'view' => $this->view,
            'facility_type' => $this->facilityType,
            'source' => $this->source,
            'channel' => $this->channel,
            'segment' => $this->segment,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }

    /**
     * Room KPIs need a finite period. All-time reports therefore show today's
     * operational room position while booking and cash metrics remain all-time.
     *
     * @return array{0: Carbon, 1: Carbon, 2: bool}
     */
    public function roomPeriod(): array
    {
        if ($this->startDate || $this->endDate) {
            return [
                ($this->startDate ?? $this->endDate)->copy()->startOfDay(),
                ($this->endDate ?? $this->startDate)->copy()->endOfDay(),
                false,
            ];
        }

        $today = ResortTime::now();

        return [$today->copy()->startOfDay(), $today->copy()->endOfDay(), true];
    }
}
