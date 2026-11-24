<?php

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\AmenitySeeder;
use Database\Seeders\FacilitySeeder;
use Database\Seeders\FacilityTypeSeeder;
use Database\Seeders\RateSeeder;
use Database\Seeders\ResortServicesSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || ! str_ends_with(config('database.connections.sqlite.database'), 'confirmed-policy-preview.sqlite')) {
    throw new RuntimeException('This helper is limited to the dedicated policy preview SQLite database.');
}
Artisan::call('migrate', ['--force' => true]);
foreach ([RoleSeeder::class, AdminUserSeeder::class, FacilityTypeSeeder::class,
    AmenitySeeder::class, FacilitySeeder::class, RateSeeder::class,
    ResortServicesSeeder::class] as $seeder) {
    Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
}
$guest = User::firstOrCreate(['email' => 'policy-guest@villamercedes.test'], [
    'name' => 'Policy Preview Guest', 'password' => Hash::make('PreviewPolicy123!'), 'email_verified_at' => now(),
    'phone' => '09171234567', 'address' => 'Ilagan, Isabela', 'nationality' => 'Filipino', 'gender' => 'female', 'valid_id_type' => 'philsys',
]);
$guest->assignRole(Role::Customer->value);
$owner = User::firstOrCreate(['email' => 'policy-owner@villamercedes.test'], [
    'name' => 'Policy Preview Owner', 'password' => Hash::make('PreviewPolicy123!'), 'email_verified_at' => now(),
]);
$owner->assignRole([Role::Administrator->value, Role::Owner->value]);
$room = Facility::whereHas('facilityType', fn ($q) => $q->where('slug', 'room'))->firstOrFail();
$line = Reservation::firstOrCreate(['reservation_code' => 'VM-POLICY-PREVIEW'], [
    'user_id' => $guest->id, 'facility_id' => $room->id, 'reservation_date' => now()->toDateString(),
    'check_in' => now()->addDays(30)->format('Y-m-d').' 09:00', 'check_out' => now()->addDays(30)->format('Y-m-d').' 17:00',
    'guest_count' => 2, 'early_check_in_hours' => 0, 'late_check_out_hours' => 0,
    'base_price' => 2000, 'additional_charges' => 0, 'total_price' => 2000, 'deposit_required' => 1000,
    'status' => ReservationStatus::Approved, 'reservation_source' => ReservationSource::Online,
    'policy_version' => config('resort.policy.confirmed_version'), 'policies_accepted_at' => now(),
    'booking_workflow' => ['infants_count' => 1, 'children_count' => 1],
]);
Payment::firstOrCreate(['reservation_id' => $line->id, 'reference_number' => 'SYNTHETIC-POLICY-PAYMENT'], [
    'payment_date' => now()->toDateString(), 'amount' => 1000, 'method' => 'bank_transfer', 'status' => 'verified', 'verified_at' => now(), 'verified_by' => $owner->id,
]);
echo 'Synthetic preview reservation '.$line->id.PHP_EOL;
