<footer class="bg-surface border-t border-border mt-12 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div>
                <h3 class="text-lg font-semibold text-primary mb-4">Villa Mercedes Nature Farm & Resort</h3>
                <p class="text-sm text-text-muted">
                    Experience nature, comfort, and hospitality.
                </p>
            </div>
            <div>
                <h4 class="text-md font-medium text-text mb-4">Quick Links</h4>
                <ul class="space-y-2 text-sm text-text-muted">
                    <li><a href="{{ route('home') }}" class="hover:text-primary transition">Home</a></li>
                    <li><a href="{{ route('facilities.index') }}" class="hover:text-primary transition">Facilities</a></li>
                    <li><a href="{{ route('faq.index') }}" class="hover:text-primary transition">FAQ</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-md font-medium text-text mb-4">Legal</h4>
                <p class="text-sm text-text-muted">
                    &copy; {{ date('Y') }} Villa Mercedes Nature Farm & Resort.<br>
                    All rights reserved.
                </p>
            </div>
        </div>
    </div>
</footer>
