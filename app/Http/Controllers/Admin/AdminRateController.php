<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRateRequest;
use App\Http\Requests\Admin\UpdateRateRequest;
use App\Models\Facility;
use App\Models\Rate;
use App\Services\Admin\RateManagementService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminRateController extends Controller
{
    public function __construct(
        private readonly RateManagementService $service
    ) {}

    public function index(): View
    {
        $rates = Rate::with('facility')->get();

        return view('admin.rates.index', compact('rates'));
    }

    public function show(Rate $rate): View
    {
        return view('admin.rates.show', compact('rate'));
    }

    public function store(StoreRateRequest $request, Facility $facility): RedirectResponse
    {
        $data = $request->validated();
        if (isset($data['start_date'])) {
            $data['start_date'] = Carbon::parse($data['start_date']);
        }
        if (isset($data['end_date'])) {
            $data['end_date'] = Carbon::parse($data['end_date']);
        }

        $rate = $this->service->createRate($request->user(), $facility, $data);

        return redirect()->route('admin.facilities.show', $facility)
            ->with('success', 'Rate created successfully.');
    }

    public function update(UpdateRateRequest $request, Rate $rate): RedirectResponse
    {
        $data = $request->validated();
        if (isset($data['start_date'])) {
            $data['start_date'] = Carbon::parse($data['start_date']);
        }
        if (isset($data['end_date'])) {
            $data['end_date'] = Carbon::parse($data['end_date']);
        }

        $this->service->updateRate($request->user(), $rate, $data);

        return redirect()->route('admin.facilities.show', $rate->facility_id)
            ->with('success', 'Rate updated successfully.');
    }

    public function toggleStatus(Request $request, Rate $rate): RedirectResponse
    {
        $this->service->toggleRateStatus($request->user(), $rate);

        return redirect()->back()
            ->with('success', 'Rate status toggled successfully.');
    }
}
