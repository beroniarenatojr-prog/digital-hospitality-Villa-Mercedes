# Design System — DESIGN.md

**Product surface:** Public guest experience (home, listings, gallery, facility pages, booking affordances).  
**Admin / dense forms:** Calm and utilitarian — reuse tokens, avoid cinematic motion and marketing layouts.

Living target also tracked in `docs/public-site/design-system.md`. Source of truth for tokens: `tailwind.config.js` + `resources/css/app.css`.

A generated design system also sits in `design-system/`, produced for this project by `ui-ux-pro-max`. It is advisory and subordinate to this document — its palette is a catalog palette matched by product type, and it names thirteen colours of which exactly one exists in the theme. `DESIGN-SYSTEM.md` holds the authority order and the full conflict record; take its structure and accessibility prompts, never its colours, type or elevation.

---

## Branding Identity

| Element | Value |
|---------|--------|
| Full name | Villa Mercedes Nature Farm & Resort |
| Short name | Villa Mercedes |
| Tagline | Experience nature, comfort, and heartful hospitality. |
| Location | Barangay Namnama, City of Ilagan, Isabela, Philippines |
| Values | CARE — Compassion, Accountability, Respect, Ethics |
| Config | `config/resort.php` (`name`, `short_name`, `tagline`, contact, social, stats) |

### Brand marks (do not invent substitutes)

| Asset | Use |
|-------|-----|
| `public/images/logo-villa-mercedes-mark.png` | Header, auth marks |
| `public/images/logo-villa-mercedes.png` | Footer, admin sidebar, arrival pass |
| Real grounds photography | Heroes and section anchors — entrance, cottages, halls, pools |

**Rules**

- Never replace the logo with initials, emoji, or a generic leaf icon.
- On branded first viewports, the resort name is a hero-level signal (`font-heading`), not only nav text.
- Photos lead; UI is a quiet overlay.
- Keep real copy (Ilagan, rooms & cottages, event hosting, packages). Do not invent fake occupancy metrics beyond published stats (4 pools, hotel & cottages, 4 halls, day & overnight packages).

---

## Design Themes

### Public: cinematic nature-farm hospitality

Guests should feel they are already walking the grounds.

- Full-bleed photography with slow Ken Burns
- Editorial headlines; restrained UI chrome
- Glass “front desk” availability / booking bar as the handshake, not a marketing widget
- One composition per first viewport — not a dashboard of cards and stats
- Warm earth palette (browns, cream, bronze) — not purple SaaS, neon glow, or newspaper broadsheet

### Admin / auth forms: utilitarian calm

- Clear hierarchy, dense tables, sidebars
- Same color tokens; minimal animation
- Prefer clarity over atmosphere

---

## Colors

Use **only** these tokens (Tailwind theme keys):

| Token | Hex | Role |
|-------|-----|------|
| `primary` | `#623F1F` | Earthy brown brand fill, links, focus |
| `primary-hover` | `#472A16` | Hover states |
| `primary-dark` | `#3F2614` | Dark interactive states; surfaces use primary to match the original logo |
| `primary-light` | `#f1e4d4` | Soft brand surfaces |
| `secondary` | `#6B7F5B` | Sage secondary actions and positive emphasis |
| `accent` | `#C96F4F` | Terracotta highlight and attention |
| `accent-light` | `#f4ddd4` | Soft terracotta |
| `background` | `#F8F6EE` | Warm cream page canvas |
| `surface` | `#fffdf8` | Cards / panels |
| `text` | `#2E3A2F` | Forest body text |
| `text-muted` / `muted-foreground` | `#596356` | Secondary copy |
| `muted` | `#eee9de` | Muted fills |
| `border` | `#D9C9B2` | Sand dividers / card edges |
| `success` | `#059669` | Positive |
| `warning` | `#d97706` | Caution |
| `danger` | `#e11d48` | Errors / destructive |
| `info` | `#0284c7` | Informational |

**Hero overlays:** black ~15% → 65% vertical gradient, or `primary` at high opacity on closing CTAs.

**Shadow:** `shadow-glass` → `0 8px 32px 0 rgba(0, 0, 0, 0.05)`.

---

## Fonts

Self-hosted via Fontsource (not a default Inter/system-only stack).

| Token | Family | Use |
|-------|--------|-----|
| `font-heading` | Cormorant Garamond | Editorial headlines, hero H1 |
| `font-sans` | Figtree Variable | Body, UI, forms, buttons |
| `font-display` | Outfit Variable | Short brand lockup in header / nav |
| `font-script` | Great Vibes | At most one decorative quote / lockup |

**Buttons:** Figtree semibold, ~14–16px, `min-height` 44px, `rounded-lg`, primary fill with white text.

---

## Spacing

| Convention | Value |
|------------|--------|
| Page gutters | `px-4 sm:px-6 lg:px-8` |
| Content max width | `max-w-7xl` (80rem) |
| Hero inner | `max-w-6xl` |
| Public hero | `min-h-screen` where used; listing heroes ~48vh / lg 62vh |
| Cards | `rounded-3xl`, `border-border`, `shadow-sm` → stronger on hover |
| Availability bar | `rounded-2xl`, `bg-surface/95 backdrop-blur-md`, elevated shadow |
| Touch targets | Prefer `min-h-[44px]` on primary CTAs |
| Bottom dock | `--dock-bottom` (0, or the measured height of a page's docking bar) plus `env(safe-area-inset-bottom)` — never a per-element `bottom-*` |
| Admin sidebar | `16rem` |

**Layout principle:** One job per section — one headline, one short supporting sentence, one primary action where needed. Avoid pill clusters, stat strips, and competing hero overlays.

---

## Animations

Motion is **slow and intentional**. Prefer CSS + Alpine; no GSAP.

| Motion | Behavior |
|--------|----------|
| Ken Burns | `.animate-kenburns` / `.hero-photo` — scale ~1.0 → 1.08 over ~22s, alternate |
| Hero enter | `.animate-hero-copy` (~0.8s), `.animate-hero-desk` (~0.9s, delayed) |
| Scroll reveal | Alpine `x-reveal` + `.js-reveal` — opacity + ~24px translate, ~0.7s, stagger via `--reveal-delay` |
| UI hover | Card lift / image scale ~200–500ms; no bounce |
| Modals | Alpine `x-transition` opacity + scale |

**Rules**

- One ambient motion at a time (hero Ken Burns **or** competing hover spectacle — not both fighting).
- No autoplay carousels as the main storytelling device.
- Durations: UI 400–800ms; Ken Burns 18–24s.

### Reduced motion

Honor `prefers-reduced-motion: reduce`:

- Disable Ken Burns and hero enter travel
- Force reveals visible immediately (CSS + JS check in `x-reveal`)

---

## Accessibility Guidelines

1. **Skip link** — Header skip to `#main-content`.
2. **Focus** — Global `:focus-visible` outline `2px solid #623F1F` with offset; interactive controls use ring utilities.
3. **Semantics** — Correct headings; `aria-current`, `aria-expanded`, `aria-controls`, `aria-label`, `aria-live` where status updates.
4. **Dialogs** — `role="dialog"`, `aria-modal`, labelled title, Escape to close, focus trap where implemented.
5. **Icons** — Decorative SVGs `aria-hidden="true"`; icon-only buttons need accessible names (`sr-only` or `aria-label`).
6. **Contrast** — Prefer `text` / `text-muted` on `background` / `surface`; white text only on primary or photo overlays with sufficient shade.
7. **Touch** — Keep primary actions ≥ 44px tall.
8. **Motion** — Always provide reduced-motion path.
9. **Forms** — Visible labels (`InputLabel`), error association (`InputError`), do not rely on placeholder alone.
10. **Admin density** — Still keyboard-reachable; do not remove focus styles for aesthetics.

---

## Component vocabulary (existing)

Prefer existing Blade components under `resources/views/components/`: buttons, Card, Badge, Alert, EmptyState, LoadingCard, form primitives, SectionHeading, PriceDisplay, FacilityCard / FeaturedFacilityCard, SocialLinks, BackToTop.

Layouts: `layouts/customer` (public), `layouts/guest` (auth), `layouts/admin` (back office).

### The signed-in account shell

The guest account area has its own small vocabulary, so a page in it is never
announced three different ways:

| Component | Job |
|-----------|-----|
| `x-customer.page-header` | The one way an account page states where you are (`breadcrumbs` or `back`), what the page is (`title`, `eyebrow`) and what you can do on it (`actions` slot). One typeface and one scale — `font-heading`, `text-3xl sm:text-4xl lg:text-5xl` — across every account page. |
| `x-customer.account-nav` | The account's sections. Rendered once by `layouts/customer`, sticky under the header, on every account page except a booking in progress. **This is the only place the sections are named**: the header carries no section links, so the two bars cannot say the same thing in two different words, and a page does not invent its own way back. |
| Price summary (`customer/booking/partials/price-summary.blade.php`) | The one place a total is both printed and explained: a line per component with the arithmetic it is made of, whether the figure is our estimate from the published rates or the resort's confirmed total, and a note saying what moved it and why (`resources/js/price-change.js`). The confirm-step breakdown and the summary beside the form render the same partial, and the sticky bar names the total too — that is the figure a guest leaves the page with. |
| `x-customer.reservation-tabs` | The three questions a booking answers — Your stay, Arrival, Payments. A record's parts are tabs of the record, not a hand-written "back to …" link, and one facet answers one question at a time: the stay page does not also carry the desk code, the money page does not also carry the bed count. |
| Reservation card (`customer/reservations/partials/reservation-card.blade.php`) | One booking on **My reservations**, with one way into it: the link opens the page that matters for where the booking is (`App\Enums\ReservationDestination`) — a pending booking opens its deposit and payment history, a confirmed stay that has not finished opens **Arrival**, and a booking that is over opens the record itself. One link per page: a booking whose deposit can be paid on the card shows the payment button alone, never a second path to the same page. |
| Booking steps (convention, no component) | The reservation flow is one four-step story: **Space → Plan → Guests → Confirm**. The picker (`customer/booking/select.blade.php`) is step 1, "Space", and it is where dates are first asked for: they filter the list down to the spaces that are free — by `AvailabilitySearchService::openAmong`, so the picker and the availability search cannot disagree — and then travel into the wizard, which counts the rest under the same four names and prefills them. Adding a step means renaming it in both places, because the guest is reading one journey. When nothing is free for the dates they arrived with, the empty state offers the nearest dates that are — `AvailabilitySearchService::nearbyOpenWindows`, asked of the same rules, nearest first, keeping the tab, the party and the cottage session — and says plainly when the days either side are taken too, rather than leaving the guest to guess. What survives is ordered by what the guest asked for, not by name — `FacilityQueryService::rankForGuests` puts the spaces that include the whole party first, then the lowest published rate, then a card with a photo, with the name only as the tie-break that keeps pagination stable — and the page prints the order it used, because a list that reorders for invisible reasons has to be re-read from the top. |

The account nav's labels are quoted in tests (`My reservations`, `New reservation`,
`My Inquiries`), so sentence case and the capital I are load-bearing — see
`CustomerNavigationTest`, `ReservationDashboardTest`, `CustomerDepositWorkflowTest`.**Rule of one navigation.** A signed-in page shows the account sections once.
If a new account page needs to point somewhere else, it uses `page-header`
breadcrumbs or `reservation-tabs` — it does not add another nav bar, and it
does not restate the sections.

### The bottom of the screen, and the booking calendar

Two pieces of the guest flow are not written per view, because each is one answer to a
question several elements ask at once:

| Piece | Job |
|-------|-----|
| Dock insets (`resources/css/app.css`) | The header, chat launcher, chat panel, back-to-top, booking bar and footer all sit on one vocabulary: `--dock-bottom` is the height of whatever is pinned to the bottom edge (0 everywhere except the page that says `body.has-bottom-dock`), and `--safe-top/--safe-bottom/--safe-left/--safe-right` are the display's own insets, which only resolve because `layouts/customer` sets `viewport-fit=cover`. A fixed helper reads these; it does not carry its own `bottom-*` utility, because two answers to one question is how the launcher ended up painted over the booking bar. |
| Booking calendar (`resources/css/app.css`) | flatpickr's palette and its 39px day cells are answered once, for every date field on the guest side: chosen day `primary`, today `accent`, hover `primary-light`, unavailable `text.muted`, 44px of touch height, and a time field at 16px so iOS does not zoom on focus. It is themed here rather than in a stylesheet of its own because the palette scan reaches `app.css` and `resources/js` only, and its selectors are doubled because the library's CSS is injected after this file. The stock values it replaces are recorded in `docs/MOBILE-AUDIT.md`. |
