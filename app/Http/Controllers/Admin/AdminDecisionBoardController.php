<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FacilityType;
use App\Services\Admin\DecisionSupportService;
use Carbon\Carbon;
use App\Support\ResortTime;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDecisionBoardController extends Controller
{
    public function __construct(
        private readonly DecisionSupportService $service
    ) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after:from'],
            'facility_type' => ['nullable', 'string'],
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : ResortTime::databaseNow()->startOfDay();

        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : $from->copy()->addDays(13)->endOfDay();

        // A very wide window makes the timeline bars unreadable and the queries
        // heavy for no operational benefit.
        if ($from->diffInDays($to) > 60) {
            $to = $from->copy()->addDays(60)->endOfDay();
        }

        $facilityTypeSlug = $validated['facility_type'] ?? null;

        return view('admin.decision-board.index', [
            'from' => $from,
            'to' => $to,
            'facilityTypeSlug' => $facilityTypeSlug,
            'facilityTypes' => FacilityType::where('is_active', true)->orderBy('name')->get(),
            'rows' => $this->service->timeline($from, $to, $facilityTypeSlug),
            'days' => $this->dayColumns($from, $to),
        ]);
    }

    /**
     * Column headers for the timeline, one per day in the window.
     *
     * @return list<array{label: string, sublabel: string, offset: float, width: float}>
     */
    private function dayColumns(Carbon $from, Carbon $to): array
    {
        $totalMinutes = max(1, $from->diffInMinutes($to));
        $columns = [];

        $day = $from->copy()->startOfDay();

        while ($day->lessThan($to)) {
            $end = $day->copy()->addDay()->startOfDay();
            $visibleEnd = $end->greaterThan($to) ? $to : $end;
            $visibleStart = $day->greaterThan($from) ? $day : $from;

            $columns[] = [
                'label' => $day->format('D'),
                'sublabel' => $day->format('M j'),
                'offset' => round(($from->diffInMinutes($visibleStart) / $totalMinutes) * 100, 3),
                'width' => round(($visibleStart->diffInMinutes($visibleEnd) / $totalMinutes) * 100, 3),
            ];

            $day = $end;
        }

        return $columns;
    }
}
