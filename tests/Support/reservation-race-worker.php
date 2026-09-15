<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (! app()->environment('testing') || ! str_contains((string) config('database.connections.mysql.database'), 'test')) {
    throw new RuntimeException('Race workers may only run against a test database.');
}

Illuminate\Support\Facades\Notification::fake();
Illuminate\Support\Facades\Http::preventStrayRequests();
[$script, $operation, $targetId, $actorId, $gate, $ready] = $argv;
$actor = App\Models\User::findOrFail($actorId);
$target = $operation === 'booking'
    ? App\Models\Facility::findOrFail($targetId)
    : App\Models\Reservation::with('payments')->findOrFail($targetId);
file_put_contents($ready, 'ready');
$deadline = microtime(true) + 20;
while (! is_file($gate)) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Timed out waiting for race start.');
    }
    usleep(10000);
    clearstatcache(true, $gate);
}

try {
    if ($operation === 'booking') {
        App::make(App\Services\ReservationService::class)->createReservation(
            $target, $actor, now()->addDays(60)->setTime(14, 0), now()->addDays(62)->setTime(12, 0),
            App\Enums\ReservationSource::Online, 2
        );
    } elseif ($operation === 'deposit') {
        App::make(App\Services\PaymentService::class)->createPayment($target, $actor, 1500, App\Enums\PaymentMethod::GCash,
            'test-proof.png', ['reference_number' => 'RACE-'.getmypid(), 'payment_destination_id' => App\Models\PaymentDestination::firstOrFail()->id]);
    } else {
        App::make(App\Services\PaymentService::class)->createPayment($target, $actor, 3000, App\Enums\PaymentMethod::GCash);
    }
    echo 'accepted';
} catch (App\Exceptions\Reservation\ReservationConflictException|App\Exceptions\Payment\InvalidPaymentAmountException|App\Exceptions\Payment\PaymentNotEligibleException) {
    echo 'rejected';
}
