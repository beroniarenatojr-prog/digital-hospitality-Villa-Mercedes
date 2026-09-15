<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivityLogIndexRequest;
use App\Services\Admin\ActivityLogQueryService;
use App\Support\LiveFilter;
use Illuminate\View\View;

class AdminActivityLogController extends Controller
{
    public function __construct(
        private readonly ActivityLogQueryService $activityLogs
    ) {}

    public function index(ActivityLogIndexRequest $request): View
    {
        $activities = $this->activityLogs->paginate($request);

        if (LiveFilter::wantsPartial($request)) {
            return view('admin.activity-log.partials.results', compact('activities'));
        }

        return view('admin.activity-log.index', [
            'activities' => $activities,
            'staffMembers' => $this->activityLogs->staffOptions(),
        ]);
    }
}
