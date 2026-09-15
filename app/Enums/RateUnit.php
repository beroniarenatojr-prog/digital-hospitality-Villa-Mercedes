<?php

namespace App\Enums;

enum RateUnit: string
{
    case PerNight = 'per night';
    case PerEvent = 'per event';
    case PerHour = 'per hour';
    case PerPerson = 'per person';
    case PerDay = 'per day';
    case PerSession = 'per session';
    case PerPackage = 'per package';

    public static function normalize(string $unit): string
    {
        $normalized = strtolower(trim(str_replace('_', ' ', $unit)));

        return in_array($normalized, ['night', 'event', 'hour', 'person', 'day', 'session', 'package'], true)
            ? 'per '.$normalized
            : $normalized;
    }
}
