<nav class="flex flex-wrap gap-4 border-b border-border" aria-label="This booking">
    @foreach (['customer.reservation-groups.show' => 'Stay details', 'customer.reservation-groups.payments' => 'Payments'] as $route => $label)
        <a href="{{ route($route, [$group, ...request()->only('tab', 'page')]) }}" @if(request()->routeIs($route)) aria-current="page" @endif @class(['inline-flex min-h-11 items-center border-b-2 px-3 py-2 text-sm font-semibold text-primary focus:ring-2 focus:ring-primary', 'border-primary' => request()->routeIs($route), 'border-transparent' => !request()->routeIs($route)])>{{ $label }}</a>
    @endforeach
</nav>
