<x-customer-layout>
    @include('pages.partials.page-intro', [
        'eyebrow' => 'TESDA training',
        'title' => 'Learn hospitality where guests stay',
        'description' => config('resort.training.intro'),
    ])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-16">
        @if ($errors->any())
            <x-alert type="error">
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        <section>
            <h2 class="text-2xl font-display font-bold text-text">Open batches</h2>
            <p class="mt-2 text-text-muted max-w-3xl">
                Training happens in the working resort, so every batch is scheduled against real rooms and
                the cafe. That is why slots are limited and dates are fixed.
            </p>

            <div class="mt-8 grid lg:grid-cols-2 gap-6" x-data="{ applyingTo: null }">
                @forelse($batches as $batch)
                    <article class="bg-surface border border-border rounded-2xl p-6 shadow-sm flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-xl font-display font-bold text-text">{{ $batch->program }}</h3>
                                <p class="text-sm text-text-muted mt-1">Cohort {{ $batch->cohort_code }}</p>
                            </div>
                            <x-badge :status="$batch->status->label()" />
                        </div>

                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex gap-2">
                                <dt class="font-medium text-text">Dates:</dt>
                                <dd class="text-text-muted">{{ $batch->dateRangeLabel() }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="font-medium text-text">Session hours:</dt>
                                <dd class="text-text-muted">{{ $batch->sessionHoursLabel() }}</dd>
                            </div>
                            <div class="flex gap-2">
                                <dt class="font-medium text-text">Slots:</dt>
                                <dd class="{{ $batch->isFull() ? 'text-danger font-medium' : 'text-text-muted' }}">
                                    {{ $batch->isFull() ? 'Full — waitlist only' : $batch->slotsRemaining() . ' of ' . $batch->trainee_capacity . ' still open' }}
                                </dd>
                            </div>
                            @if($batch->facilities->isNotEmpty())
                                <div class="flex gap-2">
                                    <dt class="font-medium text-text">Training areas:</dt>
                                    <dd class="text-text-muted">{{ $batch->facilities->pluck('name')->join(', ') }}</dd>
                                </div>
                            @endif
                        </dl>

                        <div class="mt-6 pt-4 border-t border-border">
                            <button type="button" @click="applyingTo = applyingTo === {{ $batch->id }} ? null : {{ $batch->id }}"
                                    class="text-sm font-semibold text-primary hover:underline">
                                <span x-show="applyingTo !== {{ $batch->id }}">Apply for this batch</span>
                                <span x-show="applyingTo === {{ $batch->id }}" x-cloak>Hide application form</span>
                            </button>

                            <form method="POST" action="{{ route('training.apply') }}" class="mt-4 space-y-3"
                                  x-show="applyingTo === {{ $batch->id }}" x-cloak>
                                @csrf
                                <input type="hidden" name="training_batch_id" value="{{ $batch->id }}">

                                <div class="grid sm:grid-cols-2 gap-3">
                                    <div>
                                        <x-input-label for="name_{{ $batch->id }}" value="Full name" />
                                        <x-text-input id="name_{{ $batch->id }}" name="name" type="text" class="mt-1 block w-full" required />
                                    </div>
                                    <div>
                                        <x-input-label for="phone_{{ $batch->id }}" value="Mobile number" />
                                        <x-text-input id="phone_{{ $batch->id }}" name="phone" type="text" class="mt-1 block w-full" required />
                                    </div>
                                </div>

                                <div>
                                    <x-input-label for="email_{{ $batch->id }}" value="Email" />
                                    <x-text-input id="email_{{ $batch->id }}" name="email" type="email" class="mt-1 block w-full" required />
                                </div>

                                <div>
                                    <x-input-label for="address_{{ $batch->id }}" value="Address (optional)" />
                                    <x-text-input id="address_{{ $batch->id }}" name="address" type="text" class="mt-1 block w-full" />
                                </div>

                                <div>
                                    <x-input-label for="message_{{ $batch->id }}" value="Anything we should know? (optional)" />
                                    <textarea id="message_{{ $batch->id }}" name="message" rows="3"
                                              class="mt-1 block w-full rounded-md border-border shadow-sm focus:border-primary focus:ring-primary text-sm"></textarea>
                                </div>

                                <x-primary-button type="submit">
                                    {{ $batch->isFull() ? 'Join the waitlist' : 'Submit application' }}
                                </x-primary-button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="lg:col-span-2 bg-surface border border-border rounded-2xl p-8 text-center">
                        <p class="text-text-muted">
                            No batch is open for applications right now. Message us on Facebook or use the contact
                            page and we will tell you when the next cohort is scheduled.
                        </p>
                    </div>
                @endforelse
            </div>
        </section>

        <div class="grid lg:grid-cols-2 gap-8">
            @foreach(config('resort.training.programs') as $program)
                <article class="bg-surface border border-border rounded-2xl p-8 shadow-sm">
                    <p class="text-sm font-semibold uppercase tracking-widest text-accent">{{ $program['duration'] }}</p>
                    <h2 class="mt-2 text-2xl font-display font-bold text-text">{{ $program['title'] }}</h2>
                    <p class="mt-4 text-text-muted">{{ $program['description'] }}</p>
                    <ul class="mt-6 space-y-2 text-sm text-text-muted">
                        @foreach($program['highlights'] as $highlight)
                            <li class="flex gap-2">
                                <span class="text-primary">•</span>
                                {{ $highlight }}
                            </li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>

        <section class="grid md:grid-cols-2 gap-8">
            <div>
                <h2 class="text-xl font-display font-bold text-text mb-4">What scholarships may include</h2>
                <ul class="space-y-2 text-text-muted">
                    @foreach(config('resort.training.benefits') as $benefit)
                        <li>{{ $benefit }}</li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h2 class="text-xl font-display font-bold text-text mb-4">Typical requirements</h2>
                <ul class="space-y-2 text-text-muted">
                    @foreach(config('resort.training.requirements') as $requirement)
                        <li>{{ $requirement }}</li>
                    @endforeach
                </ul>
            </div>
        </section>

        <section class="bg-surface border border-border rounded-2xl p-8 md:p-10">
            <p class="text-text-muted mb-6">{{ config('resort.training.note') }}</p>
            <div class="flex flex-wrap gap-4">
                <x-primary-button tag="a" href="{{ config('resort.facebook_url') }}" target="_blank" rel="noopener noreferrer">
                    Inquire on Facebook
                </x-primary-button>
                <x-secondary-button tag="a" href="{{ route('contact') }}">
                    Contact the resort
                </x-secondary-button>
            </div>
        </section>
    </div>
</x-customer-layout>
