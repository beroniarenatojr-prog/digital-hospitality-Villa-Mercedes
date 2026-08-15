@props(['icon' => null, 'title', 'description'])

<div {{ $attributes->merge(['class' => 'text-center py-12 px-4 sm:px-6 lg:px-8 border-2 border-dashed border-border rounded-xl']) }}>
    @if ($icon)
        <div class="mx-auto h-12 w-12 text-text-muted mb-4">
            {{ $icon }}
        </div>
    @endif
    <h3 class="mt-2 text-sm font-medium text-text">{{ $title }}</h3>
    <p class="mt-1 text-sm text-text-muted">{{ $description }}</p>
    @if (isset($action))
        <div class="mt-6">
            {{ $action }}
        </div>
    @endif
</div>
