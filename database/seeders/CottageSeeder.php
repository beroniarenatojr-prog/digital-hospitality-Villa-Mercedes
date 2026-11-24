<?php

namespace Database\Seeders;

use App\Enums\FacilityStatus;
use App\Enums\FacilityType as FacilityTypeEnum;
use App\Models\Facility;
use App\Models\FacilityType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CottageSeeder extends Seeder
{
    public function run(): void
    {
        $type = FacilityType::query()->where('slug', FacilityTypeEnum::Cottage->value)->firstOrFail();

        for ($number = 1; $number <= 12; $number++) {
            $name = sprintf('Cottage %02d', $number);
            Facility::query()->updateOrCreate(['slug' => Str::slug($name)], [
                'facility_type_id' => $type->id,
                'name' => $name,
                'description' => 'A guest-selectable cottage beside the public pool area. Pool entrance is paid separately at the entrance.',
                'capacity' => null,
                'max_capacity' => 50,
                'status' => FacilityStatus::Available->value,
                'is_active' => true,
                'sort_order' => $number,
            ]);
        }
    }
}
