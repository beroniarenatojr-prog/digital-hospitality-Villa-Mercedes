@props([
    'action' => null,
    'title' => 'Sign out?',
    'consequence' => 'You go back to the sign-in page. Anything typed on this page and not saved is lost.',
    'confirm' => 'Sign out',
    'cancel' => 'Stay signed in',
])

@php
    // One confirmation, both sides of the desk. The caller supplies the trigger,
    // because the ways out differ — a sidebar button, a menu item, an icon in a
    // rail — while the question and the answers do not.
    //
    // The trigger stays a real submit button inside a real form, and calling
    // ask() is what the script adds on top. A confirmation that only works while
    // the script runs would leave someone signed in with no way out, which is
    // the failure this screen otherwise spends its time fixing.
    //
    // The dialog is teleported to the body because some triggers sit inside the
    // sidebar, which becomes a transformed drawer on a phone; a fixed overlay
    // inside it would be fixed to the drawer instead of the screen.
    $dialogId = 'confirm-sign-out-'.\Illuminate\Support\Str::random(8);
    $endpoint = $action ?? route('logout');
    $path = 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1';
@endphp

<div
    x-data="{
        open: false,
        trigger: null,
        ask() {
            // Whoever asked is remembered from the document rather than assumed,
            // so focus can go back to the right control in either layout.
            this.trigger = document.activeElement;
            this.open = true;
            this.$nextTick(() => this.$refs.cancel.focus());
        },
        dismiss() {
            this.open = false;
            this.trigger?.focus();
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
    {{ $attributes }}
>
    {{ $trigger }}

    <template x-teleport="body">
        <div
            x-cloak
            x-show="open"
            x-transition.opacity.duration.150ms
            @keydown.escape="dismiss()"
            class="fixed inset-0 z-50 flex items-end justify-center bg-primary/50 p-4 sm:items-center"
            @click.self="dismiss()"
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
                    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-light text-primary" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="{{ $path }}" />
                        </svg>
                    </span>

                    <div class="min-w-0">
                        <h2 id="{{ $dialogId }}-title" class="text-base font-semibold text-text">{{ $title }}</h2>
                        <p id="{{ $dialogId }}-body" class="mt-1 text-sm text-text-muted">{{ $consequence }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ $endpoint }}" class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    @csrf

                    <button
                        type="button"
                        x-ref="cancel"
                        @click="dismiss()"
                        class="inline-flex min-h-[42px] items-center justify-center rounded-lg border border-border bg-surface px-4 text-sm font-semibold text-text transition-colors duration-150 hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    >
                        {{ $cancel }}
                    </button>

                    <button
                        type="submit"
                        class="inline-flex min-h-[42px] items-center justify-center rounded-lg border border-transparent bg-primary px-4 text-sm font-semibold text-white transition-colors duration-150 hover:bg-primary-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                    >
                        {{ $confirm }}
                    </button>
                </form>
            </div>
        </div>
    </template>
</div>
