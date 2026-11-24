# Generated Design System — DESIGN-SYSTEM.md

`ui-ux-pro-max` can generate a per-project design system into
`design-system/<project-slug>/`. This project generated one. This document records what
that tree is, what it may decide, and every place it disagrees with the resort's own
palette — so that a file an agent finds *newest* does not become the file it trusts
*most*.

The generated file is **advisory**. It is not a source of truth, and nothing in it
overrides a token, a guard test, or `DESIGN.md`.

---

## Authority order

| Rank | Artifact | Decides |
|------|----------|---------|
| 1 | `tailwind.config.js` + `resources/css/app.css` | The tokens themselves — every colour, font, radius, shadow and motion value. Enforced by `SharedVocabularyTest` and friends |
| 2 | `docs/DESIGN.md` | How those tokens are used: composition, one job per section, motion, accessibility, component vocabulary |
| 3 | `.superdesign/design-system.md` | Living design target, same standing as `DESIGN.md` |
| 4 | `design-system/<slug>/MASTER.md` *(generated)* | **Advisory only.** Structure, states, accessibility and per-stack implementation questions. Never colour, never type, never elevation |

A generated recommendation that contradicts rank 1 or 2 is discarded, not negotiated.
Its value is the parts this repo has no opinion about: the pre-delivery checklist, the
state and empty-state prompts, and the `--stack laravel|html-tailwind` implementation
rows.

## What was generated

| | |
|---|---|
| File | `design-system/villa-mercedes/MASTER.md` (214 lines) |
| Generated | 2026-09-19, by `ui-ux-pro-max-cli` 2.15.0 via `--design-system --persist` |
| Query | `"hotel hospitality resort nature farm"` |
| Match | `products.csv` row 38 — **Hotel/Hospitality**. The correct top hit |
| Dials | none: `--variance/--motion/--density` were left at default |

The match is right. What it resolved *to* is another brand's palette, because the
catalog's palettes are 1:1 with its 192 product types and none of them is this resort.
The implemented resort palette is now the earthy brown, forest, sage, terracotta,
sand, and cream system documented in `docs/DESIGN.md`.

Two structural notes before the conflicts. The product row's own reasoning
(`ui-reasoning.csv` row 38) asks for **"Warm neutrals + Gold (#D4AF37)"** and
**"Elegant + Welcoming typography"** — which is closer to this resort than what the
generator emitted, so the skill disagrees with itself here. And the dials cannot settle
any of it: the CLI documents them as spacing, layout variance and motion-snippet inputs,
while the palette comes from that 1:1 row.

## The palette

The generated table has sixteen roles, and the file names **thirteen distinct colours** in
all — the table, plus `#CA8A04` in a provenance note and `#E2E8F0` on the input spec
further down. Its colors remain advisory; the implemented resort tokens are the earthy
palette below, chosen from the supplied reference image and the existing logo:

| Generated (`MASTER.md`) | Resort token (`tailwind.config.js`) |
|---|---|
| Primary `#1E3A8A` (:21) | `primary #623F1F` |
| Secondary `#3B82F6` | `secondary #6B7F5B` |
| Accent/CTA `#A16207` | `accent #C96F4F` |
| Background `#F8FAFC` (:27) | `background #F8F6EE` |
| Foreground `#1E40AF` (:28) — body copy | `text #2E3A2F` |
| Card Foreground `#1E40AF` (:30) | `text #2E3A2F` |
| Muted `#E9EEF5` / Muted Foreground `#475569` | `muted #eee9de` / `text-muted #596356` |
| Border `#BFDBFE` (:33) | `border #D9C9B2` |
| Destructive `#DC2626` | `danger #e11d48` |
| Ring `#1E3A8A` | `:focus-visible` outline `primary` (`app.css`) |

Three roles are `#FFFFFF` (On Primary, Card, On Destructive). White is genuinely the
house answer for text on a primary surface, so those happen to be usable — but they are
right by luck, not by knowledge of this product.

**Twelve rendered tokens have no row at all**, so anyone reconciling the app to this
table would delete them to make room: `primary-hover`, `primary-dark`, `primary-light`,
`accent-light`, `danger` + `danger-light`, `success` + `success-light`, `warning` +
`warning-light`, `info` + `info-light`. So do the `shadow-glass` elevation, the
`kenburns`/`hero-enter` animations, and the three theme steps the Tailwind plugins read
through no class name (`gray.400`, `gray.500`, `blue.600`) — the steps rule 17 exists to
protect.

The table's third column invents sixteen `--color-*` variables. `app.css` defines
**zero** of them; its only custom properties are `--dock-bottom` and the four `--safe-*`
insets. Adopting that column would create a second palette beside the enforced one.

**This is not a question of taste, and that is the reason it is written down.** Following
the table means writing literal hexes into a Blade view or `app.css`.
`SharedVocabularyTest::LITERAL_COLOUR_PATTERN` fails a literal colour in `app.css`, and
its `PALETTE_PATTERN` fails the stock Tailwind scales that would be the alternative in a
view. The generated palette cannot land in this repository without failing the suite.

## Outside the palette

| Area | Generated | This project | Source |
|---|---|---|---|
| Heading / body | Playfair Display SC / Karla (:42-43) | Cormorant Garamond / Figtree (`font-heading`, `font-sans`), plus Outfit lockup and one Great Vibes quote | `tailwind.config.js`; `DESIGN.md` Fonts |
| Typography provenance | Matched on the keyword *hospitality* | The product row asks only for "Elegant + Welcoming typography" | `typography.csv` row 33 is **Restaurant Menu**; `ui-reasoning.csv` row 38 |
| Font delivery | Google Fonts `@import` | Self-hosted via Fontsource (four `@fontsource*` deps in `package.json`) | `DESIGN.md` Fonts ("Self-hosted via Fontsource") |
| Style | **Liquid Glass** (:167), "fluid morph transitions aligned to Apple platform behavior" (:173) | Glass is one component — the availability bar (`bg-surface/95 backdrop-blur-md`); motion is slow and intentional | `DESIGN.md` Themes, Animations. The skill's own README scopes Liquid Glass as an Apple platform *material*, and flags it `cost:moderate` with conditional accessibility |
| Page pattern | **Hero + Testimonials + CTA** with a testimonials **carousel** (prev/next, pause, announce) | No carousel exists anywhere in `resources/views`; reviews are a static `x-review-quote` grid linking to `/reviews` | `home.blade.php:329`; `DESIGN.md` "No autoplay carousels as the main storytelling device" |
| Section order | Hero > Problem statement > Solution overview > Testimonials > CTA | Hero (:12) > glass availability bar (:48) > "What brings you here?" (:56) > Featured stays > "How to reserve your stay" (:176) > reviews | `home.blade.php` |
| Timing | "Always use transitions (150-300ms)"; `transition: all 200ms ease` on every control (:87, :104, :117) | UI 400-800ms; card lift / image scale 200-500ms; no bounce | `DESIGN.md` Animations. `transition: all` also animates properties nobody intended |
| Card radius | 12px (:114) | `rounded-3xl` (24px) for cards | `DESIGN.md` Spacing |
| Card fill | `background: #F8FAFC` (:113) — its own **Background** hex, on a page whose background is that same value | `surface #fffdf8` on `background #F8F6EE` | Contradicts its own table at :29 (`Card #FFFFFF`) |
| Input border | `#E2E8F0` (:132) | `border #D9C9B2` | A colour that appears **nowhere in its own palette table** |
| Focus | `outline: none` on `:focus` (:140), navy ring | Global `:focus-visible` outline, 2px `theme('colors.primary.DEFAULT')` + offset | `app.css:274`; `DESIGN.md` Accessibility 2 and 10 |
| Button hover | `opacity: 0.9` | The `primary-hover #472A16` token | `DESIGN.md` Colors |
| Card cursor | `cursor: pointer` on the card element itself | One link per card, and the link is the target | `DESIGN.md` — the reservation card opens exactly one destination |

## Internal inconsistencies

These do not depend on anyone's judgement, which is why they are kept separate:

1. **The retrieval header sends an agent to the wrong path.** `MASTER.md:3` says to check
   `design-system/pages/[page-name].md`; the installer's own output and the CLI's help say
   `design-system/villa-mercedes/pages/[page].md`. No `pages/` directory was created.
2. **`.card` paints itself the Background token** (:113) while its own table gives Card as
   `#FFFFFF` (:29) — cards would be invisible against the page.
3. **It drops the product-specific material its engine holds.** The reasoning row's
   must-haves (`room-booking`, `virtual-tour`) and its key effects ("Room gallery + Amenity
   reveals") never reach the file; the Key Effects line is the *style* row's Apple-morph
   text instead. The persisted file is less product-aware than the CSV behind it.

## What it gets right

Credited on the same evidence, because an audit that only counts faults is not an audit:

- **Anti-patterns: "Poor photos + Complex booking."** Both are this product's real risks —
  a photography-first site, and a four-step wizard over mixed inventory.
- **Buttons 8px** (:85) and **inputs 16px** (:134) match the house spec (`rounded-lg`, and
  the 16px that stops iOS zooming a focused field).
- **Modals 16px** (:155) is a radius the views already use (`rounded-2xl`, 67 uses).
- **"No content hidden behind fixed navbars"** is the `--dock-bottom` / `--safe-*` rule in
  other words, and **"no horizontal scroll on mobile"** agrees with `MOBILE-AUDIT.md`.
- **SVG icons, visible focus, `prefers-reduced-motion`, 4.5:1 contrast, no layout-shifting
  hovers** all agree with `DESIGN.md`.
- **Social proof belongs on the home page** — the page already has a reviews section.
- **The shadow table conflicts with nothing**: those are Tailwind's own default elevation
  steps restated as variables. It merely omits `shadow-glass`.

What it lacks is as useful as what it says. Its checklist has no touch-target minimum
(44px, `MobileAvailabilityTest`), no palette-token rule, no money/status vocabulary, and
no admin date-with-year rule. The guard tests remain the gate.

## Regenerating

The skill's scripts need Python 3, and `python`/`python3` on this machine hit the
Microsoft Store alias stub. Use the interpreter Laragon ships:

```bash
"C:\laragon\bin\python\python-3.13\python.exe" .agents/skills/ui-ux-pro-max/scripts/search.py \
  "hotel hospitality resort nature farm" \
  --design-system --persist -p "Villa Mercedes" -f markdown --output-dir . --force
```

`design-system/` is gitignored, so regenerating is free: it changes nothing tracked.
Expect the output to conflict with the palette again in exactly the ways above — a
regeneration reproduces the conflict rather than resolving it, because the palette is
matched by product type, not by brand. A page override would go in
`design-system/villa-mercedes/pages/<page>.md` (mind finding 1 above) and should record
only that page's genuine deviations from the resort tokens.

Use visible headings for routine tasks and the content-panel component for optional reference material. Avoid HTML <summary> disclosure rows throughout the interface.
