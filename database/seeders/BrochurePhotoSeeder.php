<?php

namespace Database\Seeders;

use App\Enums\FacilityType as FacilityTypeEnum;
use App\Enums\PagePhotoCollection;
use App\Models\Facility;
use App\Models\GalleryImage;
use App\Models\PagePhoto;
use App\Models\ResortPool;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrochurePhotoSeeder extends Seeder
{
    public function run(): void
    {
        $sourceRoot = database_path('seeders/assets/brochure');

        if (! File::isDirectory($sourceRoot)) {
            return;
        }

        $this->attachRoomPhotos($sourceRoot);
        $this->attachCottagePhotos($sourceRoot);
        $this->attachPoolPhotos($sourceRoot);

        $this->attachFacilityPhotos([
            'Diamond Hall' => [
                ['file' => 'halls/diamond-hall.webp', 'caption' => 'Diamond Hall'],
            ],
            'Emerald Hall' => [
                ['file' => 'halls/emerald-hall.webp', 'caption' => 'Emerald Hall'],
            ],
            'Sapphire Hall' => [
                ['file' => 'halls/sapphire-hall.webp', 'caption' => 'Sapphire Hall'],
            ],
            'Opal Hall' => [
                ['file' => 'halls/opal-hall.webp', 'caption' => 'Opal Hall'],
            ],
            'Private Pool + Function Hall (Day Use)' => [
                ['file' => 'pools/private-pool.jpg', 'caption' => 'Private pool with function hall'],
                ['file' => 'gallery/pool-by-day.jpg', 'caption' => 'Day-use pool grounds'],
            ],
            'Private Pool + Function Hall (Night Use)' => [
                ['file' => 'pools/private-pool.jpg', 'caption' => 'Private pool with function hall'],
                ['file' => 'gallery/pool-by-night.jpg', 'caption' => 'Evening pool access'],
            ],
            'Private Pool + Function Hall (Overnight Stay)' => [
                ['file' => 'pools/private-pool.jpg', 'caption' => 'Private pool with function hall'],
                ['file' => 'rooms/dorm-a.webp', 'caption' => 'Air-conditioned dorm rooms'],
            ],
            'Sapphire Hall + Pool + Jacuzzi (Day Use)' => [
                ['file' => 'halls/sapphire-hall.webp', 'caption' => 'Sapphire Hall'],
                ['file' => 'gallery/pool-by-day.jpg', 'caption' => 'Sapphire Hall and pool'],
            ],
            'Sapphire Hall + Pool + Jacuzzi (Night Use)' => [
                ['file' => 'halls/sapphire-hall.webp', 'caption' => 'Sapphire Hall'],
                ['file' => 'gallery/pool-by-night.jpg', 'caption' => 'Evening pool access'],
            ],
            'Sapphire Hall + Pool + Jacuzzi (Overnight Stay)' => [
                ['file' => 'halls/sapphire-hall.webp', 'caption' => 'Sapphire Hall'],
                ['file' => 'rooms/room-11-classic-family-plus.webp', 'caption' => 'Two air-conditioned rooms'],
            ],
        ], $sourceRoot);

        $this->attachGalleryPhotos([
            ['file' => 'gallery/resort-team.jpg', 'title' => 'Resort team', 'caption' => 'The people behind Villa Mercedes hospitality.'],
            ['file' => 'gallery/guest-wings.jpg', 'title' => 'Guest wings', 'caption' => 'Warm lights over our wooden architecture.'],
            ['file' => 'gallery/cafe-restobar.jpg', 'title' => 'Café restobar', 'caption' => 'Wicker lights and a coffee bar for guests.'],
            ['file' => 'gallery/cafe-lounge.jpg', 'title' => 'Café lounge', 'caption' => 'Open seating looking out to the grounds.'],
            ['file' => 'gallery/pool-by-day.jpg', 'title' => 'Pool by day', 'caption' => 'Nature-framed swimming for all ages.'],
            ['file' => 'gallery/pool-by-night.jpg', 'title' => 'Pool at night', 'caption' => 'Evening swimming under resort lighting.'],
        ], $sourceRoot);

        $this->removeRetiredBrochurePhotos();
    }

    private function attachRoomPhotos(string $sourceRoot): void
    {
        $rooms = Facility::query()
            ->whereHas('facilityType', fn ($query) => $query->where('slug', FacilityTypeEnum::Room->value))
            ->get();

        foreach ($rooms as $room) {
            $this->attachPhotosToFacility(
                $room,
                $this->photosForRoom($room->name),
                $sourceRoot,
                'facilities/rooms/'.$room->slug,
                makePrimary: true,
            );
        }
    }

    private function attachCottagePhotos(string $sourceRoot): void
    {
        $captions = [
            1 => 'Villa Mercedes cottage grounds',
            2 => 'Queen cottage',
            3 => 'Poolside wooden cottage',
            4 => 'Open-air cottage pavilion',
            5 => 'Covered cottage seating',
            6 => 'Jhonmers cottage',
            7 => 'John Christopher cottage',
            8 => 'Czyree cottage',
            9 => 'John Paul cottage',
            10 => 'Jerome cottage',
            11 => 'Mercy cottage',
            12 => 'John cottage',
        ];

        for ($number = 1; $number <= 12; $number++) {
            $name = sprintf('Cottage %02d', $number);
            $cottage = Facility::query()->where('name', $name)->first();

            if ($cottage === null) {
                continue;
            }

            $this->attachPhotosToFacility(
                $cottage,
                [[
                    'file' => 'cottages/cottage-'.sprintf('%02d', $number).'.jpg',
                    'caption' => $captions[$number] ?? $name,
                ]],
                $sourceRoot,
                'facilities/cottages/'.$cottage->slug,
                makePrimary: true,
            );
        }
    }

    private function attachPoolPhotos(string $sourceRoot): void
    {
        $photos = [
            'adult-pool' => 'pools/adult-pool.png',
            'kiddie-pool' => 'pools/kiddie-pool.jpg',
            'private-pool' => 'pools/private-pool.jpg',
        ];

        foreach ($photos as $slug => $relativePath) {
            $stored = $this->storePublicCopy($sourceRoot, $relativePath, 'resort-pools');

            if ($stored === null) {
                continue;
            }

            ResortPool::query()
                ->where('slug', $slug)
                ->update(['image_path' => $stored]);
        }
    }

    /**
     * @return list<array{file: string, caption: string}>
     */
    private function photosForRoom(string $name): array
    {
        $drivePhoto = $this->drivePhotoForRoom($name);

        if ($drivePhoto === null) {
            return [];
        }

        return [
            ['file' => 'rooms/'.$drivePhoto, 'caption' => $name],
        ];
    }

    private function drivePhotoForRoom(string $name): ?string
    {
        return match ($name) {
            'Superior (Room 01)' => 'room-01-superior.webp',
            'Superior (Room 1)',
            'Superior (Room 3)',
            'Superior (Room 5)' => 'rooms-1-5-superior.jpg',
            'Superior (Room 2)',
            'Superior (Room 4)' => 'rooms-1-5-superior-2.jpg',
            'Superior Family (Room 02)' => 'room-02-superior-family.webp',
            'Superior Barkada (Room 10)' => 'room-10-superior-family.webp',
            'Standard Barkada (Room 11)' => 'room-11-classic-family-plus.webp',
            'Standard Barkada (Room 12)' => 'room-12-classic-family-plus.webp',
            'Deluxe Barkada (Room 13)',
            'Deluxe Barkada (Room 14)',
            'Deluxe Barkada (Room 15)' => 'rooms-13-15-superior-family.webp',
            'Superior Barkada (Room 16)' => 'room-16-family-suite.webp',
            'Superior Family (Room 17)' => 'room-17-family-suite.webp',
            'Standard Family (Room 21)',
            'Standard Family (Room 22)',
            'Standard Family (Room 23)',
            'Standard Family (Room 24)',
            'Standard Family (Room 25)' => 'rooms-21-25-standard-family.webp',
            'Deluxe (Room 26)',
            'Deluxe (Room 27)',
            'Deluxe (Room 28)',
            'Deluxe (Room 29)' => 'rooms-26-29-deluxe.webp',
            'Dorm (A)' => 'dorm-a.webp',
            'Dorm (B)' => 'dorm-b.webp',
            default => null,
        };
    }

    /**
     * @param  array<string, list<array{file: string, caption: string}>>  $map
     */
    private function attachFacilityPhotos(array $map, string $sourceRoot): void
    {
        foreach ($map as $facilityName => $photos) {
            $facility = Facility::query()->where('slug', Str::slug($facilityName))->first();

            if (! $facility) {
                continue;
            }

            $this->attachPhotosToFacility($facility, $photos, $sourceRoot);
        }
    }

    /**
     * @param  list<array{file: string, caption: string}>  $photos
     */
    private function attachPhotosToFacility(
        Facility $facility,
        array $photos,
        string $sourceRoot,
        string $directory = 'facilities',
        bool $makePrimary = false,
    ): void {
        $sort = $makePrimary ? 0 : (int) ($facility->galleryImages()->max('sort_order') ?? 0);
        $primaryPaths = [];

        foreach ($photos as $photo) {
            $stored = $this->storePublicCopy($sourceRoot, $photo['file'], $directory);

            if ($stored === null) {
                continue;
            }

            if ($makePrimary) {
                GalleryImage::query()->updateOrCreate(
                    [
                        'facility_id' => $facility->id,
                        'image_path' => $stored,
                    ],
                    [
                        'caption' => $photo['caption'],
                        'sort_order' => $sort++,
                        'is_active' => true,
                    ]
                );
                $primaryPaths[] = $stored;

                continue;
            }

            GalleryImage::query()->firstOrCreate(
                [
                    'facility_id' => $facility->id,
                    'image_path' => $stored,
                ],
                [
                    'caption' => $photo['caption'],
                    'sort_order' => ++$sort,
                    'is_active' => true,
                ]
            );
        }

        if ($makePrimary && $primaryPaths !== []) {
            $facility->galleryImages()
                ->whereNotIn('image_path', $primaryPaths)
                ->get()
                ->each(function (GalleryImage $image) use (&$sort): void {
                    $image->update(['sort_order' => $sort++]);
                });
        }
    }

    /**
     * @param  list<array{file: string, title: string, caption: string}>  $photos
     */
    private function attachGalleryPhotos(array $photos, string $sourceRoot): void
    {
        $sort = 0;

        foreach ($photos as $photo) {
            $stored = $this->storePublicCopy($sourceRoot, $photo['file'], 'pages/gallery');

            if ($stored === null) {
                continue;
            }

            PagePhoto::query()->updateOrCreate(
                [
                    'collection' => PagePhotoCollection::Gallery,
                    'image_path' => $stored,
                ],
                [
                    'title' => $photo['title'],
                    'caption' => $photo['caption'],
                    'sort_order' => ++$sort,
                    'is_active' => true,
                ]
            );
        }
    }

    private function removeRetiredBrochurePhotos(): void
    {
        PagePhoto::query()
            ->where('collection', PagePhotoCollection::Gallery)
            ->get()
            ->filter(fn (PagePhoto $photo) => $this->isRetiredBrochurePath($photo->image_path))
            ->each(function (PagePhoto $photo): void {
                Storage::disk('public')->delete($photo->image_path);
                $photo->delete();
            });

        GalleryImage::query()
            ->get()
            ->filter(fn (GalleryImage $image) => $this->isRetiredBrochurePath($image->image_path))
            ->each(function (GalleryImage $image): void {
                Storage::disk('public')->delete($image->image_path);
                $image->delete();
            });
    }

    private function isRetiredBrochurePath(string $path): bool
    {
        return in_array(basename($path), [
            'deluxe.jpg',
            'dormitory.jpg',
            'dormitory-bunks.jpg',
            'family-room.jpg',
            'family-three-beds.jpg',
            'superior-twin.jpg',
            'twin-deluxe.jpg',
            'pool-deck.jpg',
            'pool-slide.jpg',
            'villa-sign.jpg',
            'grounds-cart.jpg',
            'entrance-egg-chairs.jpg',
            'private-pool.jpg',
            'villa-exterior.jpg',
            'sapphire-pool-hall.jpg',
            'sapphire-hall-dining.jpg',
            'diamond-hall.png',
            'diamond-hall.jpg',
            'emerald-hall.png',
            'opal-hall.png',
            'opal-hall.jpg',
            'sapphire-hall.png',
            'sapphire-hall.jpg',
        ], true);
    }

    private function storePublicCopy(string $sourceRoot, string $relativePath, string $directory): ?string
    {
        $source = $sourceRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (! File::isFile($source)) {
            return null;
        }

        $filename = $directory.'/'.pathinfo($relativePath, PATHINFO_BASENAME);

        if (! Storage::disk('public')->exists($filename)) {
            Storage::disk('public')->put($filename, File::get($source));
        }

        return $filename;
    }
}
