<?php

namespace App\Http\Controllers;

use App\Enums\FacilityType;
use App\Models\Facility;
use App\Models\FacilityType as FacilityTypeModel;
use App\Models\MenuCategory;
use App\Models\ResortFee;
use App\Models\ResortPool;
use App\Models\ResortRule;
use App\Models\ResortSetting;
use App\Models\SwimmingSession;
use App\Services\AvailabilityService;
use App\Services\CottageBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResortController extends Controller
{
    public function __construct(
        private readonly CottageBookingService $cottageBookingService,
        private readonly AvailabilityService $availabilityService,
    ) {}

    public function show(): View
    {
        return view('pages.resort', [
            'pools' => ResortPool::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'sessions' => SwimmingSession::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'rules' => ResortRule::query()->where('is_active', true)->where('is_public', true)->orderBy('sort_order')->get(),
            'fees' => ResortFee::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'menuCategories' => MenuCategory::query()->where('is_active', true)->with(['items' => fn ($query) => $query->where('is_available', true)])->orderBy('sort_order')->get(),
            'cottages' => $this->cottages(),
            'settings' => ResortSetting::query()->pluck('value', 'key'),
        ]);
    }

    public function cottageAvailability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'session' => ['required', 'string', 'max:50'],
        ]);
        $session = SwimmingSession::query()->where('slug', $validated['session'])->where('is_active', true)->firstOrFail();
        $cottages = $this->cottages();
        $resolvedPeriod = $session->periodFor($validated['date']);
        $this->availabilityService->primeOverlaps($cottages, $resolvedPeriod[0], $resolvedPeriod[1]);

        $cottages = $cottages->map(function (Facility $cottage) use ($session, $validated): array {
            $resolved = $this->cottageBookingService->resolve($cottage, $session, $validated['date']);
            $available = $this->availabilityService->isAvailable($cottage, $resolved['check_in'], $resolved['check_out'])->isAvailable;

            return [
                'id' => $cottage->id,
                'name' => $cottage->name,
                'available' => $available,
                'booking_url' => $available ? route('customer.booking.create', ['facility' => $cottage, 'date' => $validated['date'], 'session' => $session->slug]) : null,
            ];
        });

        return response()->json(['data' => $cottages]);
    }

    private function cottages()
    {
        $typeId = FacilityTypeModel::query()->where('slug', FacilityType::Cottage->value)->value('id');

        return Facility::query()
            ->where('facility_type_id', $typeId ?: 0)
            ->where('is_active', true)
            ->with(['facilityType', 'packageComponents.componentFacility', 'partOfPackages'])
            ->orderBy('sort_order')
            ->get();
    }
}
