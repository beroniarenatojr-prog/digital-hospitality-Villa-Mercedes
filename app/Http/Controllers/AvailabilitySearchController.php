<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchAvailabilityRequest;
use App\Services\AvailabilitySearchService;
use Illuminate\Http\JsonResponse;

class AvailabilitySearchController extends Controller
{
    public function __invoke(SearchAvailabilityRequest $request, AvailabilitySearchService $search): JsonResponse
    {
        return response()->json($search->search(
            $request->serviceType(),
            $request->date('check_in'),
            $request->date('check_out'),
            (int) $request->validated('guests'),
            $request->validated('session'),
        ));
    }
}
