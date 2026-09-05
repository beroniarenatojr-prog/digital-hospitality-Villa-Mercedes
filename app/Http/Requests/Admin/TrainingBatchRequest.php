<?php

namespace App\Http\Requests\Admin;

use App\Enums\TrainingBatchStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrainingBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $batch = $this->route('training_batch');

        return [
            'program' => ['required', 'string', 'max:255'],
            'cohort_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('training_batches', 'cohort_code')->ignore($batch?->id)->whereNull('deleted_at'),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'daily_start_time' => ['nullable', 'date_format:H:i', 'required_with:daily_end_time'],
            'daily_end_time' => ['nullable', 'date_format:H:i', 'required_with:daily_start_time', 'after:daily_start_time'],
            'trainee_capacity' => ['required', 'integer', 'min:0', 'max:500'],
            'status' => ['required', Rule::in(TrainingBatchStatus::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
            'facility_ids' => ['required', 'array', 'min:1'],
            'facility_ids.*' => ['integer', 'exists:facilities,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'facility_ids' => 'resources used',
            'daily_start_time' => 'session start time',
            'daily_end_time' => 'session end time',
        ];
    }
}
