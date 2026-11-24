@props([
    'action',
    'method' => 'PATCH',
    'label',
    'title',
    'consequence',
    'reasonLabel',
    'reasonName' => 'reason',
    'reasonRequired' => true,
    'reasonPlaceholder' => null,
    'confirm' => null,
    'tone' => 'danger',
    'size' => 'md',
])

@php
    $dialogId = 'form-action-'.\Illuminate\Support\Str::random(8);
    $confirmLabel = $confirm ?? $label;
    $toneClasses = $tone === 'primary'
        ? 'border border-transparent bg-primary text-white hover:bg-primary-hover focus-visible:ring-primary'
        : 'border border-transparent bg-danger text-white hover:bg-danger/90 focus-visible:ring-danger';
@endphp

{{-- Actions that change a record and need a written reason open here. The
     reason is required in the dialog itself, so the server never receives an
     empty rejection note to work around. --}}
<div
    x-data="{
        open: false,
        show() {
            this.open = true;
            this.$nextTick(() => this.$refs.reason.focus());
        },
        hide() {
            this.open = false;
            this.$refs.trigger.focus();
        },
        trap(event) {
            const items = [ ...this.$refs.dialog.querySelectorAll('a[href], button:not([disabled]), textarea, input') ];
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
            <h2 id="{{ $dialogId }}-title" class="text-base font-semibold text-text">{{ $title }}</h2>
            <p id="{{ $dialogId }}-body" class="mt-1 text-sm text-text-muted">{{ $consequence }}</p>

            <form method="POST" action="{{ $action }}" class="mt-4">
                @csrf
                @method($method)

                <label for="{{ $dialogId }}-reason" class="admin-label">
                    {{ $reasonLabel }}
                    @unless ($reasonRequired)
                        <span class="text-text-muted">(optional)</span>
                    @endunless
                </label>
                <textarea
                    x-ref="reason"
                    name="{{ $reasonName }}"
                    id="{{ $dialogId }}-reason"
                    rows="3"
                    @if ($reasonRequired) required aria-required="true" @endif
                    @if ($reasonPlaceholder) placeholder="{{ $reasonPlaceholder }}" @endif
                    class="admin-input mt-1"
                ></textarea>
                <p class="admin-help">The guest sees this wording when the reservation is updated.</p>

                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        @click="hide()"
                        class="inline-flex min-h-[42px] items-center justify-center rounded-lg border border-border bg-surface px-4 text-sm font-semibold text-text transition-colors duration-150 hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="inline-flex min-h-[42px] items-center justify-center rounded-lg px-4 text-sm font-semibold transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 {{ $toneClasses }}"
                    >
                        {{ $confirmLabel }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
