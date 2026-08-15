<x-customer-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="mb-8 text-center">
            <h1 class="text-4xl font-bold text-text mb-4">Welcome to Villa Mercedes</h1>
            <p class="text-lg text-text-muted">Experience nature and comfort.</p>
        </div>

        <x-card class="p-8 mb-8 text-center bg-primary text-white border-0 shadow-lg">
            <h2 class="text-2xl font-bold mb-4">Reserve Your Stay</h2>
            <p class="mb-6 opacity-90">Find the perfect room, function hall, or package for your needs.</p>
            <x-primary-button class="bg-surface text-primary hover:bg-background">
                Browse Facilities
            </x-primary-button>
        </x-card>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-card class="p-6">
                <h3 class="text-xl font-bold text-primary mb-2">Rooms</h3>
                <p class="text-text-muted mb-4">Comfortable accommodations surrounded by nature.</p>
                <x-secondary-button>View Rooms</x-secondary-button>
            </x-card>
            <x-card class="p-6">
                <h3 class="text-xl font-bold text-primary mb-2">Function Halls</h3>
                <p class="text-text-muted mb-4">Perfect for weddings, meetings, and events.</p>
                <x-secondary-button>View Halls</x-secondary-button>
            </x-card>
            <x-card class="p-6">
                <h3 class="text-xl font-bold text-primary mb-2">Packages</h3>
                <p class="text-text-muted mb-4">Bundled services for the best resort experience.</p>
                <x-secondary-button>View Packages</x-secondary-button>
            </x-card>
        </div>
    </div>
</x-customer-layout>
