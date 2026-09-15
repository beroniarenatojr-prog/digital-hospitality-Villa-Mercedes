<?php

namespace App\Console\Commands;

use App\Models\GalleryImage;
use App\Models\PagePhoto;
use App\Services\PublicImageOptimizer;
use App\Support\PublicHomeCache;
use Illuminate\Console\Command;

class OptimizePublicImages extends Command
{
    protected $signature = 'public-images:optimize {--force : Re-encode images already stored as WebP}';

    protected $description = 'Create display-sized WebP copies for public facility and gallery photos';

    public function handle(PublicImageOptimizer $imageOptimizer): int
    {
        $updated = 0;
        $skipped = 0;

        foreach ([GalleryImage::class, PagePhoto::class] as $modelClass) {
            $modelClass::query()->orderBy('id')->each(function ($image) use ($imageOptimizer, &$updated, &$skipped): void {
                if (! $this->option('force') && str_ends_with(strtolower($image->image_path), '.webp')) {
                    $skipped++;

                    return;
                }

                $optimizedPath = $imageOptimizer->optimizeStored($image->image_path);

                if ($optimizedPath === null) {
                    $this->warn("Skipped missing or unsupported image: {$image->image_path}");
                    $skipped++;

                    return;
                }

                if ($optimizedPath !== $image->image_path) {
                    $image->forceFill(['image_path' => $optimizedPath])->saveQuietly();
                    $updated++;
                }
            });
        }

        if ($updated > 0) {
            PublicHomeCache::forget();
        }

        $this->info("Optimized {$updated} public image(s); skipped {$skipped}.");
        $this->line('Original files were retained so the conversion is reversible.');

        return self::SUCCESS;
    }
}
