@if(filled(config('resort.messenger_url')))
    <div data-support-chat>
        <button type="button" data-support-chat-open data-chat-launcher
            class="fixed bottom-5 right-4 z-[70] inline-flex h-14 w-14 items-center justify-center rounded-full bg-[#0866ff] text-white shadow-lg transition hover:bg-[#0759df] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0866ff] focus-visible:ring-offset-2 sm:bottom-6 sm:right-6"
            aria-label="Open Messenger chat" aria-controls="resort-support-chat" aria-expanded="false">
            <svg data-chat-open-icon class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 12a8.38 8.38 0 0 1-.9 3.8A8.5 8.5 0 0 1 12 21a8.38 8.38 0 0 1-3.8-.9L3 21l.9-5.2A8.38 8.38 0 0 1 3 12a8.5 8.5 0 0 1 18 0Z" />
            </svg>
            <svg data-chat-close-icon hidden class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" />
            </svg>
        </button>

        <section id="resort-support-chat" data-chat-panel hidden role="dialog" aria-modal="false" aria-labelledby="resort-support-chat-title"
            class="fixed inset-x-3 bottom-24 z-[70] max-h-[calc(100dvh-7rem)] overflow-y-auto rounded-2xl border border-border bg-white shadow-[0_16px_64px_rgba(0,0,0,0.18)] sm:left-auto sm:right-6 sm:w-80">
            <header class="flex items-center gap-3 border-b border-border px-5 py-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary p-2.5">
                    <img src="{{ asset('images/logo-villa-mercedes-mark.png') }}" alt="" class="h-full w-full object-contain" width="28" height="28">
                </span>
                <div class="min-w-0 flex-1">
                    <h2 id="resort-support-chat-title" class="font-display text-base font-semibold text-text">{{ config('resort.short_name') }}</h2>
                    <p class="mt-0.5 text-xs leading-5 text-text-muted">Chat with us on Messenger</p>
                </div>
                <button type="button" data-chat-minimize class="-mr-2 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-text-muted transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary" aria-label="Close Messenger chat">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                </button>
            </header>

            <div class="bg-background px-5 py-6">
                <div class="rounded-xl rounded-tl-sm border border-border bg-white px-4 py-3 text-sm leading-6 text-text">
                    <p class="font-semibold">Hi there! 👋</p>
                    <p class="mt-1">How can we help you?</p>
                    <p class="mt-2">Send us a message on Messenger to ask about your visit.</p>
                </div>
            </div>

            <div class="px-5 pb-4 pt-1">
                <a href="{{ config('resort.messenger_url') }}" target="_blank" rel="noopener noreferrer" data-messenger-link aria-describedby="support-messenger-description" class="flex min-h-12 items-center justify-center gap-2 rounded-xl bg-[#0866ff] px-3 py-3 text-sm font-semibold text-white transition hover:bg-[#0759df] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0866ff] focus-visible:ring-offset-2">
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M12 2C6.37 2 2 6.12 2 11.68c0 2.91 1.2 5.42 3.16 7.15.16.14.26.35.27.57l.05 1.78c.02.57.61.94 1.13.71l1.98-.87a.8.8 0 0 1 .53-.04c.91.25 1.87.38 2.88.38 5.63 0 10-4.12 10-9.68S17.63 2 12 2Zm6.01 7.45-2.94 4.66a1.5 1.5 0 0 1-2.17.4l-2.34-1.75a.6.6 0 0 0-.72 0l-3.16 2.4c-.42.32-.97-.18-.69-.63l2.94-4.66a1.5 1.5 0 0 1 2.17-.4l2.34 1.75a.6.6 0 0 0 .72 0l3.16-2.4c.42-.32.97.18.69.63Z" />
                    </svg>
                    Message us on Messenger
                </a>
                <p id="support-messenger-description" class="mt-3 text-center text-xs leading-5 text-text-muted">Opens Messenger in a new tab. Replies arrive there.</p>
            </div>
        </section>
    </div>
@endif
