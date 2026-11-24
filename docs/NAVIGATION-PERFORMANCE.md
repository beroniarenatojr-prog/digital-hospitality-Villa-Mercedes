# Navigation performance — 2026-11-11

## Confirmed causes and changes

The Laragon Apache site used PHP 8.3.30 with OPcache disabled. A local diagnostic
measured 248 ms loading the Composer autoloader and 727 ms bootstrapping Laravel,
before page-specific work. The database connection plus `SELECT 1` took 57 ms.
Static-file response time was approximately 23 ms. This places a substantial
part of the delay in PHP startup rather than network transfer or page styling.

Enabled OPcache in the active web PHP configuration:
`C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.ini`.
Apache was restarted and an HTTP diagnostic confirmed OPcache was active.
The previous configuration is saved beside it as
`php.ini.before-navigation-performance-20261111.bak`.

Windows also exposed a template-cache mismatch: the default view finder returned
mixed slash separators while `view:cache` compiled real paths with backslashes.
The same report template hashed to two different compiled files. The application
now canonicalizes found view paths, including namespaced views, before Blade
computes cache keys. No vendor files were changed. Regression tests cover both
normal and namespaced lookup. Views were precompiled successfully.

## Measurements and limits

Direct unauthenticated HTTP time to first byte, same Laragon hostname:

- Home: repeated baseline 968 ms; final check 119 ms.
- Admin login: repeated baseline 812 ms; final check 82 ms.
- Initial home request before changes: 16,174 ms. This cold observation is not
  comparable to a warmed request and is not used to calculate a speedup.

Final read-only local HTTP profiler samples, including application bootstrap,
role lookup, middleware, controller execution, queries, and HTML rendering:

- Admin dashboard: 278 / 170 ms; 40 queries.
- Admin reservations: 394 / 291 ms; 12 queries.
- Admin payments: 213 / 200 ms; 13 queries.
- Admin reports: 507 / 446 ms; 122 queries.
- Public rooms: 216 / 225 ms; 9 queries (includes profiler account lookup).
- Customer reservations: 214 / 277 ms; 21 / 19 queries.

These are small local samples, not load-test percentiles or browser paint times.
The temporary loopback-only profiler selected existing accounts in memory and
used array sessions; it did not exercise login, download assets, execute browser
JavaScript, or change bookings. It was removed after measurement. Regression
tests ran against the separate configured test database.

Verification completed: the selected dashboard, reservation-management,
payment-management, reporting, and customer-navigation suites passed 123 tests
with 684 assertions. The new cache-path tests passed 2 tests with 9 assertions.
PHP syntax and changed-file whitespace checks passed. The removed diagnostic
URL returned HTTP 404. No full browser interaction or concurrency load test was
performed in this investigation.

Reports still issued 42 repeated grouped-payment aggregate queries in this data
sample. Their entire SQL workload was about 178–184 ms in the final run. This is
a future scaling opportunity; changing payment-allocation semantics was not
necessary to fix the measured multi-second navigation delay.

## Keeping navigation fast

Use the Laragon Apache hostname rather than the single-worker Windows Artisan
development server for normal module navigation. The measured fix applies to
Apache, not to a separate already-running `php artisan serve` process.

The local web PHP configuration now includes:

```ini
zend_extension=opcache
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1
opcache.revalidate_freq=0
```

Timestamp checks remain enabled so PHP edits are detected during development.
Restart the web server after changing php.ini. PHP upgrades may select a new ini
file, so check the active web runtime rather than assuming the CLI uses the same
configuration. On this machine bare `php` resolved to XAMPP, while Apache used
Laragon PHP. Use Laragon's terminal or its explicit PHP executable for Artisan.

After installing dependencies or updating templates:

```powershell
& 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' artisan view:cache
```

Do not run cache-clearing commands on every navigation or every server start.
For a production release, enable OPcache on the actual hosting runtime, build
Vite assets, precompile views, and follow the hosting environment's Laravel
deployment procedure. Keep queues and scheduled work supervised. Re-measure
authenticated requests and browser load times after deployment; local timings
do not establish internet latency or multi-user capacity.

## Rollback

Restore the backed-up ini and restart Laragon Apache to revert the server change.
To revert only template canonicalization, remove its `view.finder` registration
and class, then run `artisan view:clear`. The default finder will regenerate
views on demand. Neither change requires database migrations.
