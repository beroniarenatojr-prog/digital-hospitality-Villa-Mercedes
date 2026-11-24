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
            'Superior (Room 01)' => 2500,
            'Superior Family (Room 02)' => 4500,
            'Superior Barkada (Room 10)' => 5500,
            'Standard Barkada (Room 11)' => 6500,
            'Standard Barkada (Room 12)' => 7500,
            'Superior Barkada (Room 16)' => 5500,
            'Superior Family (Room 17)' => 5500,
            'Dorm (A)' => 10000,
            'Dorm (B)' => 10000,
        ];

        foreach (range(1, 5) as $number) {
            $roomRates["Superior (Room {$number})"] = 3500;
        }

        foreach (range(13, 15) as $number) {
            $roomRates["Deluxe Barkada (Room {$number})"] = 5500;
        }

        foreach (range(21, 25) as $number) {
            $roomRates["Standard Family (Room {$number})"] = 3500;
        }

        foreach (range(26, 29) as $number) {
            $roomRates["Deluxe (Room {$number})"] = 2500;
        }

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
                $extension = config('resort.stay.hall.extension_rates.'.$facility->slug);
                if ($extension !== null) {
                    Rate::query()->firstOrCreate(
                        ['facility_id' => $facility->id, 'name' => 'Hall Extension'],
                        ['type' => RateType::AdditionalCharge->value, 'amount' => $extension, 'unit' => 'per hour']
                    );
                }
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

        // Retired packages keep rates if those rows still exist.
        $legacyPackageRates = [
            'Day Use Package' => ['amount' => 8000, 'unit' => 'per day', 'extra_person' => 300, 'hourly' => 500],
            'Night Use Package' => ['amount' => 8000, 'unit' => 'per day', 'extra_person' => 300, 'hourly' => 500],
            'Overnight Package' => ['amount' => 12000, 'unit' => 'per night', 'extra_person' => 500, 'hourly' => 500],
            'Private Pool Package' => ['amount' => 6500, 'unit' => 'per day', 'extra_person' => 300, 'hourly' => 500],
            'Function Hall + Private Pool' => ['amount' => 30000, 'unit' => 'per event', 'extra_person' => 250, 'hourly' => 800],
            'Sapphire Hall + Pool + Jacuzzi' => ['amount' => 18000, 'unit' => 'per event', 'extra_person' => 250, 'hourly' => 800],
            'Room Accommodation Package' => ['amount' => 7500, 'unit' => 'per night', 'extra_person' => 500, 'hourly' => 500],
        ];

        foreach ($legacyPackageRates as $name => $config) {
            $facility = Facility::query()->where('slug', Str::slug($name))->first();

            if (! $facility) {
                continue;
            }

            $this->seedLegacyPackageRates($facility, $config);
        }

        $flyerPackageRates = [
            'Private Pool + Function Hall (Day Use)' => [
                ['amount' => 10000, 'max_occupancy' => 50, 'name' => '50 Pax'],
            ],
            'Private Pool + Function Hall (Night Use)' => [
                ['amount' => 12000, 'max_occupancy' => 50, 'name' => '50 Pax'],
            ],
            'Private Pool + Function Hall (Overnight Stay)' => [
                ['amount' => 15000, 'max_occupancy' => 20, 'name' => '20 Pax'],
            ],
            'Sapphire Hall + Pool + Jacuzzi (Day Use)' => [
                ['amount' => 10000, 'max_occupancy' => 50, 'name' => '50 Pax'],
                ['amount' => 15000, 'max_occupancy' => 100, 'name' => '100 Pax'],
            ],
            'Sapphire Hall + Pool + Jacuzzi (Night Use)' => [
                ['amount' => 15000, 'max_occupancy' => 50, 'name' => '50 Pax'],
                ['amount' => 20000, 'max_occupancy' => 100, 'name' => '100 Pax'],
            ],
            'Sapphire Hall + Pool + Jacuzzi (Overnight Stay)' => [
                ['amount' => 30000, 'max_occupancy' => 100, 'name' => 'Overnight Stay'],
            ],
        ];

        foreach ($flyerPackageRates as $name => $rates) {
            $facility = Facility::query()->where('slug', Str::slug($name))->first();

            if (! $facility) {
                continue;
            }

            foreach ($rates as $rate) {
                Rate::query()->updateOrCreate(
                    [
                        'facility_id' => $facility->id,
                        'type' => RateType::Base->value,
                        'max_occupancy' => $rate['max_occupancy'],
                    ],
                    [
                        'name' => $rate['name'],
                        'amount' => $rate['amount'],
                        'unit' => 'per event',
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    /**
     * @param  array{amount: int, unit: string, extra_person: int, hourly: int}  $config
     */
    private function seedLegacyPackageRates(Facility $facility, array $config): void
    {
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
