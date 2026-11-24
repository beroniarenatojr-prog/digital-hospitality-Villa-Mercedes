<div class="space-y-8 text-sm sm:text-base text-text-muted leading-relaxed">
    <p class="text-xs uppercase tracking-wide text-text-muted">Last updated {{ now()->format('F j, Y') }}</p>

    <section class="space-y-3">
        <h2 class="font-heading text-xl sm:text-2xl font-semibold text-text">1. Personal information controller</h2>
        <p>
            {{ config('resort.name') }}, {{ config('resort.location') }}, is responsible for personal information collected through this website.
            Contact us at
            @if(config('resort.email'))
                <a href="mailto:{{ config('resort.email') }}" class="text-primary font-medium hover:underline">{{ config('resort.email') }}</a>
            @endif
            @if(config('resort.phone'))
                or <a href="{{ config('resort.phone_href') }}" class="text-primary font-medium hover:underline">{{ config('resort.phone') }}</a>
            @endif.
        </p>
    </section>

    <section class="space-y-3">
        <h2 class="font-heading text-xl sm:text-2xl font-semibold text-text">2. What we collect</h2>
        <ul class="list-disc pl-5 space-y-2">
            <li>Account details: name, email, mobile number, password (stored hashed), gender, date of birth, address, nationality, valid ID type, and optional company/agency.</li>
            <li>Reservation details: stay dates, facility, guest count, companion names you provide, and payment or deposit information you submit.</li>
            <li>Technical data needed to run the service (for example session and security logs).</li>
        </ul>
        <p>
            We collect a valid <em>ID type</em> for check in verification. Present the physical ID at the front desk; do not upload ID images through this site unless staff later provide a controlled process.
        </p>
    </section>

    <section class="space-y-3">
        <h2 class="font-heading text-xl sm:text-2xl font-semibold text-text">3. Why we use it</h2>
        <ul class="list-disc pl-5 space-y-2">
            <li>Create and secure your account</li>
            <li>Process reservation requests, deposits, and status updates</li>
            <li>Contact you about your stay and verify guests at check in</li>
            <li>Improve service quality and meet legal or accounting duties</li>
        </ul>
        <p>
            Operational messages about your account or booking are separate from optional marketing. We will not use your details for unrelated marketing without a clear, separate choice.
        </p>
    </section>

    <section class="space-y-3">
        <h2 class="font-heading text-xl sm:text-2xl font-semibold text-text">4. Sharing</h2>
        <p>
            Staff and systems that operate reservations, payments, and email delivery may access information needed for those tasks.
            We do not sell personal information. We may disclose information when required by law or to protect guests and the resort.
        </p>
    </section>

    <section class="space-y-3">
        <h2 class="font-heading text-xl sm:text-2xl font-semibold text-text">5. Retention</h2>
        <p>
            We keep account and reservation records only as long as needed for the booking relationship, legitimate business records, legal claims, or other applicable requirements, then dispose of or anonymize them when no longer necessary.
        </p>
    </section>

    <section class="space-y-3">
        <h2 class="font-heading text-xl sm:text-2xl font-semibold text-text">6. Your rights</h2>
        <p>
            Under the Philippine Data Privacy Act, you may request access, correction, or other rights regarding your personal information, subject to lawful exceptions.
            Use the
            <a href="{{ route('contact') }}" class="text-primary font-medium hover:underline">contact page</a>
            or email us to make a request.
        </p>
    </section>

    <section class="space-y-3">
        <h2 class="font-heading text-xl sm:text-2xl font-semibold text-text">7. Updates</h2>
        <p>
            We may revise this notice as practices or laws change. The date above shows the latest revision.
        </p>
    </section>
</div>
