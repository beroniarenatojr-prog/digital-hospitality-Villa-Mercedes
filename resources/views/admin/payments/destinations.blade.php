<x-admin-layout>
    <x-slot name="header">QR payment accounts</x-slot>

    <x-admin.page-header
        title="QR payment accounts"
    >
        <x-slot:actions>
            <x-admin.button variant="secondary" :href="route('admin.payments.index')">Payment review</x-admin.button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mb-6">
        <x-admin.notice variant="warning" title="Check the recipient in the payment app, not just the label here" :dismissible="false">
            Upload the QR issued by the resort and confirm the account name and number in the app before activating it. A wrong recipient sends guest money somewhere the resort cannot reconcile.
        </x-admin.notice>
    </div>

    <x-admin.panel
        title="Add a QR account"
        description="To replace an existing account, add the replacement here and disable the old one. Past submissions keep the account details they were made against."
    >
        <form method="POST" action="{{ route('admin.payment-destinations.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="method" class="admin-label">Provider <span class="text-danger" aria-hidden="true">*</span></label>
                    <select id="method" name="method" required aria-required="true" class="admin-input mt-1">
                        <option value="gcash" @selected(old('method') === 'gcash')>GCash</option>
                        <option value="maya" @selected(old('method') === 'maya')>Maya</option>
                        <option value="bank_transfer" @selected(old('method') === 'bank_transfer')>Bank transfer</option>
                    </select>
                </div>

                <div>
                    <label for="bank" class="admin-label">Bank <span class="text-text-muted">(only for bank transfers)</span></label>
                    <input type="text" id="bank" name="bank" value="{{ old('bank') }}" maxlength="100" class="admin-input mt-1">
                </div>

                <div>
                    <label for="account_name" class="admin-label">Account name <span class="text-danger" aria-hidden="true">*</span></label>
                    <input type="text" id="account_name" name="account_name" value="{{ old('account_name') }}" required aria-required="true" maxlength="255" class="admin-input mt-1">
                </div>

                <div>
                    <label for="account_number" class="admin-label">Account number <span class="text-danger" aria-hidden="true">*</span></label>
                    <input type="text" id="account_number" name="account_number" value="{{ old('account_number') }}" required aria-required="true" maxlength="100"
                           inputmode="numeric" class="admin-input mt-1">
                </div>

                <div class="sm:col-span-2">
                    <label for="qr" class="admin-label">QR image <span class="text-danger" aria-hidden="true">*</span></label>
                    <input type="file" id="qr" name="qr" accept="image/png,image/jpeg" required aria-required="true"
                           class="mt-1 block w-full text-sm text-text-muted file:mr-4 file:rounded-lg file:border-0 file:bg-primary-light file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary-light/80">
                    <p class="admin-help">PNG or JPG, up to 5MB. Crop out everything except the QR so guests can scan it on a phone screen.</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="flex items-start gap-2 text-sm text-text">
                        <input type="checkbox" name="verified_account" value="1" required
                               class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-primary">
                        <span>I verified the QR recipient and these account details with the resort. <span class="text-danger" aria-hidden="true">*</span></span>
                    </label>
                </div>
            </div>

            <div class="mt-5 flex justify-end">
                <x-admin.button type="submit">Upload and activate</x-admin.button>
            </div>
        </form>
    </x-admin.panel>

    @if ($destinations->isEmpty())
        <div class="mt-6 rounded-xl border border-border bg-surface">
            <x-admin.empty
                icon="payment-destinations"
                title="No QR accounts yet"
                description="Guests cannot submit a deposit proof until at least one account is active, so add the first QR code before opening online booking."
            />
        </div>
    @else
        <div class="mt-6 grid grid-cols-1 gap-5 lg:grid-cols-2">
            @foreach ($destinations as $destination)
                <x-admin.panel
                    :title="$destination->method->label()"
                    :description="$destination->is_active ? 'Active — guests are shown this QR code for deposits.' : 'Disabled — kept on file for the proofs already recorded against it.'"
                >
                    <x-slot:actions>
                        @if ($destination->is_active)
                            <span class="rounded-sm border border-current px-2 py-0.5 text-xs font-semibold text-success">Active</span>
                        @else
                            <span class="rounded-sm border border-current px-2 py-0.5 text-xs font-semibold text-text-muted">Disabled</span>
                        @endif
                    </x-slot:actions>

                    <img
                        class="h-52 w-52 rounded-lg border border-border bg-muted object-contain"
                        src="{{ route('payment-destinations.image', $destination) }}"
                        alt="{{ $destination->method->label() }} QR code for {{ $destination->account_name }}"
                    >

                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-text-muted">Account name</dt>
                            <dd class="text-right font-medium text-text">{{ $destination->account_name }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-text-muted">Account number</dt>
                            <dd class="text-right font-mono text-text">{{ $destination->account_number }}</dd>
                        </div>
                        @if ($destination->bank)
                            <div class="flex items-baseline justify-between gap-3">
                                <dt class="text-text-muted">Bank</dt>
                                <dd class="text-right text-text">{{ $destination->bank }}</dd>
                            </div>
                        @endif
                    </dl>

                    <form method="POST" action="{{ route('admin.payment-destinations.toggle', $destination) }}" class="mt-4 border-t border-border pt-4">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="is_active" value="{{ $destination->is_active ? 0 : 1 }}">

                        @if ($destination->is_active)
                            <p class="text-sm text-text">Disabling this account stops new proofs from being submitted against it. Past payments are unaffected.</p>
                            <p class="admin-help">Add the replacement account above first, so guests are never left without a QR code to pay into.</p>
                        @else
                            <p class="text-sm text-text">Activating this account shows its QR code to guests submitting a deposit.</p>
                            <label class="mt-3 flex items-start gap-2 text-sm text-text">
                                <input type="checkbox" name="verified_account" value="1" required
                                       class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-primary">
                                <span>I checked the recipient in the payment app and these details are correct. <span class="text-danger" aria-hidden="true">*</span></span>
                            </label>
                        @endif

                        <div class="mt-4">
                            <x-admin.button type="submit" :variant="$destination->is_active ? 'danger' : 'primary'" class="w-full">
                                {{ $destination->is_active ? 'Disable this account' : 'Activate this account' }}
                            </x-admin.button>
                        </div>
                    </form>
                </x-admin.panel>
            @endforeach
        </div>
    @endif
</x-admin-layout>
