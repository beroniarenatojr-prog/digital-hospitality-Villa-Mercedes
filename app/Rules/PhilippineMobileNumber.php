<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhilippineMobileNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail($this->message());

            return;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (! preg_match('/^(0\d{10}|63\d{10})$/', $digits)) {
            $fail($this->message());
        }
    }

    private function message(): string
    {
        return 'Please enter a valid mobile number, such as 0917 123 4567.';
    }
}
