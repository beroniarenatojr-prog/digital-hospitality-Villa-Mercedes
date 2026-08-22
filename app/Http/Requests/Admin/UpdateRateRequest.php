<?php

namespace App\Http\Requests\Admin;

use App\Enums\RateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorized by middleware
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(RateType::values())],
            'amount' => ['required', 'numeric', 'gt:0'],
            'unit' => ['required', 'string', 'max:50'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.gt' => 'The rate amount must be greater than zero.',
            'effective_until.after_or_equal' => 'The effective until date must be on or after the effective from date.',
        ];
    }
}
