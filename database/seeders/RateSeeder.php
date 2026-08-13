<?php

namespace Database\Seeders;

use App\Enums\RateType;
use App\Models\Facility;
use App\Models\Rate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RateSeeder extends Seeder
{
    public function run(): void
    {
        $roomRates = [
            'Deluxe' => 2500,
            'Twin Deluxe' => 3500,
            'Superior Twin' => 3400,
            'Family Room' => 5500,
            'Dormitory' => 10000,
        ];

        foreach ($roomRates as $name => $amount) {
            $facility = Facility::query()->where('slug', Str::slug($name))->first();
            
            if ($facility) {
                Rate::query()->firstOrCreate(
                    [
                        'facility_id' => $facility->id,
                        'type' => RateType::Base->value,
                    ],
                    [
                        'name' => 'Base Rate',
                        'amount' => $amount,
                        'unit' => 'per night',
                    ]
                );

                // Additional charges for all rooms
                Rate::query()->firstOrCreate(
                    [
                        'facility_id' => $facility->id,
                        'name' => 'Early Check-in',
                    ],
                    [
                        'type' => RateType::AdditionalCharge->value,
                        'amount' => 200,
                        'unit' => 'per hour',
                    ]
                );

                Rate::query()->firstOrCreate(
                    [
                        'facility_id' => $facility->id,
                        'name' => 'Late Check-out',
                    ],
                    [
                        'type' => RateType::AdditionalCharge->value,
                        'amount' => 300,
                        'unit' => 'per hour',
                    ]
                );

                Rate::query()->firstOrCreate(
                    [
                        'facility_id' => $facility->id,
                        'name' => 'Extra Person Charge',
                    ],
                    [
                        'type' => RateType::ExtraPerson->value,
                        'amount' => 500,
                        'unit' => 'per person',
                    ]
                );
            }
        }

        $hallRates = [
            'Diamond Hall' => 25000,
            'Emerald Hall' => 12000,
            'Sapphire Hall' => 10000,
            'Opal Hall' => 10000,
        ];

        foreach ($hallRates as $name => $amount) {
            $facility = Facility::query()->where('slug', Str::slug($name))->first();
            
            if ($facility) {
                Rate::query()->firstOrCreate(
                    [
                        'facility_id' => $facility->id,
                        'type' => RateType::Base->value,
                    ],
                    [
                        'name' => 'Base Rate',
                        'amount' => $amount,
                        'unit' => 'per event',
                    ]
                );
            }
        }


    }
}
