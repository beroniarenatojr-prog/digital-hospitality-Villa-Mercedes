<?php

namespace Tests\Feature\Http;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
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
            'status' => ReservationStatus::Approved->value,
        ]);

        $file = UploadedFile::fake()->image('proof.jpg');

        $response = $this->actingAs($customer)->post(route('customer.payments.store', $reservation), [
            'amount' => 2500,
            'payment_method' => PaymentMethod::GCash->value,
            'proof' => $file,
        ]);

        $response->assertRedirect(route('customer.reservations.index', ['tab' => 'upcoming']));

        $payment = Payment::query()->where('reservation_id', $reservation->id)->first();
        $this->assertNotNull($payment);
        $this->assertNotNull($payment->proof_path);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('uploads');
        $disk->assertExists($payment->proof_path);

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

        $this->actingAs($customer2)->get(route('customer.payments.view', $payment))->assertStatus(403);
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

        $this->actingAs($admin)->get(route('admin.payments.view', $payment))->assertStatus(200);
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
        $this->assertSame(0, Payment::query()->where('reservation_id', $reservation->id)->count());
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
        $this->assertSame(0, Payment::query()->where('reservation_id', $reservation->id)->count());
    }

    public function test_customer_cannot_download_proof_using_path_traversal(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);

        $reservation = Reservation::factory()->create([
            'user_id' => $customer->id,
        ]);

        $secret = 'TOP_SECRET_PAYMENT_PROOF';
        Storage::disk('uploads')->put('safe.txt', $secret);

        $paths = [
            '../../other-file',
            '../../../etc/passwd',
            '..\\..\\other-file',
            '%2e%2e/%2e%2e/other-file',
            'uploads/../safe.txt',
        ];

        foreach ($paths as $path) {
            $payment = Payment::factory()->create([
                'reservation_id' => $reservation->id,
                'amount' => 1000,
                'status' => PaymentStatus::Pending->value,
                'proof_path' => $path,
            ]);

            $response = $this->actingAs($customer)->get(route('customer.payments.download', $payment));

            $this->assertContains($response->status(), [403, 404], "Traversal path [{$path}] must not succeed.");
            $this->assertStringNotContainsString($secret, $response->getContent());

            $view = $this->actingAs($customer)->get(route('customer.payments.view', $payment));
            $this->assertContains($view->status(), [403, 404], "View traversal path [{$path}] must not succeed.");
            $this->assertStringNotContainsString($secret, $view->getContent());
        }
    }

    public function test_admin_cannot_download_proof_using_path_traversal(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::Administrator->value);

        $reservation = Reservation::factory()->create();
        $secret = 'ADMIN_TRAVERSAL_SECRET';
        Storage::disk('uploads')->put('safe-admin.txt', $secret);

        $payment = Payment::factory()->create([
            'reservation_id' => $reservation->id,
            'amount' => 1000,
            'status' => PaymentStatus::Pending->value,
            'proof_path' => '../../safe-admin.txt',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.payments.download', $payment));

        $this->assertContains($response->status(), [403, 404]);
        $this->assertStringNotContainsString($secret, $response->getContent());
    }

    public function test_uploaded_proof_filename_cannot_escape_the_uploads_directory(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole(Role::Customer->value);

        $reservation = Reservation::factory()->create([
            'user_id' => $customer->id,
            'total_price' => 5000,
            'status' => ReservationStatus::Approved->value,
        ]);

        $file = UploadedFile::fake()->image('../../evil.jpg');

        $this->actingAs($customer)->post(route('customer.payments.store', $reservation), [
            'amount' => 2500,
            'payment_method' => PaymentMethod::GCash->value,
            'proof' => $file,
        ])->assertRedirect(route('customer.reservations.index', ['tab' => 'upcoming']));

        $payment = Payment::query()->where('reservation_id', $reservation->id)->first();
        $this->assertNotNull($payment);
        $this->assertStringNotContainsString('..', $payment->proof_path);
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('uploads');
        $disk->assertExists($payment->proof_path);
    }
}
