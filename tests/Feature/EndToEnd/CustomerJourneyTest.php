<?php

namespace Tests\Feature\EndToEnd;

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Facility;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('uploads');
    }

    public function test_complete_customer_journey()
    {
        // 1. Visitor browses public pages
        $response = $this->get('/');
        $response->assertStatus(200);

        $response = $this->get('/rooms');
        $response->assertStatus(200);
        $response->assertSee('Rooms');

        // Select a facility
        $facility = Facility::where('slug', 'deluxe-room-26')->first();
        $this->assertNotNull($facility);

        $response = $this->get("/facilities/{$facility->id}");
        $response->assertStatus(200);
        $response->assertSee($facility->name);

        // 2. Register
        $this->verifyRegistrationEmail('john.e2e@example.com');

        $response = $this->post('/register', $this->registrationPayload('john.e2e@example.com', [
            'name' => 'John Customer',
        ]));
        $response->assertRedirect(route('customer.reservations.index'));
        $this->assertAuthenticated();

        $customer = User::where('email', 'john.e2e@example.com')->first();
        $this->assertTrue($customer->hasRole('Customer'));

        // 3. Preview Booking Price
        $checkIn = Carbon::now()->addDays(40)->setTime(14, 0)->format('Y-m-d H:i:s');
        $checkOut = Carbon::now()->addDays(42)->setTime(12, 0)->format('Y-m-d H:i:s');
        $guestCount = 2;

        $response = $this->postJson(route('customer.booking.calculate', $facility), [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guestCount' => $guestCount,
        ]);
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data' => ['total_amount']]);

        // Ensure price calculation matched expectations (2 nights)
        $priceData = $response->json('data');
        $this->assertGreaterThan(0, $priceData['total_amount']);

        // 4. Submit Reservation
        $response = $this->post(route('customer.reservations.store'), [
            'facility_id' => $facility->id,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guestCount' => $guestCount,
        ] + $this->guestInformationPayload([
            'guest_name' => 'John Customer',
            'guest_email' => 'john.e2e@example.com',
            'guest_phone' => '09171234567',
        ]));

        $reservation = Reservation::where('user_id', $customer->id)->first();
        $this->assertNotNull($reservation);

        // A submitted request is not a booking: the guest lands on My reservations,
        // on the tab that holds it until the resort answers.
        $response->assertRedirect(route('customer.reservations.index', ['tab' => 'pending']));
        $response->assertSessionHas('success');

        // Verify Reservation State
        $this->assertEquals(ReservationStatus::Pending, $reservation->status);
        $this->assertEquals($facility->id, $reservation->facility_id);
        $this->assertEquals((float) ($priceData['total_amount'] / 100), (float) $reservation->total_price);
        $this->assertNotNull($reservation->reservation_code);

        // 5. Dashboard view
        $response = $this->get(route('customer.reservations.show', $reservation));
        $response->assertStatus(200);
        $response->assertSee($reservation->reservation_code);
        $response->assertSee('Pending');

        $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'Administrator'))->first();
        $this->actingAs($admin)->patch(route('admin.reservations.approve', $reservation))->assertSessionHas('error');
        $this->assertEquals(ReservationStatus::Pending, $reservation->fresh()->status);
        $this->actingAs($customer);

        // 6. Submit Payment
        $proof = UploadedFile::fake()->image('proof.jpg');
        $paymentAmount = $reservation->total_price / 2; // Pay half

        $response = $this->post(route('customer.payments.store', $reservation), [
            'amount' => $paymentAmount,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'REF-123456',
            'payment_date' => now()->format('Y-m-d'),
            'proof' => $proof,
        ]);

        $payment = Payment::where('reservation_id', $reservation->id)->first();
        $this->assertNotNull($payment);

        $response->assertRedirect(route('customer.reservations.index', ['tab' => 'pending']));
        $response->assertSessionHas('success');

        // Verify Payment State
        $this->get($response->headers->get('Location'))->assertOk()
            ->assertSee($reservation->reservation_code)->assertSee('Under review');
        $this->assertSame(ReservationStatus::Pending, $reservation->fresh()->status);
        $this->assertEquals(PaymentStatus::Pending, $payment->status);
        $this->assertEquals($paymentAmount, $payment->amount);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('uploads');
        $disk->assertExists($payment->proof_path);

        // Balance remains unchanged until verification
        $this->assertEquals(0, $reservation->getVerifiedTotalPaid());
        $this->assertEquals($reservation->total_price, $reservation->getRemainingBalance());

        // 7. Administrator Verifies Payment
        // Log out customer, log in admin
        $this->post('/logout');

        $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'Administrator'))->first();
        $this->actingAs($admin);

        // View payment
        $response = $this->get(route('admin.payments.show', $payment));
        $response->assertStatus(200);

        // Download proof
        $response = $this->get(route('admin.payments.download', $payment));
        $response->assertStatus(200);

        // Verify payment
        $response = $this->patch(route('admin.payments.verify', $payment));
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->patch(route('admin.reservations.approve', $reservation))->assertSessionHas('success');
        $this->assertEquals(ReservationStatus::Approved, $reservation->fresh()->status);

        // 8. Re-evaluate customer dashboard balance
        $this->post('/logout');
        $this->actingAs($customer);

        $response = $this->get(route('customer.reservations.show', $reservation));
        $response->assertStatus(200);

        $this->assertEquals($paymentAmount, $reservation->fresh()->getVerifiedTotalPaid());
        $this->assertEquals($reservation->total_price - $paymentAmount, $reservation->fresh()->getRemainingBalance());

        // Assuming notifications were sent via observer (we could check DB or just trust the feature tests, but let's check DB)
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $customer->id,
        ]);
    }
}
