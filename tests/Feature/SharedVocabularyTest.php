<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The vocabulary the guest and the staff member share.
 *
 * Three rules live here, because all three are things both sides of the desk
 * render — sometimes for the same record, and always in the same product:
 *
 *  0. Palette. The resort has one palette (see `tailwind.config.js`), and it is
 *     not a matter of taste per surface: a gray-200 border on a guest page and a
 *     gray-200 border on a staff page are the same mistake. This rule is
 *     therefore the widest in the suite — every view, every script, and the
 *     stylesheet — because the leak it was written for (default Tailwind colours
 *     surviving a redesign because they lived in one file nobody rescanned) has
 *     now happened three times: in the back office's Blade, in an admin script
 *     that wrote `text-gray-500` into the results region, and in the `@apply`
 *     rules of `app.css` that no Blade scan can see.
 *
 *  1. Money. `₱1,350.50` is one amount, and the guest reads it on a facility
 *     page, in their booking summary, in their payment history and in the
 *     arrival-pass email while staff read the same reservation, payment and
 *     report. Two hand-written peso symbols are two chances to disagree, so the
 *     symbol is written exactly once — in `x-amount`, which is
 *     `App\Support\Money`, and in `js/money.js`, which mirrors it for the live
 *     preview. `MoneyFormatTest` proves those three agree; this proves nobody
 *     added a fourth.
 *
 *  2. Status. A state owns its word (`label()`) and its colour (`tone()`), and
 *     `x-badge` asks for both. Handing the badge anything else — the stored
 *     value, or a string built from it — is how the guest portal and the back
 *     office started describing one booking two ways.
 *
 *  3. Cancellation. Confirmed policy permits a guest request before physical
 *     arrival, including no-show; only the owner approves refunds and records
 *     returned funds. Guest surfaces must distinguish all three stages.
 *
 *  4. The arrival pass. A pass is issued in two places — the Arrival tab and the
 *     printable page behind a saved link — and a guest holds one while reading
 *     the other, so both have to explain a missing pass in the same words. The
 *     answer lives in `Reservation::arrivalPassNotice()`; a view that writes its
 *     own is a second answer, and the headlines that announce a pass have one
 *     home each.
 *
 * Dates are deliberately not here: a staff member reading "Sep 16" as the wrong
 * September is a booking error, and that rule stays with the back office in
 * `AdminDateVocabularyTest` until the guest surfaces are held to it too.
 *
 * A `vocabulary-guard:` comment on the line (or the line above) excuses it, so
 * an exception stays local, explained, and visible rather than loosening the
 * rule for everyone.
 */
class SharedVocabularyTest extends TestCase
{
    private const PALETTE_PATTERN = '/\b(?:bg|text|border|ring|divide|outline|decoration|placeholder|caret|fill|stroke|shadow|from|via|to)-(?:gray|slate|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)-[0-9]{2,3}\b/';

    /**
     * Where the palette rule does not apply, each for a stated reason.
     *
     * Email is the exception because it is not a page: no Tailwind build reaches
     * a mail client, so those templates carry inline styles and a table layout
     * instead of utility classes. `white` and `black` are never flagged anywhere
     * by the pattern above — they belong on a primary surface and in overlays.
     */
    private const PALETTE_SKIPPED = [
        'mail/' => 'email bodies: no Tailwind build reaches a mail client, so they carry inline styles',
        'vendor/mail/' => 'the mail theme that renders those bodies',
    ];

    /**
     * A peso symbol counts as a rendered amount only when a number-producing
     * thing follows it: an interpolation, a quote or variable (a PHP or JS
     * string being built), a formatting call, or a literal digit. "Amount (₱)"
     * is a label, so it is not reported.
     */
    private const MONEY_PATTERN = '/(?:₱|&#8369;|&#x20B1;)\s*(?:\{\{|[0-9\'"$]|[a-zA-Z_]+\s*\()/u';

    /**
     * A colour written straight into the stylesheet. Written as a colour and not
     * as an id: `#admin-sidebar` cannot match, because `m` is not a hex digit.
     */
    private const LITERAL_COLOUR_PATTERN = '/(?<![a-zA-Z0-9_-])#[0-9a-fA-F]{3,8}(?![a-zA-Z0-9_-])/';

    /** Money the browser formats itself must delegate: one implementation, plus its mirror. */
    private const DECIMALS_PATTERN = '/(?:minimumFractionDigits|maximumFractionDigits|toFixed\(\s*2\s*\))/';

    /** A state handed to a chip as a word built from it, rather than as the state. */
    private const STATUS_AS_WORD_PATTERN = '/(?:->value|::value\b|->label\(\))/';

    private const GUARD_MARKER = 'vocabulary-guard';

    /**
     * A cancellation word in copy a guest can read.
     *
     * The booking columns keep their own names — `cancellation_requested_at`
     * records a withdrawal request, not a cancellation the guest performed — so
     * those are removed before the scan rather than excused line by line.
     *
     * The seeded FAQ is deliberately not scanned: the published policy has to
     * name what the resort does not accept, and `CustomerFaqTest` holds that
     * sentence instead.
     */
    private const CANCELLATION_PATTERN = '/\bcancel/i';

    /**
     * Arrival-pass copy, and the one file that may print it. A `null` owner means
     * the copy belongs to the model and must not appear in a view at all.
     */
    private const PASS_COPY = [
        'Show this at the desk' => 'pages/arrival-pass.blade.php',
        'Show this at the front desk' => 'customer/reservations/partials/arrival.blade.php',
        'the 50% deposit QR and proof upload open then' => null,
    ];

    /** The booking columns, which are not copy. */
    private const CANCELLATION_SCHEMA_WORDS = [
        'cancellation_requested_at',
        'cancellation_request_reason',
        'cancellation_reason',
        'cancelled_at',
    ];

    /** Everything a visitor or a signed-in guest can read. */
    private const GUEST_SURFACES = ['customer/', 'pages/'];

    /** Files that may write the peso symbol by hand, each for a stated reason. */
    private const MONEY_ALLOWED = [
        'components/amount.blade.php' => 'it is the component that owns the symbol and the format',
        'components/admin/report-chart.blade.php' => 'axis text and <title> tooltips sit inside <svg>, where HTML is not allowed',
        'admin/reports/print.blade.php' => 'standalone print document: it loads no Tailwind build, so the component\'s classes would not exist',
    ];

    /** The browser half of the format. Everything else imports it. */
    private const MONEY_SCRIPT = 'js/money.js';

    public function test_no_view_uses_a_default_tailwind_palette(): void
    {
        $offences = [];

        foreach ($this->viewFiles() as $path => $contents) {
            if ($this->paletteExempt($path)) {
                continue;
            }

            foreach ($this->lineOffences($contents, self::PALETTE_PATTERN) as $offence) {
                $offences[] = $path.':'.$offence;
            }
        }

        $this->assertSame([], $offences, implode("\n", [
            'These views use a default Tailwind colour instead of the resort palette.',
            'Replace it with a token: surface, background, muted, border, text, text-muted,',
            'primary (with -light/-dark/-hover), secondary, accent (with -light), success,',
            'warning, danger, info — each of those last four with a -light tint.',
            'The one place a status colour belongs is a token, so that a guest notice and a',
            'staff notice explaining the same thing cannot be two different colours.',
            '',
            ...$offences,
        ]));
    }

    /**
     * Scripts and the stylesheet paint too. Both have already hidden a leak from
     * a Blade-only scan: the failed live-filter request wrote `text-gray-500`
     * into the results region from inside the script, and the chart component in
     * `app.css` kept four gray utilities through every Blade sweep.
     */
    public function test_no_script_or_stylesheet_uses_a_default_tailwind_palette(): void
    {
        $files = $this->styleSourceFiles();

        // Named, so a broken scan root fails here rather than quietly checking
        // nothing: "no files found" would make every rule below pass vacuously.
        $this->assertArrayHasKey('css/app.css', $files, 'The stylesheet was not found to check — the scan root is wrong.');
        $this->assertArrayHasKey('js/app.js', $files, 'No scripts were found to check — the scan root is wrong.');

        $offences = [];

        foreach ($files as $path => $contents) {
            foreach ($this->lineOffences($contents, self::PALETTE_PATTERN) as $offence) {
                $offences[] = $path.':'.$offence;
            }
        }

        $this->assertSame([], $offences, implode("\n", [
            'These scripts or stylesheets carry a default Tailwind colour. What they insert',
            'into the page, or compile into it, is part of the page, so it answers to the same',
            'palette as the Blade beside it.',
            '',
            ...$offences,
        ]));
    }

    /**
     * The palette lives in two files, and only one of them is class names.
     *
     * `@tailwindcss/forms` and Tailwind's own preflight paint every unstyled
     * control from theme steps no view names: `gray.400` is the placeholder,
     * `gray.500` is a control's resting border, the placeholder, and the chevron
     * the plugin bakes into `select` as a data-URI SVG, and `blue.600` is the
     * check mark, the radio dot and the focus ring. Pointed back at the stock
     * greys, the guest portal renders a cool grey and a blue tick against a warm
     * brown palette — and every other rule in this file still passes, because
     * there is no class name to find. So this asserts the steps themselves:
     * the greys are the muted ink token, the blue is the primary token.
     */
    public function test_the_library_paints_in_the_palette_as_well(): void
    {
        $node = $this->nodeBinary();

        if ($node === null) {
            $this->markTestSkipped('Node is not available to compile the Tailwind config.');
        }

        // Compiled rather than read: the claim is about what reaches the page,
        // not about what the config declares. A plugin upgrade that reads a
        // different theme step fails here, and so does a config with the step
        // quietly put back to its stock value.
        $config = str_replace('\\', '/', base_path('tailwind.config.js'));
        $from = str_replace('\\', '/', resource_path('css/palette-probe.css'));
        // The probe lives inside the project, because it imports `postcss` and
        // `tailwindcss` and a scratch directory has no node_modules above it.
        $probe = base_path('storage/framework/palette-probe-'.Str::random(8).'.mjs');

        file_put_contents($probe, implode("\n", [
            'import postcss from \'postcss\';',
            'import tailwind from \'tailwindcss\';',
            'const input = `',
            '  @tailwind base;',
            '  @tailwind utilities;',
            '  .palette-probe-muted { @apply text-text-muted; }',
            '  .palette-probe-primary { @apply text-primary; }',
            '`;',
            'const result = await postcss([tailwind({ config: '.json_encode($config).' })])',
            '  .process(input, { from: '.json_encode($from).' });',
            'console.log(result.css);',
        ]));

        try {
            exec(escapeshellarg($node).' '.escapeshellarg($probe).' 2>&1', $output, $status);
        } finally {
            @unlink($probe);
        }

        $css = implode("\n", $output);

        $this->assertSame(0, $status, 'Could not compile the Tailwind base layer: '.$css);

        // The three steps the libraries read, as they are before the theme names
        // them: gray-400, gray-500 and blue-600.
        foreach (['#9ca3af' => 'gray-400 (the placeholder preflight reads)', '#6b7280' => 'gray-500 (a control border, placeholder and select chevron)', '#2563eb' => 'blue-600 (the check mark, radio dot and focus ring)'] as $stock => $what) {
            $this->assertStringNotContainsString(
                $stock,
                $css,
                'The compiled base layer still carries '.$stock.' — '.$what.'. Point that theme step at a token in tailwind.config.js; do not override the plugin\'s output instead.',
            );
        }

        // What a token compiles to and what the control is painted in are read
        // from the same build, so this asserts the relationship rather than a
        // colour written here, where it could drift from the theme.
        $muted = $this->colourOf($css, '/\.palette-probe-muted\s*\{[^}]*color:\s*([^;]+)/', 'the text-muted token');
        $placeholder = $this->colourOf($css, '/input::placeholder,\s*textarea::placeholder\s*\{[^}]*?color:\s*(#[0-9a-fA-F]{3,8})/', 'the placeholder rule');
        $this->assertSame($muted, $placeholder, 'A placeholder is not painted in the text-muted token.');

        $primary = $this->colourOf($css, '/\.palette-probe-primary\s*\{[^}]*color:\s*([^;]+)/', 'the primary token');
        $ring = $this->colourOf($css, "/input:where\\(\\[type=['\"]?(?:checkbox|radio)['\"]?\\]\\):focus[^{]*\\{[^}]*?--tw-ring-color:\\s*([^;]+)/", 'the focus ring');
        $this->assertSame($primary, $ring, 'A focused control does not carry a primary ring.');
    }

    /**
     * One colour out of the compiled CSS, normalised so the hex a theme step
     * produces and the `rgb(…)` an `@apply` produces compare equal.
     */
    private function colourOf(string $css, string $pattern, string $what): string
    {
        $this->assertMatchesRegularExpression($pattern, $css, 'Could not find '.$what.' in the compiled base layer.');

        preg_match($pattern, $css, $matches);
        $value = trim($matches[1]);

        if (preg_match('/#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})\b/', $value, $hex) === 1) {
            $hex = $hex[1];

            if (strlen($hex) === 3) {
                $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
            }

            $rgb = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        } else {
            preg_match_all('/\d+/', $value, $numbers);
            $rgb = array_map('intval', array_slice($numbers[0], 0, 3));
        }

        $this->assertCount(3, $rgb, 'Could not read a colour out of "'.$value.'" for '.$what.'.');

        return implode(',', $rgb);
    }

    /**
     * A colour written as a literal in the stylesheet is a token nobody can
     * find: it survives every palette rename, and the next person to correct a
     * library's default writes the hex beside it instead of fixing the step the
     * library reads. `theme(...)` keeps the value in `tailwind.config.js`.
     *
     * The pattern is a colour, not an id selector (`#admin-sidebar` cannot match
     * it), and it will say so if it ever fires on something that is not one.
     */
    public function test_the_stylesheet_names_its_colours_as_tokens(): void
    {
        $offences = [];

        foreach ($this->lineOffences(File::get(resource_path('css/app.css')), self::LITERAL_COLOUR_PATTERN) as $offence) {
            $offences[] = 'css/app.css:'.$offence;
        }

        $this->assertSame([], $offences, implode("\n", [
            'The stylesheet writes a colour as a literal instead of the token it stands for.',
            'Use `theme(\'colors.primary.DEFAULT\')` (or the token you mean) so the value keeps',
            'one home in tailwind.config.js — and so a palette change cannot miss it.',
            '',
            ...$offences,
        ]));
    }

    public function test_no_view_writes_the_peso_symbol_by_hand(): void
    {
        $offences = [];

        foreach ($this->viewFiles() as $path => $contents) {
            if (array_key_exists($path, self::MONEY_ALLOWED)) {
                continue;
            }

            foreach ($this->lineOffences($contents, self::MONEY_PATTERN) as $offence) {
                $offences[] = $path.':'.$offence;
            }
        }

        $this->assertSame([], $offences, implode("\n", [
            'These views format a peso amount by hand, on one side of the desk or the other.',
            'Use <x-amount :value="$amount" />, which guarantees two decimals, thousands',
            'separators, tabular figures, and a symbol the screen reader reads.',
            'Where Blade cannot render an element — an email body, a <option> label, a',
            'placeholder attribute, a JavaScript expression — go through App\Support\Money',
            'directly, or say why on the line (or the line above) with a `vocabulary-guard:`',
            'comment explaining why.',
            '',
            ...$offences,
        ]));
    }

    public function test_the_browser_formats_money_through_one_module(): void
    {
        $files = File::allFiles(resource_path('js'));
        $offences = [];

        foreach ($files as $file) {
            $path = 'js/'.str_replace('\\', '/', $file->getRelativePathname());

            if ($path === self::MONEY_SCRIPT) {
                continue;
            }

            $contents = $file->getContents();

            foreach ([self::MONEY_PATTERN => 'writes the peso symbol', self::DECIMALS_PATTERN => 'pins its own decimals'] as $pattern => $what) {
                foreach ($this->lineOffences($contents, $pattern) as $offence) {
                    $offences[] = $path.' '.$what.' — '.$offence;
                }
            }
        }

        $this->assertSame([], $offences, implode("\n", [
            'These scripts format money themselves instead of importing js/money.js,',
            'which is the browser half of App\Support\Money. A second implementation',
            'prints a figure the saved record does not, and the guest sees both.',
            '',
            ...$offences,
        ]));
    }

    public function test_a_status_chip_is_handed_the_state_not_a_word_built_from_it(): void
    {
        $offences = [];

        foreach ($this->viewFiles() as $path => $contents) {
            foreach ($this->statusOffences($contents) as $offence) {
                $offences[] = $path.':'.$offence;
            }
        }

        $this->assertSame([], $offences, implode("\n", [
            'These chips are handed a word built from a state rather than the state itself.',
            'Pass the enum — `<x-badge :status="$reservation->status" />` — so the chip takes',
            'the word from label() and the colour from tone(). A chip given ->value shows the',
            'database spelling ("pending") and one given ->label() has no colour of its own,',
            'which is how the guest portal and the back office drift apart.',
            '',
            ...$offences,
        ]));
    }

    /**
     * A rule that quietly stops matching is worse than no rule, because it reads
     * as a green light. These are the exact mistakes each pattern exists for.
     */
    public function test_each_guard_catches_the_mistake_it_exists_for(): void
    {
        $this->assertNotEmpty($this->lineOffences('<div class="rounded-xl border-gray-200 bg-white">', self::PALETTE_PATTERN));
        $this->assertNotEmpty($this->lineOffences('<span class="text-amber-700">', self::PALETTE_PATTERN));
        $this->assertNotEmpty($this->lineOffences('@apply text-gray-400;', self::PALETTE_PATTERN));
        $this->assertNotEmpty($this->lineOffences('to-emerald-950', self::PALETTE_PATTERN));
        $this->assertNotEmpty($this->lineOffences('outline: 2px solid #623f1f;', self::LITERAL_COLOUR_PATTERN));
        $this->assertNotEmpty($this->lineOffences('background-color: #fff;', self::LITERAL_COLOUR_PATTERN));
        $this->assertSame([], $this->lineOffences("outline: 2px solid theme('colors.primary.DEFAULT');", self::LITERAL_COLOUR_PATTERN));
        // The ids this stylesheet actually addresses are not colours, and the
        // rule must not fire on them.
        $this->assertSame([], $this->lineOffences('#admin-sidebar.is-rail .admin-nav-badge {', self::LITERAL_COLOUR_PATTERN));
        $this->assertSame([], $this->lineOffences('#admin-notifications { display: none; }', self::LITERAL_COLOUR_PATTERN));
        $this->assertSame([], $this->lineOffences('<div class="rounded-xl border-border bg-surface text-text-muted">', self::PALETTE_PATTERN));
        $this->assertSame([], $this->lineOffences('<span class="text-white bg-primary hover:bg-primary-hover">', self::PALETTE_PATTERN));
        $this->assertSame([], $this->lineOffences('<div class="bg-warning-light border-warning text-warning">', self::PALETTE_PATTERN));
        // A theme override like `[...]` or a brand hex is not a palette shade.
        $this->assertSame([], $this->lineOffences('<a class="bg-[#0866ff] text-secondary-foreground">', self::PALETTE_PATTERN));

        $this->assertNotEmpty($this->lineOffences('<x-danger-button>Cancel reservation</x-danger-button>', self::CANCELLATION_PATTERN));
        $this->assertNotEmpty($this->lineOffences('Online cancellation stays open until 48 hours before check-in.', self::CANCELLATION_PATTERN));
        $this->assertSame([], $this->lineOffences('Reschedule a pending or approved booking online.', self::CANCELLATION_PATTERN));
        // The booking columns are not copy, so the scan takes them out first.
        $this->assertSame([], $this->lineOffences(str_replace(self::CANCELLATION_SCHEMA_WORDS, '', '{{ $reservation->cancellation_requested_at }}'), self::CANCELLATION_PATTERN));
        $this->assertSame([], $this->lineOffences("{{-- vocabulary-guard: dismisses the dialog --}}\n<button>Cancel</button>", self::CANCELLATION_PATTERN));

        $this->assertNotEmpty($this->passCopyOffences(['customer/reservations/partials/stay.blade.php' => '<p>Show this at the desk</p>']));
        $this->assertNotEmpty($this->passCopyOffences(['customer/reservations/partials/arrival.blade.php' => '<p>Show this at the front desk</p>']));
        $this->assertNotEmpty($this->passCopyOffences(['customer/reservations/partials/arrival.blade.php' => '<p>Staff are reviewing this request — the 50% deposit QR and proof upload open then.</p>']));
        $this->assertNotEmpty($this->passCopyOffences(['pages/arrival-pass.blade.php' => '<p>Show this at the front desk</p>']));
        $this->assertSame([], $this->passCopyOffences([
            'pages/arrival-pass.blade.php' => '<p>Show this at the desk</p>',
            'customer/reservations/partials/arrival.blade.php' => '<p>Show this at the front desk</p>',
        ]));

        $this->assertNotEmpty($this->lineOffences('{{ ₱number_format($balance, 2) }}', self::MONEY_PATTERN));
        $this->assertNotEmpty($this->lineOffences("x-text=\"'₱' + previewData.total\"", self::MONEY_PATTERN));
        $this->assertNotEmpty($this->lineOffences('Cottage reservation: ₱500 per session', self::MONEY_PATTERN));
        $this->assertNotEmpty($this->lineOffences('<td>₱{{ $payment->amount }}</td>', self::MONEY_PATTERN));
        $this->assertSame([], $this->lineOffences('<label for="amount">Amount (₱)</label>', self::MONEY_PATTERN));
        $this->assertSame([], $this->lineOffences('<x-amount :value="$payment->amount" />', self::MONEY_PATTERN));
        $this->assertSame([], $this->lineOffences("{{-- vocabulary-guard: an <option> holds text only --}}\n₱{{ \$fee }}", self::MONEY_PATTERN));

        $this->assertNotEmpty($this->lineOffences('const total = value.toFixed(2);', self::DECIMALS_PATTERN));
        $this->assertNotEmpty($this->lineOffences('value.toLocaleString("en-PH", { minimumFractionDigits: 2 });', self::DECIMALS_PATTERN));
        $this->assertSame([], $this->lineOffences('return pesos(total);', self::DECIMALS_PATTERN));

        $this->assertNotEmpty($this->statusOffences('<x-badge :status="$reservation->status->value" />'));
        $this->assertNotEmpty($this->statusOffences('<x-badge :status="$payment->status->label()" />'));
        $this->assertNotEmpty($this->statusOffences('<x-badge status="{{ $inquiry->status->value }}" />'));
        $this->assertNotEmpty($this->statusOffences("<x-badge\n    :status=\"\$reservation->status->value\"\n    variant=\"compact\"\n/>"));
        $this->assertSame([], $this->statusOffences('<x-badge :status="$reservation->status" />'));
        $this->assertSame([], $this->statusOffences('<x-badge :status="$activity->actionLabel()" />'));
        $this->assertSame([], $this->statusOffences('<x-badge :status="App\Enums\PaymentStatus::Verified" variant="compact" />'));
    }

    public function test_the_scan_reaches_both_sides_of_the_desk(): void
    {
        $files = $this->viewFiles();

        // Named members, so a broken scan root fails here rather than quietly
        // shrinking the surface these rules apply to. Both sides are named
        // because the point is that one amount and one state serve them both.
        $this->assertArrayHasKey('components/amount.blade.php', $files);
        $this->assertArrayHasKey('customer/reservations/show.blade.php', $files);
        $this->assertArrayHasKey('customer/payments/index.blade.php', $files);
        $this->assertArrayHasKey('admin/reservations/show.blade.php', $files);
        $this->assertArrayHasKey('pages/resort.blade.php', $files);

        $this->assertSame([], $this->statusOffences(file_get_contents(resource_path('js/money.js')) ?? ''), 'The money module formats money, not status — no chip rule should apply to it.');
    }

    /**
     * Published cancellation requests must not be confused with owner approval
     * or money returned; old nonrefundable and deadline claims must not return.
     */
    public function test_guest_surfaces_distinguish_refund_requests_owner_approval_and_returned_funds(): void
    {
        // Confirmed policy now permits a request before physical arrival. It must never promise that requesting already returns funds.
        $views = $this->viewFiles();
        $discussion = $views['customer/reservations/partials/refund-flow.blade.php'];
        $policy = $views['pages/partials/confirmed-booking-policy.blade.php'];
        $this->assertStringContainsString('Request cancellation and full refund', $discussion);
        $this->assertStringContainsString('Approval does not send money.', $discussion);
        $this->assertStringContainsString('The owner approves refunds; administrators record that approval and the funds returned', $policy);
        $this->assertStringContainsString('physical check-in', $policy);
        foreach (['customer/booking/unified-workspace.blade.php', 'customer/booking/group-quote-checkout.blade.php', 'pages/partials/terms-body.blade.php'] as $path) {
            $this->assertStringNotContainsString('nonrefundable', $views[$path]);
            $this->assertStringNotContainsString('non-refundable', $views[$path]);
            $this->assertStringNotContainsString('Request a change at least', $views[$path]);
        }
    }

    /**
     * The guest reads the Arrival tab, and later the page behind a saved link.
     * Both say why there is no pass, and they say it once.
     */
    public function test_the_arrival_pass_explains_itself_in_one_voice(): void
    {
        $offences = $this->passCopyOffences($this->viewFiles());

        $this->assertSame([], $offences, implode("\n", [
            'Arrival-pass copy has been written a second time, or has moved.',
            'The reason there is no pass yet is answered once, in',
            'Reservation::arrivalPassNotice(), which both the Check in tab and the',
            'printable page render. A pass headline belongs to the one view that',
            'prints a pass. Two answers to one guest is how the account and the',
            'screenshot start disagreeing.',
            '',
            ...$offences,
        ]));

        // And the copy has not simply gone missing: the model is its home.
        $this->assertStringContainsString(
            'the 50% deposit QR and proof upload open then',
            File::get(app_path('Models/Reservation.php')),
            'Reservation::arrivalPassNotice() is where a guest reads why there is no pass.',
        );
    }

    /**
     * @param  array<string, string>  $views
     * @return list<string>
     */
    private function passCopyOffences(array $views): array
    {
        $offences = [];

        foreach (self::PASS_COPY as $copy => $owner) {
            $carriers = [];

            foreach ($views as $path => $contents) {
                if (Str::contains($contents, $copy)) {
                    $carriers[] = $path;
                }
            }

            if ($owner === null) {
                foreach ($carriers as $path) {
                    $offences[] = $path.': "'.$copy.'" is the model\'s to say, not a view\'s';
                }

                continue;
            }

            if ($carriers !== [$owner]) {
                $offences[] = '"'.$copy.'" is written in '.($carriers === [] ? 'no view at all' : implode(', ', $carriers)).'; '.$owner.' owns it';
            }
        }

        return $offences;
    }

    /**
     * The route is the enforcement. Hiding the button while leaving the endpoint
     * in place would let a bookmarked URL cancel a booking the policy refuses.
     */
    public function test_the_customer_cancel_route_is_gone(): void
    {
        $names = collect(Route::getRoutes())->map(fn ($route) => (string) $route->getName())->all();

        $this->assertNotContains('customer.reservations.cancel', $names, 'The guest cancel route is back: guests reschedule, only the resort cancels.');
        $this->assertContains('customer.reservations.withdraw', $names, 'Withdrawal is the only self-service exit a guest has.');
    }

    /**
     * Every Blade source in the application, keyed by the path a reader would
     * recognise from the repository rather than an absolute Windows path.
     *
     * @return array<string, string>
     */
    private function viewFiles(): array
    {
        $root = resource_path('views');
        $files = [];

        foreach (File::allFiles($root) as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            $files[Str::after($path, str_replace('\\', '/', $root).'/')] = $file->getContents();
        }

        // Asserted before the walk, because "no views found" would make every
        // rule above pass vacuously.
        $this->assertArrayHasKey('admin/dashboard.blade.php', $files, 'No views were found to check — the scan root is wrong.');

        return $files;
    }

    private function nodeBinary(): ?string
    {
        if (! function_exists('exec')) {
            return null;
        }

        exec('node --version 2>&1', $output, $status);

        return $status === 0 ? 'node' : null;
    }

    /**
     * The page's other two painters: the stylesheet the build compiles, and the
     * scripts that write markup into the page at runtime.
     *
     * @return array<string, string>
     */
    private function styleSourceFiles(): array
    {
        $files = ['css/app.css' => File::get(resource_path('css/app.css'))];

        foreach (File::allFiles(resource_path('js')) as $file) {
            $files['js/'.str_replace('\\', '/', $file->getRelativePathname())] = $file->getContents();
        }

        return $files;
    }

    /**
     * Email is not a page, so the palette rule does not reach it.
     *
     * @see self::PALETTE_SKIPPED for the reasons.
     */
    private function paletteExempt(string $path): bool
    {
        foreach (array_keys(self::PALETTE_SKIPPED) as $prefix) {
            if (Str::startsWith($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> "line: the source text", one per match */
    private function lineOffences(string $contents, string $pattern): array
    {
        $offences = [];
        $lines = explode("\n", $contents);

        foreach ($lines as $index => $line) {
            if (preg_match($pattern, $line) === 1 && ! $this->isExcused($lines, $index)) {
                $offences[] = ($index + 1).': '.trim($line);
            }
        }

        return $offences;
    }

    /**
     * @return list<string>
     */
    private function statusOffences(string $contents): array
    {
        $lines = explode("\n", $contents);
        $offences = [];

        foreach ($lines as $index => $line) {
            if (! Str::contains($line, '<x-badge')) {
                continue;
            }

            // A badge call may span lines — the variant attribute is often on the
            // next one — so the argument is read from the tag's own window rather
            // than from the single line the tag starts on.
            if (preg_match(self::STATUS_AS_WORD_PATTERN, $this->chipWindow($lines, $index)) === 1 && ! $this->isExcused($lines, $index)) {
                $offences[] = ($index + 1).': '.trim($line);
            }
        }

        return $offences;
    }

    /**
     * The text of one chip call: from the tag to its closing `/>`, at most a few
     * lines, so a multi-line call is read whole and the next element is not.
     *
     * @param  list<string>  $lines
     */
    private function chipWindow(array $lines, int $index): string
    {
        $window = '';
        $limit = min(count($lines), $index + 8);

        for ($line = $index; $line < $limit; $line++) {
            $window .= $lines[$line].' ';

            if (Str::contains($lines[$line], '/>')) {
                break;
            }
        }

        return $window;
    }

    /**
     * The marker may sit on the offending line or on the line above it, so an
     * explanation can be written where a comment is readable.
     *
     * @param  list<string>  $lines
     */
    private function isExcused(array $lines, int $index): bool
    {
        return Str::contains($lines[$index], self::GUARD_MARKER)
            || ($index > 0 && Str::contains($lines[$index - 1], self::GUARD_MARKER));
    }
}
