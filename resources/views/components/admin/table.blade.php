@props(['variant' => 'default'])

@php
    $isFlat = $variant === 'flat';
    $containerClasses = $isFlat
        ? 'overflow-hidden rounded-md border border-gray-200'
        : 'shadow overflow-hidden border-b border-gray-200 sm:rounded-lg';
    $scrollerClasses = $isFlat
        ? 'overflow-x-auto'
        : '-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8';
    $padClasses = $isFlat
        ? 'inline-block min-w-full align-middle'
        : 'inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8';
@endphp

<div class="flex min-w-0 flex-col">
    <div class="{{ $scrollerClasses }}">
        <div class="{{ $padClasses }}">
            <div class="{{ $containerClasses }}">
                <table class="min-w-full divide-y divide-gray-200">
                    {{ $slot }}
                </table>
            </div>
        </div>
    </div>
</div>
