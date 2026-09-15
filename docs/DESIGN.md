# Design System — DESIGN.md

**Product surface:** Public guest experience (home, listings, gallery, facility pages, booking affordances).  
**Admin / dense forms:** Calm and utilitarian — reuse tokens, avoid cinematic motion and marketing layouts.

Living target also tracked in `.superdesign/design-system.md`. Source of truth for tokens: `tailwind.config.js` + `resources/css/app.css`.

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
- Keep real copy (Ilagan, rooms & cottages, event hosting, packages). Do not invent fake occupancy metrics beyond published stats (3 pools, hotel & cottages, 4 halls, day & overnight packages).

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
| `primary` | `#623f1f` | Brand fill, links, focus |
| `primary-hover` | `#7a532c` | Hover states |
| `primary-dark` | `#432910` | Footer, deep overlays, listing heroes |
| `primary-light` | `#f3eadf` | Soft brand surfaces |
| `secondary` | `#d6c4b0` | Secondary accents |
| `accent` | `#a67c52` | Bronze highlight |
| `accent-light` | `#f4ead9` | Soft bronze |
| `background` | `#faf6f1` | Page canvas |
| `surface` | `#ffffff` | Cards / panels |
| `text` | `#1f140c` | Body |
| `text-muted` / `muted-foreground` | `#6b5748` | Secondary copy |
| `muted` | `#f3ebe3` | Muted fills |
| `border` | `#e4d5c6` | Dividers / card edges |
| `success` | `#059669` | Positive |
| `warning` | `#d97706` | Caution |
| `danger` | `#e11d48` | Errors / destructive |
| `info` | `#0284c7` | Informational |

**Hero overlays:** black ~15% → 65% vertical gradient, or `primary-dark` at high opacity on closing CTAs.

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
2. **Focus** — Global `:focus-visible` outline `2px solid #623f1f` with offset; interactive controls use ring utilities.
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
