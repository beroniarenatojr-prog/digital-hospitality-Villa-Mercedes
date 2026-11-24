<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * What a guest holding a phone is owed, and what no page can render.
 *
 * Four claims live here, and they are all the same kind of defect: something
 * invisible to the eye and invisible to every other test, so it survives a
 * review that only reads the markup.
 *
 *  1. The safe area resolves. `env(safe-area-inset-bottom)` is 0 unless the
 *     viewport opts into `viewport-fit=cover`, which means the booking bar's
 *     bottom padding and every floating helper's offset were inert while
 *     reading as though they were handled. Nothing in the page changes when
 *     this breaks, so nothing else can catch it.
 *  2. The docked page says so. The chat launcher, back-to-top and footer are
 *     rendered by the layout, not by the booking wizard's own view, so the only
 *     thing that lets them keep clear of the bar in CSS is the name the body
 *     carries. That name is asserted rather than trusted, along with the one
 *     place the arithmetic now lives.
 *  3. The calendar is reachable and on-palette. `SharedVocabularyTest` proves no
 *     literal colour was written in the stylesheet, and that is all it can
 *     prove: it cannot see a colour a library paints. flatpickr's 39px day cell
 *     and its blue selected state reached the guest through exactly that blind
 *     spot, so the mapping from its states to tokens is asserted by name.
 *  4. The date field itself is a thumb target. DESIGN.md asks for 44px; the
 *     controls a form plugin paints are 40px, which is one class' difference and
 *     no scan's business.
 *
 * Not here on purpose: which picker flatpickr chooses on a touch device. That is
 * a fact about a device rather than about this repository, and the theme is
 * correct on either branch — asserting a guess would be worse than saying so.
 */
class MobileAvailabilityTest extends TestCase
{
    /**
     * The three fixed helpers, each of which has to climb by the dock.
     *
     * @var list<string>
     */
    private const DOCK_CONSUMERS = ['[data-back-to-top]', '[data-chat-launcher]', '[data-chat-panel]'];

    /**
     * Offsets that used to be utilities on the elements themselves, which is how
     * two answers to one question ended up in two files with no page where they
     * could be compared.
     *
     * @var list<string>
     */
    private const STALE_POSITION_UTILITIES = ['bottom-20', 'bottom-24', 'bottom-5', 'right-4', 'right-5'];

    public function test_the_guest_layout_opts_into_the_display_safe_area(): void
    {
        $this->assertStringContainsString(
            'viewport-fit=cover',
            $this->layout(),
            'Without `viewport-fit=cover` every `env(safe-area-inset-*)` in app.css resolves to 0, so the '
            .'booking bar\'s bottom padding and the floating helpers\' offsets are inert while still reading '
            .'as though they were handled.',
        );
    }

    public function test_the_page_that_docks_a_bar_names_itself_to_the_fixed_helpers(): void
    {
        $css = $this->stylesheet();

        $this->assertStringContainsString(
            'has-bottom-dock',
            $this->layout(),
            'The booking wizard pins a bar to the bottom of the screen. It has to say so on the body, or the '
            .'chat launcher, back-to-top and footer have no way to keep clear of it in CSS.',
        );

        // Named rather than assumed: a default that quietly became 0 would hide
        // every floating helper behind the bar it is meant to sit above.
        $this->assertStringContainsString('--dock-bottom: 5.75rem', $css, 'The dock height is no longer estimated in the stylesheet.');
        $this->assertStringContainsString('body:not(.has-bottom-dock)', $css, 'The dock height no longer falls back to zero off the docked page.');

        foreach (self::DOCK_CONSUMERS as $consumer) {
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($consumer, '/').'\s*\{[^}]*?var\(--dock-bottom\)/',
                $css,
                $consumer.' no longer reads `--dock-bottom`, so it will sit on top of a docked bar.',
            );
        }

        foreach (['components/back-to-top.blade.php', 'components/support-chat.blade.php'] as $view) {
            foreach (self::STALE_POSITION_UTILITIES as $utility) {
                $this->assertDoesNotMatchRegularExpression(
                    '/(?<![a-z-])'.preg_quote($utility, '/').'(?![0-9.])/',
                    $this->viewSource($view),
                    $view.' positions itself with `'.$utility.'` again; its offset belongs with the other docks '
                    .'in app.css, where it can be read against the bar it has to clear.',
                );
            }
        }
    }

    public function test_the_booking_calendar_is_a_thumb_target_and_on_palette(): void
    {
        $theme = $this->calendarTheme();

        // flatpickr ships this at 39px behind a single-class selector. Both
        // halves are load-bearing: the size is the touch minimum, and the
        // doubled class is what lets the rule win against a stylesheet that the
        // browser injects after this one.
        $this->assertMatchesRegularExpression(
            '/\.flatpickr-day\.flatpickr-day\s*\{[^}]*height:\s*2\.75rem/',
            $theme,
            'A day cell is no longer 44px tall, or lost the doubled selector that beats flatpickr\'s own chunk.',
        );

        $this->assertMatchesRegularExpression(
            '/\.flatpickr-day\.flatpickr-day\.selected[^{]*\{[^}]*background:\s*theme\(\'colors\.primary\.DEFAULT\'\)/',
            $theme,
            'The chosen day no longer carries the primary token, so the stock blue is showing again.',
        );

        $this->assertMatchesRegularExpression(
            '/\.flatpickr-day\.flatpickr-day\.flatpickr-disabled[^{]*\{[^}]*color:\s*theme\(\'colors\.text\.muted\'\)/',
            $theme,
            'An unavailable day no longer reads as muted ink.',
        );

        $this->assertMatchesRegularExpression(
            '/\.flatpickr-time\.flatpickr-time input\s*\{[^}]*font-size:\s*1rem/',
            $theme,
            'The time field dropped below 16px, which is what makes iOS Safari zoom the page when it is tapped.',
        );

        // The stock strip is 307.875px, the width of the stock panel. Left
        // alone inside a fluid panel, the grid hugs the left edge and the cells
        // stay pinned at the old width no matter how much room there is.
        $this->assertMatchesRegularExpression(
            '/\.flatpickr-calendar\.flatpickr-calendar \.flatpickr-days,\s*\.flatpickr-calendar\.flatpickr-calendar \.dayContainer\s*\{[^}]*width:\s*100%/s',
            $theme,
            'The day strip no longer fills the calendar, so the grid will not use the panel it is drawn in.',
        );
    }

    public function test_the_booking_date_triggers_are_a_thumb_target(): void
    {
        $wizard = $this->viewSource('components/reservation-schedule.blade.php');

        foreach (['date', 'departure'] as $field) {
            $this->assertMatchesRegularExpression(
                '/:id="\$prefix\.\x27-'.$field.'\x27"[^>]*class="[^"]*min-h-11/',
                $wizard,
                $field.' is a 40px control. The form plugin\'s padding is not the guest\'s problem: give it `min-h-11`.',
            );
        }
    }

    /** Named for what it reads: not Laravel's `view()`, which renders one. */
    private function viewSource(string $path): string
    {
        return File::get(resource_path('views/'.$path));
    }

    private function layout(): string
    {
        return $this->viewSource('layouts/customer.blade.php');
    }

    private function stylesheet(): string
    {
        return File::get(resource_path('css/app.css'));
    }

    /** The calendar block alone, so a token used elsewhere cannot satisfy these claims. */
    private function calendarTheme(): string
    {
        $css = $this->stylesheet();
        $start = strpos($css, '.flatpickr-calendar.flatpickr-calendar {');

        $this->assertNotFalse($start, 'The booking calendar is no longer themed in app.css.');

        return substr($css, $start);
    }
}
