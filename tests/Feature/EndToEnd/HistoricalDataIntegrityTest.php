<?php

namespace Tests\Feature\EndToEnd;

use App\Models\Facility;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricalDataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_rate_change_historical_reservation_integrity()
    {
        $customer = User::factory()->create();
        $customer->assignRole('Customer');

        $facility = Facility::query()->where('slug', 'deluxe-room-27')->firstOrFail();
        $rate = $facility->rates()->where('type', 'base')->first();

        // 1. Create Reservation A well clear of demo-seeded stays
        $checkInA = Carbon::now()->addDays(40)->format('Y-m-d');
        $checkOutA = Carbon::now()->addDays(42)->format('Y-m-d');

        $this->actingAs($customer)->post(route('customer.reservations.store'), [
            'facility_id' => $facility->id,
            'check_in' => $checkInA,
            'check_out' => $checkOutA,
            'guestCount' => 2,
        ] + $this->guestInformationPayload());

        $reservationA = Reservation::where('user_id', $customer->id)->first();
        $originalPrice = $reservationA->total_price;

        // 2. Admin changes the rate
        $admin = User::whereHas('roles', fn($q) => $q->where('name', 'Administrator'))->first();
        $newAmount = $rate->amount + 50000; // Increase by 500
        
        $this->actingAs($admin)->put(route('admin.rates.update', $rate), [
            'type' => 'base',
            'name' => 'Updated Base Rate',
            'amount' => $newAmount,
            'unit' => 'per night',
            'is_active' => true,
        ]);

        $this->assertEquals($newAmount, $rate->fresh()->amount);

        // 3. Create Reservation B
        $checkInB = Carbon::now()->addDays(50)->format('Y-m-d');
        $checkOutB = Carbon::now()->addDays(52)->format('Y-m-d');

        $this->actingAs($customer)->post(route('customer.reservations.store'), [
            'facility_id' => $facility->id,
            'check_in' => $checkInB,
            'check_out' => $checkOutB,
            'guestCount' => 2,
        ] + $this->guestInformationPayload());

        $reservationB = Reservation::where('user_id', $customer->id)->where('id', '!=', $reservationA->id)->first();

        // 4. Verify Historical Integrity
        $this->assertEquals($originalPrice, $reservationA->fresh()->total_price);
        $this->assertGreaterThan($originalPrice, $reservationB->total_price);
    }
}
