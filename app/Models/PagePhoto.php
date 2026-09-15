<?php

namespace App\Models;

use App\Enums\PagePhotoCollection;
use Database\Factories\PagePhotoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class PagePhoto extends Model
{
    /** @use HasFactory<PagePhotoFactory> */
    use HasFactory;

    protected $fillable = [
        'collection',
        'image_path',
        'title',
        'caption',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'collection' => PagePhotoCollection::class,
            'is_active' => 'boolean',
        ];
    }

    public function url(): string
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($this->image_path);
    }

    public function displayTitle(): string
    {
        return $this->title ?: $this->collection->label();
    }

    /**
     * @param  Builder<PagePhoto>  $query
     * @return Builder<PagePhoto>
     */
    public function scopeForCollection(Builder $query, PagePhotoCollection $collection): Builder
    {
        return $query->where('collection', $collection)->where('is_active', true);
    }

    /**
     * @param  Builder<PagePhoto>  $query
     * @return Builder<PagePhoto>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
