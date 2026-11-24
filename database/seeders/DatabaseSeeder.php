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
            FacilityTypeSeeder::class,
            AmenitySeeder::class,
            FacilitySeeder::class,
            CottageSeeder::class,
            RateSeeder::class,
            ResortServicesSeeder::class,
            BrochurePhotoSeeder::class,
            FaqCategorySeeder::class,
            FaqSeeder::class,
        ]);

    }
}
