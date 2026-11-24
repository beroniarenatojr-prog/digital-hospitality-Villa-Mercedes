<?php

namespace App\Services\Admin;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Support\Admin\ReportFilters;
use App\Support\ResortTime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** One report, one date basis. Screen and exports consume the same result. */
class ManagementReports
{
    use ManagementReportInsights;
    public const VIEWS = [
        'executive' => 'Bookings',
        'services' => 'Completed services',
        'revenue' => 'Payments',
        'balances' => 'Balances',
    ];

    public function build(ReportFilters $filters): array
    {
        $report = $this->data($filters);
        return $this->interpret($report, $filters);
    }

    private function data(ReportFilters $filters): array
    {
        return match ($filters->view) {
            'revenue' => $this->payments($filters),
            'balances' => $this->balances($filters),
            default => $this->bookings($filters),
        };
    }

    private function bookings(ReportFilters $filters): array
    {
        $completed = $filters->view === 'services';
        $query = Reservation::with(['facility.facilityType', 'reservationGroup']);
        $this->dimensions($query, $filters);
        $column = $completed ? 'check_out' : 'created_at';
        $this->dates($query, $column, $filters, ! $completed);
        if ($completed) {
            $query->whereIn('status', ['approved', 'completed'])
                ->where(fn (Builder $q) => $q->where('status', 'completed')->orWhereNotNull('checked_out_at'));
        }
        $rows = $query->orderBy($column)->orderBy('id')->get();
        $accepted = $rows->filter(fn ($r) => in_array($r->status, [ReservationStatus::Approved, ReservationStatus::Completed], true));
        $cancelled = $rows->where('status', ReservationStatus::Cancelled)->count();
        $basis = $completed ? 'Scheduled check-out date; only completed or checked-out services.' : 'Booking creation date; statuses reflect the records as they are now.';
        $metrics = $completed
            ? [
                $this->metric('Completed service reservations', $rows->count()),
                $this->metric('Completed service value', $accepted->sum('total_price'), true, 'Agreed charges, not payments received or profit.'),
            ]
            : [
                $this->metric('Service reservations', $rows->count(), false, 'One room, cottage, hall or package per reservation.'),
                $this->metric('Confirmed or completed', $accepted->count()),
                $this->metric('Confirmed booking value', $accepted->sum('total_price'), true, 'Excludes pending, cancelled, rejected and expired requests.'),
                $this->metric('Cancelled', $cancelled, false, $rows->isEmpty() ? 'No reservations in this period.' : number_format($cancelled / $rows->count() * 100, 1).'% of reservations created in this period.'),
            ];
        $events = $rows->map(fn ($r) => ['date' => $completed ? $r->check_out : $r->created_at->copy()->timezone(ResortTime::timezone()), 'value' => $completed ? (float) $r->total_price : 1]);
        $charts = [$this->trend($events, $filters, $completed ? 'Completed service value over time' : 'Reservations created over time', $completed)];
        $serviceRows = $accepted->groupBy(fn ($r) => $r->facility?->facilityType?->name ?? 'Unassigned')->map(fn ($items, $name) => ['label' => $name, 'value' => $completed ? (float) $items->sum('total_price') : $items->count()])->sortByDesc('value')->values()->all();
        $charts[] = $this->chart('services', $completed ? 'Completed value by service type' : 'Confirmed reservations by service type', 'bar', $serviceRows, $completed);
        $tables = [];
        if (! $completed) {
            $tables[] = $this->table('Booking sources', ['Source', 'Reservations', 'Confirmed / completed', 'Confirmed value'], ['text', 'number', 'number', 'money'], $rows->groupBy(fn ($r) => $r->reservation_source?->label() ?? 'Unspecified')->map(function ($items, $name) {
                $confirmed = $items->filter(fn ($r) => in_array($r->status, [ReservationStatus::Approved, ReservationStatus::Completed], true));
                return ['cells' => [$name, $items->count(), $confirmed->count(), $confirmed->sum('total_price')]];
            })->values()->all());
            $tables[] = $this->table('Reservation outcomes', ['Current status', 'Reservations'], ['text', 'number'], $rows->groupBy(fn ($r) => $r->status->label())->map(fn ($items, $status) => ['cells' => [$status, $items->count()]])->values()->all());
        }
        $tables[] = $this->table($completed ? 'Completed service records' : 'Reservation records', ['Reservation', $completed ? 'Scheduled check-out' : 'Created', 'Space', 'Status', 'Agreed value'], ['link', 'text', 'text', 'text', 'money'], $rows->map(fn ($r) => [
            'url' => route('admin.reservations.show', $r),
            'cells' => [$r->reservation_code, ($completed ? $r->check_out : $r->created_at->copy()->timezone(ResortTime::timezone()))->format('M j, Y'), $r->facility?->name ?? 'Unassigned', $r->status->label(), (float) $r->total_price],
        ])->all(), true);
        return $this->report($filters, $basis, $metrics, $charts, $tables, 'Each booked service counts once, including each service in a group. A scheduled arrival is the planned check-in date. An actual check-in is recorded when staff check the guest in. The reservation count does not measure either arrivals or the number of people on site.');
    }

    private function payments(ReportFilters $filters): array
    {
        $query = Payment::with(['reservation', 'reservationGroup']);
        $this->dates($query, 'payment_date', $filters);
        if ($filters->source) {
            // A receipt linked both to a line and its group is still one payment row.
            $query->where(fn (Builder $q) => $q
                ->whereHas('reservation', fn (Builder $r) => $r->where('reservation_source', $filters->source))
                ->orWhereHas('reservationGroup', fn (Builder $g) => $g->where('source', $filters->source)));
        }
        $payments = $query->orderBy('payment_date')->orderBy('id')->get();
        $verified = $payments->where('status', PaymentStatus::Verified);
        $refunds = $this->refunds($filters);
        $received = (float) $verified->sum('amount');
        $returned = (float) collect($refunds)->sum('amount');
        $metrics = [
            $this->metric('Verified payments received', $received, true),
            $this->metric('Refunds returned', $returned, true, 'Recorded as returned, not merely approved.'),
            $this->metric('Receipts less refunds', $received - $returned, true, 'Money movement only; not profit or earned revenue.'),
            $this->metric('Awaiting verification', $payments->where('status', PaymentStatus::Pending)->sum('amount'), true, 'Excluded from payments received.'),
        ];
        $events = $verified->map(fn ($p) => ['date' => $p->payment_date, 'value' => (float) $p->amount]);
        $charts = [$this->trend($events, $filters, 'Verified payments over time', true)];
        $charts[] = $this->chart('methods', 'Verified payments by method', 'bar', $verified->groupBy(fn ($p) => $p->method->label())->map(fn ($items, $name) => ['label' => $name, 'value' => (float) $items->sum('amount')])->sortByDesc('value')->values()->all(), true);
        $tables = [$this->table('Payment records', ['Payment', 'Payment date', 'Booking', 'Method', 'Status', 'Amount'], ['link', 'text', 'text', 'text', 'text', 'money'], $payments->map(fn ($p) => [
            'url' => route('admin.payments.show', $p),
            'cells' => ['Payment #'.$p->id, $p->payment_date->format('M j, Y'), $p->reservationGroup?->group_code ?? $p->reservation?->reservation_code ?? 'Unlinked', $p->method->label(), $p->status->label(), (float) $p->amount],
        ])->all(), true)];
        if ($refunds !== []) {
            $tables[] = $this->table('Returned refunds', ['Booking', 'Returned', 'Amount'], ['link', 'text', 'money'], array_map(fn ($r) => ['url' => $r['url'], 'cells' => [$r['code'], $r['date']->format('M j, Y'), $r['amount']]], $refunds));
        }
        return $this->report($filters, 'Payment date for receipts; recorded return date for refunds.', $metrics, $charts, $tables, 'Group and individual payments are counted once. Payments are not split across service types because a group receipt can cover several services.');
    }

    private function balances(ReportFilters $filters): array
    {
        $single = Reservation::with(['payments', 'facility'])->whereNull('reservation_group_id')->whereIn('status', ['approved', 'completed']);
        $groups = ReservationGroup::with(['payments', 'directPayments', 'reservations'])->whereIn('status', ['approved', 'completed']);
        if ($filters->source) {
            $single->where('reservation_source', $filters->source);
            $groups->where('source', $filters->source);
        }
        $rows = [];
        foreach ($single->get()->concat($groups->get()) as $booking) {
            if (in_array($booking->booking_workflow['refund']['status'] ?? null, ['approved', 'returned'], true)) {
                continue;
            }
            $isGroup = $booking instanceof ReservationGroup;
            $payments = $isGroup ? $booking->payments->concat($booking->directPayments)->unique('id') : $booking->payments;
            $paid = $this->cents($payments->where('status', PaymentStatus::Verified)->sum('amount'));
            $price = $this->cents($isGroup ? $booking->total_amount : $booking->total_price);
            $balance = max(0, $price - $paid);
            $credit = max(0, $paid - $price);
            if ($balance === 0 && $credit === 0) {
                continue;
            }
            $departed = $isGroup
                ? $booking->isCompleted() || ($booking->reservations->isNotEmpty() && $booking->reservations->every(fn ($r) => $r->checked_out_at !== null || $r->status === ReservationStatus::Completed))
                : $booking->status === ReservationStatus::Completed || $booking->checked_out_at !== null;
            $rows[] = [
                'url' => route($isGroup ? 'admin.reservation-groups.show' : 'admin.reservations.show', $booking),
                'cells' => [$isGroup ? $booking->group_code : $booking->reservation_code, $departed ? 'Completed / checked out' : 'Confirmed / in house', $price / 100, $paid / 100, $balance / 100, $credit / 100],
                'balance' => $balance / 100, 'credit' => $credit / 100, 'departed' => $departed,
            ];
        }
        $rows = collect($rows)->sortByDesc(fn ($r) => ($r['departed'] ? 1e12 : 0) + $r['balance'])->values();
        $metrics = [
            $this->metric('Still unpaid', $rows->sum('balance'), true, 'Confirmed and completed bookings only.'),
            $this->metric('Unpaid after checkout', $rows->where('departed', true)->sum('balance'), true),
            $this->metric('Bookings with a balance', $rows->where('balance', '>', 0)->count()),
            $this->metric('Overpayments to review', $rows->sum('credit'), true),
        ];
        $tables = [$this->table('Balances to follow up', ['Booking', 'Stay status', 'Agreed total', 'Verified paid', 'Still unpaid', 'Overpaid'], ['link', 'text', 'money', 'money', 'money', 'money'], $rows->all())];
        return $this->report($filters, 'Current balances as of '.ResortTime::now()->format('M j, Y g:i A').' ('.ResortTime::timezone().').', $metrics, [], $tables, 'Each group appears once, including payments to its member reservations and directly to the group. Pending receipts do not reduce balances.');
    }

    private function refunds(ReportFilters $filters): array
    {
        $rows = [];
        // Group refund fields and workflow describe the same return: never add both.
        $singles = Reservation::whereNull('reservation_group_id')->where('booking_workflow->refund->status', 'returned');
        $groups = ReservationGroup::where(fn (Builder $q) => $q->where('booking_workflow->refund->status', 'returned')->orWhere('refund_status', 'processed'));
        if ($filters->source) {
            $singles->where('reservation_source', $filters->source);
            $groups->where('source', $filters->source);
        }
        foreach ($singles->get()->concat($groups->get()) as $booking) {
            $refund = $booking->booking_workflow['refund'] ?? [];
            $isGroup = $booking instanceof ReservationGroup;
            $date = $refund['returned_at'] ?? ($isGroup ? $booking->refunded_at : null);
            if (! $date) {
                continue;
            }
            $date = Carbon::parse($date)->timezone(ResortTime::timezone());
            if (($filters->startDate && $date->lt($filters->startDate->copy()->startOfDay())) || ($filters->endDate && $date->gt($filters->endDate->copy()->endOfDay()))) {
                continue;
            }
            $rows[] = ['code' => $isGroup ? $booking->group_code : $booking->reservation_code, 'date' => $date,
                'amount' => isset($refund['amount_cents']) ? $refund['amount_cents'] / 100 : (float) $booking->refund_amount,
                'url' => route($isGroup ? 'admin.reservation-groups.show' : 'admin.reservations.show', $booking)];
        }
        return $rows;
    }

    private function report(ReportFilters $filters, string $basis, array $metrics, array $charts, array $tables, string $note): array
    {
        return ['title' => self::VIEWS[$filters->view] ?? 'Bookings', 'basis' => $basis, 'metrics' => $metrics, 'charts' => $charts, 'tables' => $tables, 'note' => $note];
    }

    private function metric(string $label, int|float $value, bool $money = false, string $note = ''): array
    {
        return compact('label', 'value', 'money', 'note');
    }

    private function table(string $title, array $columns, array $types, array $rows, bool $detail = false): array
    {
        return compact('title', 'columns', 'types', 'rows', 'detail');
    }

    private function trend(Collection $events, ReportFilters $filters, string $title, bool $money): array
    {
        $from = $filters->startDate?->copy() ?? ResortTime::today()->startOfMonth();
        $to = $filters->endDate?->copy() ?? ResortTime::today();
        $grouping = $filters->grouping;
        if ($grouping === 'auto') {
            $days = (int) $from->diffInDays($to) + 1;
            $grouping = match (true) {
                $days <= 31 => 'day', $days <= 120 => 'week', $days <= 1096 => 'month', default => 'year',
            };
        }
        // Keep trends readable even for a long custom range.
        if ($from->diffInDays($to) > 730 && in_array($grouping, ['day', 'week'], true)) {
            $grouping = 'month';
        }
        $key = fn (Carbon $d) => match ($grouping) {
            'day' => $d->format('Y-m-d'), 'week' => $d->copy()->startOfWeek()->format('Y-m-d'), 'year' => $d->format('Y'), default => $d->format('Y-m'),
        };
        $totals = $events->groupBy(fn ($e) => $key($e['date']))->map(fn ($items) => $items->sum('value'));
        $cursor = $from->copy();
        match ($grouping) { 'week' => $cursor->startOfWeek(), 'month' => $cursor->startOfMonth(), 'year' => $cursor->startOfYear(), default => $cursor->startOfDay() };
        $rows = [];
        while ($cursor->lte($to)) {
            $rows[] = ['label' => $key($cursor), 'value' => (float) ($totals[$key($cursor)] ?? 0)];
            match ($grouping) { 'day' => $cursor->addDay(), 'week' => $cursor->addWeek(), 'year' => $cursor->addYear(), default => $cursor->addMonth() };
        }
        return $this->chart('trend', $title, 'line', $rows, $money) + ['grouping' => ucfirst($grouping)];
    }

    private function chart(string $id, string $title, string $type, array $rows, bool $money): array
    {
        return compact('id', 'title', 'type', 'rows', 'money');
    }

    private function dimensions(Builder $query, ReportFilters $filters): void
    {
        if ($filters->facilityType) {
            $query->whereHas('facility.facilityType', fn (Builder $q) => $q->where('slug', $filters->facilityType));
        }
        if ($filters->source) {
            $query->where('reservation_source', $filters->source);
        }
    }

    private function dates(Builder $query, string $column, ReportFilters $filters, bool $utc = false): void
    {
        if ($filters->startDate) {
            $date = $filters->startDate->copy()->startOfDay();
            $query->where($column, '>=', $utc ? $date->timezone(config('app.timezone')) : $date);
        }
        if ($filters->endDate) {
            $date = $filters->endDate->copy()->endOfDay();
            $query->where($column, '<=', $utc ? $date->timezone(config('app.timezone')) : $date);
        }
    }

    private function cents(mixed $value): int
    {
        return (int) round((float) $value * 100);
    }
}
