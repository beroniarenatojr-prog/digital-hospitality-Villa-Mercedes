<?php

namespace Database\Seeders;

use App\Enums\RateType;
use App\Models\Facility;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Rate;
use App\Models\ResortFee;
use App\Models\ResortPool;
use App\Models\ResortRule;
use App\Models\ResortSetting;
use App\Models\SwimmingSession;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ResortServicesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Adult Pool', 'adult-pool', 'A public swimming pool for adult guests.', 'General adult pool; no accessibility features are implied.', false],
            ['Kiddie Pool', 'kiddie-pool', 'A public pool intended for children during day swimming.', 'Children must be watched by a responsible adult.', false],
            ['Private Pool', 'private-pool', 'Available only with selected private-pool packages.', 'Ask staff which package includes private use.', true],
        ] as $index => [$name, $slug, $description, $audience, $packageOnly]) {
            ResortPool::query()->updateOrCreate(['slug' => $slug], [
                'name' => $name, 'description' => $description, 'audience_note' => $audience,
                'is_package_only' => $packageOnly, 'is_active' => true, 'sort_order' => $index,
            ]);
        }

        foreach ([
            ['Day Swimming', 'day', '09:00', '17:00', 100, 70, 'Children below 4 feet', true, 500, 0],
            ['Night Swimming', 'night', '17:00', '22:00', 120, null, null, false, 500, 1],
        ] as [$name, $slug, $start, $end, $adult, $child, $qualifier, $childrenAllowed, $cottage, $order]) {
            SwimmingSession::query()->updateOrCreate(['slug' => $slug], [
                'name' => $name, 'starts_at' => $start, 'ends_at' => $end,
                'adult_entrance_fee' => $adult, 'child_entrance_fee' => $child,
                'child_qualifier' => $qualifier, 'children_allowed' => $childrenAllowed,
                'cottage_fee' => $cottage, 'is_active' => true, 'sort_order' => $order,
            ]);
        }

        $rules = [
            'Shower before entering the pool.',
            'Watch your children. Unattended solo bathing is prohibited.',
            'Proper swimwear must be worn at all times. Dark-colored shirts, pants, and maong are not allowed.',
            'Food and drinks are not permitted in the pool area.',
            'Drinks, appliances, and other electronic devices are subject to the applicable corkage fee.',
            'No alcohol or glass articles are allowed in or around the pool.',
            'No spitting water or blowing your nose in the pool.',
            'No pets allowed.',
            'No running or rough play.',
            'Do not throw balls, frisbees, or other objects.',
            'No diving, pushing, or bombing.',
            'No shouting.',
            'Do not urinate in the pool. Use the restrooms.',
        ];
        foreach ($rules as $index => $text) {
            ResortRule::query()->updateOrCreate(['text' => $text], ['is_active' => true, 'is_public' => true, 'sort_order' => $index]);
        }

        foreach ([
            ['Beer', 20, 'per bottle or can', null],
            ['Local hard liquor below 1 liter', 50, 'per item', null],
            ['Local hard liquor — 1 liter', 100, 'per item', null],
            ['Imported hard liquor', 120, 'per item', null],
            ['Appliances', 100, 'per item', 'Rice cooker, electric pot, electric fan, electric griller, speaker, and similar appliances.'],
            ['Catering, lights & sounds', 2000, 'per arrangement', null],
            ['Photoshoot', 1500, 'per session', null],
        ] as $index => [$name, $amount, $unit, $description]) {
            ResortFee::query()->updateOrCreate(['name' => $name], compact('amount', 'unit', 'description') + ['is_active' => true, 'sort_order' => $index]);
        }

        foreach ([
            'cafe_hours' => 'Open daily, 7:00 AM–10:00 PM',
            'cafe_viber' => '0935 207 8289',
            'cafe_service_charge' => '3',
            'cafe_outside_food_notice' => 'Outside food is not allowed. A ₱200 corkage fee may be charged where applicable.',
        ] as $key => $value) {
            ResortSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $menu = [
            ['Viands', 'Good for 3–4 persons', [
                ['Ensaladang Talong', 195, null], ['Sisig', 245, 'Add egg +₱20'], ['Pork Adobo', 285, null],
                ['Lechon Kawali', 315, null], ['Chicken Inasal', 365, null], ['Bangus Belly Steak', 315, null],
                ['Sinigang na Hipon', 315, null], ['Bulalo', 450, null], ['Crispy Pata', 750, null],
            ]],
            ['Solo Meal', 'Served with solo rice', [
                ['Pork BBQ', 195, null], ['Sisig', 195, null], ['Chicken Inasal', 205, null], ['Bangus Belly Steak', 215, null],
            ]],
            ['Rice', null, [
                ['Plain Rice', 25, null], ['Garlic Fried Rice', 145, null], ['Adobo Fried Rice', 165, null], ['Yang Chow Fried Rice', 185, null],
            ]],
        ];

        foreach ($menu as $categoryOrder => [$categoryName, $subtitle, $items]) {
            $category = MenuCategory::query()->updateOrCreate(['slug' => Str::slug($categoryName)], [
                'name' => $categoryName, 'subtitle' => $subtitle, 'is_active' => true, 'sort_order' => $categoryOrder,
            ]);
            foreach ($items as $itemOrder => [$name, $price, $note]) {
                MenuItem::query()->updateOrCreate(
                    ['menu_category_id' => $category->id, 'name' => $name],
                    ['price' => $price, 'note' => $note, 'is_available' => true, 'sort_order' => $itemOrder]
                );
            }
        }

        Facility::query()->whereHas('facilityType', fn ($query) => $query->where('slug', 'cottage'))->each(function (Facility $cottage): void {
            Rate::query()->updateOrCreate(
                ['facility_id' => $cottage->id, 'type' => RateType::Base->value],
                ['name' => 'Cottage session', 'amount' => 500, 'unit' => 'per session', 'is_active' => true]
            );
        });
    }
}
