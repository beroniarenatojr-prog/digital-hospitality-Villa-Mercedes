<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FileUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxSize = (int) config('upload.max_size', 5120);
        $allowedMimes = implode(',', config('upload.allowed_mimes', []));

        return [
            'file' => ['required', 'file', "max:{$maxSize}", "mimes:{$allowedMimes}"],
        ];
    }
}
