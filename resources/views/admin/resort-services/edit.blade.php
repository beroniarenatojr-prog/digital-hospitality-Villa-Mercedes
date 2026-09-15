<x-admin-layout>
    <x-slot name="header">Resort Services</x-slot>

    <div class="max-w-6xl">
        <x-alert type="success" :message="session('success')" class="mb-6" />
        @if($errors->any())<div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <form method="POST" action="{{ route('admin.resort-services.update') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf @method('PUT')

            <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold text-gray-900">Café information</h2>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <label class="text-sm font-medium text-gray-700">Opening hours<input name="settings[cafe_hours]" value="{{ old('settings.cafe_hours', $settings['cafe_hours'] ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300" required></label>
                    <label class="text-sm font-medium text-gray-700">Viber number<input name="settings[cafe_viber]" value="{{ old('settings.cafe_viber', $settings['cafe_viber'] ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300" required></label>
                    <label class="text-sm font-medium text-gray-700">Service charge (%)<input name="settings[cafe_service_charge]" type="number" step="0.01" min="0" value="{{ old('settings.cafe_service_charge', $settings['cafe_service_charge'] ?? 3) }}" class="mt-1 block w-full rounded-md border-gray-300" required></label>
                    <label class="text-sm font-medium text-gray-700 md:col-span-2">Outside-food notice<textarea name="settings[cafe_outside_food_notice]" rows="2" class="mt-1 block w-full rounded-md border-gray-300" required>{{ old('settings.cafe_outside_food_notice', $settings['cafe_outside_food_notice'] ?? '') }}</textarea></label>
                </div>
            </section>

            <details open class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <summary class="cursor-pointer text-xl font-semibold text-gray-900">Pools</summary>
                <div class="mt-5 space-y-5">
                    @foreach($pools as $pool)
                        <fieldset class="grid gap-3 rounded-lg border border-gray-200 p-4 md:grid-cols-2">
                            <legend class="px-2 font-semibold">{{ $pool->name }}</legend>
                            <label class="text-sm">Name<input name="pools[{{ $pool->id }}][name]" value="{{ old("pools.{$pool->id}.name", $pool->name) }}" class="mt-1 block w-full rounded-md border-gray-300" required></label>
                            <label class="text-sm">Display order<input name="pools[{{ $pool->id }}][sort_order]" type="number" min="0" value="{{ $pool->sort_order }}" class="mt-1 block w-full rounded-md border-gray-300" required></label>
                            <label class="text-sm md:col-span-2">Description<textarea name="pools[{{ $pool->id }}][description]" rows="2" class="mt-1 block w-full rounded-md border-gray-300">{{ old("pools.{$pool->id}.description", $pool->description) }}</textarea></label>
                            <label class="text-sm md:col-span-2">Audience note<input name="pools[{{ $pool->id }}][audience_note]" value="{{ old("pools.{$pool->id}.audience_note", $pool->audience_note) }}" class="mt-1 block w-full rounded-md border-gray-300"></label>
                            <label class="text-sm">Replace photo<input name="pool_images[{{ $pool->id }}]" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full"></label>
                            <div class="flex gap-6 items-center"><input type="hidden" name="pools[{{ $pool->id }}][is_active]" value="0"><label class="text-sm"><input type="checkbox" name="pools[{{ $pool->id }}][is_active]" value="1" @checked($pool->is_active)> Active</label><input type="hidden" name="pools[{{ $pool->id }}][is_package_only]" value="0"><label class="text-sm"><input type="checkbox" name="pools[{{ $pool->id }}][is_package_only]" value="1" @checked($pool->is_package_only)> Package only</label></div>
                        </fieldset>
                    @endforeach
                    <div class="grid gap-3 rounded-lg border border-dashed border-gray-300 p-4 md:grid-cols-2"><p class="font-semibold md:col-span-2">Add a pool</p><input name="new_pool[name]" placeholder="Pool name" class="rounded-md border-gray-300"><input name="new_pool[description]" placeholder="Description" class="rounded-md border-gray-300"></div>
                </div>
            </details>

            <details open class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <summary class="cursor-pointer text-xl font-semibold text-gray-900">Swimming sessions and cottage price</summary>
                <p class="mt-2 text-sm text-gray-500">Session times control cottage availability. Pool entrance is paid onsite; the cottage fee becomes the reservation price.</p>
                <div class="mt-5 space-y-4">
                    @foreach($sessions as $session)
                        <fieldset class="grid gap-3 rounded-lg border border-gray-200 p-4 md:grid-cols-4">
                            <legend class="px-2 font-semibold">{{ $session->name }}</legend>
                            <label class="text-sm">Name<input name="sessions[{{ $session->id }}][name]" value="{{ $session->name }}" class="mt-1 block w-full rounded-md border-gray-300" required></label>
                            <label class="text-sm">Starts<input name="sessions[{{ $session->id }}][starts_at]" type="time" value="{{ substr($session->starts_at, 0, 5) }}" class="mt-1 block w-full rounded-md border-gray-300" required></label>
                            <label class="text-sm">Ends<input name="sessions[{{ $session->id }}][ends_at]" type="time" value="{{ substr($session->ends_at, 0, 5) }}" class="mt-1 block w-full rounded-md border-gray-300" required></label>
                            <label class="text-sm">Order<input name="sessions[{{ $session->id }}][sort_order]" type="number" min="0" value="{{ $session->sort_order }}" class="mt-1 block w-full rounded-md border-gray-300" required></label>
                            <label class="text-sm">Adult entrance<input name="sessions[{{ $session->id }}][adult_entrance_fee]" type="number" min="0" step="0.01" value="{{ $session->adult_entrance_fee }}" class="mt-1 block w-full rounded-md border-gray-300" required></label>
                            <label class="text-sm">Child entrance<input name="sessions[{{ $session->id }}][child_entrance_fee]" type="number" min="0" step="0.01" value="{{ $session->child_entrance_fee }}" class="mt-1 block w-full rounded-md border-gray-300"></label>
                            <label class="text-sm">Cottage fee<input name="sessions[{{ $session->id }}][cottage_fee]" type="number" min="0" step="0.01" value="{{ $session->cottage_fee }}" class="mt-1 block w-full rounded-md border-gray-300" required></label>
                            <label class="text-sm">Child qualifier<input name="sessions[{{ $session->id }}][child_qualifier]" value="{{ $session->child_qualifier }}" class="mt-1 block w-full rounded-md border-gray-300"></label>
                            <div class="flex gap-6 md:col-span-4"><input type="hidden" name="sessions[{{ $session->id }}][children_allowed]" value="0"><label class="text-sm"><input type="checkbox" name="sessions[{{ $session->id }}][children_allowed]" value="1" @checked($session->children_allowed)> Children may swim</label><input type="hidden" name="sessions[{{ $session->id }}][is_active]" value="0"><label class="text-sm"><input type="checkbox" name="sessions[{{ $session->id }}][is_active]" value="1" @checked($session->is_active)> Active</label></div>
                        </fieldset>
                    @endforeach
                    <div class="grid gap-3 rounded-lg border border-dashed border-gray-300 p-4 md:grid-cols-4"><p class="font-semibold md:col-span-4">Add a session</p><input name="new_session[name]" placeholder="Session name" class="rounded-md border-gray-300"><input name="new_session[starts_at]" type="time" class="rounded-md border-gray-300"><input name="new_session[ends_at]" type="time" class="rounded-md border-gray-300"><input name="new_session[adult_entrance_fee]" type="number" min="0" step="0.01" placeholder="Adult fee" class="rounded-md border-gray-300"><input name="new_session[child_entrance_fee]" type="number" min="0" step="0.01" placeholder="Child fee" class="rounded-md border-gray-300"><input name="new_session[cottage_fee]" type="number" min="0" step="0.01" value="500" placeholder="Cottage fee" class="rounded-md border-gray-300"></div>
                </div>
            </details>

            <details class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <summary class="cursor-pointer text-xl font-semibold text-gray-900">Pool rules</summary>
                <div class="mt-5 space-y-3">@foreach($rules as $rule)<div class="grid gap-3 md:grid-cols-[1fr_7rem_auto_auto]"><textarea name="rules[{{ $rule->id }}][text]" rows="2" class="rounded-md border-gray-300" required>{{ $rule->text }}</textarea><input name="rules[{{ $rule->id }}][sort_order]" type="number" min="0" value="{{ $rule->sort_order }}" class="rounded-md border-gray-300"><span><input type="hidden" name="rules[{{ $rule->id }}][is_active]" value="0"><label class="text-sm"><input type="checkbox" name="rules[{{ $rule->id }}][is_active]" value="1" @checked($rule->is_active)> Active</label></span><span><input type="hidden" name="rules[{{ $rule->id }}][is_public]" value="0"><label class="text-sm"><input type="checkbox" name="rules[{{ $rule->id }}][is_public]" value="1" @checked($rule->is_public)> Public</label></span></div>@endforeach<textarea name="new_rule[text]" rows="2" placeholder="Add a rule" class="block w-full rounded-md border-dashed border-gray-300"></textarea></div>
            </details>

            <details class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <summary class="cursor-pointer text-xl font-semibold text-gray-900">Corkage and fees</summary>
                <div class="mt-5 space-y-3">@foreach($fees as $fee)<div class="grid gap-3 rounded-lg border border-gray-200 p-3 md:grid-cols-5"><input name="fees[{{ $fee->id }}][name]" value="{{ $fee->name }}" class="rounded-md border-gray-300" required><input name="fees[{{ $fee->id }}][amount]" type="number" min="0" step="0.01" value="{{ $fee->amount }}" class="rounded-md border-gray-300" required><input name="fees[{{ $fee->id }}][unit]" value="{{ $fee->unit }}" class="rounded-md border-gray-300"><input name="fees[{{ $fee->id }}][description]" value="{{ $fee->description }}" class="rounded-md border-gray-300"><span><input name="fees[{{ $fee->id }}][sort_order]" type="hidden" value="{{ $fee->sort_order }}"><input type="hidden" name="fees[{{ $fee->id }}][is_active]" value="0"><label class="text-sm"><input type="checkbox" name="fees[{{ $fee->id }}][is_active]" value="1" @checked($fee->is_active)> Active</label></span></div>@endforeach<div class="grid gap-3 md:grid-cols-2"><input name="new_fee[name]" placeholder="Add fee" class="rounded-md border-dashed border-gray-300"><input name="new_fee[amount]" type="number" min="0" step="0.01" placeholder="Amount" class="rounded-md border-dashed border-gray-300"></div></div>
            </details>

            <details class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <summary class="cursor-pointer text-xl font-semibold text-gray-900">Menu</summary>
                <div class="mt-5 space-y-5">
                    <div class="space-y-3">@foreach($categories as $category)<div class="grid gap-3 md:grid-cols-[1fr_1fr_7rem_auto]"><input name="categories[{{ $category->id }}][name]" value="{{ $category->name }}" class="rounded-md border-gray-300" required><input name="categories[{{ $category->id }}][subtitle]" value="{{ $category->subtitle }}" class="rounded-md border-gray-300"><input name="categories[{{ $category->id }}][sort_order]" type="number" min="0" value="{{ $category->sort_order }}" class="rounded-md border-gray-300"><span><input type="hidden" name="categories[{{ $category->id }}][is_active]" value="0"><label class="text-sm"><input type="checkbox" name="categories[{{ $category->id }}][is_active]" value="1" @checked($category->is_active)> Active</label></span></div>@endforeach<input name="new_category[name]" placeholder="Add category" class="rounded-md border-dashed border-gray-300"></div>
                    <div class="space-y-3 border-t border-gray-200 pt-5">@foreach($items as $item)<div class="grid gap-3 rounded-lg border border-gray-200 p-3 md:grid-cols-5"><select name="items[{{ $item->id }}][menu_category_id]" class="rounded-md border-gray-300">@foreach($categories as $category)<option value="{{ $category->id }}" @selected($item->menu_category_id === $category->id)>{{ $category->name }}</option>@endforeach</select><input name="items[{{ $item->id }}][name]" value="{{ $item->name }}" class="rounded-md border-gray-300" required><input name="items[{{ $item->id }}][note]" value="{{ $item->note }}" class="rounded-md border-gray-300"><input name="items[{{ $item->id }}][price]" type="number" min="0" step="0.01" value="{{ $item->price }}" class="rounded-md border-gray-300"><span><input name="items[{{ $item->id }}][sort_order]" type="hidden" value="{{ $item->sort_order }}"><input type="hidden" name="items[{{ $item->id }}][is_available]" value="0"><label class="text-sm"><input type="checkbox" name="items[{{ $item->id }}][is_available]" value="1" @checked($item->is_available)> Available</label></span></div>@endforeach<div class="grid gap-3 md:grid-cols-3"><select name="new_item[menu_category_id]" class="rounded-md border-dashed border-gray-300"><option value="">Add item to…</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select><input name="new_item[name]" placeholder="Item name" class="rounded-md border-dashed border-gray-300"><input name="new_item[price]" type="number" min="0" step="0.01" placeholder="Price" class="rounded-md border-dashed border-gray-300"></div></div>
                </div>
            </details>

            <div class="sticky bottom-4 flex justify-end"><button type="submit" class="rounded-lg bg-primary px-6 py-3 font-semibold text-white shadow-lg hover:bg-primary-dark">Save resort services</button></div>
        </form>
    </div>
</x-admin-layout>
