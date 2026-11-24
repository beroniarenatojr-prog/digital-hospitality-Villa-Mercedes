@if($reservation->canReview())
    @php $review = \App\Models\GuestReview::where('reservation_id', $reservation->id)->first(); @endphp
    {{--
        `scroll-mt-36` clears the fixed header plus the account nav, so a link to
        #review lands the heading in view rather than under the chrome.
    --}}
    <section id="review" class="bg-surface border border-border rounded-2xl shadow-sm p-5 sm:p-6 scroll-mt-36">
        <h2 class="font-heading text-2xl font-semibold text-text">{{ $review ? 'Your feedback' : 'Rate your experience' }}</h2>
        @if($review && ($review->moderated_at || $review->is_published))
            <p class="mt-2 text-sm text-text">{{ $review->rating }} out of 5 stars · {{ $review->is_published ? 'Published' : 'Shared privately with staff' }}</p>
            @if($review->body)<p class="mt-2 text-sm whitespace-pre-wrap text-text">{{ $review->body }}</p>@endif
        @else
            <p class="mt-2 text-sm text-text-muted">How did you feel about your visit? Feedback is optional. Staff review submissions, and you choose whether yours may appear publicly.</p>
            <form method="POST" action="{{ route('customer.reviews.store', $reservation) }}" class="mt-5 space-y-5">
                @csrf
                <fieldset>
                    <legend class="text-sm font-medium text-text">Overall rating</legend>
                    <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1">
                        @for($star = 1; $star <= 5; $star++)
                            <label class="inline-flex items-center gap-2 py-2 text-sm text-text">
                                <input type="radio" name="rating" value="{{ $star }}" required @checked(old('rating', $review?->rating) == $star)>
                                {{ $star }} {{ $star === 1 ? 'star' : 'stars' }}
                            </label>
                        @endfor
                    </div>
                </fieldset>

                <div>
                    <x-input-label for="review_body" value="Your experience (optional)" />
                    <textarea id="review_body" name="body" maxlength="2000" rows="4" class="mt-2 block w-full rounded-lg border-border text-text shadow-sm focus:border-primary focus:ring-primary">{{ old('body', $review?->body) }}</textarea>
                </div>

                <div>
                    <x-input-label for="public_consent" value="Who may see this feedback?" />
                    <select id="public_consent" name="public_consent" required class="mt-2 block w-full rounded-lg border-border text-text focus:border-primary focus:ring-primary">
                        <option value="">Choose visibility</option>
                        <option value="0" @selected(old('public_consent', $review?->public_consent === null ? '' : (int) $review->public_consent) === 0 || old('public_consent') === '0')>Resort staff only</option>
                        <option value="1" @selected(old('public_consent', $review?->public_consent) == '1')>Staff may publish it with my first name and last initial</option>
                    </select>
                </div>

                <x-primary-button type="submit" class="w-full justify-center">
                    {{ $review ? 'Update feedback' : 'Submit feedback' }}
                </x-primary-button>
            </form>
        @endif
    </section>
@endif
