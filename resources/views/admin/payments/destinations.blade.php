<x-admin-layout>
    <div class="max-w-5xl mx-auto space-y-6">
        <h1 class="text-2xl font-semibold">QR payment accounts</h1>
        <p>Upload the resort-issued QR and check the recipient in the payment app before activating it. To replace an account, add its replacement here and disable the old one; historical submissions retain their original account details.</p>
        <form method="POST" action="{{ route('admin.payment-destinations.store') }}" enctype="multipart/form-data" class="bg-white border rounded-lg p-5 grid sm:grid-cols-2 gap-4">
            @csrf
            <label>Provider<select name="method" class="block w-full rounded" required><option value="gcash">GCash</option><option value="maya">Maya</option><option value="bank_transfer">Bank transfer</option></select></label>
            <label>Bank (if applicable)<input name="bank" value="{{ old('bank') }}" class="block w-full rounded" maxlength="100"></label>
            <label>Account name<input name="account_name" value="{{ old('account_name') }}" required class="block w-full rounded" maxlength="255"></label>
            <label>Account number<input name="account_number" value="{{ old('account_number') }}" required class="block w-full rounded" maxlength="100"></label>
            <label>QR image (PNG or JPG, up to 5 MB)<input type="file" name="qr" accept="image/png,image/jpeg" required class="block w-full mt-2"></label>
            <label class="flex gap-2 items-start"><input type="checkbox" name="verified_account" value="1" required><span>I verified the QR recipient and these account details with the resort.</span></label>
            <button class="bg-primary text-white rounded px-4 py-2">Upload and activate</button>
        </form>
        <div class="grid sm:grid-cols-2 gap-5">
            @foreach($destinations as $destination)
                <section class="bg-white border rounded-lg p-5 space-y-2">
                    <h2 class="font-semibold">{{ $destination->method->label() }} · {{ $destination->is_active ? 'Active' : 'Disabled' }}</h2>
                    <img class="w-52 h-52 object-contain" src="{{ route('payment-destinations.image', $destination) }}" alt="{{ $destination->method->label() }} QR for {{ $destination->account_name }}">
                    <p>{{ $destination->account_name }} · {{ $destination->account_number }} {{ $destination->bank }}</p>
                    <form method="POST" action="{{ route('admin.payment-destinations.toggle', $destination) }}">@csrf @method('PATCH')
                        <input type="hidden" name="is_active" value="{{ $destination->is_active ? 0 : 1 }}">
                        @unless($destination->is_active)<label class="block"><input type="checkbox" name="verified_account" value="1" required> Recipient details are verified</label>@endunless
                        <button class="border rounded px-3 py-2 mt-2">{{ $destination->is_active ? 'Disable' : 'Activate' }}</button>
                    </form>
                </section>
            @endforeach
        </div>
    </div>
</x-admin-layout>
