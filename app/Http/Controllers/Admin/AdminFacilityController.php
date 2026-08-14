<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFacilityRequest;
use App\Http\Requests\Admin\UpdateFacilityRequest;
use App\Models\Facility;
use App\Services\Admin\FacilityManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminFacilityController extends Controller
{
    public function __construct(
        private readonly FacilityManagementService $service
    ) {}

    public function index(): View
    {
        $facilities = Facility::with('facilityType')->get();

        return view('admin.facilities.index', compact('facilities'));
    }

    public function show(Facility $facility): View
    {
        $facility->load(['amenities', 'rates', 'galleryImages']);

        return view('admin.facilities.show', compact('facility'));
    }

    public function store(StoreFacilityRequest $request): RedirectResponse
    {
        $facility = $this->service->createFacility($request->user(), $request->validated());

        return redirect()->route('admin.facilities.show', $facility)
            ->with('success', 'Facility created successfully.');
    }

    public function update(UpdateFacilityRequest $request, Facility $facility): RedirectResponse
    {
        $this->service->updateFacility($request->user(), $facility, $request->validated());

        return redirect()->route('admin.facilities.show', $facility)
            ->with('success', 'Facility updated successfully.');
    }

    public function toggleStatus(Request $request, Facility $facility): RedirectResponse
    {
        $this->service->toggleFacilityStatus($request->user(), $facility);

        return redirect()->back()
            ->with('success', 'Facility status toggled successfully.');
    }

    public function destroy(Request $request, Facility $facility): RedirectResponse
    {
        $this->service->deleteFacility($request->user(), $facility);

        return redirect()->route('admin.facilities.index')
            ->with('success', 'Facility deleted successfully.');
    }
}
