@php
    $statusOf = function ($review): array {
        if ($review->is_published) {
            return ['label' => 'Published', 'class' => 'text-success'];
        }

        if ($review->moderated_at) {
            return ['label' => 'Reviewed privately', 'class' => 'text-text-muted'];
        }

        return ['label' => 'Awaiting review', 'class' => 'text-warning'];
    };
@endphp

<x-admin-layout>
    <x-slot name="header">Guest feedback</x-slot>

    <x-admin.page-header
        title="Guest feedback"
    >
        <x-slot:actions>
            <x-admin.button variant="secondary" :href="route('admin.reviews.index')">Refresh list</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    @if ($reviews->isEmpty())
        <div class="rounded-xl border border-border bg-surface">
            <x-admin.empty
                icon="reviews"
                title="No guest reviews yet"
                description="Reviews appear here after a guest stays and the review invitation goes out. Nothing needs moderating until then."
            />
        </div>
    @else
        <div class="space-y-4">
            @foreach ($reviews as $review)
                @php($status = $statusOf($review))

                <x-admin.panel
                    :title="$review->user?->name ?? 'Guest'"
                    :badge="$review->rating.' / 5'"
                    :description="$review->reservation?->facility?->name.' · '.($review->public_consent === false ? 'Private feedback — do not publish' : 'Guest allowed public sharing')"
                >
                    <x-slot:actions>
                        <span class="text-sm font-semibold {{ $status['class'] }}">{{ $status['label'] }}</span>
                    </x-slot:actions>

                    <p class="max-w-[70ch] whitespace-pre-wrap text-sm text-text">{{ $review->body ?: 'Rating only; no written comment.' }}</p>

                    <form method="POST" action="{{ route('admin.reviews.update', $review) }}" class="mt-4 border-t border-border pt-4">
                        @csrf
                        @method('PATCH')

                        <label for="reason-{{ $review->id }}" class="admin-label">
                            Moderation note
                            <span class="text-text-muted">(required to unpublish)</span>
                        </label>
                        <textarea
                            id="reason-{{ $review->id }}"
                            name="reason"
                            rows="2"
                            maxlength="500"
                            aria-describedby="review-reason-error-{{ $review->id }}"
                            @error("reason.{$review->id}") aria-invalid="true" @enderror
                            class="admin-input mt-1"
                        >{{ $review->moderation_reason }}</textarea>
                        <p class="admin-help">Staff-only. The guest never sees this note.</p>
                        @error("reason.{$review->id}")
                            <p id="review-reason-error-{{ $review->id }}" role="alert" class="admin-help text-danger">{{ $message }}</p>
                        @enderror

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            @if ($review->public_consent !== false)
                                <x-admin.button type="submit" name="action" value="publish" size="sm">Publish to the public site</x-admin.button>
                            @endif

                            @if ($review->is_published)
                                <x-admin.button type="submit" name="action" value="unpublish" variant="danger" size="sm">Unpublish with reason</x-admin.button>
                            @endif
                        </div>
                    </form>
                </x-admin.panel>
            @endforeach
        </div>

        @if ($reviews->hasPages())
            <div class="mt-6">
                {{ $reviews->withQueryString()->links() }}
            </div>
        @endif
    @endif
</x-admin-layout>
