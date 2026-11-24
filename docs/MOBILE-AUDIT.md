# Mobile Availability Audit — Villa Mercedes guest flow

**Scope:** the guest surfaces a phone actually reaches — the availability search bar, the
Space picker, the facility detail panel, and the booking wizard's docked checkout bar.

**How to read this.** Every verdict below is against the repository, not against a
screenshot, and the evidence is quoted so it can be re-checked in one grep. An audit that
marks a component ✅ without checking whether the component exists is worse than no audit,
because the ✅ is what stops the next person from looking.

---

## 1. Correction of record

A previous audit of this flow used a different stack and reported nine findings. Six of
its nine ✅s were assertions about components that are not in this repository.

### 1.1 The stack

| Claimed | Actual |
|---|---|
| Next.js + Tailwind CSS + TypeScript | Laravel + Blade + Alpine 3 + Tailwind v3 + Vite 6 |

There is no `tsconfig.json`, no `next.config.*`, and no `.ts`/`.tsx` file outside
`node_modules`. `docs/RULES.md` lists "Inertia / Livewire / React SPA layers unless
explicitly requested" under **Do not scaffold**, and "Replace Blade+Alpine with a SPA
'because it's modern'" under **Never**. The acceptance criteria for any work here are the
ones in `docs/DESIGN.md` and `docs/RULES.md`; "aligned with Next.js + TypeScript" cannot be
satisfied without abandoning the architecture that carries the product's whole argument.

### 1.2 Finding-by-finding

| # | Prior verdict | Verdict now | Evidence |
|---|---|---|---|
| 1 | "Compact summary pill → ✅" | **Not present** | No summary pill exists. The only sticky element in the flow is the wizard's four-step *progress* bar (`customer/booking/create.blade.php`, `sticky top-20 z-30`), which names steps, not the search. The Space picker (`customer/booking/select.blade.php`) has no sticky element at all. |
| 1 | "Full-screen bottom sheet modal → ✅" | **Not present** | Dates are entered inline: native `<input type="date">` in the picker's filter card, flatpickr in the wizard. `components/modal.blade.php` and `components/reservation-auth-modal.blade.php` are both centred dialogs, not sheets. |
| 1 | "Large calendar grid inputs → ✅ 44×44px" | **False — 39×39px** | See §1.3. This is the one ✅ that mattered, and it was wrong. |
| 2 | "Vertical stacking → ✅" | **Holds** | The picker list is `grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3`. |
| 2 | "Thumbnail image on top → ✅" | **Holds** | `aspect-video w-full object-cover` above the card body. |
| 2 | "Title, price, amenity badges → ✅" | **Holds** | Type label, name as the heading link, description, capacity, "From" rate with `<x-amount>`. |
| 2 | "**Select Room** button in thumb zone → ✅" | **Wrong label, wrong frame** | The card CTA is **"View details"** (full-width, `min-h-[44px]`), in both the picker and `components/availability-result-card.blade.php`. "Room list" also mis-frames the domain: this system's thesis is competing claims on rooms, cottages, halls and packages, so a rooms-only list would contradict it. |
| 3 | "Bottom-docked bar, fixed during scroll → ✅" | **Holds, with two defects** | The bar is real: `lg:hidden fixed inset-x-0 bottom-0 z-50` in the wizard. It carried an inert safe-area padding and a launcher collision (§2). |
| 3 | "Total price + **Reserve Now** CTA → ✅" | **Dynamic label** | The CTA's text is `bottomBarLabel`, which says what the next step is. Naming it "Reserve Now" would be wrong on step 1 of 4. |

### 1.3 The calendar, measured

The audit blessed flatpickr's grid at "44×44px". The installed stylesheet says otherwise.
Computed from `node_modules/flatpickr/dist/flatpickr.min.css`:

| Rule | Stock value | Why it matters |
|---|---|---|
| `.flatpickr-day` | `max-width: 39px; height: 39px` | Below the 44px minimum DESIGN.md asks for, on the control a guest taps most. |
| `.flatpickr-calendar` | `width: 307.875px; font-size: 14px; line-height: 24px` | A 308px calendar overflows a 320px viewport once page gutters are removed. |
| `.flatpickr-time` | `max-height: 40px`; `input{font-size:14px}` | The hour/minute/AM-PM wheels are a third control at 14px — and 14px is the threshold at which iOS Safari zooms the page on focus. |

Every colour the stock stylesheet paints, with nothing in any view naming it:

| Stock colour | Where it lands |
|---|---|
| `#569ff7` | The selected day, range endpoints, week-range inset shadows |
| `#e6e6e6` | Calendar hairlines, day hover/focus fill, in-range shadows, the time row's top border |
| `#393939` | Day ink, time input ink, time separator and AM/PM ink, spinner arrow triangles |
| `#959ea9` | Today's border, previous/next-month hover ink |
| `#f64747` | The month navigation arrow's hover fill |
| `#eee` | Hour/minute/AM-PM hover and focus fill |
| `rgba(0,0,0,.9)` / `(0,0,0,.54)` / `(0,0,0,.5)` | Month label and nav ink, weekday ink, disabled year ink |
| `rgba(57,57,57,.3)` / `(57,57,57,.1)` | Other-month days, disabled days |
| `rgba(57,57,57,.15)` / `rgba(0,0,0,.05\|.1\|.2)` | Spinner arrow borders; hover and active fills |

This is the palette rule in `RULES.md` 17 — "a colour a library paints is part of the
palette too, and it is the part no scan can see" — reaching a library the project had not
audited yet. The same blindness is recorded there as having already cost four leaks through
`@tailwindcss/forms`. `theme: "light"` was passed to flatpickr while only
`flatpickr/dist/flatpickr.min.css` was imported, so the option added a class that nothing
styled.

---

## 2. What was changed

### 2.1 The safe area was inert

`layouts/customer.blade.php` set `width=device-width, initial-scale=1` with no
`viewport-fit=cover`, and Safari resolves `env(safe-area-inset-*)` to `0` unless the page
opts in. The booking bar's `padding-bottom: calc(0.75rem + env(safe-area-inset-bottom))`
therefore resolved to a flat 12px, and the CTA the whole flow is aimed at sat in the home
indicator's gesture area. The security here is that the bug was *silent*: the padding was
written, read correctly, and did nothing.

**Now:** the layout opts into `cover`, and one vocabulary in `resources/css/app.css` owns the
bottom of the screen — `--dock-bottom` (the height of whatever is pinned to the bottom edge),
plus `--safe-top/--safe-bottom/--safe-left/--safe-right`. The header, chat launcher, chat
panel, back-to-top and footer all read it. The docked page names itself
(`body.has-bottom-dock`, set by route in the layout), so the fallback is zero on every page
that docks nothing.

### 2.2 The launcher sat on the bar

The Messenger launcher is `z-[70]` and the booking bar `z-50`, and the bar held back its
right `pr-24` for the launcher — so the launcher was painted *over* the bar, inside the
gutter the bar had reserved for it. A thumb going for the total or the CTA could open
Messenger instead.

**Now:** the launcher, chat panel and back-to-top climb by `--dock-bottom`, so nothing
floats over the bar; the bar keeps the full width and takes the display's own left/right
insets. The height is the stylesheet's estimate, refined by the bar measuring itself on boot
and on resize, because the bar grows a fourth line when the total has moved since the guest
first saw it.

### 2.3 The calendar is now the resort's

`resources/css/app.css` maps every state in §1.3 to a token: chosen day → `primary`, today →
`accent`, hover → `primary-light`, unavailable → `text.muted`, ink → `text`, hairlines →
`border`, panel → `surface`. Day cells are 44px tall and take their width from the
seven-column grid, so the calendar fits a 320px phone instead of overflowing it. The time
field is 16px, which is the threshold that stops iOS zooming on focus.

Measured in a browser at a 533px viewport: the panel is 336px wide, the day strip fills it,
and a cell is **48×44px**. At 320px the panel is 288px and a cell is 41×44px — 44px wide is
not reachable there, because seven of them plus a gutter exceeds the screen; the honest
choice is the widest cell that does not overflow, which is what the fluid width buys.

Two constraints shaped the implementation and are worth knowing before editing it:

- **The theme cannot live in its own file.** `SharedVocabularyTest` scans `css/app.css` plus
  `resources/js/**`, so a new stylesheet would be unscanned — the exact leak the guard's own
  docblock says has happened three times.
- **The selectors are doubled on purpose.** `flatpickr.min.css` is imported lazily by
  `app.js` alongside the library, so it lands in a chunk injected *after* `app.css`, and an
  equally specific rule loses to it regardless of how this file is ordered.

### 2.4 The date field is a thumb target

`create.blade.php`'s two date triggers were `<x-text-input class="pl-10 block w-full">`,
which the form plugin paints at about 40px — under the minimum, on the first control in the
flow. They now carry `min-h-11`.

### 2.5 What only the browser caught

Three defects survived a green test suite, a resolved stylesheet and fifty specificity
comparisons. Each is recorded here because each would have shipped silently.

1. **The dock measurement went stale.** The bar is 86px tall once its total and its
   "estimate from published rates" line are in it, but it measures 69px at boot — before the
   webfont lands and before the third line fills. A single `x-init` measurement therefore
   published a number 17px short of the truth, and the chat launcher cleared the bar by 3px
   by luck rather than by arithmetic. It is now observed with a `ResizeObserver`, so the
   value tracks the bar instead of describing an earlier one. The stylesheet's own default
   (`5.75rem`) deliberately errs upward for the same reason.
2. **The day strip was still 307.875px.** That is the width of the *stock* panel, and the
   panel is now fluid, so the grid sat against the left edge of a 336px box and the cells
   stayed at 44px when the room existed for 48. The strip follows the panel now.
3. **The `z-index` was dead on arrival.** flatpickr writes `z-index: 99999` as an inline
   style when it positions the panel, so no rule in the stylesheet could ever place it
   "above the docks and below the chat". It is left alone — it floats over the bar rather
   than behind it, which is what a date field wants — and the code no longer claims
   otherwise.

**Confirmed by eye, at a narrow viewport:** the panel is white on a warm border with a
brown month label and muted weekdays, the chosen-state brown replaces the stock blue, and
the Messenger launcher sits clear above the docked bar instead of on top of it.

**One thing left as it was.** flatpickr positions itself and can open below a field with a
row or two past the bottom of the viewport, which then needs a scroll. That is stock
behaviour and unchanged here; forcing `position: "above"`, or giving the panel a
viewport-bounded height with its own scroller, is the follow-up if it proves annoying on a
phone. It floats above the docked bar rather than disappearing behind it, which is the part
that would have hidden the guest's own date.

---

## 3. Recorded, not built

Both are real and both are outside the scope of this pass. They are written down rather than
dropped, with the evidence needed to pick either one up.

**N1 — the front door zooms on iOS.** `components/availability-bar.blade.php` gives every
control `text-sm` (14px) in `$controlClasses`: the hero's "What do you need?", "Date In",
"Date Out" and "Guests" fields. Tapping any of them on an iPhone zooms the page, at the one
control the resort's whole funnel starts from. The fix is a type scale that stays ≥16px on
touch and returns to 14px from `sm` up, with the fixed `h-11` unchanged.

**N2 — the picker forgets what it is showing.** `customer/booking/select.blade.php` states
the applied window once, in a heading above the results, and names the order it used. Scroll
the list and all of it leaves the viewport: the guest is choosing a space with no sight of
the dates they are choosing it for, and no way back to the filter without scrolling up. A
compact sticky summary — the applied window, the party, the free count, and one tap back to
the filter — is the honest version of what the original audit called a "summary pill".

---

## 4. Acceptance criteria in this stack

- Blade + Alpine 3 + Tailwind v3, tokens only; no SPA layer, no second palette.
- Every primary action ≥44px; the calendar's day cells ≥44px of height at every viewport.
- No literal colour in `app.css`; a library's colours are answered through `theme(...)`.
- `prefers-reduced-motion` honoured; no new gradients or decorative motion added.
- The docked checkout bar stays fixed across every scroll state, clears the home indicator,
  and shares the bottom edge with nothing else.

**Guards added** — `tests/Feature/MobileAvailabilityTest.php`: the viewport opt-in, the dock
vocabulary and its consumers, the calendar's cell size, its fluid grid and its token
mapping, and the 44px date triggers. Each guard is a claim a browser can contradict, so the
suite is a floor rather than a substitute for looking at the page. Choose `npm run build`,
`php artisan test` and `npm run test:ui` to check.

**One thing no test can settle:** which picker flatpickr chooses on a touch device. It
prefers the native datetime widget and falls back to its own calendar when the config needs
more than native inputs can express. The theme above is correct on either branch, and
`disableMobile` is deliberately left unset so the native picker keeps serving phones.
