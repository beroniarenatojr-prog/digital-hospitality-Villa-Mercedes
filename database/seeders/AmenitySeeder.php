<?php

namespace Database\Seeders;

use App\Models\Amenity;
use Illuminate\Database\Seeder;

class AmenitySeeder extends Seeder
{
    public function run(): void
    {
        $amenities = [
            ['name' => 'Free WiFi', 'icon' => 'wifi'],
            ['name' => 'Air Conditioning', 'icon' => 'snowflake'],
            ['name' => 'Smart TV', 'icon' => 'tv'],
            ['name' => 'Private Bathroom', 'icon' => 'bath'],
            ['name' => 'Free Parking', 'icon' => 'car'],
            ['name' => 'Swimming Pool Access', 'icon' => 'water'],
            ['name' => 'Sound System', 'icon' => 'speaker'],
            ['name' => 'Projector & Screen', 'icon' => 'projector'],
            ['name' => 'Stage', 'icon' => 'stage'],
            ['name' => 'Catering Area', 'icon' => 'utensils'],
        ];

        foreach ($amenities as $amenity) {
            Amenity::query()->firstOrCreate(
                ['name' => $amenity['name']],
                $amenity
            );
        }
    }
}
