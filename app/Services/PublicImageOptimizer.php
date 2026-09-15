<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores public marketing images at display-friendly dimensions.
 *
 * The original upload is not persisted: new facility and page photos are
 * encoded as WebP before storage, which keeps card and gallery downloads much
 * smaller without requiring a client-side image library.
 */
class PublicImageOptimizer
{
    private const MAX_DIMENSION = 1600;

    private const WEBP_QUALITY = 82;

    public function store(UploadedFile $file, string $directory): string
    {
        $sourcePath = $file->getRealPath();

        if (! is_string($sourcePath)) {
            return $file->store($directory, 'public');
        }

        $optimized = $this->encodeWebp($sourcePath, trim($directory, '/').'/'.Str::uuid().'.webp');

        return $optimized ?? $file->store($directory, 'public');
    }

    /**
     * Converts an existing public image without deleting its original file.
     * The returned path may safely replace a model's image_path.
     */
    public function optimizeStored(string $path): ?string
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $extensionlessPath = pathinfo(str_replace('\\', '/', $path), PATHINFO_DIRNAME);
        $filename = pathinfo($path, PATHINFO_FILENAME).'.webp';
        $targetPath = ($extensionlessPath === '.' ? '' : trim($extensionlessPath, '/').'/').$filename;

        return $this->encodeWebp($disk->path($path), $targetPath);
    }

    private function encodeWebp(string $sourcePath, string $targetPath): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return null;
        }

        $dimensions = @getimagesize($sourcePath);
        $contents = @file_get_contents($sourcePath);

        if ($dimensions === false || $contents === false) {
            return null;
        }

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            return null;
        }

        [$width, $height] = $dimensions;
        $scale = min(1, self::MAX_DIMENSION / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        $encoded = imagewebp($canvas, null, self::WEBP_QUALITY);
        $webp = ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        if (! $encoded || ! is_string($webp) || $webp === '') {
            return null;
        }

        if (! Storage::disk('public')->put($targetPath, $webp, ['visibility' => 'public'])) {
            return null;
        }

        return $targetPath;
    }
}
