<x-admin-layout>
    <div class="max-w-5xl mx-auto space-y-5">
        <h1 class="text-2xl font-semibold">Guest feedback</h1>
        <p>Review for privacy, spam, or abusive content. A low rating alone is not a reason to suppress feedback. Customer ratings and comments cannot be edited here.</p>
        @foreach($reviews as $review)
            <article class="bg-white border rounded-lg p-5 space-y-3">
                <h2 class="font-semibold">{{ $review->user?->name }} · {{ $review->rating }}/5 · {{ $review->is_published ? 'Published' : ($review->moderated_at ? 'Reviewed privately' : 'Awaiting review') }}</h2>
                <p>{{ $review->reservation?->facility?->name }} · {{ $review->public_consent === false ? 'Private feedback — do not publish' : 'Public sharing permitted' }}</p>
                <p class="whitespace-pre-wrap">{{ $review->body ?: 'Rating only; no written comment.' }}</p>
                <form method="POST" action="{{ route('admin.reviews.update', $review) }}" class="flex flex-wrap gap-3">@csrf @method('PATCH')
                    <label class="w-full">Moderation note <span class="text-sm font-normal text-gray-600">(required to unpublish a review)</span><textarea name="reason" rows="2" maxlength="500" aria-describedby="review-reason-error-{{ $review->id }}" class="block w-full rounded mt-1">{{ $review->moderation_reason }}</textarea></label>
                    @error("reason.{$review->id}")<p id="review-reason-error-{{ $review->id }}" role="alert" class="w-full text-sm text-red-700">{{ $message }}</p>@enderror
                    @if($review->public_consent !== false)<button name="action" value="publish" class="bg-primary text-white rounded px-4 py-2">Publish</button>@endif
                    <button name="action" value="private" class="border rounded px-4 py-2">Mark reviewed privately</button>
                    @if($review->is_published)<button name="action" value="unpublish" class="border rounded px-4 py-2">Unpublish with reason</button>@endif
                </form>
            </article>
        @endforeach
        {{ $reviews->links() }}
    </div>
</x-admin-layout>
