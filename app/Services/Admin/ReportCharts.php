<?php

namespace App\Services\Admin;

use App\Enums\ReservationStatus;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Reservation;
use App\Support\Admin\ReportFilters;
use App\Support\ResortTime;
use Carbon\Carbon;

trait ReportCharts
{
    public function getCharts(ReportFilters $filters): array
    {
        $zone = ResortTime::timezone();
        $today = ResortTime::today();
        $reservations = Reservation::with('facility.facilityType');
        $this->applyReservationDimensions($reservations, $filters->facilityType, $filters->source);
        if ($filters->startDate || $filters->endDate) {
            $reservations->where(function ($dateQuery) use ($filters): void {
                $dateQuery->where(function ($bookingQuery) use ($filters): void {
                    $this->applyDateFilter($bookingQuery, 'reservations.created_at', $filters->startDate, $filters->endDate);
                })->orWhere(function ($earnedQuery) use ($filters): void {
                    $earnedQuery->where(function ($completedQuery): void {
                        $completedQuery
                            ->where('reservations.status', ReservationStatus::Completed)
                            ->orWhereNotNull('reservations.checked_out_at');
                    });
                    $this->applyDateFilter($earnedQuery, 'reservations.check_out', $filters->startDate, $filters->endDate);
                });
            });
        }
        $reservations = $reservations->get();
        $payments = Payment::where('status', 'verified')->with('reservation.facility.facilityType');
        $this->applyPaymentDimensions($payments, $filters->facilityType, $filters->source);
        $this->applyDateFilter($payments, 'payments.payment_date', $filters->startDate, $filters->endDate);
        $payments = $payments->get();
        $accepted = $reservations->filter(fn ($r) => in_array($r->status, [ReservationStatus::Approved, ReservationStatus::Completed]));
        $events = [];
        foreach ($accepted as $r) {
            $events[] = ['date' => $r->created_at->copy()->timezone($zone), 'key' => 'booking_value', 'value' => (float) $r->total_price, 'record' => $r];
            if ($r->status === ReservationStatus::Completed || $r->checked_out_at) {
                $events[] = ['date' => $r->check_out, 'key' => 'earned_revenue', 'value' => (float) $r->total_price, 'record' => $r];
            }
        }
        foreach ($payments as $p) {
            $events[] = ['date' => $p->payment_date, 'key' => 'cash_collected', 'value' => (float) $p->amount, 'record' => $p];
        }
        $dates = collect($events)->pluck('date')->map(fn ($d) => $d->toDateString());
        $dates = $dates->merge($reservations->map(fn ($r) => $r->created_at->copy()->timezone($zone)->toDateString()));
        $from = $filters->startDate?->copy() ?? Carbon::parse($dates->min() ?? $today->toDateString(), $zone);
        $to = $filters->endDate?->copy() ?? Carbon::parse($dates->max() ?? $today->toDateString(), $zone);
        if ($from->gt($to)) {
            $filters->startDate ? $to = $from->copy() : $from = $to->copy();
        }
        $rows = [];
        $cursor = $from->copy();
        match ($filters->grouping) {
            'week' => $cursor->startOfWeek(), 'year' => $cursor->startOfYear(), default => $cursor->startOfMonth()
        };
        while ($cursor->lte($to)) {
            $key = $this->bookingTrendsPeriodKey($cursor, $filters->grouping);
            $rows[$key] = ['label' => $key, 'booking_value' => 0, 'earned_revenue' => 0, 'cash_collected' => 0];
            match ($filters->grouping) {
                'week' => $cursor->addWeek(), 'year' => $cursor->addYear(), default => $cursor->addMonth()
            };
        }
        $records = [];
        foreach ($events as $event) {
            if ($event['date']->toDateString() < $from->toDateString() || $event['date']->toDateString() > $to->toDateString()) {
                continue;
            }
            $key = $this->bookingTrendsPeriodKey($event['date'], $filters->grouping);
            $rows[$key][$event['key']] += $event['value'];
            $r = $event['record'];
            $records[] = ['label' => ($r instanceof Payment ? 'Payment #'.$r->id : $r->reservation_code).' · '.str_replace('_', ' ', $event['key']), 'date' => $event['date']->toDateString(), 'value' => $event['value'], 'url' => route($r instanceof Payment ? 'admin.payments.show' : 'admin.reservations.show', $r)];
        }
        $comparison = 'No valid comparison available — select both dates.';
        if ($filters->startDate && $filters->endDate) {
            $days = (int) $from->diffInDays($to) + 1;
            $comparison = 'Previous period: '.$from->copy()->subDays($days)->format('M j, Y').'–'.$from->copy()->subDay()->format('M j, Y');
        }
        $series = ['booking_value' => 'Booking value', 'earned_revenue' => 'Earned operational revenue', 'cash_collected' => 'Cash collected'];
        $financial = $this->reportChart('financial', 'Booking, delivery and collections', 'line', $series, array_values($rows), 'Partial data', 'Accepted bookings by creation date; completed or checked-out services by scheduled check out; verified receipts by payment_date. Delivered service value is not formal accrual accounting. Completeness depends on recorded checkout evidence.', $comparison, $records, true);
        $financial['basis'] = $from->format('M j, Y').'–'.$to->format('M j, Y').' · '.ResortTime::timezone();

        $departments = $this->getDepartmentRevenue($filters);
        $department = $this->reportChart('departments', 'Revenue by service department', 'bar', $series, collect($departments)->where('tracked', true)->map(fn ($d) => ['label' => $d['label'], ...array_intersect_key($d, $series)])->values()->all(), 'Historical classification incomplete', 'Amounts are attributed to the booked facility department. Additional charges have no line-item department classification. F&B: Not tracked yet — sales transactions are required.', $comparison, $records, true);

        $movementReservations = Reservation::with('facility.facilityType')
            ->where('status', ReservationStatus::Approved)
            ->where(function ($movementQuery) use ($today): void {
                $lastDay = $today->copy()->addDays(13)->endOfDay();
                $movementQuery
                    ->whereBetween('check_in', [$today->copy()->startOfDay(), $lastDay])
                    ->orWhereBetween('check_out', [$today->copy()->startOfDay(), $lastDay]);
            });
        $this->applyReservationDimensions($movementReservations, $filters->facilityType, $filters->source);
        $movementReservations = $movementReservations->get();

        $arrivals = [];
        for ($i = 0; $i < 14; $i++) {
            $day = $today->copy()->addDays($i);
            $arrivals[] = ['label' => $day->format('M j'), 'arrivals' => $movementReservations->filter(fn ($r) => $r->check_in->toDateString() === $day->toDateString())->count(), 'departures' => $movementReservations->filter(fn ($r) => $r->check_out->toDateString() === $day->toDateString())->count(), 'forecast' => true];
        }
        $movementRecords = $movementReservations->map(fn ($r) => ['label' => $r->reservation_code, 'date' => $r->check_in->format('M j').'–'.$r->check_out->format('M j'), 'value' => null, 'url' => route('admin.reservations.show', $r)])->values()->all();
        $movement = $this->reportChart('movement', 'Check ins and check outs · next 14 days', 'column', ['arrivals' => 'Scheduled check ins', 'departures' => 'Scheduled check outs'], $arrivals, 'Complete', 'Approved reservations on scheduled check in and check out dates. Lighter columns show on-books demand. Today’s action queues are independent of historical report dates.', 'No comparison period — this chart looks forward, and historical approval snapshots are not tracked.', $movementRecords);

        $facilities = Facility::with('facilityType')->where('is_active', true)->when($filters->facilityType, fn ($q, $type) => $q->whereHas('facilityType', fn ($q) => $q->where('slug', $type)))->orderBy('name')->get();
        // Physical occupancy must include all sources even when the report selects one source.
        $occupied = Reservation::whereNotNull('checked_in_at')->whereNull('checked_out_at')->pluck('facility_id');
        $onBooksQuery = Reservation::query()
            ->where('status', ReservationStatus::Approved)
            ->whereNull('checked_in_at')
            ->where('check_in', '<', $today->copy()->addDay())
            ->where('check_out', '>', $today);
        $this->applyReservationDimensions($onBooksQuery, $filters->facilityType, $filters->source);
        $onBooks = $onBooksQuery->pluck('facility_id');
        $grid = $facilities->map(fn ($f) => ['label' => $f->name, 'state' => $occupied->contains($f->id) ? 'Occupied' : ucfirst($f->status->value), 'type' => $f->facilityType->name, 'url' => route('admin.facilities.show', $f)])->all();
        $occupancy = $facilities->groupBy('facilityType.name')->map(fn ($group, $type) => ['label' => $type, 'actual' => $group->whereIn('id', $occupied)->count(), 'on_books' => $group->whereIn('id', $onBooks)->count(), 'inventory' => $group->count()])->sortByDesc('actual')->values()->all();
        $occupancyChart = $this->reportChart('occupancy', 'Occupied inventory by facility type · today', 'bar', ['actual' => 'Actually occupied', 'on_books' => 'Approved on-books', 'inventory' => 'Active inventory'], $occupancy, 'Partial data', 'Unique facilities, not guests or room nights. Actual occupancy uses all sources to reflect physical occupancy. On-books uses the selected source. Capacity-based facilities can serve multiple bookings; these bars are facility counts.', 'Current snapshot; historical inventory snapshots are not tracked.', array_map(fn ($f) => ['label' => $f['label'].' · '.$f['state'], 'date' => $today->toDateString(), 'value' => null, 'url' => $f['url']], $grid));
        $bookingRecords = $reservations->filter(function ($r) use ($filters, $zone) {
            $date = $r->created_at->copy()->timezone($zone)->toDateString();

            return (! $filters->startDate || $date >= $filters->startDate->toDateString()) && (! $filters->endDate || $date <= $filters->endDate->toDateString());
        })->map(fn ($r) => ['label' => $r->reservation_code, 'date' => $r->created_at->copy()->timezone($zone)->toDateString(), 'value' => 1, 'url' => route('admin.reservations.show', $r)])->values()->all();
        $summary = $this->getBookingSummary($filters->startDate, $filters->endDate, $filters->facilityType, $filters->source);
        $sourceChart = $this->reportChart('sources', 'Reservations by Source', 'bar', ['count' => 'Reservations'], collect($summary['by_source'])->map(fn ($count, $source) => ['label' => ucfirst(str_replace('_', ' ', $source)), 'count' => $count])->values()->all(), 'Complete', 'Recorded reservation requests grouped by source and creation date, including pending and cancelled outcomes. Online and walk-in are the tracked sources.', $comparison, $bookingRecords);
        $trendRows = [];
        foreach ($bookingRecords as $record) {
            $key = $this->bookingTrendsPeriodKey(Carbon::parse($record['date'], $zone), $filters->grouping);
            $trendRows[$key] = ($trendRows[$key] ?? 0) + 1;
        }
        $trendChart = $this->reportChart('reservations', 'Reservation Trends', 'line', ['count' => 'Reservations'], array_map(fn ($row) => ['label' => $row['label'], 'count' => $trendRows[$row['label']] ?? 0], array_values($rows)), 'Complete', 'Reservation requests by creation date, including pending and cancelled outcomes.', $comparison, $bookingRecords);

        return ['financial' => $financial, 'departments' => $department, 'movement' => $movement, 'occupancy' => $occupancyChart, 'sources' => $sourceChart, 'reservations' => $trendChart, 'facility_grid' => $grid];
    }

    private function reportChart(string $id, string $title, string $type, array $series, array $rows, string $status, string $definition, string $comparison, array $records, bool $money = false): array
    {
        if (count($records) === 0) {
            $status = 'Not enough data';
        }

        return compact('id', 'title', 'type', 'series', 'rows', 'status', 'definition', 'comparison', 'records', 'money') + ['basis' => ResortTime::timezone(), 'drill_down' => '#'.$id.'-records'];
    }
}
