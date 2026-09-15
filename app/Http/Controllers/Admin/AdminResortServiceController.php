<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FacilityType;
use App\Enums\RateType;
use App\Http\Controllers\Controller;
use App\Models\Facility;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Rate;
use App\Models\ResortFee;
use App\Models\ResortPool;
use App\Models\ResortRule;
use App\Models\ResortSetting;
use App\Models\SwimmingSession;
use App\Services\PublicImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminResortServiceController extends Controller
{
    public function __construct(private readonly PublicImageOptimizer $imageOptimizer) {}

    public function edit(): View
    {
        return view('admin.resort-services.edit', [
            'pools' => ResortPool::query()->orderBy('sort_order')->get(),
            'sessions' => SwimmingSession::query()->orderBy('sort_order')->get(),
            'rules' => ResortRule::query()->orderBy('sort_order')->get(),
            'fees' => ResortFee::query()->orderBy('sort_order')->get(),
            'categories' => MenuCategory::query()->orderBy('sort_order')->get(),
            'items' => MenuItem::query()->with('category')->orderBy('menu_category_id')->orderBy('sort_order')->get(),
            'settings' => ResortSetting::query()->pluck('value', 'key'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'settings.cafe_hours' => ['required', 'string', 'max:255'],
            'settings.cafe_viber' => ['required', 'string', 'max:32'],
            'settings.cafe_service_charge' => ['required', 'numeric', 'min:0', 'max:100'],
            'settings.cafe_outside_food_notice' => ['required', 'string', 'max:1000'],
            'pools' => ['array'], 'pools.*.name' => ['required', 'string', 'max:100'], 'pools.*.description' => ['nullable', 'string', 'max:1000'],
            'pools.*.audience_note' => ['nullable', 'string', 'max:500'], 'pools.*.sort_order' => ['integer', 'min:0'], 'pools.*.is_active' => ['boolean'], 'pools.*.is_package_only' => ['boolean'],
            'pool_images.*' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'sessions' => ['array'], 'sessions.*.name' => ['required', 'string', 'max:100'], 'sessions.*.starts_at' => ['required', 'date_format:H:i'], 'sessions.*.ends_at' => ['required', 'date_format:H:i'],
            'sessions.*.adult_entrance_fee' => ['required', 'numeric', 'min:0'], 'sessions.*.child_entrance_fee' => ['nullable', 'numeric', 'min:0'], 'sessions.*.child_qualifier' => ['nullable', 'string', 'max:255'],
            'sessions.*.cottage_fee' => ['required', 'numeric', 'min:0'], 'sessions.*.children_allowed' => ['boolean'], 'sessions.*.is_active' => ['boolean'], 'sessions.*.sort_order' => ['integer', 'min:0'],
            'rules' => ['array'], 'rules.*.text' => ['required', 'string', 'max:1000'], 'rules.*.is_active' => ['boolean'], 'rules.*.is_public' => ['boolean'], 'rules.*.sort_order' => ['integer', 'min:0'],
            'fees' => ['array'], 'fees.*.name' => ['required', 'string', 'max:150'], 'fees.*.amount' => ['required', 'numeric', 'min:0'], 'fees.*.unit' => ['nullable', 'string', 'max:100'],
            'fees.*.description' => ['nullable', 'string', 'max:1000'], 'fees.*.is_active' => ['boolean'], 'fees.*.sort_order' => ['integer', 'min:0'],
            'categories' => ['array'], 'categories.*.name' => ['required', 'string', 'max:100'], 'categories.*.subtitle' => ['nullable', 'string', 'max:255'], 'categories.*.is_active' => ['boolean'], 'categories.*.sort_order' => ['integer', 'min:0'],
            'items' => ['array'], 'items.*.menu_category_id' => ['required', 'exists:menu_categories,id'], 'items.*.name' => ['required', 'string', 'max:150'], 'items.*.note' => ['nullable', 'string', 'max:255'],
            'items.*.price' => ['required', 'numeric', 'min:0'], 'items.*.is_available' => ['boolean'], 'items.*.sort_order' => ['integer', 'min:0'],
            'new_pool.name' => ['nullable', 'string', 'max:100'], 'new_pool.description' => ['nullable', 'string', 'max:1000'],
            'new_session.name' => ['nullable', 'string', 'max:100'], 'new_session.starts_at' => ['nullable', 'date_format:H:i'], 'new_session.ends_at' => ['nullable', 'date_format:H:i'],
            'new_session.adult_entrance_fee' => ['nullable', 'numeric', 'min:0'], 'new_session.child_entrance_fee' => ['nullable', 'numeric', 'min:0'], 'new_session.cottage_fee' => ['nullable', 'numeric', 'min:0'],
            'new_rule.text' => ['nullable', 'string', 'max:1000'], 'new_fee.name' => ['nullable', 'string', 'max:150'], 'new_fee.amount' => ['nullable', 'numeric', 'min:0'],
            'new_category.name' => ['nullable', 'string', 'max:100'], 'new_item.name' => ['nullable', 'string', 'max:150'], 'new_item.menu_category_id' => ['nullable', 'exists:menu_categories,id'], 'new_item.price' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $request): void {
            foreach ($data['settings'] as $key => $value) {
                ResortSetting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
            }
            foreach ($data['pools'] ?? [] as $id => $row) {
                $pool = ResortPool::query()->findOrFail($id);
                $pool->update($row);
                if ($request->hasFile("pool_images.{$id}")) {
                    $pool->update(['image_path' => $this->imageOptimizer->store($request->file("pool_images.{$id}"), 'resort-pools')]);
                }
            }
            foreach ($data['sessions'] ?? [] as $id => $row) {
                SwimmingSession::query()->findOrFail($id)->update($row);
            }
            foreach ($data['rules'] ?? [] as $id => $row) {
                ResortRule::query()->findOrFail($id)->update($row);
            }
            foreach ($data['fees'] ?? [] as $id => $row) {
                ResortFee::query()->findOrFail($id)->update($row);
            }
            foreach ($data['categories'] ?? [] as $id => $row) {
                $row['slug'] = Str::slug($row['name']);
                MenuCategory::query()->findOrFail($id)->update($row);
            }
            foreach ($data['items'] ?? [] as $id => $row) {
                MenuItem::query()->findOrFail($id)->update($row);
            }

            if (filled($data['new_pool']['name'] ?? null)) {
                ResortPool::query()->create(['name' => $data['new_pool']['name'], 'slug' => Str::slug($data['new_pool']['name']), 'description' => $data['new_pool']['description'] ?? null, 'is_active' => true]);
            }
            if (filled($data['new_session']['name'] ?? null) && filled($data['new_session']['starts_at'] ?? null) && filled($data['new_session']['ends_at'] ?? null)) {
                SwimmingSession::query()->create([
                    'name' => $data['new_session']['name'], 'slug' => Str::slug($data['new_session']['name']), 'starts_at' => $data['new_session']['starts_at'], 'ends_at' => $data['new_session']['ends_at'],
                    'adult_entrance_fee' => $data['new_session']['adult_entrance_fee'] ?? 0, 'child_entrance_fee' => $data['new_session']['child_entrance_fee'] ?? null,
                    'cottage_fee' => $data['new_session']['cottage_fee'] ?? 500, 'children_allowed' => true, 'is_active' => true, 'sort_order' => SwimmingSession::max('sort_order') + 1,
                ]);
            }
            if (filled($data['new_rule']['text'] ?? null)) {
                ResortRule::query()->create(['text' => $data['new_rule']['text'], 'is_active' => true, 'is_public' => true, 'sort_order' => ResortRule::max('sort_order') + 1]);
            }
            if (filled($data['new_fee']['name'] ?? null)) {
                ResortFee::query()->create(['name' => $data['new_fee']['name'], 'amount' => $data['new_fee']['amount'] ?? 0, 'is_active' => true, 'sort_order' => ResortFee::max('sort_order') + 1]);
            }
            if (filled($data['new_category']['name'] ?? null)) {
                MenuCategory::query()->create(['name' => $data['new_category']['name'], 'slug' => Str::slug($data['new_category']['name']), 'is_active' => true, 'sort_order' => MenuCategory::max('sort_order') + 1]);
            }
            if (filled($data['new_item']['name'] ?? null) && filled($data['new_item']['menu_category_id'] ?? null)) {
                MenuItem::query()->create(['menu_category_id' => $data['new_item']['menu_category_id'], 'name' => $data['new_item']['name'], 'price' => $data['new_item']['price'] ?? 0, 'is_available' => true]);
            }

            $fee = SwimmingSession::query()->where('is_active', true)->orderBy('sort_order')->value('cottage_fee');
            if ($fee !== null) {
                Facility::query()->whereHas('facilityType', fn ($query) => $query->where('slug', FacilityType::Cottage->value))->each(fn (Facility $cottage) => Rate::query()->updateOrCreate(
                    ['facility_id' => $cottage->id, 'type' => RateType::Base->value],
                    ['name' => 'Cottage session', 'amount' => $fee, 'unit' => 'per session', 'is_active' => true]
                ));
            }
        });

        return back()->with('success', 'Resort services updated.');
    }
}
