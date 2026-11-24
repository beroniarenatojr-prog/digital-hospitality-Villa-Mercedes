<div class="space-y-8 text-sm sm:text-base text-text-muted leading-relaxed">
    <p class="text-xs uppercase tracking-wide text-text-muted">Published {{ config('resort.policy.published_on') }} · {{ config('resort.policy.version') }}</p>

    <section class="space-y-3">
        <h2 class="font-heading text-xl sm:text-2xl font-semibold text-text">1. Who we are</h2>
        <p>
            {{ config('resort.name') }} (“we”, “us”, or “the resort”) operates this website and online reservation tools for stays and visits at
            {{ config('resort.location') }}.
        </p>
        <p>
            Contact:
            @if(config('resort.email'))
                <a href="mailto:{{ config('resort.email') }}" class="text-primary font-medium hover:underline">{{ config('resort.email') }}</a>
            @endif
            @if(config('resort.phone'))
                · <a href="{{ config('resort.phone_href') }}" class="text-primary font-medium hover:underline">{{ config('resort.phone') }}</a>
            @endif
        </p>
    </section>

    <section class="space-y-3">
        <h2 class="font-heading text-xl sm:text-2xl font-semibold text-text">2. Accounts</h2>
        <p>
            Creating an account lets you request reservations, receive updates, and manage bookings linked to your email.
            You must provide accurate contact details and keep your password confidential. You are responsible for activity under your account.
        </p>
    </section>

    <section class="space-y-3">
        <h2 class="font-heading text-xl sm:text-2xl font-semibold text-text">3. Reservations and payment</h2>
        <p>
            Submitting a reservation request does not guarantee a confirmed booking until the resort accepts it and any required deposit is verified.
            Prices and availability follow the saved quote and policy version accepted during booking. Future terms do not rewrite historical accepted terms or prices.
        </p>
        <p>
            Guests submit at least a 50% down payment and may pay 100% online. Submitted proof remains under review until staff verify the transfer. Function halls needing a staff quotation receive an exact quote before payment. Any remaining balance is due at physical check-in.
        </p>
        <p>
            Before physical check-in, including no-show, verified payments are eligible for full refund. Staff receive cancellation requests and notify the owner; the owner approves refunds; administrators record that approval and the funds returned. Request, approval, and return are separate stages. Physical check-in ends this eligibility.
        </p>
        <p>
            Rescheduling has no notice deadline before physical check-in. Staff review the new service, dates, availability, and price difference before confirmation. Cottage session changes require staff assistance.
        </p>
        <p>
            Guests must present a valid ID matching the lead guest details at check-in when staff request it.
        </p>
    </section>

    @include('pages.partials.confirmed-booking-policy')
    <section class="space-y-3">
        <h2 class="font-heading text-xl sm:text-2xl font-semibold text-text">4. Guest conduct</h2>
        <p>
            Guests agree to follow house rules published by the resort, respect other visitors, and use facilities only as intended.
            The resort may refuse or end a stay for safety, damage, or serious rule violations, subject to applicable law.
        </p>
    </section>

    <section class="space-y-3">
        <h2 class="font-heading text-xl sm:text-2xl font-semibold text-text">5. Acceptable use of this website</h2>
        <p>
            Do not misuse the site (for example by attempting unauthorized access, scraping, or submitting false booking information).
            We may suspend accounts that abuse the service.
        </p>
    </section>

    <section class="space-y-3">
        <h2 class="font-heading text-xl sm:text-2xl font-semibold text-text">6. Privacy</h2>
        <p>
            How we collect and use personal data is described in our
            @if(! empty($asModal))
                <button type="button" @click="legalDoc = 'privacy'" class="font-semibold text-primary hover:underline">Privacy Notice</button>.
            @else
                <a href="{{ route('privacy') }}" class="text-primary font-medium hover:underline">Privacy Notice</a>.
            @endif
        </p>
    </section>

    <section class="space-y-3">
        <h2 class="font-heading text-xl sm:text-2xl font-semibold text-text">7. Changes</h2>
        <p>
            We may update these terms as the booking service changes. The date above shows the latest revision. Continued use after changes means you accept the updated terms.
        </p>
    </section>

    <p>
        Questions about these terms?
        <a href="{{ route('contact') }}" class="text-primary font-medium hover:underline">Contact us</a>.
    </p>
</div>
