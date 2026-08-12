<?php

namespace App\Helpers;

class Sanitizer
{
    public static function string(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }
}
