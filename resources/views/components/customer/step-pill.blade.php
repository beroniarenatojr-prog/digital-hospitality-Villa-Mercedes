@props([
    'step' => 1,
    'label' => 'Select category',
])

{{--
    Compact booking progress chip. Lives in the page header actions slot so the
    guest always sees which step of the reservation journey they are on.
--}}
<p {{ $attributes->merge([
    'class' => 'inline-flex min-h-10 items-center gap-2 rounded-full border border-border bg-primary-light/80 px-4 py-2 text-[11px] font-semibold uppercase tracking-[0.14em] text-primary',
]) }}>
    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-accent" aria-hidden="true"></span>
    <span>Step {{ $step }}: {{ $label }}</span>
</p>
