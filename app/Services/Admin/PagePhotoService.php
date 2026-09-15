<?php

namespace App\Services\Admin;

use App\Enums\PagePhotoCollection;
use App\Enums\Role;
use App\Exceptions\Admin\PhotoLimitExceededException;
use App\Exceptions\Admin\UnauthorizedAdminActionException;
use App\Models\PagePhoto;
use App\Models\User;
use App\Services\PublicImageOptimizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PagePhotoService
{
    public function __construct(
        private readonly PublicImageOptimizer $imageOptimizer
    ) {}

    private function authorizeAdmin(User $actor): void
    {
        if (! $actor->hasRole(Role::Administrator->value)) {
            throw new UnauthorizedAdminActionException('Only administrators can manage page photos.');
        }
    }

    /**
     * @param  list<UploadedFile>  $images
     * @return list<PagePhoto>
     */
    public function upload(User $actor, PagePhotoCollection $collection, array $images, ?string $title = null, ?string $caption = null): array
    {
        $this->authorizeAdmin($actor);

        $existing = PagePhoto::query()->where('collection', $collection)->count();
        $incoming = count($images);

        if ($existing + $incoming > $collection->maxCount()) {
            throw new PhotoLimitExceededException(
                "The {$collection->label()} page can hold at most {$collection->maxCount()} photos."
            );
        }

        return DB::transaction(function () use ($actor, $collection, $images, $title, $caption) {
            $sortOrder = (int) (PagePhoto::query()->where('collection', $collection)->max('sort_order') ?? 0);
            $created = [];
            $single = count($images) === 1;

            foreach ($images as $image) {
                $path = $this->imageOptimizer->store($image, 'pages/'.$collection->value);

                $photo = PagePhoto::query()->create([
                    'collection' => $collection,
                    'image_path' => $path,
                    'title' => $single ? $title : null,
                    'caption' => $single ? $caption : null,
                    'sort_order' => ++$sortOrder,
                    'is_active' => true,
                ]);

                $created[] = $photo;
            }

            activity()
                ->causedBy($actor)
                ->event('page_photos_uploaded')
                ->log(count($created)." photo(s) uploaded to the {$collection->label()} page.");

            return $created;
        });
    }

    /**
     * @param  array{title?: string|null, caption?: string|null, sort_order?: int|null, is_active?: bool}  $data
     */
    public function update(User $actor, PagePhoto $photo, array $data): PagePhoto
    {
        $this->authorizeAdmin($actor);

        if (array_key_exists('title', $data)) {
            $photo->title = $data['title'];
        }
        if (array_key_exists('caption', $data)) {
            $photo->caption = $data['caption'];
        }
        if (array_key_exists('sort_order', $data) && $data['sort_order'] !== null) {
            $photo->sort_order = $data['sort_order'];
        }
        if (array_key_exists('is_active', $data)) {
            $photo->is_active = (bool) $data['is_active'];
        }

        $photo->save();

        activity()
            ->causedBy($actor)
            ->performedOn($photo)
            ->event('page_photo_updated')
            ->log("A {$photo->collection->label()} photo was updated.");

        return $photo;
    }

    public function delete(User $actor, PagePhoto $photo): void
    {
        $this->authorizeAdmin($actor);

        if (Storage::disk('public')->exists($photo->image_path)) {
            Storage::disk('public')->delete($photo->image_path);
        }

        $collection = $photo->collection;
        $photo->delete();

        activity()
            ->causedBy($actor)
            ->event('page_photo_deleted')
            ->log("A photo was removed from the {$collection->label()} page.");
    }
}
