<?php

namespace Tests\Feature\EndToEnd;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReservationCatalog();
        Storage::fake('uploads');
    }

    public function test_reporting_reflects_actual_transactional_data()
    {
        $customer = User::factory()->create();
        $customer->assignRole('Customer');

        $admin = User::whereHas('roles', fn($q) => $q->where('name', 'Administrator'))->first();

        $facility = Facility::where('is_active', true)->first();

        // Admin checks report before
        $response = $this->actingAs($admin)->get(route('admin.reports.index', [
            'date_from' => now()->startOfMonth()->format('Y-m-d'),
            'date_until' => now()->endOfMonth()->format('Y-m-d'),
        ]));
        
        $response->assertStatus(200);

        // Make Reservation
        $checkIn = Carbon::now()->addDays(2)->format('Y-m-d');
        $checkOut = Carbon::now()->addDays(4)->format('Y-m-d');
        
        $this->actingAs($customer)->post(route('customer.reservations.store'), [
            ...$this->guestInformationPayload(),
            'facility_id' => $facility->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guestCount' => 2,
        ]);

        $reservation = Reservation::where('user_id', $customer->id)->first();
        $this->assertNotNull($reservation);

        // Approve it
        $this->actingAs($admin)->patch(route('admin.reservations.approve', $reservation));

        // Submit Payment
        $payment = Payment::factory()->create([
            'reservation_id' => $reservation->id,
            'amount' => $reservation->total_price,
            'status' => PaymentStatus::Pending,
        ]);

        // Verify Payment
        $this->actingAs($admin)->patch(route('admin.payments.verify', $payment));

        // Admin checks report after
        $response = $this->actingAs($admin)->get(route('admin.reports.index', [
            'date_from' => now()->startOfMonth()->format('Y-m-d'),
            'date_until' => now()->endOfMonth()->format('Y-m-d'),
        ]));
        
        $response->assertStatus(200);
        $response->assertSee(number_format($reservation->total_price, 2));
    }
}
