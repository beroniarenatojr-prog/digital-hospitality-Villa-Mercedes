@props([
    'action',
    'method' => 'PATCH',
    'label',
    'title',
    'consequence',
    'confirm' => null,
    'tone' => 'danger',
    'size' => 'md',
])

@php
    // Several of these can sit on one screen, so the dialog's label and
    // description ids have to be unique per instance.
    $dialogId = 'confirm-'.\Illuminate\Support\Str::random(8);
    $confirmLabel = $confirm ?? $label;
    $toneClasses = $tone === 'primary'
        ? 'border border-transparent bg-primary text-white hover:bg-primary-hover'
        : 'border border-transparent bg-danger text-white hover:bg-danger/90';
@endphp

{{-- Consequential actions state what will change, in plain words, before they
     run. The dialog is a real dialog: labelled, modal, Escape closes it, focus
     lands on the safe choice, and Tab cycles inside it. --}}
<div
    x-data="{
        open: false,
        submit() {
            this.hide();

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = @js($action);
            form.style.display = 'none';

            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = @js(csrf_token());
            form.appendChild(csrf);

            const method = document.createElement('input');
            method.type = 'hidden';
            method.name = '_method';
            method.value = @js($method);
            form.appendChild(method);

            document.body.appendChild(form);
            form.submit();
        },
        show() {
            this.open = true;
            this.$nextTick(() => this.$refs.cancel.focus());
        },
        hide() {
            this.open = false;
            this.$refs.trigger.focus();
        },
        trap(event) {
            const items = [ ...this.$refs.dialog.querySelectorAll('a[href], button:not([disabled])') ];
            if (items.length === 0) return;
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (! event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
    }"
    @keydown.escape="hide()"
    class="inline-flex w-full"
>
    <x-admin.button
        x-ref="trigger"
        :variant="$tone === 'primary' ? 'primary' : 'danger'"
        :size="$size"
        @click="show()"
        aria-haspopup="dialog"
        x-bind:aria-expanded="open ? 'true' : 'false'"
        class="w-full"
    >
        {{ $label }}
    </x-admin.button>

    <div
        x-cloak
        x-show="open"
        x-transition.opacity.duration.150ms
        class="fixed inset-0 z-50 flex items-end justify-center bg-primary/50 p-4 sm:items-center"
        @click.self="hide()"
    >
        <div
            x-ref="dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $dialogId }}-title"
            aria-describedby="{{ $dialogId }}-body"
            @keydown.tab="trap($event)"
            class="w-full max-w-md rounded-xl border border-border bg-surface p-5 shadow-xl"
        >
            <div class="flex items-start gap-3">
                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $tone === 'primary' ? 'bg-primary-light text-primary' : 'bg-danger-light text-danger' }}" aria-hidden="true">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                    </svg>
                </span>

                <div class="min-w-0">
                    <h2 id="{{ $dialogId }}-title" class="text-base font-semibold text-text">{{ $title }}</h2>
                    <p id="{{ $dialogId }}-body" class="mt-1 text-sm text-text-muted">{{ $consequence }}</p>
                    @isset($details)
                        <div class="mt-3 rounded-lg bg-muted p-3 text-sm text-text">{{ $details }}</div>
                    @endisset
                </div>
            </div>

            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    x-ref="cancel"
                    @click="hide()"
                    class="inline-flex min-h-[42px] items-center justify-center rounded-lg border border-border bg-surface px-4 text-sm font-semibold text-text transition-colors duration-150 hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                >
                    Cancel
                </button>

                <button
                    type="button"
                    @click="submit()"
                    class="inline-flex min-h-[42px] items-center justify-center rounded-lg px-4 text-sm font-semibold transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 {{ $toneClasses }} {{ $tone === 'primary' ? 'focus-visible:ring-primary' : 'focus-visible:ring-danger' }}"
                >
                    {{ $confirmLabel }}
                </button>
            </div>
        </div>
    </div>
</div>
