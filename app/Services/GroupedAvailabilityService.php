<?php

namespace App\Services;

use App\DTOs\AvailabilityResult;
use App\DTOs\PriceBreakdown;
use App\Enums\FacilityType;
use App\Enums\RateType;
use App\Models\Facility;
use App\Models\SwimmingSession;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;

class GroupedAvailabilityService
{
    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly PricingService $pricingService,
        private readonly CottageBookingService $cottageBookingService,
    ) {}

    /**
     * Check availability and calculate quotes for a group of proposed service lines.
     *
     * @param array<int, array{
     *   facility_id: int,
     *   check_in?: string|null,
     *   check_out?: string|null,
     *   date?: string|null,
     *   swimming_session_id?: int|string|null,
     *   session?: string|null,
     *   guest_count?: int|null,
     *   guestCount?: int|null,
     *   pax_tier?: int|null,
     *   early_check_in_hours?: int|null,
     *   late_check_out_hours?: int|null
     * }> $lines
     * @return array{
     *   lines: array<int, array<string, mixed>>,
     *   all_available: bool,
     *   total_estimate: float,
     *   deposit_estimate: float,
     *   checked_at: string,
     *   version_token: string
     * }
     */
    public function evaluateGroup(array $lines, bool $customer = true): array
    {
        $checkedAt = now();
        $evaluatedLines = [];
        $allAvailable = true;
        $totalEstimate = 0.0;
        $seen = [];

        foreach ($lines as $index => $line) {
            $facilityId = (int) ($line['facility_id'] ?? 0);
            $facility = Facility::with(['facilityType', 'rates'])->find($facilityId);

            if (isset($seen[$facilityId])) {
                $evaluatedLines[$index] = ['facility_id' => $facilityId, 'facility_name' => $facility?->name ?? 'Unknown facility', 'is_available' => false, 'reason' => 'This facility is already in your booking. Edit its existing line instead.', 'quoted_amount' => 0.0];
                $allAvailable = false;
                continue;
            }
            $seen[$facilityId] = true;

            if (! $facility || ! $facility->is_active || ! $facility->isOnlineBookable()) {
                $evaluatedLines[$index] = [
                    'facility_id' => $facilityId,
                    'facility_name' => $facility?->name ?? 'Unknown facility',
                    'facility_type' => $facility?->facilityType?->slug,
                    'is_available' => false,
                    'reason' => 'This facility is not available for online booking.',
                    'quoted_amount' => 0.0,
                    'breakdown' => null,
                ];
                $allAvailable = false;
                continue;
            }

            $guestCount = (int) ($line['guest_count'] ?? $line['guestCount'] ?? 1);
            $paxTier = ! empty($line['pax_tier']) ? (int) $line['pax_tier'] : null;
            $earlyHours = (int) ($line['early_check_in_hours'] ?? 0);
            $lateHours = (int) ($line['late_check_out_hours'] ?? 0);

            // Handle Cottage Fixed-Session
            if ($facility->isCottage()) {
                $session = null;
                if (! empty($line['swimming_session_id'])) {
                    $session = SwimmingSession::find($line['swimming_session_id']);
                } elseif (! empty($line['session'])) {
                    $session = SwimmingSession::where('slug', $line['session'])->first();
                }

                $visitDate = $line['date'] ?? $line['check_in'] ?? null;
                $visitDate = $visitDate ? Carbon::parse($visitDate)->toDateString() : null;

                if (! $session || ! $session->is_active || ! $visitDate || $visitDate < now('Asia/Manila')->toDateString()) {
                    $evaluatedLines[$index] = [
                        'facility_id' => $facility->id,
                        'facility_name' => $facility->name,
                        'facility_type' => 'cottage',
                        'is_available' => false,
                        'reason' => 'Choose a future visit date and an active swimming session.',
                        'quoted_amount' => 0.0,
                        'breakdown' => null,
                    ];
                    $allAvailable = false;
                    continue;
                }

                $checkIn = Carbon::parse($visitDate . ' ' . $session->starts_at);
                $checkOut = Carbon::parse($visitDate . ' ' . $session->ends_at);
                if ($checkOut->lte($checkIn)) {
                    $checkOut->addDay();
                }
                if ($checkIn->lte(now('Asia/Manila'))) {
                    $evaluatedLines[$index] = ['facility_id' => $facility->id, 'facility_name' => $facility->name, 'facility_type' => 'cottage', 'is_available' => false, 'reason' => 'This swimming session has already started in Philippine time.', 'quoted_amount' => 0.0];
                    $allAvailable = false;
                    continue;
                }

                // Check capacity
                if ($guestCount > $facility->maxGuestCount()) {
                    $evaluatedLines[$index] = [
                        'facility_id' => $facility->id,
                        'facility_name' => $facility->name,
                        'facility_type' => 'cottage',
                        'is_available' => false,
                        'reason' => "Guest count exceeds maximum capacity ({$facility->maxGuestCount()}).",
                        'quoted_amount' => 0.0,
                        'breakdown' => null,
                    ];
                    $allAvailable = false;
                    continue;
                }

                $avail = $this->availabilityService->isAvailable($facility, $checkIn, $checkOut);
                if (! $avail->isAvailable) {
                    $evaluatedLines[$index] = [
                        'facility_id' => $facility->id,
                        'facility_name' => $facility->name,
                        'facility_type' => 'cottage',
                        'is_available' => false,
                        'availability_status' => $avail->status()->value,
                        'reason' => $avail->reason ?? 'Cottage is reserved for this session.',
                        'quoted_amount' => 0.0,
                        'breakdown' => null,
                    ];
                    $allAvailable = false;
                    continue;
                }

                $priceBreakdown = $this->cottageBookingService->price($facility, $session->id, $checkIn, $checkOut);
                $lineTotal = $priceBreakdown->totalAmount / 100;
                $totalEstimate += $lineTotal;

                $evaluatedLines[$index] = [
                    'facility_id' => $facility->id,
                    'facility_name' => $facility->name,
                    'facility_type' => 'cottage',
                    'session_id' => $session->id,
                    'session_name' => $session->name,
                    'date' => $visitDate,
                    'check_in' => $checkIn->toDateTimeString(),
                    'check_out' => $checkOut->toDateTimeString(),
                    'guest_count' => $guestCount,
                    'is_available' => true,
                    'reason' => null,
                    'quoted_amount' => $lineTotal,
                    'breakdown' => [
                        'base_amount' => $priceBreakdown->baseAmount / 100,
                        'total_amount' => $lineTotal,
                    ],
                ];
                continue;
            }

            // Other Facilities: Rooms, Halls, Packages
            if (empty($line['check_in']) || empty($line['check_out'])) {
                $evaluatedLines[$index] = [
                    'facility_id' => $facility->id,
                    'facility_name' => $facility->name,
                    'facility_type' => $facility->facilityType?->slug,
                    'is_available' => false,
                    'reason' => 'Please provide check in and check out dates.',
                    'quoted_amount' => 0.0,
                    'breakdown' => null,
                ];
                $allAvailable = false;
                continue;
            }

            try {
                $checkIn = Carbon::parse($line['check_in']);
                $checkOut = Carbon::parse($line['check_out']);

                // Validate Schedule using existing reservation schedule validator
                app(ReservationSchedule::class)->validate(
                    $facility,
                    $checkIn,
                    $checkOut,
                    $earlyHours,
                    $lateHours,
                    customer: $customer
                );

                // Capacity check
                if ($guestCount > $facility->maxGuestCount()) {
                    $evaluatedLines[$index] = [
                        'facility_id' => $facility->id,
                        'facility_name' => $facility->name,
                        'facility_type' => $facility->facilityType?->slug,
                        'is_available' => false,
                        'reason' => "Guest count exceeds maximum capacity ({$facility->maxGuestCount()}).",
                        'quoted_amount' => 0.0,
                        'breakdown' => null,
                    ];
                    $allAvailable = false;
                    continue;
                }

                // Check availability
                $avail = $this->availabilityService->isAvailable(
                    $facility,
                    $checkIn,
                    $checkOut,
                    null,
                    $guestCount,
                    $earlyHours,
                    $lateHours,
                    lockForUpdate: false,
                    paxTier: $paxTier
                );

                if (! $avail->isAvailable) {
                    $evaluatedLines[$index] = [
                        'facility_id' => $facility->id,
                        'facility_name' => $facility->name,
                        'facility_type' => $facility->facilityType?->slug,
                        'is_available' => false,
                        'availability_status' => $avail->status()->value,
                        'reason' => $avail->reason ?? 'Facility is not available for this period.',
                        'quoted_amount' => 0.0,
                        'breakdown' => null,
                    ];
                    $allAvailable = false;
                    continue;
                }

                // Price calculation
                $calcBreakdown = $this->pricingService->calculate(
                    $facility,
                    $checkIn,
                    $checkOut,
                    $earlyHours,
                    $lateHours,
                    max(0, $guestCount - (($facility->isRoom() || $facility->isPackage()) ? (int) ($line['children_count'] ?? 0) : 0)),
                    $paxTier
                );

                $lineTotal = $calcBreakdown->totalAmount / 100;
                $totalEstimate += $lineTotal;

                $evaluatedLines[$index] = [
                    'facility_id' => $facility->id,
                    'facility_name' => $facility->name,
                    'facility_type' => $facility->facilityType?->slug,
                    'check_in' => $checkIn->toDateTimeString(),
                    'check_out' => $checkOut->toDateTimeString(),
                    'guest_count' => $guestCount,
                    'pax_tier' => $paxTier,
                    'early_check_in_hours' => $earlyHours,
                    'late_check_out_hours' => $lateHours,
                    'is_available' => true,
                    'reason' => null,
                    'quoted_amount' => $lineTotal,
                    'breakdown' => [
                        'base_amount' => $calcBreakdown->baseAmount / 100,
                        'extra_person_charge' => $calcBreakdown->extraPersonCharge / 100,
                        'early_check_in_charge' => $calcBreakdown->earlyCheckInCharge / 100,
                        'late_check_out_charge' => $calcBreakdown->lateCheckOutCharge / 100,
                        'hall_extension_charge' => $calcBreakdown->hallExtensionCharge / 100,
                        'total_amount' => $lineTotal,
                    ],
                ];
            } catch (\Throwable $e) {
                $evaluatedLines[$index] = [
                    'facility_id' => $facility->id,
                    'facility_name' => $facility->name,
                    'facility_type' => $facility->facilityType?->slug,
                    'is_available' => false,
                    'reason' => $e->getMessage(),
                    'quoted_amount' => 0.0,
                    'breakdown' => null,
                ];
                $allAvailable = false;
            }
        }

        $windows = collect($evaluatedLines)->filter(fn ($line) => $line['is_available'] ?? false)
            ->map(fn ($line, $index) => ['index' => $index, 'start' => Carbon::parse($line['check_in']), 'end' => Carbon::parse($line['check_out'])])
            ->sortBy('start')->values();
        if ($windows->count() > 1) {
            $visitEnd = $windows->first()['end'];
            foreach ($windows->skip(1) as $window) {
                if ($window['start']->gt($visitEnd->copy()->addHours(2))) {
                    $index = $window['index'];
                    $evaluatedLines[$index]['is_available'] = false;
                    $evaluatedLines[$index]['reason'] = 'This schedule is separate from the other services. Choose a time within the same resort visit.';
                    $totalEstimate -= $evaluatedLines[$index]['quoted_amount'];
                    $allAvailable = false;
                }
                if ($window['end']->gt($visitEnd)) $visitEnd = $window['end'];
            }
        }

        // Generate version token sealing the evaluated payload and timestamp
        $versionPayload = [
            'timestamp' => $checkedAt->timestamp,
            'lines' => collect($evaluatedLines)->map(fn ($l) => [
                'facility_id' => $l['facility_id'],
                'check_in' => $l['check_in'] ?? null,
                'check_out' => $l['check_out'] ?? null,
                'guest_count' => $l['guest_count'] ?? 1,
                'quoted_amount' => $l['quoted_amount'] ?? 0.0,
                'is_available' => $l['is_available'] ?? false,
            ])->all(),
            'total_estimate' => $totalEstimate,
            'all_available' => $allAvailable,
        ];

        $versionToken = Crypt::encryptString(json_encode($versionPayload));
        foreach ($evaluatedLines as $index => &$line) {
            $line['availability_status'] = ($line['is_available'] ?? false) ? 'available' : ($line['availability_status'] ?? 'unavailable');
            $line['status'] = \App\Enums\ServiceAvailability::from($line['availability_status'])->label();
            $line['checked_at'] = $checkedAt->toIso8601String();
            $line['version_token'] = Crypt::encryptString(json_encode([
                'timestamp' => $checkedAt->timestamp,
                'index' => $index,
                'facility_id' => $line['facility_id'],
                'check_in' => $line['check_in'] ?? null,
                'check_out' => $line['check_out'] ?? null,
                'guest_count' => $line['guest_count'] ?? null,
                'quoted_amount' => $line['quoted_amount'] ?? 0,
            ]));
        }
        unset($line);

        return [
            'lines' => $evaluatedLines,
            'all_available' => $allAvailable,
            'total_estimate' => $totalEstimate,
            'deposit_estimate' => ceil((int) round($totalEstimate * 100) / 2) / 100,
            'checked_at' => $checkedAt->format('M d, Y g:i:s A'),
            'visit_window' => $windows->isEmpty() ? null : [
                'start' => $windows->first()['start']->toDateTimeString(),
                'end' => $windows->max('end')->toDateTimeString(),
            ],
            'version_token' => $versionToken,
        ];
    }

    /**
     * Validate an availability token to ensure it hasn't expired (max 10 mins).
     */
    public function isTokenFresh(string $token, int $maxAgeSeconds = 600): bool
    {
        try {
            $data = json_decode(Crypt::decryptString($token), true);
            if (! is_array($data) || empty($data['timestamp'])) {
                return false;
            }
            return (now()->timestamp - (int) $data['timestamp']) <= $maxAgeSeconds;
        } catch (\Throwable) {
            return false;
        }
    }

    public function matchesCurrentQuote(string $token, array $evaluation): bool
    {
        try {
            $original = json_decode(Crypt::decryptString($token), true);
            $current = json_decode(Crypt::decryptString($evaluation['version_token']), true);
            unset($original['timestamp'], $current['timestamp']);

            return $this->isTokenFresh($token) && $original === $current;
        } catch (\Throwable) {
            return false;
        }
    }
}
