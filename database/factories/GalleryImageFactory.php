<?php

namespace Database\Factories;

use App\Models\Facility;
use App\Models\GalleryImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryImage>
 */
class GalleryImageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'facility_id' => Facility::factory(),
            'image_path' => 'facilities/example.jpg',
            'caption' => $this->faker->optional()->words(4, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
