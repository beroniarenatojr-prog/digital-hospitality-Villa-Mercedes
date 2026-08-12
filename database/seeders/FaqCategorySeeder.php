<?php

namespace Database\Seeders;

use App\Models\FaqCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FaqCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'General',
            'Reservations',
            'Payments',
            'Facilities',
        ];

        foreach ($categories as $index => $category) {
            FaqCategory::query()->firstOrCreate(
                ['slug' => Str::slug($category)],
                [
                    'name' => $category,
                    'sort_order' => $index,
                ]
            );
        }
    }
}
