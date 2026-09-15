<x-customer-layout>
    @include('pages.partials.page-intro', [
        'eyebrow' => 'Gallery',
        'title' => 'Experience our world',
        'description' => $photos->isNotEmpty()
            ? 'Immerse yourself in the beauty of our lodges, pools, and event spaces.'
            : 'Glimpses of our lodges, pools, and event spaces. Photos will appear here as they are added.',
    ])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16"
         x-reveal
         x-data="{ lightbox: null, items: {{ \Illuminate\Support\Js::from($photos->isNotEmpty() ? $photos->map(fn ($photo) => [
             'title' => $photo->displayTitle(),
             'caption' => $photo->caption,
             'url' => $photo->url(),
         ])->values() : collect(config('resort.gallery'))->map(fn ($item) => [
             'title' => $item['title'],
             'caption' => $item['caption'],
             'url' => null,
         ])->values()) }} }">
        @if($photos->isNotEmpty())
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-5">
                @foreach($photos as $index => $photo)
                    <button
                        type="button"
                        @class([
                            'group relative overflow-hidden rounded-[1.5rem] text-left focus:outline-none focus:ring-2 focus:ring-accent',
                            'col-span-2 aspect-[16/10]' => $index < 2,
                            'aspect-square' => $index >= 2,
                        ])
                        @click="lightbox = {{ $index }}"
                    >
                        <img src="{{ $photo->url() }}" alt="{{ $photo->displayTitle() }}" loading="lazy" class="w-full h-full object-cover transition duration-700 ease-out group-hover:scale-110">
                        <span class="absolute inset-0 bg-gradient-to-t from-primary-dark/85 via-primary/25 to-transparent transition duration-500 group-hover:from-primary-dark/90" aria-hidden="true"></span>
                        <span class="absolute inset-x-0 bottom-0 p-4">
                            <span class="block font-semibold text-white text-sm">{{ $photo->displayTitle() }}</span>
                            @if($photo->caption)
                                <span class="block text-xs text-accent-light mt-0.5 line-clamp-2">{{ $photo->caption }}</span>
                            @endif
                        </span>
                    </button>
                @endforeach
            </div>
        @else
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach(config('resort.gallery') as $index => $item)
                    <button
                        type="button"
                        @class([
                            'rounded-[1.5rem] text-left p-4 flex flex-col justify-end text-white hover:brightness-110 transition focus:outline-none focus:ring-2 focus:ring-accent',
                            'col-span-2 aspect-[16/10]' => $index < 2,
                            'aspect-square' => $index >= 2,
                        ])
                        @style(['background: linear-gradient(160deg, rgba(98,63,31,0.95) '.($index * 8).'%, rgba(67,41,16,0.85) 100%)'])
                        @click="lightbox = {{ $index }}"
                    >
                        <span class="font-semibold">{{ $item['title'] }}</span>
                        <span class="text-xs text-white/80 mt-1">{{ $item['caption'] }}</span>
                    </button>
                @endforeach
            </div>
        @endif

        <div
            x-show="lightbox !== null"
            x-cloak
            class="fixed inset-0 z-[60] bg-black/80 flex items-center justify-center p-6"
            @keydown.escape.window="lightbox = null"
            @click.self="lightbox = null"
        >
            <div class="max-w-3xl w-full bg-surface rounded-2xl overflow-hidden">
                <template x-if="lightbox !== null && items[lightbox] && items[lightbox].url">
                    <img :src="items[lightbox].url" :alt="items[lightbox].title" class="w-full max-h-[70vh] object-contain bg-black">
                </template>
                <div class="p-6 text-center">
                    <h3 class="font-heading text-2xl font-semibold text-text" x-text="lightbox !== null ? items[lightbox].title : ''"></h3>
                    <p class="mt-3 text-text-muted" x-text="lightbox !== null ? (items[lightbox].caption || '') : ''"></p>
                    <button type="button" class="mt-6 text-sm font-medium text-primary hover:underline" @click="lightbox = null">Close</button>
                </div>
            </div>
        </div>

        <p class="mt-10 text-center text-sm text-text-muted">
            Follow more photos on
            <a href="{{ config('resort.facebook_url') }}" class="text-primary hover:underline" target="_blank" rel="noopener noreferrer">Facebook</a>
            and
            <a href="{{ config('resort.instagram_url') }}" class="text-primary hover:underline" target="_blank" rel="noopener noreferrer">Instagram</a>.
        </p>
    </div>
</x-customer-layout>
