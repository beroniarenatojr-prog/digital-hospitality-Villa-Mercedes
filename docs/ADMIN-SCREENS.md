# Admin screens — what each one is for

**Scope:** the back office only. One line per screen answering two questions: what does staff
come here to find out, and what do they decide because of it.

**Why this exists.** The muted subtitle under each admin page title was removed on 2026-09-21 —
a screen now names itself and nothing more (the dashboard also carries the resort date beside its
title). This document is that explanation in one place, so a new staff member (or the next
developer) can read the whole back office in a single pass.

Screens appear in sidebar order. The parenthesised route name is the one in
`App\Support\Admin\AdminNav`, which is also the list the sidebar renders.

**See also:** [`ADMIN-GUIDANCE.md`](ADMIN-GUIDANCE.md) — the design for teaching the reservation,
payment and reservation workflows to a new staff member in short cues, rather than in prose.
That one is a design; this one describes what ships today.

---

## Front of house

| Screen | What it answers | What staff decide |
|---|---|---|
| **Dashboard** (`admin.dashboard`) | "Today's operations": how many reservations and payments are waiting, which requests customers sent in today and who sent them (the waiting counts and this list re-read themselves while the screen is open, so a booking sent mid-shift appears without a reload), who arrives today, what the next seven days hold, which spaces are booked today, and what is due to depart. | What to work on first — the oldest request still waiting is at the top of today's list. Whether an arrival has actually been checked in. |
| **Reservation Calendar** (`admin.decision-board`) | Held dates across rooms, halls, cottages and packages. | Filter the date window and open a reservation from its timeline bar. |
| **Reservations** (`admin.reservations.index`) | Every booking request, walk-in and stay in one list, with today's arrivals pinned above it. | Which request to open first — the status changes themselves happen on the record, not from the list. |
| **New walk-in** (`admin.reservations.create`) | The same booking questions the guest form asks, filled in at the desk. | Whether to take a booking on the spot — it is approved the moment it is submitted, and nothing is saved until then. |
| **Reservation record** (`admin.reservations.show`) | One stay: the guest sheet, payment summary, pricing snapshot, recent activity, and a reschedule calculator that answers "can they stay longer?". | Every status change listed above, plus printing the **guest information sheet** carried to the desk. |
| **Payment review** (`admin.payments.index`) | Proofs waiting to be checked, with method, reference and amount. | Verify or reject each proof. A rejected or short payment means deciding whether to request a top-up or record cash taken on site. |
| **Operations** (`admin.reports.operational`) | Four tabs about right now: **guest registry** (checked-in guests, printable emergency list), **current occupancy**, **today's action lists** (walk-ins, missed arrivals, overdue departures) and **available now** (what can still be sold, at what party size). | Who needs attention this shift, and whether a walk-in can be sold a specific space. |
| **Customers** (`admin.customers.index`) | Everyone with a guest account, how many stays they have booked and when they last arrived; one guest's record holds their reservation and payment history. | Whether a guest's history and outstanding balance support the request in front of you. |
| **Resort QR accounts** (`admin.payment-destinations.index`) | Which resort-issued QR codes and accounts guests pay a deposit into. | Which account is shown to guests. Only one may be active per provider, because the guest's proof is checked against the code that was displayed. |
| **Guest reviews** (`admin.reviews.index`) | Feedback guests submitted, unmoderated first. | Publish, unpublish, or keep a review private. Suppressing one requires a recorded privacy, spam or abuse reason — the rating and the guest's words are never edited, and a low score is not a reason on its own. |

## The resort

| Screen | What it answers | What staff decide |
|---|---|---|
| **Facilities** (`admin.facilities.index`) | The bookable catalogue — rooms, cottages, halls and packages — with type, capacity, listing state, amenities, package components and photos. | What stays on sale, which spaces are hidden from the public site, what a package actually consumes when it is booked, and which space is retired while its reservations and history stay intact. |
| **Rates** (`admin.rates.index`) | Every price the booking engine can charge: base, seasonal, extra-person, and additional charges such as early check-in. | What a stay costs, and whether a price is still in use. Prices already booked are never recalculated. |
| **Resort Services** (`admin.resort-services.edit`) | Café details, pools and swimming sessions, cottages, pool rules, corkage fees, and the public menu with its categories. | What the resort publicly promises and charges for — published the moment it is saved. |
| **Photos** (`admin.photos.index`) | The photos behind one public collection, with their titles, captions and order. | Which images carry a public page, and in what order guests meet them. |

## Support and records

| Screen | What it answers | What staff decide |
|---|---|---|
| **Inquiries** (`admin.inquiries.index`) | Questions guests sent from the public site, oldest unanswered first. | What to answer, and whether the thread is resolved — answering moves it to Pending until the guest replies, Closing ends it. |
| **FAQs** (`admin.faqs.index`) | The questions and answers published on the public help page, grouped by category (the categories have their own screen under this same sidebar section). | What the help page states as fact, and whether a question or a whole category is published or hidden. |
| **Reports** (`admin.reports.index`) | The same filtered window asked two ways: **Overview** (cash, bookings, season, deposits) and **Financial** (booking value, delivered services, cash), exportable as CSV or PDF. | How a period actually went. Booking figures count the creation date, cash the payment date, and delivered revenue the departure date, so the three never quietly disagree. |
| **Activity Log** (`admin.activity-log.index`) | Every staff action with who did it, when, and the record it touched. Read-only. | Nothing — it cannot be edited or deleted, including by an administrator. It exists to answer "who approved, verified or changed this?" |
| **Notifications** (`admin.notifications.index`, reached from the bell in the header, so no sidebar entry) | Reservation and payment alerts for the front desk. | What has happened since the last shift; opening one marks it read and goes to the record it is about. |

---

## Screen behaviour worth knowing

- **The dashboard keeps itself current.** Its two numbers-or-list panels — *Needs attention* and
  *New requests today* — ask for their own contents again every 20 seconds while the tab is visible,
  stop while the tab is hidden, offline, or on a metered/slow connection, and only redraw when
  something actually changed: a booking that arrives mid-shift appears on its own, and a staff member
  reading a row is never interrupted by a rebuild. Each panel's header number (the total, the count
  beside the title) arrives in the same answer as the rows it belongs to, so the two can never
disagree, and a request that arrives while the screen is open is marked *New* and named in the
  panel's status line. The rest of the dashboard is still a snapshot of the moment it was opened.

---

## Deliberately not here

- No accounting, payroll or purchasing. The MVP keeps a reservations and payments ledger, not a
  general ledger (`docs/PRD.md` §"out of scope").
- **Stock inventory is parked.** The screen was taken off the back office on 2026-09-21; its
  tables, models and data remain, and the screen itself waits in
  [`docs/parked/stock-inventory/`](parked/stock-inventory/README.md) with restore steps.
