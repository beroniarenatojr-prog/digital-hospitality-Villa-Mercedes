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
| Config keys | snake_case | `cancellation_cutoff_hours` |
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
| Schema change | New migration under `database/migrations/` |
| Admin UI | `resources/views/admin/...` + `Admin*Controller` |
| Guest UI | `resources/views/customer/...` or `pages/...` |
| Public motion/tokens | `tailwind.config.js`, `resources/css/app.css`, `resources/js/app.js` |
| Docs | `docs/*.md` (this set) + `.superdesign/` for design living notes |

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

---

## Coding Principles

1. **One rule set** — Availability, pricing, locking, and status transitions must be reusable by guest, admin, and Decision Board.
2. **Thin controllers** — No multi-step domain logic, no direct conflict checks in controllers.
3. **Lock the graph** — Any create/reschedule path that can contend for package/component inventory must use deterministic `lockForUpdate` ordering.
4. **Occupancy windows** — Conflict detection uses early/late padded windows, not check-in/out labels alone.
5. **Snapshot pricing** — Do not reprice stored historical reservations when rates change.
6. **Pending holds inventory** — Pending and Approved both block; Decision Board must remain meaningful.
7. **Config as policy** — Cancel/reschedule cutoffs, stay windows, OTP limits, deposit flags live in config; FAQ must not drift.
8. **Ownership checks** — Customer services verify `user_id`; admin services verify Administrator.
9. **Side effects after commit** — Prefer observers / `afterCommit` / queued notifications so HTTP failures do not strand mail.
10. **Immutable audit** — Do not “fix” activity log rows from app code.
11. **Money safety** — Integer cents in calculations; validate amounts server-side; proofs on private disk.
12. **Minimal diffs** — Change only what the task needs; no drive-by refactors or unsolicited markdown sprawl.
13. **Match existing style** — Mirror neighboring files for imports, naming, and Blade structure.
14. **Tests for domain risk** — Conflict locking, policy cutoffs, and payment eligibility deserve tests when touched.
15. **Accessibility & motion** — Public UI must honor focus-visible and `prefers-reduced-motion`.

---

## Rules for Cursor IDE / AI Agent

### Always

- Read surrounding code and these docs (`docs/PRD.md`, `DESIGN.md`, `ARCHITECTURE.md`, `SCHEMA.md`, `RULES.md`) before inventing architecture.
- Keep controllers thin; put rules in services.
- Use existing enums, DTOs, Form Requests, and Tailwind tokens.
- Preserve brand assets and design tokens from DESIGN.md / `.superdesign/design-system.md`.
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
- Admin: utilitarian, dense, token-consistent.
- Reuse Blade components before creating new ones.
- Honor reduced motion and 44px primary targets.

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
| `ARCHITECTURE.md` | Services, workflows, or folder patterns change |
| `SCHEMA.md` | Tables, relationships, or authZ model change |
| `RULES.md` | Agent/scaffolding hard rules change |

Keep README.md as the operator-facing overview; keep this `docs/` set as the structured product/engineering source of truth for agents and contributors.
