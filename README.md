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
3. **Explainable owner decisions.** The Decision Board states what is free, what a pending
   request would displace, what that displacement is worth, and what the alternatives cost.
   The reasoning is rule-based and auditable rather than a black-box forecast.

## Baseline capabilities

### Public site
Home, rooms, halls, packages, gallery, FAQ, contact, facility detail, and a booking page
with live price preview.

### Guest portal
Reservations with reschedule and cancel inside the published policy window, itemised
pricing snapshot, partial payments with proof upload, inquiries, and notifications.

### Administrator back office
Facilities (amenities, package components, gallery, status), rates, reservations
(approve, reject, reschedule, cancel, complete, walk-in encoding, check-in, check-out),
payments (verify, reject), inquiries, FAQs, customers, reports, and the Owner Decision Board.

### Pricing model
Base, seasonal, extra-person and per-hour additional-charge rates. Amounts are computed in
integer cents and stored as a snapshot on the reservation, so historical bookings are never
retroactively repriced by a rate change.

## Domain rules worth knowing

- Online reservations start as `Pending`; staff-encoded walk-ins are `Approved` immediately.
- `Pending` and `Approved` reservations both block availability. A pending request freezes
  inventory, which is exactly why the opportunity cost of holding it is shown to the owner.
- Guests may cancel or reschedule online until `resort.policy.*_cutoff_hours` before
  check-in (48 hours by default). After that the portal explains the deadline and directs
  the guest to call the resort. The published FAQ and the enforced rule read the same config.
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
the admin back office and the Decision Board.

## Local setup

Requirements: PHP 8.2+, Composer, Node 18+, MySQL 8.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan queue:work
```

Open the Laragon vhost (`http://digital-hospitality-villa-mercedes.test`). Apache
serves HTML and static files in parallel with OPcache. `php artisan serve` remains a
valid fallback but is single-threaded on Windows and will feel much slower.

Keep `php artisan queue:work` (or `composer run dev`, which already runs
`queue:listen`) running so reservation and auth mail leave the request. Without a
worker, pages stay fast and mail waits in the `jobs` table.

Local defaults are `SESSION_DRIVER=file` and `CACHE_STORE=file`. Application data
still uses MySQL 8. Tests use the MySQL database named in `phpunit.xml`.

On Laragon, the folder name becomes a `.test` host (this project resolves as
`http://digital-hospitality-villa-mercedes.test` when Laragon magic DNS is enabled).
A custom name such as `VillaMercedesLaragon.test` works only after you add it in
Laragon and the Windows hosts file. That is an environment setting, not an
application change.

Payment proofs are stored on the private `uploads` disk (`storage/app/uploads`).
See `UPLOAD_MAX_SIZE` and `UPLOAD_ALLOWED_MIMES` in `.env.example`.

Front-end assets use Vite 6 with Tailwind CSS v3 via PostCSS (`resources/css/app.css`).

Seeded administrator: `admin@villamercedes.test` / `Password123!`  
Seeded customer: `customer@villamercedes.test` / `Password123!`

Customer sign-in is `/login`. Administrator sign-in is `/admin/login`. There is no public administrator registration.

Customer registration checks whether the email already has an account before sending a 6-digit OTP. If it does, no code is sent. If it does not, the code is emailed and a `users` row is created only after verification. Registration also requires a mobile number so staff can look guests up in admin search. OTP timing and attempt limits are in `config/otp.php`. Local development can use `MAIL_MAILER=log` or a real SMTP provider via `.env`; do not commit mail passwords.

### Production authentication checklist

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<public-host>
SESSION_DRIVER=file
CACHE_STORE=file
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
AUTH_REVEAL_PASSWORD_RESET_LINK=false
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
