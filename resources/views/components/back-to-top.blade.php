<button
    type="button"
    data-back-to-top
    x-data="{ show: false }"
    x-init="show = window.scrollY > 400"
    @scroll.window.passive="show = window.scrollY > 400"
    x-show="show"
    x-cloak
    x-transition.opacity
    @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
    {{-- Offset in `app.css` beside the other fixed helpers, so it climbs by
         whatever is docked to the bottom of the screen instead of landing on
         top of it. --}}
    class="fixed z-40 inline-flex items-center justify-center w-12 h-12 rounded-full bg-primary text-white shadow-lg hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
    aria-label="Back to top"
>
    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" /></svg>
</button>
