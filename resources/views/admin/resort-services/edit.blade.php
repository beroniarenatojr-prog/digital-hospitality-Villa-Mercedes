<x-admin-layout>
    <x-slot name="header">Resort services</x-slot>

    <x-admin.page-header
        title="Resort services"
    />

    <form method="POST" action="{{ route('admin.resort-services.update') }}" enctype="multipart/form-data" class="max-w-5xl space-y-6">
        @csrf
        @method('PUT')

        @if ($errors->any())
            <x-admin.notice variant="danger" title="Some details need fixing" :dismissible="false">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-admin.notice>
        @endif

        <x-admin.panel title="Café information" description="Contact details guests use to reach the café, and the service charge added to café orders.">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="cafe_hours" class="admin-label">Opening hours <span class="text-danger" aria-hidden="true">*</span></label>
                    <input id="cafe_hours" name="settings[cafe_hours]" value="{{ old('settings.cafe_hours', $settings['cafe_hours'] ?? '') }}" required aria-required="true"
                           class="admin-input mt-1">
                </div>

                <div>
                    <label for="cafe_viber" class="admin-label">Viber number <span class="text-danger" aria-hidden="true">*</span></label>
                    <input id="cafe_viber" name="settings[cafe_viber]" value="{{ old('settings.cafe_viber', $settings['cafe_viber'] ?? '') }}" required aria-required="true"
                           inputmode="tel" class="admin-input mt-1">
                </div>

                <div>
                    <label for="cafe_service_charge" class="admin-label">Service charge (%) <span class="text-danger" aria-hidden="true">*</span></label>
                    <input id="cafe_service_charge" name="settings[cafe_service_charge]" type="number" step="0.01" min="0"
                           value="{{ old('settings.cafe_service_charge', $settings['cafe_service_charge'] ?? 3) }}" required aria-required="true"
                           class="admin-input mt-1">
                    <p class="admin-help">Added to every café order at checkout.</p>
                </div>

                <div class="sm:col-span-2">
                    <label for="cafe_outside_food_notice" class="admin-label">Outside-food notice <span class="text-danger" aria-hidden="true">*</span></label>
                    <textarea id="cafe_outside_food_notice" name="settings[cafe_outside_food_notice]" rows="2" required aria-required="true"
                              class="admin-input mt-1">{{ old('settings.cafe_outside_food_notice', $settings['cafe_outside_food_notice'] ?? '') }}</textarea>
                    <p class="admin-help">Shown to guests on the public page. Keep it identical to the rule staff enforce at the door.</p>
                </div>
            </div>
        </x-admin.panel>

        <x-admin.panel title="Pools" :badge="$pools->count()" description="Each pool gets its own description and audience note. Order controls how they appear on the public page.">
            <div class="space-y-5">
                @foreach ($pools as $pool)
                    <fieldset class="rounded-xl border border-border p-4">
                        <legend class="px-2 text-sm font-semibold text-text">{{ $pool->name }}</legend>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label for="pool-name-{{ $pool->id }}" class="admin-label">Name <span class="text-danger" aria-hidden="true">*</span></label>
                                <input id="pool-name-{{ $pool->id }}" name="pools[{{ $pool->id }}][name]" value="{{ old("pools.{$pool->id}.name", $pool->name) }}" required aria-required="true"
                                       class="admin-input mt-1">
                            </div>

                            <div>
                                <label for="pool-order-{{ $pool->id }}" class="admin-label">Display order <span class="text-danger" aria-hidden="true">*</span></label>
                                <input id="pool-order-{{ $pool->id }}" name="pools[{{ $pool->id }}][sort_order]" type="number" min="0" value="{{ $pool->sort_order }}" required aria-required="true"
                                       class="admin-input mt-1">
                            </div>

                            <div class="sm:col-span-2">
                                <label for="pool-description-{{ $pool->id }}" class="admin-label">Description</label>
                                <textarea id="pool-description-{{ $pool->id }}" name="pools[{{ $pool->id }}][description]" rows="2"
                                          class="admin-input mt-1">{{ old("pools.{$pool->id}.description", $pool->description) }}</textarea>
                            </div>

                            <div class="sm:col-span-2">
                                <label for="pool-audience-{{ $pool->id }}" class="admin-label">Audience note</label>
                                <input id="pool-audience-{{ $pool->id }}" name="pools[{{ $pool->id }}][audience_note]" value="{{ old("pools.{$pool->id}.audience_note", $pool->audience_note) }}"
                                       class="admin-input mt-1">
                                <p class="admin-help">For example which guests a pool suits, or a shallow-depth note.</p>
                            </div>

                            <div>
                                <label for="pool-photo-{{ $pool->id }}" class="admin-label">Replace photo</label>
                                <input id="pool-photo-{{ $pool->id }}" name="pool_images[{{ $pool->id }}]" type="file" accept="image/jpeg,image/png,image/webp"
                                       class="mt-1 block w-full text-sm text-text-muted file:mr-4 file:rounded-lg file:border-0 file:bg-primary-light file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary-light/80">
                            </div>

                            <div class="flex flex-wrap items-center gap-5 sm:items-end">
                                <div class="flex items-center gap-2">
                                    <input type="hidden" name="pools[{{ $pool->id }}][is_active]" value="0">
                                    <input type="checkbox" id="pool-active-{{ $pool->id }}" name="pools[{{ $pool->id }}][is_active]" value="1" @checked($pool->is_active)
                                           class="h-4 w-4 rounded border-border text-primary focus:ring-primary">
                                    <label for="pool-active-{{ $pool->id }}" class="text-sm text-text">Active</label>
                                </div>

                                <div class="flex items-center gap-2">
                                    <input type="hidden" name="pools[{{ $pool->id }}][is_package_only]" value="0">
                                    <input type="checkbox" id="pool-package-{{ $pool->id }}" name="pools[{{ $pool->id }}][is_package_only]" value="1" @checked($pool->is_package_only)
                                           class="h-4 w-4 rounded border-border text-primary focus:ring-primary">
                                    <label for="pool-package-{{ $pool->id }}" class="text-sm text-text">Package only</label>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                @endforeach

                <fieldset class="rounded-xl border border-dashed border-border p-4">
                    <legend class="px-2 text-sm font-semibold text-text">Add a pool</legend>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label for="new_pool_name" class="admin-label">Pool name</label>
                            <input id="new_pool_name" name="new_pool[name]" placeholder="Kiddie Pool" class="admin-input mt-1">
                        </div>
                        <div>
                            <label for="new_pool_description" class="admin-label">Description</label>
                            <input id="new_pool_description" name="new_pool[description]" placeholder="Shallow pool for young children" class="admin-input mt-1">
                        </div>
                    </div>
                    <p class="admin-help">Leave blank to add nothing. A new pool starts active.</p>
                </fieldset>
            </div>
        </x-admin.panel>

        <x-admin.panel
            title="Swimming sessions and cottage price"
            :badge="$sessions->count()"
            description="Session times control cottage availability. Pool entrance is paid at the entrance; the cottage fee becomes the reservation price."
        >
            <div class="space-y-4">
                @foreach ($sessions as $session)
                    <fieldset class="rounded-xl border border-border p-4">
                        <legend class="px-2 text-sm font-semibold text-text">{{ $session->name }}</legend>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <label for="session-name-{{ $session->id }}" class="admin-label">Name <span class="text-danger" aria-hidden="true">*</span></label>
                                <input id="session-name-{{ $session->id }}" name="sessions[{{ $session->id }}][name]" value="{{ $session->name }}" required aria-required="true" class="admin-input mt-1">
                            </div>

                            <div>
                                <label for="session-starts-{{ $session->id }}" class="admin-label">Starts <span class="text-danger" aria-hidden="true">*</span></label>
                                <input id="session-starts-{{ $session->id }}" name="sessions[{{ $session->id }}][starts_at]" type="time" value="{{ substr($session->starts_at, 0, 5) }}" required aria-required="true" class="admin-input mt-1">
                            </div>

                            <div>
                                <label for="session-ends-{{ $session->id }}" class="admin-label">Ends <span class="text-danger" aria-hidden="true">*</span></label>
                                <input id="session-ends-{{ $session->id }}" name="sessions[{{ $session->id }}][ends_at]" type="time" value="{{ substr($session->ends_at, 0, 5) }}" required aria-required="true" class="admin-input mt-1">
                            </div>

                            <div>
                                <label for="session-order-{{ $session->id }}" class="admin-label">Order <span class="text-danger" aria-hidden="true">*</span></label>
                                <input id="session-order-{{ $session->id }}" name="sessions[{{ $session->id }}][sort_order]" type="number" min="0" value="{{ $session->sort_order }}" required aria-required="true" class="admin-input mt-1">
                            </div>

                            <div>
                                <label for="session-adult-{{ $session->id }}" class="admin-label">Adult entrance (₱) <span class="text-danger" aria-hidden="true">*</span></label>
                                <input id="session-adult-{{ $session->id }}" name="sessions[{{ $session->id }}][adult_entrance_fee]" type="number" min="0" step="0.01" value="{{ $session->adult_entrance_fee }}" required aria-required="true" class="admin-input mt-1">
                            </div>

                            <div>
                                <label for="session-child-{{ $session->id }}" class="admin-label">Child entrance (₱)</label>
                                <input id="session-child-{{ $session->id }}" name="sessions[{{ $session->id }}][child_entrance_fee]" type="number" min="0" step="0.01" value="{{ $session->child_entrance_fee }}" class="admin-input mt-1">
                            </div>

                            <div>
                                <label for="session-cottage-{{ $session->id }}" class="admin-label">Cottage fee (₱) <span class="text-danger" aria-hidden="true">*</span></label>
                                <input id="session-cottage-{{ $session->id }}" name="sessions[{{ $session->id }}][cottage_fee]" type="number" min="0" step="0.01" value="{{ $session->cottage_fee }}" required aria-required="true" class="admin-input mt-1">
                                <p class="admin-help">This is what a guest pays to book a cottage for the session.</p>
                            </div>

                            <div>
                                <label for="session-qualifier-{{ $session->id }}" class="admin-label">Child qualifier</label>
                                <input id="session-qualifier-{{ $session->id }}" name="sessions[{{ $session->id }}][child_qualifier]" value="{{ $session->child_qualifier }}" class="admin-input mt-1">
                                <p class="admin-help">Who counts as a child, e.g. “4 feet and below”.</p>
                            </div>

                            <div class="flex flex-wrap items-center gap-5 sm:col-span-2 lg:col-span-4">
                                <div class="flex items-center gap-2">
                                    <input type="hidden" name="sessions[{{ $session->id }}][children_allowed]" value="0">
                                    <input type="checkbox" id="session-children-{{ $session->id }}" name="sessions[{{ $session->id }}][children_allowed]" value="1" @checked($session->children_allowed)
                                           class="h-4 w-4 rounded border-border text-primary focus:ring-primary">
                                    <label for="session-children-{{ $session->id }}" class="text-sm text-text">Children may swim</label>
                                </div>

                                <div class="flex items-center gap-2">
                                    <input type="hidden" name="sessions[{{ $session->id }}][is_active]" value="0">
                                    <input type="checkbox" id="session-active-{{ $session->id }}" name="sessions[{{ $session->id }}][is_active]" value="1" @checked($session->is_active)
                                           class="h-4 w-4 rounded border-border text-primary focus:ring-primary">
                                    <label for="session-active-{{ $session->id }}" class="text-sm text-text">Active</label>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                @endforeach

                <fieldset class="rounded-xl border border-dashed border-border p-4">
                    <legend class="px-2 text-sm font-semibold text-text">Add a session</legend>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label for="new_session_name" class="admin-label">Session name</label>
                            <input id="new_session_name" name="new_session[name]" placeholder="Evening Swim" class="admin-input mt-1">
                        </div>
                        <div>
                            <label for="new_session_starts" class="admin-label">Starts</label>
                            <input id="new_session_starts" name="new_session[starts_at]" type="time" class="admin-input mt-1">
                        </div>
                        <div>
                            <label for="new_session_ends" class="admin-label">Ends</label>
                            <input id="new_session_ends" name="new_session[ends_at]" type="time" class="admin-input mt-1">
                        </div>
                        <div>
                            <label for="new_session_adult" class="admin-label">Adult entrance (₱)</label>
                            <input id="new_session_adult" name="new_session[adult_entrance_fee]" type="number" min="0" step="0.01" class="admin-input mt-1">
                        </div>
                        <div>
                            <label for="new_session_child" class="admin-label">Child entrance (₱)</label>
                            <input id="new_session_child" name="new_session[child_entrance_fee]" type="number" min="0" step="0.01" class="admin-input mt-1">
                        </div>
                        <div>
                            <label for="new_session_cottage" class="admin-label">Cottage fee (₱)</label>
                            <input id="new_session_cottage" name="new_session[cottage_fee]" type="number" min="0" step="0.01" value="500" class="admin-input mt-1">
                        </div>
                    </div>
                    <p class="admin-help">Leave the name blank to add nothing.</p>
                </fieldset>
            </div>
        </x-admin.panel>

        <x-admin.panel title="Pool rules" :badge="$rules->count()" description="Only rules marked Public are printed on the guest page. Deactivating a rule keeps it here as a record without showing it to guests.">
            <div class="space-y-4">
                @foreach ($rules as $rule)
                    <fieldset class="rounded-xl border border-border p-4">
                        <legend class="px-2 text-sm font-semibold text-text">Rule {{ $loop->iteration }}</legend>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_7rem]">
                            <div>
                                <label for="rule-text-{{ $rule->id }}" class="admin-label">Rule <span class="text-danger" aria-hidden="true">*</span></label>
                                <textarea id="rule-text-{{ $rule->id }}" name="rules[{{ $rule->id }}][text]" rows="2" required aria-required="true"
                                          class="admin-input mt-1">{{ $rule->text }}</textarea>
                            </div>

                            <div>
                                <label for="rule-order-{{ $rule->id }}" class="admin-label">Order</label>
                                <input id="rule-order-{{ $rule->id }}" name="rules[{{ $rule->id }}][sort_order]" type="number" min="0" value="{{ $rule->sort_order }}" class="admin-input mt-1">
                            </div>

                            <div class="flex flex-wrap items-center gap-5 sm:col-span-2">
                                <div class="flex items-center gap-2">
                                    <input type="hidden" name="rules[{{ $rule->id }}][is_active]" value="0">
                                    <input type="checkbox" id="rule-active-{{ $rule->id }}" name="rules[{{ $rule->id }}][is_active]" value="1" @checked($rule->is_active)
                                           class="h-4 w-4 rounded border-border text-primary focus:ring-primary">
                                    <label for="rule-active-{{ $rule->id }}" class="text-sm text-text">Active</label>
                                </div>

                                <div class="flex items-center gap-2">
                                    <input type="hidden" name="rules[{{ $rule->id }}][is_public]" value="0">
                                    <input type="checkbox" id="rule-public-{{ $rule->id }}" name="rules[{{ $rule->id }}][is_public]" value="1" @checked($rule->is_public)
                                           class="h-4 w-4 rounded border-border text-primary focus:ring-primary">
                                    <label for="rule-public-{{ $rule->id }}" class="text-sm text-text">Public</label>
                                </div>
                            </div>
                        </div>
                    </fieldset>
                @endforeach

                <div>
                    <label for="new_rule_text" class="admin-label">Add a rule</label>
                    <textarea id="new_rule_text" name="new_rule[text]" rows="2" placeholder="e.g. No diving in the shallow end."
                              class="admin-input mt-1 border-dashed"></textarea>
                </div>
            </div>
        </x-admin.panel>

        <x-admin.panel title="Corkage and fees" :badge="$fees->count()" description="Charges that are not part of a booking, such as corkage or rental of extra equipment.">
            <div class="space-y-4">
                @foreach ($fees as $fee)
                    <fieldset class="rounded-xl border border-border p-4">
                        <legend class="px-2 text-sm font-semibold text-text">{{ $fee->name }}</legend>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <label for="fee-name-{{ $fee->id }}" class="admin-label">Name <span class="text-danger" aria-hidden="true">*</span></label>
                                <input id="fee-name-{{ $fee->id }}" name="fees[{{ $fee->id }}][name]" value="{{ $fee->name }}" required aria-required="true" class="admin-input mt-1">
                            </div>
                            <div>
                                <label for="fee-amount-{{ $fee->id }}" class="admin-label">Amount (₱) <span class="text-danger" aria-hidden="true">*</span></label>
                                <input id="fee-amount-{{ $fee->id }}" name="fees[{{ $fee->id }}][amount]" type="number" min="0" step="0.01" value="{{ $fee->amount }}" required aria-required="true" class="admin-input mt-1">
                            </div>
                            <div>
                                <label for="fee-unit-{{ $fee->id }}" class="admin-label">Unit</label>
                                <input id="fee-unit-{{ $fee->id }}" name="fees[{{ $fee->id }}][unit]" value="{{ $fee->unit }}" class="admin-input mt-1">
                            </div>
                            <div>
                                <label for="fee-description-{{ $fee->id }}" class="admin-label">Description</label>
                                <input id="fee-description-{{ $fee->id }}" name="fees[{{ $fee->id }}][description]" value="{{ $fee->description }}" class="admin-input mt-1">
                            </div>
                        </div>

                        <div class="mt-3 flex items-center gap-2">
                            <input type="hidden" name="fees[{{ $fee->id }}][sort_order]" value="{{ $fee->sort_order }}">
                            <input type="hidden" name="fees[{{ $fee->id }}][is_active]" value="0">
                            <input type="checkbox" id="fee-active-{{ $fee->id }}" name="fees[{{ $fee->id }}][is_active]" value="1" @checked($fee->is_active)
                                   class="h-4 w-4 rounded border-border text-primary focus:ring-primary">
                            <label for="fee-active-{{ $fee->id }}" class="text-sm text-text">Active</label>
                        </div>
                    </fieldset>
                @endforeach

                <fieldset class="rounded-xl border border-dashed border-border p-4">
                    <legend class="px-2 text-sm font-semibold text-text">Add a fee</legend>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label for="new_fee_name" class="admin-label">Fee name</label>
                            <input id="new_fee_name" name="new_fee[name]" placeholder="Corkage per bottle" class="admin-input mt-1">
                        </div>
                        <div>
                            <label for="new_fee_amount" class="admin-label">Amount (₱)</label>
                            <input id="new_fee_amount" name="new_fee[amount]" type="number" min="0" step="0.01" class="admin-input mt-1">
                        </div>
                    </div>
                </fieldset>
            </div>
        </x-admin.panel>

        <x-admin.panel title="Menu" :badge="$items->count()" description="Café menu categories and items. Prices are what guests see; the service charge is added separately at checkout.">
            <div class="space-y-6">
                <div class="space-y-4">
                    <h3 class="text-sm font-semibold text-text">Categories</h3>

                    @foreach ($categories as $category)
                        <fieldset class="rounded-xl border border-border p-4">
                            <legend class="px-2 text-sm font-semibold text-text">{{ $category->name }}</legend>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_1fr_7rem]">
                                <div>
                                    <label for="category-name-{{ $category->id }}" class="admin-label">Name <span class="text-danger" aria-hidden="true">*</span></label>
                                    <input id="category-name-{{ $category->id }}" name="categories[{{ $category->id }}][name]" value="{{ $category->name }}" required aria-required="true" class="admin-input mt-1">
                                </div>
                                <div>
                                    <label for="category-subtitle-{{ $category->id }}" class="admin-label">Subtitle</label>
                                    <input id="category-subtitle-{{ $category->id }}" name="categories[{{ $category->id }}][subtitle]" value="{{ $category->subtitle }}" class="admin-input mt-1">
                                </div>
                                <div>
                                    <label for="category-order-{{ $category->id }}" class="admin-label">Order</label>
                                    <input id="category-order-{{ $category->id }}" name="categories[{{ $category->id }}][sort_order]" type="number" min="0" value="{{ $category->sort_order }}" class="admin-input mt-1">
                                </div>
                            </div>

                            <div class="mt-3 flex items-center gap-2">
                                <input type="hidden" name="categories[{{ $category->id }}][is_active]" value="0">
                                <input type="checkbox" id="category-active-{{ $category->id }}" name="categories[{{ $category->id }}][is_active]" value="1" @checked($category->is_active)
                                       class="h-4 w-4 rounded border-border text-primary focus:ring-primary">
                                <label for="category-active-{{ $category->id }}" class="text-sm text-text">Active</label>
                            </div>
                        </fieldset>
                    @endforeach

                    <div>
                        <label for="new_category_name" class="admin-label">Add a category</label>
                        <input id="new_category_name" name="new_category[name]" placeholder="e.g. Rice meals" class="admin-input mt-1 border-dashed">
                    </div>
                </div>

                <div class="space-y-4 border-t border-border pt-5">
                    <h3 class="text-sm font-semibold text-text">Items</h3>

                    @foreach ($items as $item)
                        <fieldset class="rounded-xl border border-border p-4">
                            <legend class="px-2 text-sm font-semibold text-text">{{ $item->name }}</legend>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <label for="item-category-{{ $item->id }}" class="admin-label">Category</label>
                                    <select id="item-category-{{ $item->id }}" name="items[{{ $item->id }}][menu_category_id]" class="admin-input mt-1">
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" @selected($item->menu_category_id === $category->id)>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="item-name-{{ $item->id }}" class="admin-label">Item <span class="text-danger" aria-hidden="true">*</span></label>
                                    <input id="item-name-{{ $item->id }}" name="items[{{ $item->id }}][name]" value="{{ $item->name }}" required aria-required="true" class="admin-input mt-1">
                                </div>
                                <div>
                                    <label for="item-note-{{ $item->id }}" class="admin-label">Note</label>
                                    <input id="item-note-{{ $item->id }}" name="items[{{ $item->id }}][note]" value="{{ $item->note }}" class="admin-input mt-1">
                                </div>
                                <div>
                                    <label for="item-price-{{ $item->id }}" class="admin-label">Price (₱)</label>
                                    <input id="item-price-{{ $item->id }}" name="items[{{ $item->id }}][price]" type="number" min="0" step="0.01" value="{{ $item->price }}" class="admin-input mt-1">
                                </div>
                            </div>

                            <div class="mt-3 flex items-center gap-2">
                                <input type="hidden" name="items[{{ $item->id }}][sort_order]" value="{{ $item->sort_order }}">
                                <input type="hidden" name="items[{{ $item->id }}][is_available]" value="0">
                                <input type="checkbox" id="item-available-{{ $item->id }}" name="items[{{ $item->id }}][is_available]" value="1" @checked($item->is_available)
                                       class="h-4 w-4 rounded border-border text-primary focus:ring-primary">
                                <label for="item-available-{{ $item->id }}" class="text-sm text-text">Available — untick when the kitchen runs out</label>
                            </div>
                        </fieldset>
                    @endforeach

                    <fieldset class="rounded-xl border border-dashed border-border p-4">
                        <legend class="px-2 text-sm font-semibold text-text">Add an item</legend>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <label for="new_item_category" class="admin-label">Category</label>
                                <select id="new_item_category" name="new_item[menu_category_id]" class="admin-input mt-1">
                                    <option value="">Select a category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="new_item_name" class="admin-label">Item name</label>
                                <input id="new_item_name" name="new_item[name]" placeholder="Ensaladang Talong" class="admin-input mt-1">
                            </div>
                            <div>
                                <label for="new_item_price" class="admin-label">Price (₱)</label>
                                <input id="new_item_price" name="new_item[price]" type="number" min="0" step="0.01" class="admin-input mt-1">
                            </div>
                        </div>
                    </fieldset>
                </div>
            </div>
        </x-admin.panel>

        <div class="sticky bottom-4 flex flex-wrap items-center justify-end gap-2 rounded-xl border border-border bg-surface/95 p-3 backdrop-blur">
            <span class="mr-auto text-sm text-text-muted">Changes are published to the guest page as soon as you save.</span>
            <x-admin.button type="submit">Save resort services</x-admin.button>
        </div>
    </form>
</x-admin-layout>
