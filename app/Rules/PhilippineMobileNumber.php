<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhilippineMobileNumber implements ValidationRule
{
    public static function normalize(mixed $value): mixed
    {
        if (! is_string($value) || ! preg_match('/\A[\d\s-]+\z/u', $value)) {
            return $value;
        }

        return preg_replace('/[\s-]+/u', '', $value) ?? $value;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail($this->message());

            return;
        }

        if (! preg_match('/\A09\d{9}\z/', $value)) {
            $fail($this->message());
        }
    }

    private function message(): string
    {
        return 'The :attribute must be exactly 11 digits and start with 09, for example 09171234567.';
    }
}
