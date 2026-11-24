<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * One rule, on purpose: a rendered date in the back office carries its year.
 *
 * "Sep 16" is how a stay gets filed against the wrong September, and the cost of
 * that mistake is a guest standing at the gate — so this reads the Blade sources
 * rather than a rendered page, failing at the moment the format is written, and
 * names the file and line so the fix is obvious.
 *
 * A clock time ("3:30 PM") is fine, because there is no date in it to be
 * ambiguous about, and an input value (Y-m-d, Y-m-d\TH:i) passes by naming a year.
 *
 * The other two conventions moved out of this file when they stopped being
 * back-office concerns:
 *
 *  - the palette is now one rule for the whole application — every view, every
 *    script, and the stylesheet — in `SharedVocabularyTest`, because a guest page
 *    and a staff page disagreeing about what "gray" means was never a
 *    back-office problem;
 *  - money and status are read by both sides for the same record, so they live
 *    there too.
 *
 * The scan still covers the shared components an admin view reaches through
 * <x-...>, however deep: a component that prints a year-less date shows up on an
 * admin screen exactly as if the date were written there. Guest-only components
 * no admin screen renders stay out of scope until this rule is extended to them.
 */
class AdminDateVocabularyTest extends TestCase
{
    private const DATE_PATTERN = '/(?:format|translatedFormat)\(\s*([\'"])([^\'"]*)\1/';

    /** Tokens that answer "which year?". */
    private const YEAR_TOKENS = ['Y', 'o', 'y'];

    /** Tokens that answer "which day or month?" and therefore need a year. */
    private const CALENDAR_TOKENS = ['d', 'D', 'j', 'l', 'N', 'S', 'w', 'z', 'F', 'm', 'M', 'n', 't'];

    public function test_admin_views_never_render_a_date_without_a_year(): void
    {
        $offences = [];

        foreach ($this->adminViewFiles() as $path => $contents) {
            foreach ($this->dateOffences($contents) as $offence) {
                $offences[] = $path.':'.$offence;
            }
        }

        $this->assertSame([], $offences, implode("\n", [
            'These admin views render a date or month without a year, which is how a',
            'booking for "Sep 16" is read as the wrong September. Add Y to the format,',
            'or use <x-admin.date-time> / <x-admin.date-range>, which always carry it.',
            'Time-only formats such as "g:i A" are fine and are not reported.',
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
        $this->assertNotEmpty($this->dateOffences("{{ \$reservation->check_in->format('M j, g:i A') }}"));
        $this->assertNotEmpty($this->dateOffences("{{ \$payment->payment_date->format('M j') }}"));
        $this->assertSame([], $this->dateOffences("{{ \$reservation->check_in->format('M j, Y g:i A') }}"));
        $this->assertSame([], $this->dateOffences("{{ \$reservation->check_in->format('g:i A') }}"));
        $this->assertSame([], $this->dateOffences("{{ \$startDate?->format('Y-m-d') }}"));
    }

    public function test_the_scan_reaches_the_shared_components_the_back_office_paints(): void
    {
        $files = $this->adminViewFiles();

        // Named members of the closure, so a broken <x-...> walk fails here
        // instead of quietly shrinking the surface the rule applies to.
        $this->assertArrayHasKey('components/badge.blade.php', $files);
        $this->assertArrayHasKey('components/input-error.blade.php', $files);
        $this->assertArrayHasKey('components/payment-overpayment.blade.php', $files);

        // ...and the one it reaches through payment-overpayment rather than
        // directly, which is the whole reason the walk is transitive.
        $this->assertArrayHasKey('components/alert.blade.php', $files);

        // A guest-only component no admin screen renders stays out of scope.
        $this->assertArrayNotHasKey('components/review-quote.blade.php', $files);
    }

    /**
     * Every Blade source the back office paints, keyed by a path that reads like
     * the one in the repository rather than an absolute Windows path.
     *
     * @return array<string, string>
     */
    private function adminViewFiles(): array
    {
        $roots = [
            'views/admin' => '*.blade.php',
            'views/components/admin' => '*.blade.php',
            'views/vendor/pagination' => '*.blade.php',
            'views/layouts' => 'admin.blade.php',
        ];

        $files = [];

        foreach ($roots as $directory => $pattern) {
            $root = resource_path($directory);

            if (! File::isDirectory($root)) {
                continue;
            }

            foreach (File::allFiles($root) as $file) {
                if (! fnmatch($pattern, $file->getFilename()) && ! fnmatch($pattern, $file->getRelativePathname())) {
                    continue;
                }

                // Both sides are normalised to forward slashes first: on Windows
                // resource_path() hands back backslashes while Symfony's pathname
                // uses slashes, and a prefix that does not match leaves the whole
                // absolute path as the key — which silently disarms the allow-list.
                $prefix = str_replace('\\', '/', resource_path('views')).'/';
                $path = str_replace('\\', '/', $file->getPathname());

                $files[Str::after($path, $prefix)] = $file->getContents();
            }
        }

        // Asserted before the walk, because "no admin views found" would make
        // every rule below pass vacuously.
        $this->assertArrayHasKey('admin/dashboard.blade.php', $files, 'No admin views were found to check — the scan root is wrong.');

        foreach ($this->sharedComponentsReachableFrom($files) as $key => $contents) {
            $files[$key] = $contents;
        }

        return $files;
    }

    /**
     * Follows <x-...> references out of the admin sources, transitively, and
     * returns the shared components it lands on. Names that resolve to a
     * class-based component or to no file at all (`x-admin-layout`, `x-slot`)
     * are skipped, and so is anything already part of the scan.
     *
     * @param  array<string, string>  $adminSources
     * @return array<string, string>
     */
    private function sharedComponentsReachableFrom(array $adminSources): array
    {
        $known = $adminSources;
        $found = [];
        $frontier = array_keys($adminSources);

        while ($frontier !== []) {
            $next = [];

            foreach ($frontier as $key) {
                foreach ($this->componentNamesIn($known[$key] ?? '') as $name) {
                    $path = null;

                    foreach (['views/components/'.$name.'.blade.php', 'views/components/'.$name.'/index.blade.php'] as $candidate) {
                        if (File::exists(resource_path($candidate))) {
                            $path = $candidate;
                            break;
                        }
                    }

                    $relative = Str::after($path ?? '', 'views/');

                    if ($path === null || array_key_exists($relative, $known)) {
                        continue;
                    }

                    $found[$relative] = File::get(resource_path($path));
                    $known[$relative] = $found[$relative];
                    $next[] = $relative;
                }
            }

            $frontier = $next;
        }

        return $found;
    }

    /** @return list<string> component names as paths, e.g. "admin/amount" */
    private function componentNamesIn(string $contents): array
    {
        preg_match_all('/<x-([a-zA-Z0-9.:_-]+)/', $contents, $matches);

        return array_values(array_unique(array_map(
            fn (string $name): string => str_replace(['::', '.'], '/', $name),
            $matches[1] ?? [],
        )));
    }

    /**
     * @return list<string> "line: the source text", one per match
     */
    private function lineNumbersMatching(string $contents, string $pattern): array
    {
        $offences = [];

        foreach (explode("\n", $contents) as $index => $line) {
            if (preg_match($pattern, $line) === 1) {
                $offences[] = ($index + 1).': '.trim($line);
            }
        }

        return $offences;
    }

    /** @return list<string> */
    private function dateOffences(string $contents): array
    {
        $lines = explode("\n", $contents);
        $offences = [];

        foreach ($lines as $index => $line) {
            if (preg_match_all(self::DATE_PATTERN, $line, $matches, PREG_SET_ORDER) === 0) {
                continue;
            }

            foreach ($matches as $match) {
                if (! $this->formatNeedsYear($match[2])) {
                    continue;
                }

                $offences[] = ($index + 1).': '.trim($line);
            }
        }

        return $offences;
    }

    /**
     * A format needs a year when it names a day or a month. A format that only
     * names clock parts ("g:i A", "H:i") does not, because there is no date in it
     * to be ambiguous about.
     */
    private function formatNeedsYear(string $format): bool
    {
        if (Str::contains($format, self::YEAR_TOKENS)) {
            return false;
        }

        return Str::contains($format, self::CALENDAR_TOKENS);
    }
}
