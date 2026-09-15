<?php

namespace App\Http\Requests\Admin;

use App\Enums\PagePhotoCollection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePagePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'collection' => ['required', Rule::enum(PagePhotoCollection::class)],
            'images' => ['required', 'array', 'min:1', 'max:12'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'title' => ['nullable', 'string', 'max:120'],
            'caption' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'images' => 'photos',
            'images.*' => 'photo',
        ];
    }
}
