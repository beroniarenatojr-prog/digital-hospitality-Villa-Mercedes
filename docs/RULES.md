# Project Rules — RULES.md

Hard rules for humans and AI agents working in this repository. Prefer these over generic Laravel scaffolding habits when they conflict.

---

## Naming Conventions

### PHP

| Kind | Convention | Example |
|------|------------|---------|
| Classes | PascalCase | `ReservationService` |
| Methods / vars | camelCase | `lockFacilityGraph` |
| Enums | PascalCase backed by string values as used in DB/UI | `ReservationStatus::Pending` |
| Form requests | Verb + noun | `StoreReservationRequest` |
| Controllers | Resource / role prefix | `AdminReservationController`, `CustomerPaymentController` |
| Jobs / commands | Imperative | `ExpireUnpaidReservations` |
| Config keys | snake_case | `withdrawal_cutoff_hours` |
| DB tables / columns | snake_case, plural tables | `package_components`, `check_in_at` |
| Route names | dot notation by area | `admin.reservations.show`, `customer.payments.store` |

### Front-end

| Kind | Convention |
|------|------------|
| Blade views | kebab or folder resource paths: `admin/reservations/show.blade.php` |
| Components | PascalCase class / kebab tag: `<x-facility-card>` |
| Tailwind | Use theme tokens (`bg-primary`, `text-muted`) — no ad-hoc purple/indigo SaaS palettes |
| JS modules | Existing Alpine + Vite entry style; prefer small deferred imports |

### Reservations & money

- Reservation codes: `VM-YYYYMMDD-XXXXXX`
- Pricing math in **integer cents** inside services/DTOs; persist decimal snapshot fields as the app already does
- Never invent status strings — use enums

### Brand copy

- Full name / short name / tagline from `config('resort.*')`
- Do not invent fake stats beyond `config('resort.stats')`

---

## Project Scaffolding

### Required shape for new features

```
FormRequest  →  Controller (thin)  →  Service  →  Model
                              ↘ DTO / Enum as needed
                              ↘ Notification queued when side effects are mail/in-app
```

### Where new code goes

| Change | Location |
|--------|----------|
| Domain rule | `app/Services/...` (not controller, not Blade) |
| HTTP validation | `app/Http/Requests/...` |
| Status / type | `app/Enums/...` |
| Transfer object | `app/DTOs/...` |
| Policy window / brand | Prefer `config/resort.php` (or payments/otp) over hard-coded Blade copy |
| Timezone / resort day | `config/resort.php` (`timezone`), read through `app/Support/ResortTime.php` |
| Schema change | New migration under `database/migrations/` |
| Admin UI | `resources/views/admin/...` + `Admin*Controller` |
| Guest UI | `resources/views/customer/...` or `pages/...` |
| Public motion/tokens | `tailwind.config.js`, `resources/css/app.css`, `resources/js/app.js` |
| Docs | `docs/*.md` (this set) + `docs/public-site/` for design living notes |

### Do not scaffold

- Inertia / Livewire / React SPA layers unless explicitly requested
- New repository interfaces by default (users already have a limited repository; services are the norm)
- Training / TESDA booking modules (removed)
- Postgres RLS policies (MySQL + app auth)
- Duplicate payment gateways without implementing `PaymentGateway`
- New top-level CSS frameworks

### Local / ops expectations

- PHP 8.2+, Composer, Node 18+, MySQL 8
- After booking/auth work: ensure queue worker path remains valid
- Tests: `php artisan test` against the PHPUnit test database
- Seeded accounts stay in README — do not commit real production credentials
- The project design skill lives in `.agents/skills/`.
- That skill's scripts need Python 3, and `python` / `python3` cannot be used here: both
  resolve to the Microsoft Store alias stub in `WindowsApps`, which prints "Python was not
  found". Call the interpreter that Laragon already ships, by absolute path:
  `C:\laragon\bin\python\python-3.13\python.exe .agents/skills/ui-ux-pro-max/scripts/search.py "<query>" --design-system`.
  Add `-B` to skip `__pycache__`. The catalog validates with
  `python.exe validate_data.py` in that skill's `scripts/` folder (12 domain files, 22
  stacks, `ui-reasoning.csv`).

---

## Coding Principles

1. **One rule set** — Availability, pricing, locking, and status transitions must be reusable by guest, admin, and Decision Board.
2. **Thin controllers** — No multi-step domain logic, no direct conflict checks in controllers.
3. **Lock the graph** — Any create/reschedule path that can contend for package/component inventory must use deterministic `lockForUpdate` ordering.
4. **Occupancy windows** — Conflict detection uses early/late padded windows, not check-in/out labels alone.
5. **Snapshot pricing** — Do not reprice stored historical reservations when rates change.
6. **Pending holds inventory** — Pending and Approved both block; Decision Board must remain meaningful.
7. **Config as policy** — Reschedule and withdrawal cutoffs, stay windows, OTP limits, deposit flags live in config; FAQ must not drift.
8. **Ownership checks** — Customer services verify `user_id`; admin services verify Administrator.
9. **Side effects after commit** — Prefer observers / `afterCommit` / queued notifications so HTTP failures do not strand mail.
10. **Immutable audit** — Do not “fix” activity log rows from app code.
11. **Money safety** — Integer cents in calculations; validate amounts server-side; proofs on private disk.
12. **Minimal diffs** — Change only what the task needs; no drive-by refactors or unsolicited markdown sprawl.
13. **Match existing style** — Mirror neighboring files for imports, naming, and Blade structure.
14. **Tests for domain risk** — Conflict locking, policy cutoffs, and payment eligibility deserve tests when touched.
15. **Accessibility & motion** — Public UI must honor focus-visible and `prefers-reduced-motion`.
16. **Resort clock** — “Today”, “tonight”, arrival lists, and occupancy are resort calendar days: resolve them through `App\Support\ResortTime`, never a bare `now()`. Instants (`created_at`, `paid_at`, `deposit_due_at`) stay in `config('app.timezone')`.
17. **One palette, everywhere** — The resort has a single palette (`tailwind.config.js`); no Blade file, script, or `@apply` rule may use a default Tailwind colour scale, on either side of the desk. Use the tokens: `surface`, `background`, `muted`, `border`, `text`, `text-muted`, `primary` (+`-light`/`-dark`/`-hover`), `secondary`, `accent` (+`-light`), and `success`/`warning`/`danger`/`info` each with a `-light` tint. `white`/`black` are for text and overlays on a primary surface, never for a panel. A colour a **library** paints is part of the palette too, and it is the part no scan can see, because no class name carries it: `theme.extend.colors.gray.400`/`gray.500`/`blue.600` are pointed at `text-muted`/`primary` for exactly this reason — Tailwind's preflight reads the first for placeholders and `@tailwindcss/forms` reads all three for every control's border, placeholder, select chevron, tick and focus ring. Do not "restore" those steps to the stock greys, and do not reach for a literal hex in `app.css` to correct a library default; fix it where the library reads it. (The two Facebook/Messenger brand blues in the social components are the one deliberate exception, and `SharedVocabularyTest` records it.)
18. **One date vocabulary in the back office** — A rendered date in an admin view always carries its year: `` `format('M j')` `` is how a stay gets filed against the wrong September. Use `x-admin.date-time` / `x-admin.date-range`, or name a year (Y).
19. **One shared vocabulary** — Money and statuses are read by the guest and the staff member for the same record, so they have one form and one word. Every peso amount renders through `x-amount` (or `App\Support\Money` where Blade cannot render an element, or `js/money.js` in the browser), and every status chip is handed the state enum, never `->value` or `->label()`. An exception needs a local `vocabulary-guard:` comment saying why. `SharedVocabularyTest` enforces the palette and both shared rules — following `<x-…>` out of a view only where a rule needs it — `AdminDateVocabularyTest` enforces the date rule, `MoneyFormatTest` proves the server, the browser and the email format money identically, and `StatusVocabularyTest` proves both sides of the desk show the same word for the same state.
20. **Publish, don't leak** — `is_active` and `is_listed` answer different questions (withdrawn from sale vs. inventory-only) and a guest catalog needs both, so guest-facing facility queries go through `Facility::listed()`. An unlisted facility is sold *inside* a package — the Private Function Hall belongs to Package I — and must never appear as a card, a search suggestion, or a standalone booking; the admin keeps the whole inventory (`AdminCatalogCache` filters the other way, on `isOnlineBookable()`). `PackageSystemTest::test_private_function_hall_is_not_listed_or_bookable_standalone` guards the public listing, the guest picker, and the store path.
21. **A pass is earned, and its copy has one home** — An arrival pass is a confirmed-and-paid document. `Reservation::canShowArrivalPass()` is the only gate for the guest's code and printable pass: `Approved` **and** the required deposit verified, or any verified payment when the record carries no deposit (a walk-in, a booking made before the deposit workflow, a deployment with that workflow off). `Reservation::arrivalPassNotice()` is the only explanation when the gate is false, and both the Arrival tab and the page behind a saved pass link render it, because that link outlives the state it was issued in. Do not write pass copy into a view: `SharedVocabularyTest::test_the_arrival_pass_explains_itself_in_one_voice` fails on a second answer.
22. **A hold is capped, and counted where it is created** — A Pending row keeps its space off the market until its review window closes, so one self-service account is limited to `resort.policy.max_pending_holds` (default 5) unapproved reservations. The count happens inside the booking transaction, under lock, in `ReservationService::assertWithinPendingHoldLimit()` — checking before the transaction would let two racing submissions both take the last slot. Staff bookings are never capped (the desk books groups), Pending is the only status that counts, and 0 removes the ceiling. The ceiling is a published rule: change the seeder *and* add a data migration, because `FaqSeeder` only runs from `DatabaseSeeder`.
23. **Approval is a decision, not a receipt** — The order is: the guest submits a request, staff answer it, and only then does the 50% deposit open. `Reservation::canAcceptGuestPayment()` is the single gate for guest money and it requires **Approved**; `ReservationService::updateStatus()` sets `deposit_due_at` at the moment of approval, because that is when the guest is told to pay (capped, as ever, at the start of the visit). Nothing about money may change a status: `PaymentService::verifyPayment()` and `recordOnSite()` record funds and never call `changeStatus()`, and `reservations:expire-unpaid` releases both a review window nobody answered and an approved booking whose deposit deadline passed with nothing attached — never a confirmation whose stay has begun, because that one is settled by a person at the desk. A pending row's `deposit_due_at` is the review window, not a payment deadline — the same column, two meanings, named by the status that owns it.
24. **Hours open the venue, bookings only fill it** — A function hall is a same-day venue: four standard hours inside 9 AM to 10 PM (`resort.stay.hall.*`), with everything past those four hours charged the hall's own **Hall Extension** rate. So advice about extra hours has two ceilings, not one: the next booking on the space *and* the shutting hour. `DecisionSupportService::extraHourOptions()` stops at `hall.close` — listing the first refused hour so the ceiling has a face instead of twelve promises — and prices a hall with the hall's rate, never the room's **Late Check-out** rate. "Nothing else is booked" is not permission to promise an hour the venue cannot open for.

---

## Rules for Cursor IDE / AI Agent

### Always

- Read surrounding code and these docs (`docs/PRD.md`, `DESIGN.md`, `ARCHITECTURE.md`, `SCHEMA.md`, `RULES.md`) before inventing architecture.
- Keep controllers thin; put rules in services.
- Use existing enums, DTOs, Form Requests, and Tailwind tokens.
- Preserve brand assets and design tokens from DESIGN.md / `docs/public-site/design-system.md`.
- Treat mixed-resource locking and snapshot pricing as sacred — ask before changing semantics.
- Prefer absolute paths in tooling; run commands in the project root.
- Only commit when the user explicitly asks; never update git config; never force-push.

### Never

- Double-sell by bypassing `AvailabilityService` / facility-graph locks.
- Put secrets (`.env`, API keys, mail passwords) in commits or docs.
- Reintroduce removed training modules without an explicit product request.
- Replace Blade+Alpine with a SPA “because it’s modern.”
- Use Inter/Roboto-only stacks or purple-gradient SaaS aesthetics on the public site.
- Invent OTA/channel-manager scope.
- Add Postgres RLS jargon as if it exists here — document app-layer auth instead.
- Edit historical migrations that already shipped; add a new migration.
- Weaken OTP / deposit / cutoff enforcement for convenience in production paths.
- Skip ownership checks on customer mutation endpoints.
- Generate large unrelated file trees or “cleanup” renames unless asked.

### When implementing UI

- Public: photography-first, brand-first hero, one job per section (DESIGN.md).
- Admin: `x-admin.*` is the vocabulary — `page-header`, `panel`, `table`, `filter-form`,
  `live-filter`, `filter-bar`, `filter-field`, `stat`, `data-status`, `date-time`,
  `date-range`, `empty`, `button`, `notice`, `confirm-action`, `form-action` — on the
  palette tokens in `resources/css/app.css`. Do not hand-roll a panel, a table, a peso, a
  date, or a filter row: a screen that filters on a button uses `filter-bar` (its fields
  are `filter-field`, its reset lives in the panel header, its quick ranges go in the
  `below` slot) and a screen that filters as you type uses `filter-form`, so neither can
  grow a shape of its own.
- Money and status chips are shared with the guest portal, not admin-only: `x-amount` for
  every peso, `x-badge :status="$model->status"` for every state. Staff and guests must read
  the same figure and the same word for the same record.
- The palette (all views, all scripts, `app.css`), money, and status vocabulary are enforced
  by `SharedVocabularyTest`; dates with years by `AdminDateVocabularyTest`. Run both after
  touching any Blade file, script, or the stylesheet — on either side of the desk.
- A palette check over class names cannot see a colour a library supplies, and this has now
  hidden a leak four times: a gray-500 default border and placeholder, a blue-600 tick and
  focus ring, a gray-500 `<select>` chevron inside a data-URI SVG, and a gray-400 placeholder
  from preflight. When a control looks off-brand with no class to blame, suspect the plugin,
  and fix it through the theme step it reads rather than by overriding its output.
- Back office destinations are `App\Support\Admin\AdminNav::groups()`; the sidebar renders
  that list and invents nothing. An admin route has to be reachable from it or named in
  `AdminSidebarNavigationTest::OFF_MAP` with the reason staff are not offered it — a screen
  with no way in is a screen the back office does not have.
- Reuse Blade components before creating new ones.
- `.agents/skills/ui-ux-pro-max/SKILL.md` answers structure, accessibility, responsive,
  chart and per-stack questions from local CSVs — search it before inventing UI advice or
  hand-rolling a pattern. Two limits, both binding: its colour output is advisory only (it
  will offer palettes from its own catalog, and rule 17 makes `tailwind.config.js` the
  single palette for both sides of the desk — take its patterns, states and implementation
  notes, take colours from the tokens); and its guidance is generic where this repo is
  specific, so `DESIGN.md`, the `x-admin.*` vocabulary, and the guard tests above win any
  disagreement. Its `--persist` output is the same bargain: `design-system/<slug>/MASTER.md`
  is generated, gitignored and subordinate, and `docs/DESIGN-SYSTEM.md` records what it may
  decide and every place it conflicts.
- Honor reduced motion and 44px primary targets.
- Anything pinned to the bottom edge reads `--dock-bottom` and the `--safe-*` insets from
  `app.css`; do not give a fixed helper its own `bottom-*` utility. A library widget is
  themed in `app.css` with `theme(...)` tokens and a doubled selector — never with a literal
  colour, and never in a stylesheet of its own, because the palette scan reaches `app.css`
  and `resources/js` only. `MobileAvailabilityTest` guards both.

### When touching schema

- Update `docs/SCHEMA.md` if tables/relationships/authZ meaningfully change.
- Keep ERD and model list honest; note soft deletes and package graph edges.

### When unsure

- Prefer the behavior already encoded in `ReservationService`, `AvailabilityService`, `PricingService`, and `DecisionSupportService`.
- Prefer config keys over magic numbers.
- Ask the user before destructive data migrations or payment-gateway behavior changes.

### Documentation maintenance

| Doc | Update when… |
|-----|----------------|
| `PRD.md` | Scope, MVP, or success metrics change |
| `DESIGN.md` | Tokens, motion, or brand rules change |
| `MOBILE-AUDIT.md` | The guest flow's mobile affordances change — docked bars, safe-area insets, touch targets, or the booking calendar |
| `ADMIN-AUDIT.md` | Back office content or its supporting files change — panel copy, empty states, shared `x-admin.*` vocabulary, or the chart components |
| `DESIGN-SYSTEM.md` | The design skill is reinstalled or its generated master is regenerated — the authority order and the conflict record are read off both |
| `ARCHITECTURE.md` | Services, workflows, or folder patterns change |
| `SCHEMA.md` | Tables, relationships, or authZ model change |
| `RULES.md` | Agent/scaffolding hard rules change |

Keep README.md as the operator-facing overview; keep this `docs/` set as the structured product/engineering source of truth for agents and contributors.
