<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$packageType = App\Models\FacilityType::where('slug', 'resort_package')->first();
$package = App\Models\Facility::where('facility_type_id', $packageType->id)->whereHas('packageComponents')->where('is_active', true)->first();
$component = $package->packageComponents()->first()->componentFacility;

$checkIn = \Carbon\Carbon::now()->addDays(10)->format('Y-m-d');
$checkOut = \Carbon\Carbon::now()->addDays(12)->format('Y-m-d');
$checkInCarbon = \Carbon\Carbon::parse($checkIn);
$checkOutCarbon = \Carbon\Carbon::parse($checkOut);

$parentPackages = $component->partOfPackages()->get();
echo "Component's parent packages count: " . $parentPackages->count() . PHP_EOL;

if ($parentPackages->count() > 0) {
    $parentPackage = $parentPackages->first();
    echo "Parent Package ID: " . $parentPackage->id . " Name: " . $parentPackage->name . PHP_EOL;
    
    $blockingStatuses = [
        App\Enums\ReservationStatus::Pending->value,
        App\Enums\ReservationStatus::Approved->value,
    ];
    
    $query = App\Models\Reservation::query()
        ->where('facility_id', $parentPackage->id)
        ->whereIn('status', $blockingStatuses)
        ->where(function ($q) use ($checkInCarbon, $checkOutCarbon) {
            $q->where('check_in', '<', $checkOutCarbon)
              ->where('check_out', '>', $checkInCarbon);
        });
        
    echo "Conflicting reservations on parent package: " . $query->count() . PHP_EOL;
}
