<?php

namespace App\Http\Controllers\Customer;

use App\Enums\FacilityType;
use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Services\Customer\UnfinishedBookings;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerHomeController extends Controller
{
    public function __construct(
        private readonly UnfinishedBookings $unfinished,
    ) {}

    public function __invoke(Request $request): View
    {
        $services = collect([
            FacilityType::Room,
            FacilityType::Cottage,
            FacilityType::ResortPackage,
            FacilityType::FunctionHall,
        ])->map(function (FacilityType $type): array {
            $facility = Facility::query()
                ->listed()
                ->whereHas('facilityType', fn ($query) => $query->where('slug', $type->value))
                ->with('facilityType', 'galleryImages')
                ->orderBy('name')
                ->first();

            return [
                'type' => $type->value,
                'name' => match ($type) {
                    FacilityType::Room => 'Rooms',
                    FacilityType::Cottage => 'Cottages',
                    FacilityType::ResortPackage => 'Resort packages',
                    FacilityType::FunctionHall => 'Function halls',
                },
                'tag' => match ($type) {
                    FacilityType::Room => 'Overnight stay',
                    FacilityType::Cottage => 'Day pass',
                    FacilityType::ResortPackage => 'Curated',
                    FacilityType::FunctionHall => 'Gatherings',
                },
                'description' => match ($type) {
                    FacilityType::Room => 'A comfortable stay with dates and guests checked before you choose.',
                    FacilityType::Cottage => 'A day-use visit with a swimming session and a space that fits your group.',
                    FacilityType::ResortPackage => 'A prepared resort experience with its included spaces and schedule.',
                    FacilityType::FunctionHall => 'Tell us about your event and let the resort prepare a quotation.',
                },
                'action' => 'See available spaces',
                'image' => $facility?->galleryImages?->first()?->url(),
                'imageAlt' => $facility ? $facility->name : $type->label(),
            ];
        })->values()->all();

        return view('customer.home', [
            'services' => $services,
            'unfinished' => $this->unfinished->for($request->user()),
        ]);
    }
}
