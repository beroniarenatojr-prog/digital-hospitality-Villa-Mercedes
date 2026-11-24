<?php

namespace Tests\Feature\EndToEnd;

use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PackageAndConcurrencyIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReservationCatalog();
    }

    public function test_package_and_component_booking_conflict()
    {
        $customerA = User::factory()->create();
        $customerA->assignRole('Customer');

        $customerB = User::factory()->create();
        $customerB->assignRole('Customer');

        $packageType = FacilityType::where('slug', 'resort_package')->first();
        $package = Facility::where('facility_type_id', $packageType->id)
            ->where('is_active', true)
            ->where('stay_spans_midnight', true)
            ->whereHas('packageComponents.componentFacility', function ($query) {
                $query->where('is_listed', true)->where('is_active', true);
            })
            ->first();
        
        // Find a resource that is part of the package
        $component = $package->packageComponents()->with('componentFacility.facilityType')->get()
            ->first(fn ($part) => $part->componentFacility->isRoom())->componentFacility;
        $this->assertNotNull($component);

        \App\Models\Rate::create([
            'facility_id' => $package->id,
            'type' => \App\Enums\RateType::Base->value,
            'name' => 'Base Rate',
            'amount' => 10000,
            'unit' => 'per package',
            'is_active' => true,
        ]);

        [$checkIn, $checkOut] = $package->occupancyWindowForDate(Carbon::now()->addDays(10));
        $checkIn = $checkIn->toDateTimeString();
        $checkOut = $checkOut->toDateTimeString();

        // 1. Customer A books the Package
        $responseA = $this->actingAs($customerA)->post(route('customer.reservations.store'), [
            ...$this->guestInformationPayload(),
            'facility_id' => $package->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guestCount' => 2,
        ]);
        $responseA->assertRedirect();
        
        $this->assertDatabaseHas('reservations', [
            'user_id' => $customerA->id,
            'facility_id' => $package->id,
        ]);

        // 2. Customer B attempts to book the Component on the same dates
        $responseB = $this->actingAs($customerB)->post(route('customer.reservations.store'), [
            ...$this->guestInformationPayload(),
            'facility_id' => $component->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guestCount' => 2,
        ]);

        $responseB->assertSessionHas('error');
        $this->assertDatabaseMissing('reservations', [
            'user_id' => $customerB->id,
            'facility_id' => $component->id,
            'check_in' => $checkIn,
        ]);

        // Reverse scenario
        [$checkIn2, $checkOut2] = $package->occupancyWindowForDate(Carbon::now()->addDays(20));
        $checkIn2 = $checkIn2->toDateTimeString();
        $checkOut2 = $checkOut2->toDateTimeString();

        // Customer B books the Component
        $responseB2 = $this->actingAs($customerB)->post(route('customer.reservations.store'), [
            ...$this->guestInformationPayload(),
            'facility_id' => $component->id,
            'check_in' => $checkIn2,
            'check_out' => $checkOut2,
            'guestCount' => 2,
        ]);
        $responseB2->assertRedirect();

        $this->assertDatabaseHas('reservations', [
            'user_id' => $customerB->id,
            'facility_id' => $component->id,
        ]);

        // Customer A attempts to book the Package
        $responseA2 = $this->actingAs($customerA)->post(route('customer.reservations.store'), [
            ...$this->guestInformationPayload(),
            'facility_id' => $package->id,
            'check_in' => $checkIn2,
            'check_out' => $checkOut2,
            'guestCount' => 2,
        ]);
        $responseA2->assertSessionHas('error');

        $this->assertDatabaseMissing('reservations', [
            'user_id' => $customerA->id,
            'facility_id' => $package->id,
            'check_in' => $checkIn2,
        ]);
    }

    public function test_capacity_edge_cases()
    {
        $customer = User::factory()->create();
        $customer->assignRole('Customer');

        $facility = Facility::where('is_active', true)->where('max_capacity', '>', 2)->first();
        $this->assertNotNull($facility);

        $checkIn = Carbon::now()->addDays(30)->format('Y-m-d');
        $checkOut = Carbon::now()->addDays(32)->format('Y-m-d');

        // Over capacity fails
        $overCapacity = $facility->max_capacity + 5;
        $response = $this->actingAs($customer)->post(route('customer.reservations.store'), [
            ...$this->guestInformationPayload(),
            'facility_id' => $facility->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guestCount' => $overCapacity,
        ]);

        $this->assertDatabaseMissing('reservations', [
            'user_id' => $customer->id,
            'facility_id' => $facility->id,
            'guest_count' => $overCapacity,
        ]);
    }
}
