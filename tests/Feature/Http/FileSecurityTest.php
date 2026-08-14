<?php

namespace Tests\Feature\Http;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('uploads');
    }

    public function test_customer_can_upload_valid_proof_and_download_it()
    {
        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);

        $reservation = Reservation::factory()->create([
            'user_id' => $customer->id,
            'total_price' => 5000,
        ]);

        $file = UploadedFile::fake()->image('proof.jpg');

        $response = $this->actingAs($customer)->post(route('customer.payments.store', $reservation), [
            'amount' => 2500,
            'payment_method' => PaymentMethod::GCash->value,
            'proof' => $file,
        ]);

        $response->assertRedirect(route('customer.reservations.show', $reservation));

        $payment = Payment::first();
        $this->assertNotNull($payment);
        $this->assertNotNull($payment->proof_path);

        Storage::disk('uploads')->assertExists($payment->proof_path);

        // Download proof
        $downloadResponse = $this->actingAs($customer)->get(route('customer.payments.download', $payment));
        $downloadResponse->assertStatus(200);
    }

    public function test_customer_cannot_download_another_customers_proof()
    {
        $customer1 = User::factory()->create();
        $customer1->assignRole(Role::Customer->value);

        $customer2 = User::factory()->create();
        $customer2->assignRole(Role::Customer->value);

        $reservation = Reservation::factory()->create([
            'user_id' => $customer1->id,
        ]);

        $payment = Payment::factory()->create([
            'reservation_id' => $reservation->id,
            'amount' => 1000,
            'status' => PaymentStatus::Pending->value,
            'proof_path' => 'fake_path.jpg',
        ]);
        Storage::disk('uploads')->put('fake_path.jpg', 'content');

        $response = $this->actingAs($customer2)->get(route('customer.payments.download', $payment));
        $response->assertStatus(403);
    }

    public function test_admin_can_download_any_proof()
    {
        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);

        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);

        $reservation = Reservation::factory()->create([
            'user_id' => $customer->id,
        ]);

        $payment = Payment::factory()->create([
            'reservation_id' => $reservation->id,
            'amount' => 1000,
            'status' => PaymentStatus::Pending->value,
            'proof_path' => 'fake_admin_path.jpg',
        ]);
        Storage::disk('uploads')->put('fake_admin_path.jpg', 'content');

        $response = $this->actingAs($admin)->get(route('admin.payments.download', $payment));
        $response->assertStatus(200);
    }

    public function test_invalid_mime_type_is_rejected()
    {
        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);

        $reservation = Reservation::factory()->create([
            'user_id' => $customer->id,
        ]);

        $file = UploadedFile::fake()->create('script.php', 100, 'application/x-httpd-php');

        $response = $this->actingAs($customer)->post(route('customer.payments.store', $reservation), [
            'amount' => 1000,
            'payment_method' => PaymentMethod::GCash->value,
            'proof' => $file,
        ]);

        $response->assertSessionHasErrors('proof');
        $this->assertEquals(0, Payment::count());
    }

    public function test_oversized_file_is_rejected()
    {
        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);

        $reservation = Reservation::factory()->create([
            'user_id' => $customer->id,
        ]);

        // Max size is 5120 KB, create a 6000 KB file
        $file = UploadedFile::fake()->create('large.jpg', 6000, 'image/jpeg');

        $response = $this->actingAs($customer)->post(route('customer.payments.store', $reservation), [
            'amount' => 1000,
            'payment_method' => PaymentMethod::GCash->value,
            'proof' => $file,
        ]);

        $response->assertSessionHasErrors('proof');
        $this->assertEquals(0, Payment::count());
    }
}
