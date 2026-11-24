@props([
    'value',
    'currency' => '₱',
    'muted' => false,
])

@php
    $amount = \App\Support\Money::format($value);

    // The symbol sits in its own span so callers can restyle the figure without
    // restyling the sign, and it stays inside the accessible text on purpose:
    // "1,350.00" alone does not say which currency anyone is reconciling — and
    // the guest and the staff member are reading the same peso.
    $mutedClass = $muted ? ' text-text-muted' : '';
@endphp

{{-- The front desk and the guest portal quote the same money, so they quote it
     through the same component: two decimals, thousands separators, tabular
     figures, never a wrapped number, and never a second implementation.
     SharedVocabularyTest refuses a hand-written peso symbol anywhere in the view
     tree; MoneyFormatTest proves this component, App\Support\Money, js/money.js
     and the arrival-pass email all print the same string.

     Style the figure from the caller ($attributes), never the sign. The digits
     themselves come from App\Support\Money, so an email or a service that builds
     a sentence in PHP writes the same string as this component. --}}
<span {{ $attributes->class(['whitespace-nowrap tabular-nums'.$mutedClass]) }}><span
    class="text-text-muted">{{ $currency }}</span>{{ $amount }}</span>
