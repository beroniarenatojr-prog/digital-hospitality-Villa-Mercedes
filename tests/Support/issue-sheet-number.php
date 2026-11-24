<?php

// A separate connection is necessary to exercise the database allocation lock.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || ! str_ends_with(config('database.connections.mysql.database'), '_test')) {
    throw new RuntimeException('Concurrent numbering checks require the test database.');
}
$reservation = App\Models\Reservation::findOrFail((int) $argv[1]);
echo app(App\Services\GuestInformationSheetNumber::class)->issue($reservation, function ($issued) {
    // Hold the lock while the other request attempts issuance, as rendering does.
    usleep(200000);

    return (string) $issued->guest_sheet_control_number;
});
