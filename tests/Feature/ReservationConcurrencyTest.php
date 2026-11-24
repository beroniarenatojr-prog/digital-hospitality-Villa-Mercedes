<?php

namespace Tests\Feature;

use App\Enums\FacilityStatus;
use App\Enums\ReservationStatus;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\FacilityTypeSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReservationConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed([RoleSeeder::class, FacilityTypeSeeder::class]);
    }

    private function facility(string $slug = 'room'): Facility
    {
        $facility = Facility::factory()->create([
            'facility_type_id' => FacilityType::where('slug', $slug)->firstOrFail()->id,
            'status' => FacilityStatus::Available, 'is_active' => true, 'capacity' => 4, 'max_capacity' => 4,
        ]);
        $facility->rates()->create(['name' => 'Base', 'type' => 'base', 'amount' => 3000, 'unit' => 'per night', 'is_active' => true]);

        return $facility;
    }

    public function test_simultaneous_sibling_package_requests_accept_only_one_booking(): void
    {
        $room = $this->facility();
        $first = $this->facility('resort_package');
        $second = $this->facility('resort_package');
        foreach ([$first, $second] as $package) {
            $package->packageComponents()->create(['component_facility_id' => $room->id]);
        }
        $customer = User::factory()->create();
        $customer->assignRole('Customer');

        $this->assertSame(['accepted', 'rejected'], $this->race('booking', [$first->id, $second->id], $customer));
        $this->assertSame(1, Reservation::count());
    }

    public function test_simultaneous_payment_submissions_accept_only_one_full_balance(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('Customer');
        $reservation = Reservation::factory()->create([
            'user_id' => $customer->id, 'facility_id' => $this->facility()->id,
            'status' => ReservationStatus::Approved, 'total_price' => 3000,
        ]);

        $this->assertSame(['accepted', 'rejected'], $this->race('payment', [$reservation->id, $reservation->id], $customer));
        $this->assertSame(3000.0, $reservation->fresh()->getPendingVerificationTotal());
    }

    /** @return list<string> */
    public function test_simultaneous_new_deposit_submissions_cannot_exceed_required_deposit(): void
    {
        config(['payments.deposit_workflow' => true]);
        \App\Models\PaymentDestination::create(['method' => 'gcash', 'account_name' => 'Test', 'account_number' => 'TEST', 'qr_path' => 'test.png', 'is_active' => true]);
        $customer = User::factory()->create();
        $customer->assignRole('Customer');
        // Approval is what opens the deposit, so the race is between two guests
        // paying a confirmed booking's half at the same moment.
        $reservation = Reservation::factory()->create(['user_id' => $customer->id, 'facility_id' => $this->facility()->id,
            'status' => ReservationStatus::Approved, 'total_price' => 3000, 'deposit_required' => 1500, 'deposit_due_at' => now()->addHours(24)]);
        $this->assertSame(['accepted', 'rejected'], $this->race('deposit', [$reservation->id, $reservation->id], $customer));
        $this->assertSame(1500.0, $reservation->fresh()->getPendingVerificationTotal());
    }

    public function test_simultaneous_new_standard_holds_accept_only_one_booking(): void
    {
        config(['payments.deposit_workflow' => true]);
        \App\Models\PaymentDestination::create(['method' => 'gcash', 'account_name' => 'Test', 'account_number' => 'TEST', 'qr_path' => 'test.png', 'is_active' => true]);
        $customer = User::factory()->create();
        $customer->assignRole('Customer');
        $room = $this->facility();
        $this->assertSame(['accepted', 'rejected'], $this->race('booking', [$room->id, $room->id], $customer));
        $this->assertSame(1, Reservation::count());
        $this->assertNotNull(Reservation::first()->deposit_required);
    }

    /** @return list<string> */
    private function race(string $operation, array $targetIds, User $actor): array
    {
        $directory = sys_get_temp_dir().'/villa-mercedes-race-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $gate = $directory.'/start';
        $workers = [];
        $environment = [
            'PAYMENT_DEPOSIT_WORKFLOW' => config('payments.deposit_workflow') ? 'true' : 'false',
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => config('database.connections.mysql.database'),
            'APP_CONFIG_CACHE' => 'bootstrap/cache/race-config-not-present.php',
            'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
        ];

        try {
            foreach ($targetIds as $index => $targetId) {
                $worker = new Process([
                    PHP_BINARY, base_path('tests/Support/reservation-race-worker.php'), $operation,
                    (string) $targetId, (string) $actor->id, $gate, $directory.'/ready-'.$index,
                ], base_path(), $environment, timeout: 30);
                $worker->start();
                $workers[] = $worker;
            }

            $deadline = microtime(true) + 15;
            while (count(glob($directory.'/ready-*')) !== count($workers)) {
                if (microtime(true) >= $deadline) {
                    $this->fail('Workers did not become ready: '.implode("\n", array_map(fn (Process $worker) => $worker->getErrorOutput(), $workers)));
                }
                usleep(10000);
            }
            file_put_contents($gate, 'start');

            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
                $results[] = trim($worker->getOutput());
            }
            sort($results);

            return $results;
        } finally {
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            foreach (glob($directory.'/*') as $path) {
                unlink($path);
            }
            rmdir($directory);
        }
    }
}
