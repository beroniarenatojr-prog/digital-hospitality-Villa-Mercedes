<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\View\View;

class ArrivalPassController extends Controller
{
    public function show(Reservation $reservation): View
    {
        $reservation->load(['facility.facilityType']);

        return view('pages.arrival-pass', compact('reservation'));
    }
}
