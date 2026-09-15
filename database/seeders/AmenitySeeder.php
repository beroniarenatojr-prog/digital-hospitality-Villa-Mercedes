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
            ['name' => 'Basic Toiletries', 'icon' => 'sparkles'],
            ['name' => 'Hot/Cold Shower', 'icon' => 'shower'],
            ['name' => 'Electric Kettle', 'icon' => 'kettle'],
            ['name' => 'Dining Table', 'icon' => 'table'],
            ['name' => 'Cabinet with Hangers', 'icon' => 'hanger'],
            ['name' => 'Full Body Mirror', 'icon' => 'mirror'],
            ['name' => 'Veranda with Tables & Chairs', 'icon' => 'sun'],
            ['name' => 'Terrace with Tables & Chairs', 'icon' => 'trees'],
            ['name' => 'Water Dispenser', 'icon' => 'droplet'],
            ['name' => 'Kitchen', 'icon' => 'chef'],
            ['name' => 'Bath Towels', 'icon' => 'towel'],
            ['name' => 'Coffee Cups', 'icon' => 'coffee'],
            ['name' => 'Bottled Water', 'icon' => 'bottle'],
            ['name' => 'Comfort Room in Front of Room', 'icon' => 'door'],
        ];

        foreach ($amenities as $amenity) {
            Amenity::query()->firstOrCreate(
                ['name' => $amenity['name']],
                $amenity
            );
        }
    }
}
