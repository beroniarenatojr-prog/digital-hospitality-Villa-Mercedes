<?php

namespace App\Http\Requests;

use App\Enums\BookingServiceType;
use App\Support\StayQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchAvailabilityRequest extends FormRequest
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
            'service_type' => ['required', Rule::enum(BookingServiceType::class)],
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after_or_equal:check_in'],
            'guests' => ['required', 'integer', 'min:1', 'max:500'],
            'session' => ['nullable', 'string', 'max:50', Rule::exists('swimming_sessions', 'slug')->where('is_active', true)],
        ];
    }

    public function serviceType(): BookingServiceType
    {
        return $this->enum('service_type', BookingServiceType::class);
    }

    protected function prepareForValidation(): void
    {
        StayQuery::normalize($this);
    }
}
