<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Services\FacilityQueryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FacilityController extends Controller
{
    public function __construct(
        private readonly FacilityQueryService $queryService
    ) {}

    public function show(Facility $facility): View
    {
        abort_if(! $facility->is_active, 404);

        $facility = $this->queryService->loadFacilityDetails($facility);

        return view('facilities.show', compact('facility'));
    }
}
