<?php

namespace App\Http\Requests;

use App\Enums\GuestGender;
use App\Enums\GuestIdType;
use App\Models\User;
use App\Rules\PhilippineMobileNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32', new PhilippineMobileNumber],
            'gender' => ['nullable', Rule::enum(GuestGender::class)],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after_or_equal:1900-01-01'],
            'address' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'valid_id_type' => ['nullable', Rule::enum(GuestIdType::class)],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
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
            'company' => 'company name/agency',
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['company', 'address', 'nationality', 'gender', 'valid_id_type', 'date_of_birth'] as $field) {
            if ($this->has($field) && ! filled($this->input($field))) {
                $this->merge([$field => null]);
            }
        }

        if ($this->filled('address') && is_string($this->input('address'))) {
            $this->merge(['address' => trim($this->input('address'))]);
        }

        if ($this->filled('nationality') && is_string($this->input('nationality'))) {
            $this->merge(['nationality' => trim($this->input('nationality'))]);
        }
    }
}
