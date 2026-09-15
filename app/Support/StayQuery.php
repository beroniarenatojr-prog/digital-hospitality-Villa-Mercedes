<?php

namespace App\Support;

use Illuminate\Http\Request;

final class StayQuery
{
    /**
     * Accept both check_in/check_out and the shorter checkin/checkout aliases.
     */
    public static function normalize(Request $request): void
    {
        $checkIn = $request->input('check_in') ?: $request->input('checkin');
        $checkOut = $request->input('check_out') ?: $request->input('checkout');

        $request->merge(array_filter([
            'check_in' => is_string($checkIn) && $checkIn !== '' ? $checkIn : null,
            'check_out' => is_string($checkOut) && $checkOut !== '' ? $checkOut : null,
        ], fn ($value) => $value !== null));
    }

    public static function isComplete(Request $request): bool
    {
        self::normalize($request);

        return $request->filled(['check_in', 'check_out', 'guests']);
    }

    /**
     * @return array<string, string>
     */
    public static function parameters(Request $request): array
    {
        self::normalize($request);

        return array_filter([
            'check_in' => $request->input('check_in'),
            'check_out' => $request->input('check_out'),
            'guests' => $request->input('guests'),
            'session' => $request->input('session'),
        ], fn ($value) => $value !== null && $value !== '');
    }
}
