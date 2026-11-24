<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PersonName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match("/\\A(?=.*\\p{L})[\\p{L}\\p{M} .'’\-]+\\z/u", $value)) {
            $fail('The :attribute may only contain letters, spaces, apostrophes, periods, and hyphens.');
        }
    }
}
