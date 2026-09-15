<?php

namespace Database\Seeders;

use App\Enums\FacilityStatus;
use App\Enums\FacilityType as FacilityTypeEnum;
use App\Enums\PackageFamily;
use App\Enums\StayCategory;
use App\Models\Amenity;
use App\Models\Facility;
use App\Models\FacilityType;
use App\Models\PackageComponent;
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
            ['name' => 'Superior (Room 01)', 'capacity' => 2, 'max_capacity' => 2],
            ['name' => 'Superior Family (Room 02)', 'capacity' => 4, 'max_capacity' => 4],
            ['name' => 'Superior (Room 1)', 'capacity' => 2, 'max_capacity' => 2],
            ['name' => 'Superior (Room 2)', 'capacity' => 2, 'max_capacity' => 2],
            ['name' => 'Superior (Room 3)', 'capacity' => 2, 'max_capacity' => 2],
            ['name' => 'Superior (Room 4)', 'capacity' => 2, 'max_capacity' => 2],
            ['name' => 'Superior (Room 5)', 'capacity' => 2, 'max_capacity' => 2],
            ['name' => 'Superior Barkada (Room 10)', 'capacity' => 6, 'max_capacity' => 8],
            ['name' => 'Standard Barkada (Room 11)', 'capacity' => 8, 'max_capacity' => 10],
            ['name' => 'Standard Barkada (Room 12)', 'capacity' => 8, 'max_capacity' => 10],
            ['name' => 'Deluxe Barkada (Room 13)', 'capacity' => 6, 'max_capacity' => 8],
            ['name' => 'Deluxe Barkada (Room 14)', 'capacity' => 6, 'max_capacity' => 8],
            ['name' => 'Deluxe Barkada (Room 15)', 'capacity' => 6, 'max_capacity' => 8],
            ['name' => 'Superior Barkada (Room 16)', 'capacity' => 6, 'max_capacity' => 8],
            ['name' => 'Superior Family (Room 17)', 'capacity' => 4, 'max_capacity' => 6],
            ['name' => 'Standard Family (Room 21)', 'capacity' => 4, 'max_capacity' => 4],
            ['name' => 'Standard Family (Room 22)', 'capacity' => 4, 'max_capacity' => 4],
            ['name' => 'Standard Family (Room 23)', 'capacity' => 4, 'max_capacity' => 4],
            ['name' => 'Standard Family (Room 24)', 'capacity' => 4, 'max_capacity' => 4],
            ['name' => 'Standard Family (Room 25)', 'capacity' => 4, 'max_capacity' => 4],
            ['name' => 'Deluxe (Room 26)', 'capacity' => 2, 'max_capacity' => 2],
            ['name' => 'Deluxe (Room 27)', 'capacity' => 2, 'max_capacity' => 2],
            ['name' => 'Deluxe (Room 28)', 'capacity' => 2, 'max_capacity' => 2],
            ['name' => 'Deluxe (Room 29)', 'capacity' => 2, 'max_capacity' => 2],
            ['name' => 'Dorm (A)', 'capacity' => 10, 'max_capacity' => 10],
            ['name' => 'Dorm (B)', 'capacity' => 10, 'max_capacity' => 10],
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

        $sharedRoomAmenityIds = [];
        foreach ([
            'Free WiFi' => 'wifi',
            'Air Conditioning' => 'snowflake',
            'Hot/Cold Shower' => 'shower',
            'Bath Towels' => 'towel',
            'Coffee Cups' => 'coffee',
            'Swimming Pool Access' => 'water',
        ] as $amenityName => $icon) {
            $sharedRoomAmenityIds[] = Amenity::query()->firstOrCreate(
                ['name' => $amenityName],
                ['icon' => $icon, 'is_active' => true]
            )->id;
        }

        Facility::query()
            ->where('facility_type_id', $roomType->id)
            ->where('is_active', true)
            ->each(fn (Facility $room) => $room->amenities()->syncWithoutDetaching($sharedRoomAmenityIds));

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
                    'is_listed' => true,
                    'sort_order' => $index,
                ]
            );
        }

        Facility::query()->updateOrCreate(
            ['slug' => 'private-function-hall'],
            [
                'facility_type_id' => $hallType->id,
                'name' => 'Private Function Hall',
                'description' => 'Exclusive function hall reserved only with Package I.',
                'capacity' => 50,
                'max_capacity' => 50,
                'status' => FacilityStatus::Available->value,
                'is_active' => true,
                'is_listed' => false,
                'sort_order' => 99,
            ]
        );

        Facility::query()
            ->whereIn('name', [
                'Day Use Package',
                'Night Use Package',
                'Overnight Package',
                'Private Pool Package',
                'Function Hall + Private Pool',
                'Sapphire Hall + Pool + Jacuzzi',
                'Room Accommodation Package',
            ])
            ->update(['is_active' => false]);

        $amenityNames = [
            'Private Pool',
            'Function Hall',
            'Free WiFi',
            'CCTV Monitored Premises',
            'Exclusive Villa Use',
            'Parking Area',
            'Use of Grill',
            'Air-conditioned Room',
            'Hot and Cold Shower',
            'Cable Television',
            'Water Dispenser',
            'Veranda',
            'Sapphire Hall',
            'Chairs and Tables',
            'Jacuzzi',
            '2 Air-conditioned Rooms',
            'Living Area',
            'Kitchen with Refrigerator',
            'Pool Access',
            'Gazebo',
            'Breakfast',
            'Exclusive Pool',
            'Lounge Area',
            'Private Pool Access',
            'Resort Amenities Bundle',
        ];

        $seededAmenities = [];
        foreach ($amenityNames as $amenityName) {
            $seededAmenities[$amenityName] = Amenity::firstOrCreate(
                ['name' => $amenityName],
                ['icon' => 'default-icon', 'is_active' => true]
            );
        }

        $packageIShared = ['Private Pool', 'Function Hall', 'Free WiFi', 'CCTV Monitored Premises', 'Exclusive Villa Use', 'Parking Area', 'Use of Grill'];
        $packageIIShared = ['Sapphire Hall', 'Chairs and Tables', 'Private Pool', 'Jacuzzi', 'Free WiFi', 'CCTV Monitored Premises', 'Exclusive Villa Use', 'Parking Area', 'Use of Grill'];

        $packages = [
            [
                'name' => 'Private Pool + Function Hall (Day Use)',
                'family' => PackageFamily::PackageI,
                'category' => StayCategory::DayUse,
                'start' => '09:00',
                'end' => '17:00',
                'overnight' => false,
                'capacity' => 50,
                'max_capacity' => 50,
                'description' => 'Private pool with function hall, 9:00 AM to 5:00 PM, good for 50 pax.',
                'components' => ['Private Function Hall'],
                'amenities' => $packageIShared,
            ],
            [
                'name' => 'Private Pool + Function Hall (Night Use)',
                'family' => PackageFamily::PackageI,
                'category' => StayCategory::NightUse,
                'start' => '17:00',
                'end' => '22:00',
                'overnight' => false,
                'capacity' => 50,
                'max_capacity' => 50,
                'description' => 'Private pool with function hall, 5:00 PM to 10:00 PM, good for 50 pax.',
                'components' => ['Private Function Hall'],
                'amenities' => $packageIShared,
            ],
            [
                'name' => 'Private Pool + Function Hall (Overnight Stay)',
                'family' => PackageFamily::PackageI,
                'category' => StayCategory::Overnight,
                'start' => '14:00',
                'end' => '12:00',
                'overnight' => true,
                'capacity' => 20,
                'max_capacity' => 20,
                'description' => 'Private pool, function hall, and room accommodation, 2:00 PM to 12:00 PM the next day, good for 20 pax.',
                'components' => ['Private Function Hall', 'Dorm (A)', 'Dorm (B)'],
                'amenities' => array_merge($packageIShared, ['Air-conditioned Room', 'Hot and Cold Shower', 'Cable Television', 'Water Dispenser', 'Veranda']),
            ],
            [
                'name' => 'Sapphire Hall + Pool + Jacuzzi (Day Use)',
                'family' => PackageFamily::PackageII,
                'category' => StayCategory::DayUse,
                'start' => '09:00',
                'end' => '14:00',
                'overnight' => false,
                'capacity' => 50,
                'max_capacity' => 100,
                'description' => 'Sapphire Hall and private pool with jacuzzi, 9:00 AM to 2:00 PM.',
                'components' => ['Sapphire Hall'],
                'amenities' => $packageIIShared,
            ],
            [
                'name' => 'Sapphire Hall + Pool + Jacuzzi (Night Use)',
                'family' => PackageFamily::PackageII,
                'category' => StayCategory::NightUse,
                'start' => '17:00',
                'end' => '22:00',
                'overnight' => false,
                'capacity' => 50,
                'max_capacity' => 100,
                'description' => 'Sapphire Hall and private pool with jacuzzi, 5:00 PM to 10:00 PM.',
                'components' => ['Sapphire Hall'],
                'amenities' => $packageIIShared,
            ],
            [
                'name' => 'Sapphire Hall + Pool + Jacuzzi (Overnight Stay)',
                'family' => PackageFamily::PackageII,
                'category' => StayCategory::Overnight,
                'start' => '15:00',
                'end' => '11:00',
                'overnight' => true,
                'capacity' => 16,
                'max_capacity' => 100,
                'description' => 'Sapphire Hall, private pool with jacuzzi, and two air-conditioned rooms, 3:00 PM to 11:00 AM the next day.',
                'components' => ['Sapphire Hall', 'Standard Barkada (Room 11)', 'Standard Barkada (Room 12)'],
                'amenities' => array_merge($packageIIShared, ['2 Air-conditioned Rooms', 'Cable Television', 'Living Area', 'Kitchen with Refrigerator', 'Water Dispenser']),
            ],
        ];

        foreach ($packages as $index => $pkg) {
            $packageFacility = Facility::query()->updateOrCreate(
                ['slug' => Str::slug($pkg['name'])],
                [
                    'facility_type_id' => $packageType->id,
                    'name' => $pkg['name'],
                    'description' => $pkg['description'],
                    'capacity' => $pkg['capacity'],
                    'max_capacity' => $pkg['max_capacity'],
                    'status' => FacilityStatus::Available->value,
                    'is_active' => true,
                    'is_listed' => true,
                    'package_family' => $pkg['family']->value,
                    'stay_category' => $pkg['category']->value,
                    'stay_start_time' => $pkg['start'],
                    'stay_end_time' => $pkg['end'],
                    'stay_spans_midnight' => $pkg['overnight'],
                    'sort_order' => $index,
                ]
            );

            foreach ($pkg['components'] as $componentName) {
                $component = Facility::where('name', $componentName)->first();
                if ($component) {
                    PackageComponent::firstOrCreate([
                        'package_facility_id' => $packageFacility->id,
                        'component_facility_id' => $component->id,
                    ]);
                }
            }

            $amenityIds = [];
            foreach ($pkg['amenities'] as $amenityName) {
                if (isset($seededAmenities[$amenityName])) {
                    $amenityIds[] = $seededAmenities[$amenityName]->id;
                }
            }
            $packageFacility->amenities()->sync($amenityIds);
        }
    }
}
