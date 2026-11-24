# Product Requirements Document (PRD)

**Product:** Villa Mercedes Digital Hospitality System  
**Property:** Villa Mercedes Nature Farm & Resort — Barangay Namnama, City of Ilagan, Isabela, Philippines  
**Working research title:** *An Explainable Mixed-Resource Reservation Model for Hybrid Agri-Tourism Resorts*

---

## Scope

### In scope

A web-based reservation and operations system for a **hybrid agri-tourism resort** where guest rooms, cottages, function halls, day/night pool packages, and composite resort packages compete for the **same physical spaces**.

The product covers:

| Surface | Audience | Responsibility |
|---------|----------|----------------|
| Public site | Guests / prospects | Browse rooms, halls, packages, cottages, pools, gallery, FAQ, contact; search availability; start booking |
| Guest portal | Authenticated customers | Reserve, pay (deposit / proof), reschedule or withdraw a pending request within policy, review, notifications; questions via Facebook/Messenger |
| Administrator back office | Resort staff / owner | Facilities, rates, walk-ins, approvals, payments, reports, Decision Board |
| Domain core | System | Mixed-resource inventory, hour-native occupancy, snapshot pricing, explainable opportunity cost |

### Out of scope

- OTA / channel-manager distribution (Booking.com, Agoda, etc.)
- Full PMS accounting, payroll, purchasing, or inventory POS; the MVP includes only a simple manual stock ledger
- Mobile native apps (responsive web only)
- TESDA / training batch management (removed from product)
- Black-box ML demand forecasting (Decision Board is rule-based and auditable)
- Live gateway checkout for new payments when disabled in `config/payments.php` (manual proof remains primary)

### Problem framing

Provincial hybrid resorts often coordinate lodging, events, and day-use hours with Facebook messages and notebooks, or with software that assumes overnight hotel rooms only. Neither answers the owner’s real question:

> If I accept this booking, what do I lose?

---

## Goals

1. **Model contention correctly** — Rooms, halls, cottages, pools, and packages share one inventory graph so accepting one claim cannot silently double-sell another.
2. **Honor hour-native occupancy** — A cottage held until 9PM occupies until 9PM; early check-in and late check-out extend conflict detection, not only price.
3. **Explain owner decisions** — The Decision Board states what is free, what a pending request would displace, what that displacement is worth, and what alternatives cost — in pesos and plain language.
4. **Digitize the guest journey** — Availability search → authenticated booking → deposit / payment proof → stay lifecycle → optional review.
5. **Keep policy honest** — Published FAQ reschedule/withdrawal windows and enforced rules read the same config (`config/resort.php`).
6. **Support walk-in operations** — Staff can encode on-site bookings immediately as confirmed without blocking on the online deposit workflow.

---

## MVP Features

### Public site

- Home, about, rooms, halls, packages, cottages, pools, gallery, FAQ, contact
- Facility detail pages with amenities, gallery, and rates
- Availability search with live quotes and alternatives
- Brand-consistent photography-first presentation

### Guest portal

- Registration with email OTP + Philippine mobile number
- Online reservations start as **Pending** (inventory hold)
- Itemised pricing snapshot on the reservation (never retroactively repriced)
- Deposit workflow with payment destinations (GCash / Maya / bank) and proof upload
- Reschedule a booking, or withdraw a request that is still pending, inside the published cutoff (default 48 hours before check-in). The resort does not accept guest cancellations.
- **Arrival pass on verified payment** — the reservation code and its printable pass are issued to the guest once staff verify the deposit (default 50%); a record with no deposit needs a verified payment of its own, so a confirmed booking is never mistaken for a paid one at the desk.
- **Hold ceiling** — one self-service account may hold `resort.policy.max_pending_holds` (default 5) reservations awaiting approval at once, since each pending booking keeps its space off the market until its review window closes. Staff bookings are not capped; larger groups book through the resort.
- **Approval opens the deposit** — a request is reviewed before it is payable: staff approve (or decline) it, the approval email opens the 50% QR deposit with its own deadline, and the verified transfer is what earns the arrival pass. Nothing about money approves a booking, and approval alone does not pay it.
- Inquiries, in-app notifications, post-stay review invitation

### Administrator back office

- Facilities CRUD (amenities, package components, gallery, status, listing flags)
- Rates (base, seasonal, extra person, additional charge / per-hour)
- Reservations: approve, reject, reschedule, cancel, complete, check-in / check-out
- Walk-in encoding (status **Approved** immediately; guest contact stored separately from staff account)
- Payments: verify, reject, destinations / QR management
- Inquiries, FAQs, customers, page photos, resort services (pools, sessions, rules, fees, menu)
- Physical stock ledger for supplies and equipment: item units, quantity on hand, minimum levels, and manual movement history
- Owner Decision Board (timeline, displacement, opportunity cost, what-ifs)
- Reporting and immutable activity log

### Domain capabilities that define the product

1. **Mixed-resource inventory graph** — One `facilities` table; packages are composites via `package_components`; booking either side locks the graph in deterministic order.
2. **Hour-native occupancy** — Conflict detection uses occupancy windows, not night boundaries alone.
3. **Explainable decisions** — Rule-based opportunity cost from contending facilities’ list rates over the same window.

### Explicit non-goals for MVP

- Multi-property / multi-tenant SaaS
- Dynamic pricing algorithms beyond configured rate rows
- Guest chat as a full CRM (support chat is lightweight)

---

## Technical Requirements

| Area | Requirement |
|------|-------------|
| Runtime | PHP 8.2+, Laravel 12, MySQL 8 |
| Frontend | Blade + Vite 6 + Tailwind CSS 3 + Alpine.js |
| Auth | Customer `/login`; Administrator `/admin/login`; Spatie roles; registration OTP |
| Business logic | Service layer (controllers stay thin); Form Requests for validation |
| Money | Integer cents in pricing math; decimal storage with snapshot on reservation |
| Concurrency | `lockForUpdate` on facility graph in sorted ID order |
| Async | Database queue for mail/notifications; scheduler for OTP prune + unpaid expiry |
| Storage | Payment proofs on private `uploads` disk |
| Config as policy | `config/resort.php`, `config/payments.php`, `config/otp.php` |
| Quality | PHPUnit against dedicated test database; Pint for style |
| Local ops | Laragon vhost preferred; `queue:work` / `composer run dev` required for mail |

### Security & operations

- No public administrator registration
- Production: `APP_DEBUG=false`, secure cookies, real SMTP, queue worker, scheduler
- Activity log immutable from application updates/deletes
- Demo seeded reviews gated by `resort.demo_content`

---

## Success Metrics

### Primary (domain contribution)

| Metric | Description | Target direction |
|--------|-------------|------------------|
| Conflict-detection accuracy | System holds vs manual resort calendar for overlapping room / hall / package claims | Match or exceed manual correctness; zero double-sells in tests and UAT |
| Time-to-decision | Walk-in / extra-hour / pending approval decisions with Decision Board vs without | Reduce owner decision latency |
| Displaced revenue fidelity | Estimated opportunity cost vs actual displaced bookings / revenue | Estimates auditable and directionally useful |
| Inventory integrity under concurrency | Parallel booking attempts on coupled package/component | No double booking (locking + tests) |

### Secondary (product / operations)

| Metric | Description |
|--------|-------------|
| Online booking completion | Share of availability searches that become Pending reservations |
| Deposit conversion | Share of Pending reservations with verified deposit before expiry |
| Policy adherence | Guest reschedule/withdrawal only within cutoff; cancellations are staff-only, and FAQ and enforcement stay aligned |
| Payment ops | Median time from proof upload to verify/reject |
| Usability | ISO 25010-oriented guest and admin task success (secondary instrument) |

### Research evaluation framing

Evaluation pairs conflict accuracy and opportunity-cost explanations with operational timings (walk-in, extra hours), using usability as a secondary measure — not as a substitute for inventory correctness.
