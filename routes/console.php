<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('otp:prune')->daily();
Schedule::command('reservations:expire-unpaid')->everyMinute()->withoutOverlapping();
Schedule::command('reservations:auto-check-out')->everyMinute()->withoutOverlapping();
Schedule::command('reservations:send-checkout-reminders')->everyMinute()->withoutOverlapping();
