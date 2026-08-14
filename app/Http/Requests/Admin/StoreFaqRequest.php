<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreFaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'faq_category_id' => ['required', 'integer', 'exists:faq_categories,id'],
            'question' => ['required', 'string', 'max:1000'],
            'answer' => ['required', 'string', 'max:5000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
