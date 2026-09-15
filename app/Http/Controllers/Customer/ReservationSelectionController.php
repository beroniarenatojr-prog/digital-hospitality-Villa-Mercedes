<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Facility;
use Illuminate\Http\Request;

class ReservationSelectionController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'type' => ['nullable', 'string'], 'check_in' => ['nullable', 'date_format:Y-m-d'],
            'check_out' => ['nullable', 'date_format:Y-m-d'], 'guests' => ['nullable', 'integer', 'min:1'],
            'date' => ['nullable', 'date_format:Y-m-d'], 'session' => ['nullable', 'string', 'max:50'],
            'arrival' => ['nullable', 'date_format:Y-m-d H:i'], 'departure' => ['nullable', 'date_format:Y-m-d H:i'],
        ]);
        $type = $request->query('type', 'room');
        abort_unless(in_array($type, ['room', 'cottage', 'resort_package', 'function_hall'], true), 404);
        $facilities = Facility::where('is_active', true)->whereHas('facilityType', fn ($q) => $q->where('slug', $type))
            ->with('facilityType', 'rates', 'galleryImages')->orderBy('name')->paginate(12)->withQueryString();
        $selection = $request->only(['check_in', 'check_out', 'guests', 'date', 'session', 'arrival', 'departure']);
        return view('customer.booking.select', compact('type', 'facilities', 'selection'));
    }
}
