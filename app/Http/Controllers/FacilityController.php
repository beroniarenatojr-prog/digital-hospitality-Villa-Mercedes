<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FacilityController extends Controller
{
    public function show(Facility $facility): View
    {
        abort_if(! $facility->is_active, 404);

        $facility->load(['amenities', 'rates', 'galleryImages']);

        return view('facilities.show', compact('facility'));
    }
}
