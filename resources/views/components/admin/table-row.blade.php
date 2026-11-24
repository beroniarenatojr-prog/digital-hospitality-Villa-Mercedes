{{-- focus-within is what makes the row a keyboard destination: when a link or
     button inside it takes focus, the whole row reads as the current target. --}}
<tr {{ $attributes->merge(['class' => 'transition-colors duration-150 hover:bg-primary-light/50 focus-within:bg-primary-light/50']) }}>
    {{ $slot }}
</tr>
