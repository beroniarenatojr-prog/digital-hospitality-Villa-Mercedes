<?php

namespace App\Services\Admin;

use App\Support\Admin\ReportFilters;
use App\Support\ResortTime;

trait ManagementReportInsights
{
    private function interpret(array $report, ReportFilters $filters): array
    {
        $findings = [];
        $comparison = null;
        $metrics = array_column($report['metrics'], 'value');
        if ($filters->view === 'balances') {
            [$unpaid, $departed, $count, $credit] = $metrics;
            $findings[] = $unpaid > 0
                ? $this->money($unpaid).' remains unpaid across '.number_format($count).' bookings. '.($departed > 0 ? $this->money($departed).' ('.number_format($departed / $unpaid * 100, 1).'%) belongs to completed or checked-out stays; follow up on those records first.' : 'None of this unpaid amount belongs to completed or checked-out stays.')
                : 'No unpaid balance is recorded on the confirmed and completed bookings in this selection.';
            if ($credit > 0) {
                $findings[] = $this->money($credit).' in overpayments needs review. Check the booking and payment records before making an adjustment.';
            }
            $findings[] = 'This is a current snapshot. Historical balance snapshots are not stored, so a change over time cannot be calculated.';
            $report['charts'] = [$this->chart('balance-status', 'Unpaid balances by stay status', 'bar', [
                ['label' => 'Completed / checked out', 'value' => $departed],
                ['label' => 'Confirmed / in house', 'value' => max(0, $unpaid - $departed)],
            ], true)];
        } else {
            $index = $filters->view === 'services' ? 1 : 0;
            $money = $filters->view !== 'executive';
            if ($filters->startDate && $filters->endDate && $filters->endDate->toDateString() <= ResortTime::today()->toDateString()) {
                $days = (int) $filters->startDate->diffInDays($filters->endDate) + 1;
                $previousEnd = $filters->startDate->copy()->subDay();
                $previousStart = $previousEnd->copy()->subDays($days - 1);
                $previousFilters = new ReportFilters($previousStart, $previousEnd, $filters->grouping, $filters->view, $filters->facilityType, $filters->source);
                $previous = $this->data($previousFilters);
                $prior = $previous['metrics'][$index]['value'];
                $current = $metrics[$index];
                $comparison = [
                    'from' => $previousStart->format('M j, Y'), 'until' => $previousEnd->format('M j, Y'), 'days' => $days,
                    'label' => $report['metrics'][$index]['label'], 'current' => $current, 'previous' => $prior,
                    'change_percent' => $prior > 0 ? round(($current - $prior) / $prior * 100, 1) : null,
                ];
                $name = $report['metrics'][$index]['label'];
                $currentLabel = $money ? $this->money($current) : number_format($current);
                $priorLabel = $money ? $this->money($prior) : number_format($prior);
                if ($prior > 0) {
                    $direction = $current > $prior ? 'up' : 'down';
                    $change = $current == $prior ? 'unchanged' : $direction.' '.number_format(abs(($current - $prior) / $prior * 100), 1).'%';
                    $findings[] = $name.': '.$currentLabel.', '.$change.' from '.$priorLabel.' in the preceding '.$days.'-day period.';
                } else {
                    $findings[] = $name.': '.$currentLabel.'. The recorded total for the preceding '.$days.'-day period is '.$priorLabel.'; percentage growth cannot be calculated from zero. This does not establish that the earlier period had complete records.';
                }
            } else {
                $findings[] = 'This selection includes future dates. Results show records available now; an elapsed-period performance comparison is not shown.';
            }
            if ($filters->view === 'executive') {
                [$total, $accepted, $value, $cancelled] = $metrics;
                if ($total > 0) {
                    $findings[] = number_format($cancelled).' of '.number_format($total).' service reservations created in this period are currently cancelled ('.number_format($cancelled / $total * 100, 1).'%). '.number_format($accepted).' are confirmed or completed, worth '.$this->money($value).'.'.($total < 10 ? ' With fewer than 10 reservations, treat this as a small sample, not an established trend.' : ' These records alone do not explain why guests cancelled.');
                }
            } elseif ($filters->view === 'revenue') {
                $findings[] = $this->money($metrics[0]).' in verified receipts less '.$this->money($metrics[1]).' in returned refunds leaves '.$this->money($metrics[2]).'.'.($metrics[2] < 0 ? ' Returns exceed receipts in this period; they may relate to payments received earlier.' : ' This is money movement, not profit.');
            }
            $leader = $this->leadingCategory($report['charts'][1]);
            if ($leader !== null) {
                $findings[] = $leader;
            }
        }
        $report['findings'] = $findings;
        $report['comparison'] = $comparison;
        return $report;
    }

    private function leadingCategory(array $chart): ?string
    {
        $rows = collect($chart['rows']);
        $total = $rows->sum('value');
        if ($total <= 0) {
            return null;
        }
        $maximum = $rows->max('value');
        $leaders = $rows->where('value', $maximum)->pluck('label');
        $amount = $chart['money'] ? $this->money($maximum) : number_format($maximum).' reservations';
        $subject = $chart['id'] === 'methods' ? 'verified receipts' : ($chart['money'] ? 'completed service value' : 'confirmed or completed reservations');
        $share = number_format($maximum / $total * 100, 1).'%';
        if ($rows->count() === 1) {
            return $leaders->first().' accounts for all '.$amount.' in this selection. No other category has matching recorded activity.';
        }
        return $leaders->implode(', ').($leaders->count() > 1 ? ' are tied for the largest share' : ' has the largest share').' of '.$subject.': '.$amount.' ('.$share.')'.($leaders->count() > 1 ? ' each.' : '.');
    }

    private function money(int|float $value): string
    {
        return 'PHP '.number_format($value, 2);
    }
}
