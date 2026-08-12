<?php

namespace Database\Seeders;

use App\Enums\FacilityStatus;
use App\Enums\FacilityType as FacilityTypeEnum;
use App\Models\Facility;
use App\Models\FacilityType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FacilitySeeder extends Seeder
{
    public function run(): void
    {
        $roomType = FacilityType::query()->where('slug', FacilityTypeEnum::Room->value)->firstOrFail();
        $hallType = FacilityType::query()->where('slug', FacilityTypeEnum::FunctionHall->value)->firstOrFail();
        $packageType = FacilityType::query()->where('slug', FacilityTypeEnum::ResortPackage->value)->firstOrFail();

        // Rooms
        $rooms = [
            ['name' => 'Deluxe', 'capacity' => 2, 'max_capacity' => 2],
            ['name' => 'Twin Deluxe', 'capacity' => 2, 'max_capacity' => 2],
            ['name' => 'Superior Twin', 'capacity' => 2, 'max_capacity' => 2],
            ['name' => 'Family Room', 'capacity' => 4, 'max_capacity' => 4],
            ['name' => 'Dormitory', 'capacity' => 10, 'max_capacity' => 10], // Assuming 10 pax for dormitory
        ];

        foreach ($rooms as $index => $room) {
            Facility::query()->firstOrCreate(
                ['slug' => Str::slug($room['name'])],
                [
                    'facility_type_id' => $roomType->id,
                    'name' => $room['name'],
                    'description' => "A comfortable {$room['name']} accommodation.",
                    'capacity' => $room['capacity'],
                    'max_capacity' => $room['max_capacity'],
                    'status' => FacilityStatus::Available->value,
                    'sort_order' => $index,
                ]
            );
        }

        // Function Halls
        $halls = [
            ['name' => 'Diamond Hall', 'capacity' => 250, 'max_capacity' => 250],
            ['name' => 'Emerald Hall', 'capacity' => 100, 'max_capacity' => 100],
            ['name' => 'Sapphire Hall', 'capacity' => 100, 'max_capacity' => 100],
            ['name' => 'Opal Hall', 'capacity' => 100, 'max_capacity' => 100],
        ];

        foreach ($halls as $index => $hall) {
            Facility::query()->firstOrCreate(
                ['slug' => Str::slug($hall['name'])],
                [
                    'facility_type_id' => $hallType->id,
                    'name' => $hall['name'],
                    'description' => "The elegant {$hall['name']} for your special events.",
                    'capacity' => $hall['capacity'],
                    'max_capacity' => $hall['max_capacity'],
                    'status' => FacilityStatus::Available->value,
                    'sort_order' => $index,
                ]
            );
        }

        // Resort Packages
        $packages = [
            ['name' => 'Day Use Package', 'capacity' => 30, 'max_capacity' => 50, 'description' => 'Pool access + gazebo, 8AM-5PM', 'components' => []],
            ['name' => 'Night Use Package', 'capacity' => 30, 'max_capacity' => 50, 'description' => 'Pool access + gazebo, 6PM-12AM', 'components' => []],
            ['name' => 'Overnight Package', 'capacity' => 20, 'max_capacity' => 30, 'description' => 'Room + pool access + breakfast', 'components' => ['Deluxe']],
            ['name' => 'Private Pool Package', 'capacity' => 15, 'max_capacity' => 20, 'description' => 'Exclusive pool + lounge area', 'components' => []],
            ['name' => 'Function Hall + Private Pool', 'capacity' => 100, 'max_capacity' => 150, 'description' => 'Hall rental + private pool access', 'components' => ['Emerald Hall']],
            ['name' => 'Sapphire Hall + Pool + Jacuzzi', 'capacity' => 80, 'max_capacity' => 100, 'description' => 'Sapphire Hall + pool + Jacuzzi', 'components' => ['Sapphire Hall']],
            ['name' => 'Room Accommodation Package', 'capacity' => 4, 'max_capacity' => 6, 'description' => 'Room + resort amenities bundle', 'components' => ['Family Room']],
        ];

        foreach ($packages as $index => $pkg) {
            $packageFacility = Facility::query()->firstOrCreate(
                ['slug' => Str::slug($pkg['name'])],
                [
                    'facility_type_id' => $packageType->id,
                    'name' => $pkg['name'],
                    'description' => $pkg['description'],
                    'capacity' => $pkg['capacity'],
                    'max_capacity' => $pkg['max_capacity'],
                    'status' => FacilityStatus::Available->value,
                    'sort_order' => $index,
                ]
            );

            // Seed components if any
            foreach ($pkg['components'] as $componentName) {
                $component = Facility::where('name', $componentName)->first();
                if ($component) {
                    \App\Models\PackageComponent::firstOrCreate([
                        'package_facility_id' => $packageFacility->id,
                        'component_facility_id' => $component->id,
                    ]);
                }
            }
        }
    }
}
