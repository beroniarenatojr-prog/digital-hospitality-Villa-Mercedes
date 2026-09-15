<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * Stream a stored proof only when the path stays inside the uploads disk.
     */
    public function download(string $path, string $disk = 'uploads'): StreamedResponse
    {
        return $this->stream($path, $disk, asDownload: true);
    }

    /**
     * Open a stored proof in the browser instead of forcing a download.
     */
    public function view(string $path, string $disk = 'uploads'): StreamedResponse
    {
        return $this->stream($path, $disk, asDownload: false);
    }

    private function stream(string $path, string $disk, bool $asDownload): StreamedResponse
    {
        if (! $this->isSafeStoredPath($path) || ! Storage::disk($disk)->exists($path)) {
            abort(404, 'Proof of payment not found.');
        }

        $filename = $this->safeDownloadName($path);

        if ($asDownload) {
            return Storage::disk($disk)->download($path, $filename);
        }

        return Storage::disk($disk)->response($path, $filename, [
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function safeDownloadName(string $path): string
    {
        $filename = basename(str_replace('\\', '/', $path));
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: 'proof';

        return $filename;
    }

    public function isSafeStoredPath(?string $path): bool
    {
        if (! is_string($path) || $path === '') {
            return false;
        }

        $normalized = str_replace('\\', '/', rawurldecode($path));

        if (str_contains($normalized, '..') || str_starts_with($normalized, '/') || str_contains($normalized, ':')) {
            return false;
        }

        return true;
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
