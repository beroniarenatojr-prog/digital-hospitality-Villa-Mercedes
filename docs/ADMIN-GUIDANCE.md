# Admin guidance — teaching the three workflows without subtitle prose

**Confirmed booking practices:** [Policy and activation checklist](CONFIRMED-BOOKING-PRACTICES.md). Refund and reschedule eligibility ends at recorded physical check-in, not the scheduled arrival. Administrators record owner-approved refunds with an approval note and record returned funds separately. Checkout leaves rooms awaiting explicit staff cleaning release.

**Scope:** a first-run layer for the back office that teaches the **reservation**, **payment** and
**decision-board** workflows. It is a design, not yet built: nothing in this document is on the
branch, and §7 is the shape of the change that would be.

**The constraint it answers.** Every admin page used to carry a muted paragraph under its title;
those were removed on 2026-09-21 because a paragraph read once, at the wrong moment, by someone who
has not done the thing yet, teaches nothing. What replaces them is not a better paragraph. It is
less text, arriving later, at the decision.

---

## 1. What actually needs teaching

The screens already carry their own facts. The Decision Board prints *This booking earns*,
*Other space blocked* and *Net if approved*; the reservation record's payment summary says
*Verified payments only. Pending proofs do not reduce the balance*; the Operations tabs label
themselves. Those are numbers and labels, and they are fine.

Two things they cannot say, because they are not facts about a record:

1. **Sequence.** Which step comes before which — a decision holds dates, money verifies the hold,
   arrival turns the hold into a stay. Each screen shows its own moment and nothing about the next.
2. **Consequence.** What the action costs someone else. "Other space blocked ₱4,500" names an
   amount; it does not say that approving this request removes those dates from the pool.

That is the entire curriculum. Everything below is placed against those two gaps, and anything a
screen already says is deliberately absent from it.

---

## 2. Three layers, three lifetimes

| Layer | Where it renders | How long it lives |
|---|---|---|
| **First-shift strip** | Dashboard, one line under the page title | Until all four steps are done, or the staff member hides it. Per staff member. |
| **Three workflow cards** | Decision Board, Payment review, Reservation record — where the work starts | Until that staff member performs that workflow, or presses *Got it*. Per staff member, per workflow. |
| **Next-step cue** | Beside the control that performs the step | Never retires. It is state, not teaching. |

The layers are ordered by intrusiveness, not importance: the strip costs one line and disappears
for good, the cards cost three lines each and disappear when the work is done, and the cues cost
nothing because they are the interface.

---

## 3. Progress is a query, not a checkbox

The strip's ticks come from rows the system already writes when the work is done — `activity_log`,
matched through `ActivityActionType::fromEvent()` (which is also what `isApproval()` already uses to
tell an approval from any other status change):

| Step | Satisfied by | Written today by |
|---|---|---|
| Decide a request | `status_changed` whose new status is *Approved* (the same test `isApproval()` already applies) or *Rejected* | `ReservationService` |
| Verify a deposit | `payment_verified` | `PaymentService` |
| Check a guest in | `checked_in` | `ReservationService` |
| Check a guest out | `checked_out` | `ReservationService` |

All four entries already exist in the ledger, so the layer adds no logging and cannot disagree with
the Activity Log about what a staff member has done.

Consequences of deriving instead of storing:

- **No progress table, no "mark as done" button, no step that can be ticked by reading.** Finishing
  the checklist means doing the work, which is the only definition that stays true.
- **The layer survives a cleared browser, a new laptop, and a reset password**, because the state is
  server-side and derived from the ledger, not from a click.
- **One new column, holding only refusals:** `users.admin_guidance` (JSON, nullable) — when the strip
  was hidden, and which cards were dismissed. Nothing in it can make a step look done.

---

## 4. The copy, and its budget

Every string in the layer, with the placement that owns it. The budget is the point: this is what
"without long subtitle text" means in numbers.

| Rule | Limit |
|---|---|
| Card title | 4 words |
| Card line | 8 words, exactly three lines per card |
| Strip step label | 4 words |
| Next-step cue | 8 words |
| Any sentence in the layer | 12 words |
| A cue repeating a number already visible on the screen | never |

### 4.1 First-shift strip (Dashboard)

`Your first shift · ✓ Decide a request · Verify a deposit · Check a guest in · Check a guest out · Hide`

- Each step is a link to where the work begins: Decide → Decision Board; Verify → Payment review;
  Check in / Check out → Reservations (whose arrivals panel is the list of people to check in).
- Done steps show a tick and strikethrough; not-done steps show nothing but are never disabled.
- Screen readers get `aria-label` per step (`Done` / `Not done`) and `2 of 4 steps done` on the list,
  so progress is not carried by a tick glyph or a colour.
- When all four are done the strip renders **nothing at all** — not an empty state, not a
  congratulations panel. It has no reason to exist any more.

### 4.2 Workflow cards

Each is the first thing in the region that starts the work, titled, three lines long, with one
button. No illustration, no arrow pointing at anything, no second screen.

**On the Decision Board, above the pending queue**

> **When you decide**
> Pending requests hold dates.
> Approving blocks them for everyone.
> Rejecting requires a reason.
> *Got it*

**On Payment review, above the filter panel**

> **When you verify**
> Match the proof to the account.
> Verified money holds the space.
> Short? Ask for a top-up.
> *Got it*

**On the reservation record, beside the Decision panel**

> **While they are here**
> Check in when they arrive.
> Check out closes the stay.
> Withdrawing frees the dates.
> *Got it*

### 4.3 Next-step cues

One line, computed from the record's own state, rendered where the decision is made. This is the
layer that keeps teaching after the first run without ever asking to be read again.

| State | Cue |
|---|---|
| Reservation pending | Waiting on your decision. |
| Approved, deposit not verified | Waiting on the guest's deposit. |
| Approved, deposit verified, before arrival | Ready to check in on arrival. |
| Checked in | Ready to check out. |
| Completed | *(no cue)* |
| Rejected / cancelled / withdrawn | Dates released. |
| Payment proof pending | Check the proof against the QR account. |
| Payment rejected | Space stays held until the deposit window closes. |

### 4.4 The one place the layer prints data

On a queued request in the Decision Board, the cue names **what approving displaces**. The service
already computes it — `DecisionSupportService::analyze()` returns `conflicts`, each an
`OccupancyClaim` with `label` (`"RC-0142 — Acosta"`), `facilityName` and `durationLabel()` — so the
cue is a label over data, and it has to be narrowed to fit the budget:

| Conflicts | Cue |
|---|---|
| exactly one | `Blocks Acosta, Sep 24–26` — the guest name from `label`, the dates from `durationLabel()` |
| more than one | `Blocks 3 stays` — the count, because three names do not fit and a truncated list is worse than a number |

This is the single highest-value sentence in the layer: it turns *Other space blocked ₱4,500* into
the name of the booking that money was going to earn. The guest name is used rather than the
reservation code because the decision is about a person, and the code is on the row beside it.

---

## 5. Behaviour rules

- **Never blocks.** No modal on load, no overlay, no scroll lock, no scrolling animation to an
  element. Nothing in the layer takes focus on arrival.
- **Never reorders the work.** The strip is one line above *Needs attention*; the cards sit above
  the queue they explain. Nothing a staff member came for moves down the page by more than the
  height of the thing explaining it.
- **Never appears where it cannot be acted on:** the guest portal, the public site, the sign-in
  screen, or a print view.
- **Never duplicates a screen's own words.** If a panel already says it, the layer does not — see §1.
- **Dismissal is honest.** The strip and cards are server-side, so one staff member's *Hide* never
  hides another's, and a dismissal is not undone by clearing the browser. `x-admin.notice`'s
  existing `dismissible` prop is client-side only, which is why the layer does not reuse it for
  anything that has to stay dismissed.
- **Accessible by construction:** steps are a real `<ol>` of real links; state is text for screen
  readers, not a tick glyph alone; the tick uses the existing transition tokens and respects
  `prefers-reduced-motion`; and nothing in the layer is revealed on hover, because the desk runs
  this on a tablet where there is no hover — the back office's existing controls are click-driven
  for the same reason (the notifications menu, the filter panels).

---

## 6. Why not a tour

A spotlight tour is the obvious build, and it is wrong here for four checkable reasons:

1. **It teaches before there is anything to decide.** Day one at a quiet desk has no pending
   request to approve; the tour would explain an empty queue.
2. **It has to be dismissed before the work starts**, which is the same failure as the subtitle:
   text between a staff member and the job.
3. **It costs a focus trap and a keyboard path** for a screen nobody asked to open — the sidebar's
   drawer trap exists because the drawer holds navigation, not because overlays are cheap.
4. **It teaches once.** The cues in §4.3 teach every time the state occurs, which is the only
   schedule that survives a first week.

---

## 7. Shape of the change

| File | Why |
|---|---|
| `app/Support/Admin/AdminGuidance.php` | The one list: the four steps (key, label, route, activity event) and the three cards (key, screen, title, three lines, the event that retires it). Same role as `AdminNav::groups()` — the views render from it and the copy test reads the same source, so nothing can be reworded in one place only. |
| `app/Services/Admin/GuidanceService.php` | `stateFor(User $user)` reads `activity_log` + the JSON column and returns the steps with their done flags and the cards still owed; `dismissChecklist()`, `dismissCard()`, `reset()`. |
| `database/migrations/…_add_admin_guidance_to_users_table.php` | `json('admin_guidance')->nullable()`, cast to array. No backfill — see §9. |
| `app/Http/Controllers/Admin/AdminGuidanceController.php` | Two `POST` endpoints (dismiss strip, dismiss card), both `back()`. No GET, so the layer is not a screen. |
| `resources/views/components/admin/guidance-strip.blade.php`, `…/guidance-card.blade.php` | Rendered from the list above; the only place the markup lives. |
| `resources/views/admin/dashboard.blade.php` + the three screens | Four insertions, no restructuring. |
| `app/Console/Commands/ResetAdminGuidance.php` | `php artisan guidance:reset {email?}` — the owner resets a trainee, and the demo can be replayed. |
| `tests/Feature/Admin/AdminGuidanceTest.php` | §8. |

Nothing is added to `AdminNav`: the layer has no screen of its own, and a sidebar entry for
"help" would be a fifth thing to maintain and a fourth place a new staff member could start.

---

## 8. Tests

Held to the repo's existing style — a positive control that proves each guard catches the mistake it
exists for, as `SharedVocabularyTest::test_each_guard_catches_the_mistake_it_exists_for` does.

1. **Copy budget.** Every string rendered by the layer comes from `AdminGuidance`, and every string
   is inside the §4 budget. A view that writes its own line fails.
2. **No duplication.** No guidance string equals an existing panel description or empty-state
   description in `resources/views/admin/`, so the layer cannot grow back into the subtitle it
   replaced.
3. **Derived, not claimed.** A step ticks only from that staff member's own activity; another
   staff member's approve leaves the first staff member's strip untouched.
4. **Lifetime.** The strip is absent once hidden and once all four steps are done; a dismissed card
   does not render again on that screen; neither dismissal is undone by a new session.
5. **Surface.** The layer never renders on `customer/*`, `pages/*`, `auth/*` or the print views — the
   same guest-surface scan `SharedVocabularyTest` already performs.
6. **Nothing steals the screen.** The rendered markup contains no `role="dialog"`, no `autofocus`,
   no `inset-0`, and the strip is an `<ol>` whose steps are `<a href>`.
7. **Routes are admin-only**, matching the rest of the back office, and the reset command restores a
   hidden strip.

---

## 9. Rollout

- **No backfill.** The migration adds a nullable column and nothing else, so the strip appears once
  for every existing staff member — including the owner, who should see what staff see. One *Hide*
  ends it. Hiding it from everyone at deploy would mean nobody could evaluate it.
- **The demo keeps it.** Seeded history is attributed to the seeded administrator, so a fresh demo
  may open with one or two steps already ticked. That is a truthful reading of the data, and it
  shows the mechanism.
- **The success test, stated in advance:** every staff member's strip should be gone within a week —
  either ticked or hidden. If it is still sitting there, the copy is wrong, not the staff member.

---

## 10. Not doing

No modal welcome tour, no spotlight overlay, no carousel, no tooltips, no video, no help centre, no
badge that is never closed, no hint that exists only on hover, no progress stored as a click, and no
copy that restates a screen's own title or its own numbers.
