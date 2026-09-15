@if($reservation->canReview())
    @php $review = \App\Models\GuestReview::where('reservation_id', $reservation->id)->first(); @endphp
    <section id="review" class="bg-surface border border-border rounded-2xl p-5 sm:p-6 scroll-mt-28">
        <h2 class="font-heading text-2xl">{{ $review ? 'Your feedback' : 'Rate your experience' }}</h2>
        @if($review && ($review->moderated_at || $review->is_published))
            <p class="mt-2">{{ $review->rating }} out of 5 stars · {{ $review->is_published ? 'Published' : 'Shared privately with staff' }}</p>
            @if($review->body)<p class="mt-2 whitespace-pre-wrap">{{ $review->body }}</p>@endif
        @else
            <p class="mt-2 text-text-muted">How did you feel about your visit? Feedback is optional. Staff review submissions, and you choose whether yours may appear publicly.</p>
            <form method="POST" action="{{ route('customer.reviews.store', $reservation) }}" class="mt-4 space-y-4">
                @csrf
                <fieldset><legend class="font-semibold">Overall rating</legend><div class="flex flex-wrap gap-4 mt-2">
                    @for($star = 1; $star <= 5; $star++)<label class="inline-flex items-center gap-2 py-2"><input type="radio" name="rating" value="{{ $star }}" required @checked(old('rating', $review?->rating) == $star)> {{ $star }} {{ $star === 1 ? 'star' : 'stars' }}</label>@endfor
                </div></fieldset>
                <label class="block">Your experience (optional)<textarea name="body" maxlength="2000" rows="4" class="block w-full mt-2 rounded-lg border-border">{{ old('body', $review?->body) }}</textarea></label>
                <label class="block">Who may see this feedback?<select name="public_consent" required class="block w-full mt-2 rounded-lg border-border"><option value="">Choose visibility</option><option value="0" @selected(old('public_consent', $review?->public_consent === null ? '' : (int) $review->public_consent) === 0 || old('public_consent') === '0')>Resort staff only</option><option value="1" @selected(old('public_consent', $review?->public_consent) == '1')>Staff may publish it with my first name and last initial</option></select></label>
                <button class="bg-primary text-white rounded-lg px-4 py-3">{{ $review ? 'Update feedback' : 'Submit feedback' }}</button>
            </form>
        @endif
    </section>
@endif
