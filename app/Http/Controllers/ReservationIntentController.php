<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationIntentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'check_in' => ['nullable', 'date_format:Y-m-d'],
            'check_out' => ['nullable', 'date_format:Y-m-d'],
            'arrival' => ['nullable', 'date_format:Y-m-d H:i'],
            'departure' => ['nullable', 'date_format:Y-m-d H:i'],
            'guests' => ['nullable', 'integer', 'min:1'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'session' => ['nullable', 'string', 'max:50', 'exists:swimming_sessions,slug'],
            'availability_checked' => ['nullable', 'in:1'],
        ]);
        $facility = Facility::query()->findOrFail($data['facility_id']);
        abort_unless($facility->is_active && $facility->isOnlineBookable(), 404);

        unset($data['facility_id']);
        $url = route('customer.booking.create', ['facility' => $facility, ...array_filter($data, fn ($value) => $value !== null)]);
        // The destination is built from a real facility and allowlisted selection fields.
        // Never accept a client-provided redirect URL, price, or reservation status.
        $request->session()->put('url.intended', $url);
        $request->session()->put('reservation.selection', ['name' => $facility->name, 'url' => $url]);

        return response()->json(['name' => $facility->name, 'redirect' => $url, 'authenticated' => $request->user() !== null]);
    }
}
