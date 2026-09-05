<x-admin-layout>
    <x-slot name="header">
        Schedule Training Batch
    </x-slot>

    <nav class="mb-6 text-sm text-gray-500">
        <a href="{{ route('admin.training-batches.index') }}" class="hover:text-primary">Training</a>
        <span class="mx-2">/</span>
        <span class="text-gray-900">New batch</span>
    </nav>

    @include('admin.training-batches.partials.form', [
        'action' => route('admin.training-batches.store'),
        'method' => 'POST',
        'submitLabel' => 'Schedule batch',
    ])
</x-admin-layout>
