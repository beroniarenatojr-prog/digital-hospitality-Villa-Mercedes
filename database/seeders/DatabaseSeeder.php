<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AdminUserSeeder::class,
            CustomerUserSeeder::class,
            FacilityTypeSeeder::class,
            AmenitySeeder::class,
            FacilitySeeder::class,
            RateSeeder::class,
            TrainingBatchSeeder::class,
            FaqCategorySeeder::class,
            FaqSeeder::class,
        ]);
    }
}
