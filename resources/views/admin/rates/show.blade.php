<x-admin-layout>
    <x-slot name="header">
        {{ $rate->name }}
    </x-slot>

    <!-- Breadcrumb -->
    <nav class="mb-6 text-sm text-gray-500">
        <a href="{{ route('admin.rates.index') }}" class="hover:text-primary">Rates</a>
        <span class="mx-2">/</span>
        <span class="text-gray-900">{{ $rate->name }}</span>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Info Column -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Rate Info Card -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-900">Rate Information</h2>
                    <a href="{{ route('admin.rates.edit', $rate) }}" class="text-sm font-medium text-primary hover:text-primary-dark">Edit</a>
                </div>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Rate Name</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-semibold">{{ $rate->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Facility</dt>
                        <dd class="mt-1">
                            <a href="{{ route('admin.facilities.show', $rate->facility) }}" class="text-sm text-primary hover:text-primary-dark">
                                {{ $rate->facility->name }}
                            </a>
                            <span class="block text-xs text-gray-400">{{ $rate->facility->facilityType->name ?? '' }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Rate Type</dt>
                        <dd class="mt-1">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium
                                {{ $rate->type === \App\Enums\RateType::Base ? 'bg-blue-100 text-blue-800' : '' }}
                                {{ $rate->type === \App\Enums\RateType::Seasonal ? 'bg-amber-100 text-amber-800' : '' }}
                                {{ $rate->type === \App\Enums\RateType::AdditionalCharge ? 'bg-purple-100 text-purple-800' : '' }}
                                {{ $rate->type === \App\Enums\RateType::ExtraPerson ? 'bg-teal-100 text-teal-800' : '' }}
                            ">
                                {{ $rate->type->label() }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Amount</dt>
                        <dd class="mt-1 text-lg font-bold text-gray-900">₱{{ number_format($rate->amount, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Unit</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ str_replace('_', ' ', $rate->unit) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="mt-1">
                            @if($rate->is_active)
                                <span class="inline-flex items-center text-success text-sm font-medium">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    Active
                                </span>
                            @else
                                <span class="inline-flex items-center text-gray-400 text-sm font-medium">Inactive</span>
                            @endif
                        </dd>
                    </div>
                    @if($rate->effective_from || $rate->effective_until)
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Effective Period</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if($rate->effective_from && $rate->effective_until)
                                    {{ $rate->effective_from->format('M d, Y') }} — {{ $rate->effective_until->format('M d, Y') }}
                                @elseif($rate->effective_from)
                                    From {{ $rate->effective_from->format('M d, Y') }}
                                @endif
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>

            <!-- Rate Type Explanation -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-3">How This Rate Works</h2>
                @switch($rate->type)
                    @case(\App\Enums\RateType::Base)
                        <p class="text-sm text-gray-600">
                            This is a <strong>base rate</strong>. It is the standard rate applied to future reservations for
                            <strong>{{ $rate->facility->name }}</strong> when no matching seasonal rate is active.
                        </p>
                        @break
                    @case(\App\Enums\RateType::Seasonal)
                        <p class="text-sm text-gray-600">
                            This is a <strong>seasonal rate</strong>. It overrides the base rate for
                            <strong>{{ $rate->facility->name }}</strong> during the configured effective period.
                            If multiple seasonal rates overlap for the same facility, the pricing engine will reject the configuration.
                        </p>
                        @break
                    @case(\App\Enums\RateType::AdditionalCharge)
                        <p class="text-sm text-gray-600">
                            This is an <strong>additional charge</strong> (e.g. early check-in, late check-out) for
                            <strong>{{ $rate->facility->name }}</strong>. It is applied on top of the base or seasonal rate.
                        </p>
                        @break
                    @case(\App\Enums\RateType::ExtraPerson)
                        <p class="text-sm text-gray-600">
                            This is an <strong>extra-person charge</strong> for <strong>{{ $rate->facility->name }}</strong>.
                            It is applied when the guest count exceeds the facility's standard capacity
                            ({{ $rate->facility->capacity }} guests) but remains within the maximum capacity
                            ({{ $rate->facility->effectiveMaxCapacity() }} guests).
                        </p>
                        @break
                @endswitch
                <p class="text-xs text-gray-400 mt-3">This rate affects future pricing calculations only. Existing reservation prices are not modified.</p>
            </div>
        </div>

        <!-- Sidebar Actions Column -->
        <div class="space-y-6">
            <!-- Activate / Deactivate -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6" x-data="{ showConfirm: false, action: '' }">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Rate Status</h2>

                @if($rate->is_active)
                    <p class="text-sm text-gray-600 mb-4">This rate is currently <strong class="text-success">active</strong> and will be used in pricing calculations for future reservations.</p>
                    <button @click="action = 'deactivate'; showConfirm = true"
                            class="w-full px-4 py-2 border border-danger rounded-md text-sm font-medium text-danger bg-white hover:bg-danger-light transition">
                        Deactivate Rate
                    </button>
                @else
                    <p class="text-sm text-gray-600 mb-4">This rate is currently <strong class="text-gray-400">inactive</strong> and will not affect pricing calculations.</p>
                    <button @click="action = 'activate'; showConfirm = true"
                            class="w-full px-4 py-2 border border-success rounded-md text-sm font-medium text-success bg-white hover:bg-success-light transition">
                        Activate Rate
                    </button>
                @endif

                <!-- Confirmation Modal -->
                <div x-show="showConfirm" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                    <div class="flex min-h-full items-center justify-center p-4">
                        <div class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="showConfirm = false"></div>
                        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full p-6 z-50">
                            <h3 class="text-lg font-medium text-gray-900 mb-2" x-text="action === 'activate' ? 'Activate Rate' : 'Deactivate Rate'"></h3>
                            <template x-if="action === 'deactivate'">
                                <p class="text-sm text-gray-500 mb-4">
                                    Deactivating <strong>{{ $rate->name }}</strong> will remove it from future pricing calculations.
                                    Historical reservations that used this rate are not affected.
                                </p>
                            </template>
                            <template x-if="action === 'activate'">
                                <p class="text-sm text-gray-500 mb-4">
                                    Activating <strong>{{ $rate->name }}</strong> will include it in future pricing calculations for
                                    <strong>{{ $rate->facility->name }}</strong>.
                                </p>
                            </template>
                            <div class="flex justify-end gap-3">
                                <button type="button" @click="showConfirm = false" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    Cancel
                                </button>
                                <template x-if="action === 'deactivate'">
                                    <form method="POST" action="{{ route('admin.rates.deactivate', $rate) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="px-4 py-2 bg-danger border border-transparent rounded-md text-sm font-medium text-white hover:bg-red-700">
                                            Deactivate
                                        </button>
                                    </form>
                                </template>
                                <template x-if="action === 'activate'">
                                    <form method="POST" action="{{ route('admin.rates.activate', $rate) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="px-4 py-2 bg-success border border-transparent rounded-md text-sm font-medium text-white hover:bg-green-700">
                                            Activate
                                        </button>
                                    </form>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Actions</h2>
                <div class="space-y-2">
                    <a href="{{ route('admin.rates.edit', $rate) }}" class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition">
                        Edit Rate
                    </a>
                    <a href="{{ route('admin.facilities.show', $rate->facility) }}" class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition">
                        View Facility
                    </a>
                    <a href="{{ route('admin.rates.create', ['facility_id' => $rate->facility_id]) }}" class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition">
                        Add Another Rate
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
