<?php

namespace App\Http\Controllers;

use App\Enums\FacilityType;
use App\Models\Facility;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        return view('pages.home');
    }

    public function rooms(): View
    {
        $facilities = Facility::whereHas('facilityType', function ($query) {
            $query->where('slug', FacilityType::Room->value);
        })->where('is_active', true)->get();

        return view('pages.facilities', [
            'facilities' => $facilities,
            'title' => 'Rooms',
        ]);
    }

    public function halls(): View
    {
        $facilities = Facility::whereHas('facilityType', function ($query) {
            $query->where('slug', FacilityType::FunctionHall->value);
        })->where('is_active', true)->get();

        return view('pages.facilities', [
            'facilities' => $facilities,
            'title' => 'Function Halls',
        ]);
    }

    public function packages(): View
    {
        $facilities = Facility::whereHas('facilityType', function ($query) {
            $query->where('slug', FacilityType::ResortPackage->value);
        })->where('is_active', true)->get();

        return view('pages.facilities', [
            'facilities' => $facilities,
            'title' => 'Resort Packages',
        ]);
    }
}
