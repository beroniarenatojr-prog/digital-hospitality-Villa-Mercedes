@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'text-sm text-danger space-y-1']) }}>
        @foreach ((array) $messages as )
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
