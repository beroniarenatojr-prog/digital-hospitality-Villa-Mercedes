@props(['title', 'value', 'icon' => null, 'trend' => null, 'trendUp' => true])

<div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-100">
    <div class="p-5">
        <div class="flex items-center">
            @if($icon)
                <div class="flex-shrink-0 bg-primary-light rounded-md p-3">
                    {!! $icon !!}
                </div>
            @endif
            <div class="{{ $icon ? 'ml-5' : '' }} w-0 flex-1">
                <dl>
                    <dt class="text-sm font-medium text-gray-500 truncate">
                        {{ $title }}
                    </dt>
                    <dd>
                        <div class="text-2xl font-bold text-gray-900">
                            {{ $value }}
                        </div>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
    @if($trend !== null)
        <div class="bg-gray-50 px-5 py-3">
            <div class="text-sm">
                @if($trendUp)
                    <span class="text-success font-medium flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        {{ $trend }}
                    </span>
                @else
                    <span class="text-danger font-medium flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path></svg>
                        {{ $trend }}
                    </span>
                @endif
            </div>
        </div>
    @endif
</div>
