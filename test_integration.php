<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$customerA = App\Models\User::factory()->create();
$customerB = App\Models\User::factory()->create();

$packageType = App\Models\FacilityType::where('slug', 'resort_package')->first();
$package = App\Models\Facility::where('facility_type_id', $packageType->id)->whereHas('packageComponents')->where('is_active', true)->first();
$component = $package->packageComponents()->first()->componentFacility;

$checkIn = \Carbon\Carbon::now()->addDays(30)->format('Y-m-d');
$checkOut = \Carbon\Carbon::now()->addDays(32)->format('Y-m-d');

App\Models\Reservation::create([
    'user_id' => $customerA->id,
    'facility_id' => $package->id,
    'reservation_code' => 'TEST-12345',
    'reservation_date' => now()->toDateString(),
    'check_in' => $checkIn,
    'check_out' => $checkOut,
    'guest_count' => 2,
    'base_price' => 100,
    'total_price' => 100,
    'status' => App\Enums\ReservationStatus::Pending->value,
    'reservation_source' => App\Enums\ReservationSource::Online->value,
]);

$availabilityService = app(App\Services\AvailabilityService::class);
$result = $availabilityService->isAvailable(
    $component,
    \Carbon\Carbon::parse($checkIn),
    \Carbon\Carbon::parse($checkOut),
    null,
    2
);

echo "Available: " . ($result->isAvailable ? 'yes' : 'no') . PHP_EOL;
echo "Reason: " . $result->reason . PHP_EOL;
