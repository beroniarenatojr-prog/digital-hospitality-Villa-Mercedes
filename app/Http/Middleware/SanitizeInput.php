<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInput
{
    /**
     * Credentials and framework tokens must remain byte-for-byte identical.
     *
     * @var list<string>
     */
    private const EXCEPT = [
        '_token',
        'current_password',
        'password',
        'password_confirmation',
        'token',
        'otp',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $request->query->replace($this->sanitizeArray($request->query->all()));
        $request->request->replace($this->sanitizeArray($request->request->all()));

        return $next($request);
    }

    /**
     * @param  array<string|int, mixed>  $values
     * @return array<string|int, mixed>
     */
    private function sanitizeArray(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && in_array($key, self::EXCEPT, true)) {
                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->sanitizeArray($value);
                continue;
            }

            if (is_string($value)) {
                $values[$key] = $this->sanitizeString($value);
            }
        }

        return $values;
    }

    private function sanitizeString(string $value): string
    {
        // Canonical line endings keep length validation and stored text stable.
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        // Forms in this application accept plain text, not user-authored HTML.
        $value = strip_tags($value);

        // Remove NUL and non-printing controls while keeping tabs/newlines used
        // by textareas. These bytes have no legitimate form purpose and can
        // produce inconsistent behaviour in logs, exports, and downstream APIs.
        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

        return is_string($sanitized) ? $sanitized : '';
    }
}
