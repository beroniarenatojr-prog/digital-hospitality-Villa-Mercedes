<x-customer-layout>
    @include('pages.partials.page-intro', [
        'eyebrow' => 'Legal',
        'title' => 'Privacy Notice',
        'description' => 'How Villa Mercedes collects and uses personal information for accounts, reservations, and guest services.',
    ])

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        @include('pages.partials.privacy-body')
    </div>
</x-customer-layout>
