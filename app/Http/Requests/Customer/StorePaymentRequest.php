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
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['required', Rule::in(PaymentMethod::values())],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'proof' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                "max:{$maxSize}"
            ],
        ];
    }
}
