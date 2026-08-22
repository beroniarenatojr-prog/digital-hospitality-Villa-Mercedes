<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer1;
    private User $customer2;
    private Facility $facility;
    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed();

        $this->admin = clone User::whereHas('roles', function ($q) {
            $q->where('name', 'Administrator');
        })->first();

        $this->customer1 = User::factory()->create();
        $this->customer1->assignRole('Customer');

        $this->customer2 = User::factory()->create();
        $this->customer2->assignRole('Customer');

        $this->facility = Facility::where('is_active', true)->first();

        $this->reservation = Reservation::factory()->create([
            'user_id' => $this->customer1->id,
            'facility_id' => $this->facility->id,
            'status' => ReservationStatus::Pending,
        ]);
    }

    public function test_customer_cannot_access_admin_dashboard_or_any_admin_route()
    {
        $this->actingAs($this->customer1);

        $response = $this->get(route('admin.dashboard'));
        $response->assertForbidden();

        $response = $this->get(route('admin.reservations.index'));
        $response->assertForbidden();

        $response = $this->get(route('admin.payments.index'));
        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_admin_routes()
    {
        $response = $this->get(route('admin.dashboard'));
        $response->assertRedirect('/login');
    }

    public function test_idor_customer_cannot_access_another_customers_reservation_via_admin_routes()
    {
        $this->actingAs($this->customer2);

        $response = $this->get(route('admin.reservations.show', $this->reservation));
        
        // Should be forbidden because they are not an admin
        $response->assertForbidden();
    }

    public function test_customer_cannot_force_status_change_via_admin_routes()
    {
        $this->actingAs($this->customer1);

        $response = $this->patch(route('admin.reservations.approve', $this->reservation));
        $response->assertForbidden();

        $this->assertEquals(ReservationStatus::Pending, $this->reservation->fresh()->status);
    }

    public function test_parameter_tampering_customer_cannot_update_own_reservation_status_via_customer_routes()
    {
        $this->actingAs($this->customer1);

        $response = $this->patch(route('customer.reservations.reschedule', $this->reservation), [
            'status' => ReservationStatus::Approved->value,
            'facility_id' => $this->facility->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(6)->toDateString(),
            'guestCount' => 2,
        ]);

        $this->assertEquals(ReservationStatus::Pending, $this->reservation->fresh()->status);
    }

    public function test_admin_cannot_approve_cancelled_reservation()
    {
        $this->actingAs($this->admin);

        $this->reservation->update(['status' => ReservationStatus::Cancelled]);

        $response = $this->patch(route('admin.reservations.approve', $this->reservation));
        
        $response->assertSessionHas('error');
        $this->assertEquals(ReservationStatus::Cancelled, $this->reservation->fresh()->status);
    }

    public function test_payment_proof_download_idor_protection()
    {
        Storage::fake('uploads');
        $file = UploadedFile::fake()->image('proof.jpg');
        $path = $file->store('payments', 'uploads');

        $payment = Payment::factory()->create([
            'reservation_id' => $this->reservation->id,
            'proof_path' => $path,
            'status' => PaymentStatus::Pending,
        ]);

        // Customer 2 tries to download Customer 1's payment proof via customer route
        $this->actingAs($this->customer2);
        $response = $this->get(route('customer.payments.download', $payment));
        $response->assertForbidden();

        // Customer 1 can download their own
        $this->actingAs($this->customer1);
        $response = $this->get(route('customer.payments.download', $payment));
        $response->assertSuccessful();

        // Admin can download
        $this->actingAs($this->admin);
        $response = $this->get(route('admin.payments.download', $payment));
        $response->assertSuccessful();
    }

    public function test_admin_cannot_verify_already_verified_payment()
    {
        $this->actingAs($this->admin);

        $payment = Payment::factory()->create([
            'reservation_id' => $this->reservation->id,
            'status' => PaymentStatus::Verified,
        ]);

        $response = $this->patch(route('admin.payments.verify', $payment));
        $response->assertSessionHas('error');
    }

    public function test_admin_reject_payment_requires_reason()
    {
        $this->actingAs($this->admin);

        $payment = Payment::factory()->create([
            'reservation_id' => $this->reservation->id,
            'status' => PaymentStatus::Pending,
        ]);

        $response = $this->patch(route('admin.payments.reject', $payment), []);
        $response->assertSessionHasErrors('reason');
    }

    public function test_capacity_enforcement_blocks_over_capacity()
    {
        $this->actingAs($this->customer1);

        $maxCapacity = $this->facility->max_capacity ?? $this->facility->capacity + 1;
        $overCapacity = $maxCapacity + 5;

        // Ensure this throws exception in service or is blocked
        $response = $this->post(route('customer.reservations.store'), [
            'facility_id' => $this->facility->id,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(11)->toDateString(),
            'guestCount' => $overCapacity,
        ]);

        $this->assertDatabaseMissing('reservations', [
            'guest_count' => $overCapacity,
        ]);
    }
}
