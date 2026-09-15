<?php

namespace App\Http\Requests\Customer;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = (int) config('upload.max_size', 5120);

        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'payment_method' => ['required', Rule::in(PaymentMethod::values())],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'payment_destination_id' => [config('payments.deposit_workflow') ? 'required' : 'nullable', 'integer', 'exists:payment_destinations,id'],
            'payment_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.now(\App\Support\DepositDeadline::TIMEZONE)->toDateString()],
            'proof' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                "max:{$maxSize}"
            ],
        ];
    }
}
