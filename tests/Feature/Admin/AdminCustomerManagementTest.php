<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class AdminCustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::factory()->create();
        $this->admin->assignRole(Role::Administrator->value);

        $this->customer = User::factory()->create(['phone' => '09171234567']);
        $this->customer->assignRole(Role::Customer->value);
    }

    // --- Access Control ---

    public function test_unauthenticated_user_cannot_access_customer_management(): void
    {
        $response = $this->get(route('admin.customers.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_customer_cannot_access_customer_management(): void
    {
        $response = $this->actingAs($this->customer)->get(route('admin.customers.index'));
        $response->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_access_customer_listing(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.customers.index'));
        $response->assertStatus(200);
        $response->assertSee('Customers');
    }

    // --- Listing ---

    public function test_customers_display_in_listing(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.customers.index'));
        $response->assertStatus(200);
        $response->assertSee($this->customer->name);
        $response->assertSee($this->customer->email);
    }

    public function test_pagination_works(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.customers.index'));
        $customers = $response->viewData('customers');
        $this->assertInstanceOf(LengthAwarePaginator::class, $customers);
    }

    public function test_search_works(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.customers.index', ['search' => $this->customer->name]));
        $response->assertStatus(200);

        $customers = $response->viewData('customers');
        $this->assertTrue($customers->contains('id', $this->customer->id));
    }

    public function test_search_by_email_works(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.customers.index', ['search' => $this->customer->email]));
        $response->assertStatus(200);

        $customers = $response->viewData('customers');
        $this->assertTrue($customers->contains('id', $this->customer->id));
    }

    public function test_filter_has_reservations(): void
    {
        // Create a reservation for the test customer
        Reservation::factory()->create(['user_id' => $this->customer->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.customers.index', ['has_reservations' => '1']));
        $response->assertStatus(200);

        $customers = $response->viewData('customers');
        $this->assertTrue($customers->contains('id', $this->customer->id));
    }

    public function test_filter_without_reservations(): void
    {
        // Don't create any reservations for this customer
        $lonelyCustomer = User::factory()->create();
        $lonelyCustomer->assignRole(Role::Customer->value);

        $response = $this->actingAs($this->admin)->get(route('admin.customers.index', ['has_reservations' => '0']));
        $response->assertStatus(200);

        $customers = $response->viewData('customers');
        $this->assertTrue($customers->contains('id', $lonelyCustomer->id));
    }

    public function test_empty_state_works(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.customers.index', ['search' => 'nonexistent_customer_xyz_12345']));
        $response->assertStatus(200);
        $response->assertSee('No customers match these filters');
    }

    // --- Details ---

    public function test_admin_can_view_customer(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.customers.show', $this->customer->id));
        $response->assertStatus(200);
        $response->assertSee($this->customer->name);
    }

    public function test_customer_information_renders(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.customers.show', $this->customer->id));
        $response->assertStatus(200);
        $response->assertSee('Customer information');
        $response->assertSee($this->customer->name);
        $response->assertSee($this->customer->email);
        $response->assertSee($this->customer->phone);
    }

    public function test_reservation_summary_renders(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'status' => ReservationStatus::Approved->value,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.customers.show', $this->customer->id));
        $response->assertStatus(200);
        $response->assertSee('Reservation summary');
        $response->assertSee('Reservation history');
        $response->assertSee('Manage reservation');
        $response->assertSee(route('admin.reservations.show', $reservation), false);
    }

    public function test_payment_summary_renders(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
        ]);
        Payment::factory()->create([
            'reservation_id' => $reservation->id,
            'amount' => 1000.00,
            'status' => PaymentStatus::Verified->value,
            'payment_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.customers.show', $this->customer->id));
        $response->assertStatus(200);
        $response->assertSee('Payment summary');
        $response->assertSee('1,000.00');
    }

    // --- Security ---

    public function test_password_not_in_response(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.customers.show', $this->customer->id));
        $response->assertDontSee($this->customer->password);
    }

    public function test_remember_token_not_exposed(): void
    {
        $this->customer->update(['remember_token' => 'secret-token-abc123']);

        $response = $this->actingAs($this->admin)->get(route('admin.customers.show', $this->customer->id));
        $response->assertDontSee('secret-token-abc123');
    }

    public function test_payment_proof_path_not_exposed(): void
    {
        $reservation = Reservation::factory()->create(['user_id' => $this->customer->id]);
        Payment::factory()->create([
            'reservation_id' => $reservation->id,
            'proof_path' => 'proofs/secret-proof.jpg',
            'payment_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.customers.show', $this->customer->id));
        $response->assertDontSee('proofs/secret-proof.jpg');
    }

    // --- Relationships ---

    public function test_customer_reservations_link_correctly(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.customers.show', $this->customer->id));
        $response->assertStatus(200);
        $response->assertSee($reservation->reservation_code);
        $response->assertSee(route('admin.reservations.show', $reservation));
    }

    public function test_customer_payments_link_correctly(): void
    {
        $reservation = Reservation::factory()->create(['user_id' => $this->customer->id]);
        $payment = Payment::factory()->create([
            'reservation_id' => $reservation->id,
            'amount' => 500.00,
            'payment_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.customers.show', $this->customer->id));
        $response->assertStatus(200);
        $response->assertSee(route('admin.payments.show', $payment));
    }

    public function test_no_other_customers_data_appears(): void
    {
        $otherCustomer = User::factory()->create(['name' => 'Unrelated Customer ZZZ']);
        $otherCustomer->assignRole(Role::Customer->value);

        Reservation::factory()->create(['user_id' => $otherCustomer->id]);

        $response = $this->actingAs($this->admin)->get(route('admin.customers.show', $this->customer->id));
        $response->assertDontSee('Unrelated Customer ZZZ');
    }

    // --- Historical Integrity ---

    public function test_viewing_customer_does_not_modify_reservations(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->customer->id,
            'total_price' => 3000.00,
        ]);

        $this->actingAs($this->admin)->get(route('admin.customers.show', $this->customer->id));

        $reservation->refresh();
        $this->assertEquals('3000.00', $reservation->total_price);
    }

    // --- Customer cannot access admin customer routes ---

    public function test_customer_cannot_view_admin_customer_detail(): void
    {
        $response = $this->actingAs($this->customer)->get(route('admin.customers.show', $this->customer->id));
        $response->assertRedirect(route('admin.login'));
    }
}
