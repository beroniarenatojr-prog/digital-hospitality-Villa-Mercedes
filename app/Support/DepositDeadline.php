<?php

namespace App\Support;

use Carbon\Carbon;

final class DepositDeadline
{
    public const TIMEZONE = 'Asia/Manila';

    // Existing booking dates are resort wall-clock values. Payment deadlines
    // are instants stored in the application's timezone (UTC by default).
    public static function visitInstant(Carbon $occupancyStart): Carbon
    {
        return $occupancyStart->copy()->shiftTimezone(self::TIMEZONE)->setTimezone(config('app.timezone'));
    }

    public static function forVisit(Carbon $occupancyStart, ?Carbon $originalDeadline = null): Carbon
    {
        return ($originalDeadline?->copy() ?? now()->addHours(24))->min(self::visitInstant($occupancyStart));
    }
}
