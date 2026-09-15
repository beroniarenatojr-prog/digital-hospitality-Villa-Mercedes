<?php

namespace App\Http\Requests\Admin;

use App\Enums\ActivityActionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivityLogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'staff_id' => ['nullable', 'integer', 'exists:users,id'],
            'action_type' => ['nullable', Rule::enum(ActivityActionType::class)],
            'date_from' => ['nullable', 'date'],
            'date_until' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
