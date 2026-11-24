{{--
    Shared hospitality cues that sit under the main customer work area.
    Same three points on category, space, and reservations pages so the shell
    feels continuous without repeating marketing chrome in every section.
--}}
<section {{ $attributes->merge(['class' => 'border-t border-border pt-8 sm:pt-10']) }} aria-label="Why guests choose Villa Mercedes">
    <ul class="grid gap-6 sm:grid-cols-3 sm:gap-8">
        <li class="flex items-start gap-3">
            <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full bg-primary-light text-primary" aria-hidden="true">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </span>
            <div>
                <p class="font-semibold text-text">Bespoke Comfort</p>
                <p class="mt-0.5 text-sm text-text-muted">Thoughtfully designed private sanctuaries.</p>
            </div>
        </li>
        <li class="flex items-start gap-3">
            <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full bg-primary-light text-primary" aria-hidden="true">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </span>
            <div>
                <p class="font-semibold text-text">Attentive Hospitality</p>
                <p class="mt-0.5 text-sm text-text-muted">Hosts committed to your seamless peace of mind.</p>
            </div>
        </li>
        <li class="flex items-start gap-3">
            <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full bg-primary-light text-primary" aria-hidden="true">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </span>
            <div>
                <p class="font-semibold text-text">Nature Sanctuary</p>
                <p class="mt-0.5 text-sm text-text-muted">Tropical architecture nestled in lush greenery.</p>
            </div>
        </li>
    </ul>
</section>
