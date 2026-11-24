# Villa Mercedes Digital Hospitality System

A web-based reservation and operations system for **Villa Mercedes Nature Farm & Resort**
(Barangay Namnama, City of Ilagan, Isabela, Philippines).

The resort is not only a place to sleep. On the same grounds it runs guest rooms, four
function halls, and day-use and night-use pool packages. Those activities compete for
the *same* physical spaces.

This system is built around that fact.

## What makes it different

Commercial hotel property management systems model inventory as **rooms sold per night**,
then bolt on distribution to online travel agencies. Local capstone booking systems
usually model a **single booking queue** with payment proof upload and an admin list.

Neither answers the question the owner of a hybrid resort actually asks:

> If I accept this booking, what do I lose?

This system treats every room, hall and package as a shared asset with **competing
claims** on it — a guest stay, a hall event, or a bundled package — and explains the
cost of each decision in pesos, in plain language.

Three capabilities carry that idea:

1. **Mixed-resource inventory graph.** Rooms, function halls and packages live in one
   `facilities` table. A package is a composite: booking `Overnight Package` consumes the
   `Deluxe` room, and booking the `Deluxe` room blocks the package. Both directions are
   enforced with row-level locks in deterministic order, so concurrent requests cannot
   double-sell the same physical space.
2. **Hour-native occupancy.** A cottage held until 9PM is occupied until 9PM. Early
   check-in and late check-out hours extend the occupancy window used for conflict
   detection, not just the price.
3. **Reservation Calendar.** Staff see held dates across rooms, halls, cottages and packages, then open individual reservations to manage them. The calendar preserves linked-resource occupancy without a separate decision queue or financial comparison cards.

## Baseline capabilities

### Public site
Home, rooms, halls, packages, gallery, FAQ, contact, facility detail, and a booking page
with live price preview.

### Guest portal
Reservations with reschedule and cancel inside the published policy window, itemised
pricing snapshot, partial payments with proof upload, and notifications. Questions go to the resort’s Facebook/Messenger page.

### Administrator back office
Facilities (amenities, package components, gallery, status), rates, reservations
(approve, reject, reschedule, cancel, complete, walk-in encoding, check-in, check-out),
payments (verify, reject), FAQs, customers, reports, and the Reservation Calendar.

### Pricing model
Base, seasonal, extra-person and per-hour additional-charge rates. Amounts are computed in
integer cents and stored as a snapshot on the reservation, so historical bookings are never
retroactively repriced by a rate change.

## Domain rules worth knowing

- Online reservations start as `Pending`; staff-encoded walk-ins are `Approved` immediately.
- `Pending` and `Approved` reservations both block availability. A pending request freezes
  inventory, which is exactly why the opportunity cost of holding it is shown to the owner.
- New bookings accept policy `2026-10-confirmed-v1`: cancellation/full refunds and
  rescheduling remain eligible until recorded physical check-in, including no-show.
  Staff review changes and notify the owner; only the Owner approves refunds and
  records returned funds. Historical bookings retain their accepted terms and amounts.
- Rooms support full-rate same-day stays. Checkout leaves a cleaning hold until staff
  mark cleaning complete. See [confirmed practices and activation](docs/CONFIRMED-BOOKING-PRACTICES.md).
- Walk-in reservations store the guest's own name, phone and email, separately from the
  staff account that encoded the booking.
- Reservation codes are `VM-YYYYMMDD-XXXXXX`.

## Architecture

```
app/
  Enums/                     roles, statuses, sources, rate types
  DTOs/                      PriceBreakdown, AvailabilityResult, OccupancyClaim, DecisionImpact
  Models/                    Facility, Rate, Reservation, Payment, ...
  Services/
    AvailabilityService      overlap + capacity + package graph
    PricingService           base/seasonal/extra person/extra hours, in cents
    ReservationService       creation, reschedule, status transitions, locking
    PaymentService           submission and verification of manual payments
    Payments/                PaymentGateway contract, PayMongo and manual drivers,
                             OnlinePaymentService for live e-wallet checkout
    Admin/
      DecisionSupportService unified timeline, displacement, opportunity cost, what-ifs
      ReportingService       booking, revenue and popularity reporting
    Customer/                ownership checks and policy enforcement wrappers
```

Key rule: controllers do not contain business logic. Availability, pricing, transitions and
locking live in services, which is what makes the same rules reusable by the guest portal,
the admin back office and the Reservation Calendar.

## Local setup

Requirements: PHP 8.2+, Composer, Node 18+, MySQL 8.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed --env=migration
npm run build
php artisan queue:work
```

Open the Laragon vhost (`http://digital-hospitality-villa-mercedes.test`). Apache
serves HTML and static files in parallel with OPcache. `php artisan serve` remains a
valid fallback but is single-threaded on Windows and will feel much slower.

Keep `php artisan queue:work` (or `composer run dev`, which already runs
`queue:listen`) running so reservation and auth mail leave the request. Without a
worker, pages stay fast and mail waits in the `jobs` table.

Before the migration step on a new installation, provision separate MySQL accounts for the application, migrations and tests. Set the runtime credentials in `.env` and grant only SELECT, INSERT, UPDATE and DELETE on the resort database. For local schema changes, use an ignored `.env.migration` with the same application key and database, and a schema-management login. Put the dedicated test database and its own schema-management login in the ignored `.env.testing`. Keep the test login limited to the test database.

Local defaults are `SESSION_DRIVER=file` and `CACHE_STORE=file`. Application data
still uses MySQL 8. Tests use the MySQL database named in `phpunit.xml`.

On Laragon, the folder name becomes a `.test` host (this project resolves as
`http://digital-hospitality-villa-mercedes.test` when Laragon magic DNS is enabled).
A custom name such as `VillaMercedesLaragon.test` works only after you add it in
Laragon and the Windows hosts file. That is an environment setting, not an
application change.

For fast page navigation, enable OPcache in the **web server's** PHP configuration
and run `php artisan view:cache` after installing or updating the application.
The custom view finder keeps request-time template paths identical to the paths
precompiled by Artisan, including on Windows. See
[navigation performance setup and measurements](docs/NAVIGATION-PERFORMANCE.md).

Payment proofs are stored on the private `uploads` disk (`storage/app/uploads`).
See `UPLOAD_MAX_SIZE` and `UPLOAD_ALLOWED_MIMES` in `.env.example`.

Front-end assets use Vite 6 with Tailwind CSS v3 via PostCSS (`resources/css/app.css`).

Local seeded administrators: `admin@villamercedes.test` / `Password123!` and `admin2@villamercedes.test` / `Password123!`

Local seeded customer: `customer@villamercedes.test` / `Password123!`

Production seeding does not create the demo customer. Set `INITIAL_ADMIN_EMAIL` and a unique `INITIAL_ADMIN_PASSWORD` of at least 12 characters before running `php artisan db:seed`; the seeder refuses the local demo password.

Customer sign-in is `/login`. Administrator sign-in is `/admin/login`. There is no public administrator registration.

Customer registration checks whether the email already has an account before queuing a 6-digit OTP. If it does, no code is queued. The queue worker sends the code, and a `users` row is created only after verification. Delayed jobs skip expired or replaced codes, so keep the queue worker running while registration is available. Registration also requires a mobile number so staff can look guests up in admin search. OTP timing and attempt limits are in `config/otp.php`. Local development can use `MAIL_MAILER=log` or a real SMTP provider via `.env`; do not commit mail passwords.

### Production authentication checklist

Point the web server document root at `public/`, redirect HTTP to HTTPS and exclude `.preview/` and all local environment files from release packages. Supply production credentials separately. Restrict the MySQL listener and firewall to the application host. For a remote database, require encrypted connections on the server and configure `MYSQL_ATTR_SSL_CA` with the trusted CA certificate on the application host. Run production migrations with deployment credentials against the production environment.

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<public-host>
SESSION_DRIVER=file
CACHE_STORE=file
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
AUTH_REVEAL_PASSWORD_RESET_LINK=false
INITIAL_ADMIN_EMAIL=<private administrator email>
INITIAL_ADMIN_PASSWORD=<unique password of at least 12 characters>
MAIL_* real SMTP or provider credentials
```

Run a queue worker (`php artisan queue:work` or a process manager) so reservation and auth mail is sent after the HTTP response. Run the scheduler so expired OTP rows are pruned (`php artisan schedule:work` or cron `schedule:run`). HTTPS should terminate at the reverse proxy.

### Tests

```bash
php artisan test
```

Tests run against the `digital_hospitality_villa_mercedes_test` database configured in
`phpunit.xml`. Create it once before the first run.

### Optional: online payments

Manual proof upload (cash, bank transfer, GCash, Maya) always works. To additionally accept
live e-wallet checkout, set a gateway in `config/payments.php` via:

```
PAYMENT_GATEWAY=paymongo
PAYMONGO_SECRET_KEY=sk_test_...
PAYMONGO_PUBLIC_KEY=pk_test_...
```

With no keys configured the system stays in manual mode and says so in the UI rather than
failing at checkout.

## Research framing

Working title: *An Explainable Mixed-Resource Reservation Model for Hybrid Agri-Tourism
Resorts.*

- **Problem.** Provincial hybrid resorts coordinate lodging, events, and day-use hours using
  Facebook messages, notebooks, and software that assumes overnight hotel rooms.
- **Gap.** Global property management systems optimise rooms and OTA distribution. Local
  academic booking systems digitise one queue and evaluate with usability surveys. Neither
  models contention between guest, event, and package claims on one resource, nor explains
  the trade-off to the owner.
- **Contribution.** A mixed-resource claim model with hour-level occupancy and a
  rule-based explanation layer for approval decisions.
- **Evaluation.** Conflict-detection accuracy against the manual calendar, time-to-decision
  for walk-in and extra-hour requests, and estimated versus actual displaced revenue, with
  ISO 25010 usability as a secondary instrument.

## License

Built on the Laravel framework, which is open-sourced software licensed under the
[MIT license](https://opensource.org/licenses/MIT).
