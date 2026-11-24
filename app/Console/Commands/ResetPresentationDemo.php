<?php

namespace App\Console\Commands;

use Database\Seeders\PresentationDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ResetPresentationDemo extends Command
{
    protected $signature = 'demo:reset {--force : Allow the reset outside the local or testing environment}';

    protected $description = 'Safely restore the tagged administrator presentation scenarios';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing']) && ! $this->option('force')) {
            $this->error('Refusing to reset demo data outside local/testing. Re-run with --force only if this is intentional.');

            return self::FAILURE;
        }

        if (config('payments.gateway') !== 'manual') {
            $this->error('PAYMENT_GATEWAY must be manual before presentation data can be prepared.');

            return self::FAILURE;
        }

        $guestEmail = trim((string) config('demo.guest_email'));
        if ($guestEmail === '' || ! filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error('Set DEMO_GUEST_EMAIL to an inbox you control, then run config:clear and retry.');

            return self::FAILURE;
        }

        $ids = DB::table('reservations')
            ->where('reservation_code', 'like', PresentationDemoSeeder::RESERVATION_PREFIX.'%')
            ->pluck('id');
        $paymentIds = $ids->isEmpty()
            ? collect()
            : DB::table('payments')->whereIn('reservation_id', $ids)->pluck('id');

        DB::transaction(function () use ($ids, $paymentIds): void {
            if ($ids->isNotEmpty()) {
                if (Schema::hasTable('activity_log')) {
                    DB::table('activity_log')->where(function ($query) use ($ids, $paymentIds): void {
                        $query->where(function ($reservationActivity) use ($ids): void {
                            $reservationActivity->where('subject_type', 'App\\Models\\Reservation')->whereIn('subject_id', $ids);
                        });

                        if ($paymentIds->isNotEmpty()) {
                            $query->orWhere(function ($paymentActivity) use ($paymentIds): void {
                                $paymentActivity->where('subject_type', 'App\\Models\\Payment')->whereIn('subject_id', $paymentIds);
                            });
                        }
                    })->delete();
                }
                DB::table('payments')->whereIn('reservation_id', $ids)->delete();
                DB::table('reservations')->whereIn('id', $ids)->delete();
            }

            if (Schema::hasTable('notifications')) {
                DB::table('notifications')->where(function ($query) use ($ids, $paymentIds): void {
                    $query->where('data', 'like', '%'.PresentationDemoSeeder::RESERVATION_PREFIX.'%');
                    foreach ($ids as $id) {
                        $query->orWhere('data', 'like', '%"reservation_id":'.$id.'%');
                    }
                    foreach ($paymentIds as $id) {
                        $query->orWhere('data', 'like', '%"payment_id":'.$id.'%');
                    }
                })->delete();
            }
        });

        Storage::disk('uploads')->deleteDirectory(PresentationDemoSeeder::PROOF_DIRECTORY);
        Artisan::call('db:seed', ['--class' => PresentationDemoSeeder::class, '--force' => true]);

        $this->newLine();
        $this->info('Presentation story restored. Only tagged VM-SHOW records were replaced.');
        $this->table(
            ['Code', 'Date', 'Starting state', 'Role', 'Walkthrough action'],
            collect(PresentationDemoSeeder::MANIFEST)->map(function (array $item, string $code): array {
                $reservation = DB::table('reservations')->where('reservation_code', $code)->first();

                return [
                    $code,
                    $reservation ? substr((string) $reservation->check_in, 0, 16) : 'missing',
                    $reservation->status ?? 'missing',
                    $item['role'],
                    $item['action'],
                ];
            })->values()->all(),
        );
        $this->newLine();
        $this->line('Guest notifications: '.$guestEmail);
        $this->warn('Start php artisan queue:work before performing live approval or payment actions.');

        return self::SUCCESS;
    }
}
