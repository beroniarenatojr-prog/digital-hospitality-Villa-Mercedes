<?php

namespace Tests\Feature\Admin;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class GuestSheetNumberConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_concurrent_first_downloads_are_unique_and_concurrent_reprints_keep_the_number(): void
    {
        $this->seed();
        $one = Reservation::factory()->create(['status' => ReservationStatus::Approved]);
        $two = Reservation::factory()->create(['status' => ReservationStatus::Approved]);
        $database = config('database.connections.mysql');
        $this->assertStringEndsWith('_test', $database['database']);
        $env = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $database['database'],
            'DB_HOST' => $database['host'], 'DB_PORT' => (string) $database['port'],
            'DB_USERNAME' => $database['username'], 'DB_PASSWORD' => $database['password'],
            'MAIL_MAILER' => 'array', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
        ];
        $workers = [];
        foreach ([$one->id, $two->id, $one->id] as $id) {
            $worker = new Process([PHP_BINARY, base_path('tests/Support/issue-sheet-number.php'), (string) $id], base_path(), $env);
            $worker->start();
            $workers[] = $worker;
        }
        $numbers = [];
        foreach ($workers as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
            $numbers[] = (int) trim($worker->getOutput());
        }
        $this->assertSame($numbers[0], $numbers[2]);
        $unique = array_values(array_unique($numbers));
        sort($unique);
        $this->assertSame([1, 2], $unique);
        $this->assertEquals(3, DB::table('document_sequences')->value('next_number'));
    }
}
