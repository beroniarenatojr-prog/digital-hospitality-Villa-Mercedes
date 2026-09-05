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

                Rate::query()->firstOrCreate(
                    [
                        'facility_id' => $facility->id,
                        'name' => 'Early Check-in',
                    ],
                    [
                        'type' => RateType::AdditionalCharge->value,
                        'amount' => 500,
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
                        'amount' => 500,
                        'unit' => 'per hour',
                    ]
                );
            }
        }

        // Resort packages. Without these, a package facility is bookable in the
        // UI but PricingService throws NoApplicableRateException on submit.
        $packageRates = [
            'Day Use Package' => ['amount' => 8000, 'unit' => 'per day', 'extra_person' => 300, 'hourly' => 500],
            'Night Use Package' => ['amount' => 8000, 'unit' => 'per day', 'extra_person' => 300, 'hourly' => 500],
            'Overnight Package' => ['amount' => 12000, 'unit' => 'per night', 'extra_person' => 500, 'hourly' => 500],
            'Private Pool Package' => ['amount' => 6500, 'unit' => 'per day', 'extra_person' => 300, 'hourly' => 500],
            'Function Hall + Private Pool' => ['amount' => 30000, 'unit' => 'per event', 'extra_person' => 250, 'hourly' => 800],
            'Sapphire Hall + Pool + Jacuzzi' => ['amount' => 18000, 'unit' => 'per event', 'extra_person' => 250, 'hourly' => 800],
            'Room Accommodation Package' => ['amount' => 7500, 'unit' => 'per night', 'extra_person' => 500, 'hourly' => 500],
        ];

        foreach ($packageRates as $name => $config) {
            $facility = Facility::query()->where('slug', Str::slug($name))->first();

            if (! $facility) {
                continue;
            }

            Rate::query()->firstOrCreate(
                [
                    'facility_id' => $facility->id,
                    'type' => RateType::Base->value,
                ],
                [
                    'name' => 'Base Rate',
                    'amount' => $config['amount'],
                    'unit' => $config['unit'],
                ]
            );

            Rate::query()->firstOrCreate(
                [
                    'facility_id' => $facility->id,
                    'name' => 'Early Check-in',
                ],
                [
                    'type' => RateType::AdditionalCharge->value,
                    'amount' => $config['hourly'],
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
                    'amount' => $config['hourly'],
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
                    'amount' => $config['extra_person'],
                    'unit' => 'per person',
                ]
            );
        }
    }
}
