<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FileUploadService
{
    /**
     * @return array{path: string, disk: string, original_name: string, mime_type: string, size: int}
     */
    public function store(UploadedFile $file, string $directory = 'uploads'): array
    {
        $this->validateFile($file);

        $filename = $file->hashName();
        $path = $file->storeAs($directory, $filename, 'uploads');

        if ($path === false) {
            throw new InvalidArgumentException('Failed to store uploaded file.');
        }

        return [
            'path' => $path,
            'disk' => 'uploads',
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
        ];
    }

    public function delete(string $path, string $disk = 'uploads'): bool
    {
        return Storage::disk($disk)->delete($path);
    }

    private function validateFile(UploadedFile $file): void
    {
        $maxSize = (int) config('upload.max_size', 5120) * 1024;
        $allowedMimes = config('upload.allowed_mimes', []);

        if ($file->getSize() > $maxSize) {
            throw new InvalidArgumentException('File exceeds maximum allowed size.');
        }

        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, $allowedMimes, true)) {
            throw new InvalidArgumentException('File type is not allowed.');
        }
    }
}
