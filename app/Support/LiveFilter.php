<?php

namespace App\Support;

use Illuminate\Http\Request;

final class LiveFilter
{
    public const HEADER = 'X-Live-Filter';

    public static function wantsPartial(Request $request): bool
    {
        return $request->headers->get(self::HEADER) === '1';
    }
}
