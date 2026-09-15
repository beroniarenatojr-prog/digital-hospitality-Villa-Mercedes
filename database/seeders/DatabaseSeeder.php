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
            CottageSeeder::class,
            RateSeeder::class,
            ResortServicesSeeder::class,
            BrochurePhotoSeeder::class,
            FaqCategorySeeder::class,
            FaqSeeder::class,
            DemoGuestSeeder::class,
        ]);

        if (config('resort.demo_content')) {
            $this->call(GuestReviewSeeder::class);
        }
    }
}
