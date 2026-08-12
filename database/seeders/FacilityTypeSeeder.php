<?php

namespace Database\Seeders;

use App\Enums\FacilityType as FacilityTypeEnum;
use App\Models\FacilityType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FacilityTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => FacilityTypeEnum::Room->label(),
                'slug' => FacilityTypeEnum::Room->value,
                'description' => 'Overnight accommodations for guests.',
            ],
            [
                'name' => FacilityTypeEnum::FunctionHall->label(),
                'slug' => FacilityTypeEnum::FunctionHall->value,
                'description' => 'Event spaces for weddings, parties, and corporate events.',
            ],
            [
                'name' => FacilityTypeEnum::ResortPackage->label(),
                'slug' => FacilityTypeEnum::ResortPackage->value,
                'description' => 'Bundled services and accommodations.',
            ],
        ];

        foreach ($types as $type) {
            FacilityType::query()->firstOrCreate(
                ['slug' => $type['slug']],
                $type
            );
        }
    }
}
