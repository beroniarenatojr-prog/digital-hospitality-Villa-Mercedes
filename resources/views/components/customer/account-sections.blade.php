@php
    $sections = \App\Support\CustomerNav::accountSections();
@endphp

{{--
    Page-level account destinations. The header already lists these on wide
    screens; this row mirrors the Figma guest dashboard tabs so reservations,
    and a new booking stay one click apart on the page itself.
--}}
<nav {{ $attributes->merge(['class' => 'border-b border-border']) }} aria-label="Account sections">
    <ul class="flex flex-wrap gap-x-1 gap-y-1">
        @foreach($sections as $section)
            <li>
                <a href="{{ route($section['route']) }}"
                   @if($section['active']) aria-current="page" @endif
                   @class([
                       'inline-flex min-h-11 items-center border-b-2 px-3 py-2.5 text-xs font-semibold uppercase tracking-[0.14em] transition -mb-px focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1',
                       'border-primary text-primary' => $section['active'],
                       'border-transparent text-text-muted hover:border-border hover:text-text' => ! $section['active'],
                   ])>
                    {{ $section['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
