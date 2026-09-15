<?php

namespace Database\Factories;

use App\Enums\PagePhotoCollection;
use App\Models\PagePhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PagePhoto>
 */
class PagePhotoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'collection' => PagePhotoCollection::Gallery,
            'image_path' => 'pages/gallery/example.jpg',
            'title' => $this->faker->words(3, true),
            'caption' => $this->faker->sentence(),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
