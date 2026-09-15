<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupportChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:600'],
            'context' => ['nullable', 'string', Rule::in([
                'rooms',
                'events',
                'cottages',
                'pools',
                'booking',
                'policies',
                'staff',
                'account',
            ])],
        ];
    }
}
