<?php

namespace Tests\Feature;

use App\Enums\FacilityType as FacilityTypeEnum;
use App\Enums\PagePhotoCollection;
use App\Models\Facility;
use App\Models\GalleryImage;
use App\Models\PagePhoto;
use App\Models\ResortPool;
use Database\Seeders\BrochurePhotoSeeder;
use Database\Seeders\CottageSeeder;
use Database\Seeders\FacilitySeeder;
use Database\Seeders\FacilityTypeSeeder;
use Database\Seeders\ResortServicesSeeder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrochurePhotoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_and_hall_photos_are_attached_to_the_matching_facilities(): void
    {
        Storage::fake('public');

        $this->seed(FacilityTypeSeeder::class);
        $this->seed(FacilitySeeder::class);
        $this->seed(CottageSeeder::class);
        $this->seed(ResortServicesSeeder::class);
        $this->seed(BrochurePhotoSeeder::class);

        $expectations = [
            'Superior (Room 01)' => 'room-01-superior.webp',
            'Superior (Room 1)' => 'rooms-1-5-superior.jpg',
            'Superior (Room 2)' => 'rooms-1-5-superior-2.jpg',
            'Superior (Room 3)' => 'rooms-1-5-superior.jpg',
            'Superior (Room 4)' => 'rooms-1-5-superior-2.jpg',
            'Superior (Room 5)' => 'rooms-1-5-superior.jpg',
            'Superior Family (Room 02)' => 'room-02-superior-family.webp',
            'Superior Barkada (Room 10)' => 'room-10-superior-family.webp',
            'Standard Barkada (Room 11)' => 'room-11-classic-family-plus.webp',
            'Standard Barkada (Room 12)' => 'room-12-classic-family-plus.webp',
            'Deluxe Barkada (Room 13)' => 'rooms-13-15-superior-family.webp',
            'Deluxe Barkada (Room 14)' => 'rooms-13-15-superior-family.webp',
            'Deluxe Barkada (Room 15)' => 'rooms-13-15-superior-family.webp',
            'Superior Barkada (Room 16)' => 'room-16-family-suite.webp',
            'Superior Family (Room 17)' => 'room-17-family-suite.webp',
            'Standard Family (Room 21)' => 'rooms-21-25-standard-family.webp',
            'Standard Family (Room 25)' => 'rooms-21-25-standard-family.webp',
            'Deluxe (Room 26)' => 'rooms-26-29-deluxe.webp',
            'Deluxe (Room 29)' => 'rooms-26-29-deluxe.webp',
            'Dorm (A)' => 'dorm-a.webp',
            'Dorm (B)' => 'dorm-b.webp',
        ];

        foreach ($expectations as $facilityName => $filename) {
            $facility = Facility::query()->where('name', $facilityName)->firstOrFail();
            $photo = $facility->galleryImages()->firstOrFail();

            $this->assertSame(
                'facilities/rooms/'.$facility->slug.'/'.$filename,
                $photo->image_path,
            );
            $this->assertPublicFileExists($photo->image_path);
        }

        $rooms = Facility::query()
            ->whereHas('facilityType', fn ($query) => $query->where('slug', FacilityTypeEnum::Room->value))
            ->with('galleryImages')
            ->get();

        $this->assertCount(26, $rooms);
        $this->assertTrue($rooms->every(fn (Facility $room) => $room->galleryImages->isNotEmpty()));

        foreach (['Diamond Hall', 'Emerald Hall', 'Sapphire Hall', 'Opal Hall'] as $hallName) {
            $hall = Facility::query()->where('name', $hallName)->firstOrFail();
            $photo = $hall->galleryImages()->firstOrFail();

            $this->assertStringEndsWith('.webp', $photo->image_path);
            $this->assertPublicFileExists($photo->image_path);
        }

        $galleryPaths = PagePhoto::query()
            ->where('collection', PagePhotoCollection::Gallery)
            ->pluck('image_path');

        $this->assertTrue($galleryPaths->contains('pages/gallery/resort-team.jpg'));
        $this->assertFalse($galleryPaths->contains('pages/gallery/villa-sign.jpg'));
        $this->assertFalse($galleryPaths->contains('pages/gallery/grounds-cart.jpg'));
        $this->assertFalse($galleryPaths->contains('pages/gallery/pool-slide.jpg'));

        $retiredBasenames = [
            'deluxe.jpg',
            'twin-deluxe.jpg',
            'family-room.jpg',
            'pool-slide.jpg',
            'villa-sign.jpg',
            'private-pool.jpg',
            'villa-exterior.jpg',
            'sapphire-pool-hall.jpg',
            'diamond-hall.png',
        ];

        $facilityPaths = GalleryImage::query()->pluck('image_path');

        foreach ($retiredBasenames as $basename) {
            $this->assertFalse(
                $facilityPaths->contains(fn (string $path) => basename($path) === $basename),
                "Retired brochure photo [{$basename}] should not remain on facilities.",
            );
        }

        foreach (['Private Pool + Function Hall (Day Use)', 'Sapphire Hall + Pool + Jacuzzi (Day Use)', 'Diamond Hall', 'Opal Hall'] as $facilityName) {
            $facility = Facility::query()->where('name', $facilityName)->firstOrFail();
            $this->assertTrue($facility->galleryImages()->exists(), "{$facilityName} should keep a current photo.");
        }

        foreach ([1, 2, 12] as $number) {
            $cottage = Facility::query()->where('name', sprintf('Cottage %02d', $number))->firstOrFail();
            $photo = $cottage->galleryImages()->firstOrFail();

            $this->assertSame(
                'facilities/cottages/'.$cottage->slug.'/cottage-'.sprintf('%02d', $number).'.jpg',
                $photo->image_path,
            );
            $this->assertPublicFileExists($photo->image_path);
        }

        $poolExpectations = [
            'adult-pool' => 'resort-pools/adult-pool.png',
            'kiddie-pool' => 'resort-pools/kiddie-pool.jpg',
            'private-pool' => 'resort-pools/private-pool.jpg',
        ];

        foreach ($poolExpectations as $slug => $expectedPath) {
            $pool = ResortPool::query()->where('slug', $slug)->firstOrFail();

            $this->assertSame($expectedPath, $pool->image_path);
            $this->assertPublicFileExists($pool->image_path);
        }

        $imageCount = GalleryImage::query()->count();
        $galleryCount = $galleryPaths->count();

        $this->seed(BrochurePhotoSeeder::class);

        $this->assertSame($imageCount, GalleryImage::query()->count());
        $this->assertSame($galleryCount, PagePhoto::query()->where('collection', PagePhotoCollection::Gallery)->count());
    }

    private function assertPublicFileExists(string $path): void
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');
        $disk->assertExists($path);
    }
}
