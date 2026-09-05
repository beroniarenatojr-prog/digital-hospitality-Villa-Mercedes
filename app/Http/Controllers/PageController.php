<?php

namespace App\Http\Controllers;

use App\Enums\FacilityType;
use App\Services\FacilityQueryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(
        private readonly FacilityQueryService $queryService
    ) {}

    public function home(): View
    {
        $featuredRooms = $this->queryService->getFeaturedFacilitiesByType(FacilityType::Room->value, 3);
        $featuredHalls = $this->queryService->getFeaturedFacilitiesByType(FacilityType::FunctionHall->value, 3);
        $featuredPackages = $this->queryService->getFeaturedFacilitiesByType(FacilityType::ResortPackage->value, 3);

        return view('pages.home', compact('featuredRooms', 'featuredHalls', 'featuredPackages'));
    }

    public function training(): View
    {
        $batches = \App\Models\TrainingBatch::upcoming()
            ->with('facilities')
            ->orderBy('start_date')
            ->get();

        return view('pages.training', compact('batches'));
    }

    public function contact(): View
    {
        return view('pages.contact');
    }

    public function rooms(): View
    {
        $facilities = $this->queryService->getActiveFacilitiesByType(FacilityType::Room->value);

        return view('pages.facilities', [
            'facilities' => $facilities,
            'title' => 'Rooms',
            'description' => 'Browse our comfortable rooms and accommodations.'
        ]);
    }

    public function halls(): View
    {
        $facilities = $this->queryService->getActiveFacilitiesByType(FacilityType::FunctionHall->value);

        return view('pages.facilities', [
            'facilities' => $facilities,
            'title' => 'Function Halls',
            'description' => 'Explore our elegant function halls for your special events.'
        ]);
    }

    public function packages(): View
    {
        $facilities = $this->queryService->getActiveFacilitiesByType(FacilityType::ResortPackage->value);

        return view('pages.facilities', [
            'facilities' => $facilities,
            'title' => 'Resort Packages',
            'description' => 'Discover our exclusive resort packages for an unforgettable experience.'
        ]);
    }
}
