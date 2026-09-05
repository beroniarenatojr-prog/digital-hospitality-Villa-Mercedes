<x-admin-layout>
    <x-slot name="header">
        Edit {{ $batch->cohort_code }}
    </x-slot>

    <nav class="mb-6 text-sm text-gray-500">
        <a href="{{ route('admin.training-batches.index') }}" class="hover:text-primary">Training</a>
        <span class="mx-2">/</span>
        <a href="{{ route('admin.training-batches.show', $batch) }}" class="hover:text-primary">{{ $batch->cohort_code }}</a>
        <span class="mx-2">/</span>
        <span class="text-gray-900">Edit</span>
    </nav>

    @include('admin.training-batches.partials.form', [
        'action' => route('admin.training-batches.update', $batch),
        'method' => 'PUT',
        'submitLabel' => 'Save changes',
    ])
</x-admin-layout>
