<x-customer-layout>
    @include('pages.partials.page-intro', [
        'eyebrow' => 'Legal',
        'title' => 'Terms of Use',
        'description' => 'These terms explain how guests may use the Villa Mercedes online booking and account services.',
    ])

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        @include('pages.partials.terms-body')
    </div>
</x-customer-layout>
