<?php

namespace App\Http\Requests\Auth;

use App\Enums\GuestGender;
use App\Enums\GuestIdType;
use App\Models\User;
use App\Rules\PhilippineMobileNumber;
use App\Rules\PersonName;
use App\Services\RegistrationOtpService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class RegisterRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255', new PersonName],
            'phone' => ['required', 'string', 'size:11', new PhilippineMobileNumber],
            'gender' => ['required', Rule::enum(GuestGender::class)],
            'date_of_birth' => ['required', 'date', 'before:today', 'after_or_equal:1900-01-01'],
            'address' => ['required', 'string', 'max:255'],
            'nationality' => ['required', 'string', 'max:100', new PersonName],
            'valid_id_type' => ['required', Rule::enum(GuestIdType::class)],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:'.User::class,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || ! app(RegistrationOtpService::class)->hasVerifiedEmail($value)) {
                        $fail('Please verify this email address first.');
                    }
                },
            ],
            'password' => ['required', 'string', 'max:255', 'confirmed', Rules\Password::defaults()],
            'terms_accepted' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your full name.',
            'phone.required' => 'Please enter your mobile number.',
            'gender.required' => 'Please select your sex.',
            'date_of_birth.required' => 'Please enter your date of birth.',
            'date_of_birth.before' => 'Date of birth must be before today.',
            'address.required' => 'Please enter your address.',
            'nationality.required' => 'Please enter your nationality.',
            'valid_id_type.required' => 'Please select a valid ID type.',
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => RegistrationOtpService::EMAIL_TAKEN,
            'password.required' => 'Please enter a password.',
            'password.confirmed' => 'Password confirmation does not match.',
            'terms_accepted.accepted' => 'Please accept the Terms of Use and Privacy Notice to continue.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'valid_id_type' => 'valid ID type',
            'date_of_birth' => 'date of birth',
            'terms_accepted' => 'terms and privacy notice',
            'company' => 'company name/agency',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => PhilippineMobileNumber::normalize($this->input('phone'))]);
        }

        if ($this->has('company') && ! filled($this->input('company'))) {
            $this->merge(['company' => null]);
        }

        if ($this->has('nationality') && is_string($this->input('nationality'))) {
            $this->merge(['nationality' => trim($this->input('nationality'))]);
        }

        if ($this->has('address') && is_string($this->input('address'))) {
            $this->merge(['address' => trim($this->input('address'))]);
        }
    }
}
