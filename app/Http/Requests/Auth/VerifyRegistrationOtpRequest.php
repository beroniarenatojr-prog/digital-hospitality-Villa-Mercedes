<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyRegistrationOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $digits = max(4, (int) config('otp.digits'));

        return [
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'otp' => ['required', 'string', 'size:'.$digits],
        ];
    }

    protected function prepareForValidation(): void
    {
        $otp = preg_replace('/\D+/', '', (string) $this->input('otp', ''));

        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'otp' => is_string($otp) ? $otp : '',
        ]);
    }
}
