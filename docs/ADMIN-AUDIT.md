# Admin Page Audit — Villa Mercedes back office

**Scope:** clarity, conciseness and necessity of the back office's own content, plus the files
and styles that exist to serve it. Public guest copy is out of scope except where it makes a
claim the system no longer supports (§6).

**How to read this.** Every verdict below is against the repository, with the evidence quoted
so it can be re-checked in one grep. Three findings in the original pass did not survive
checking and are recorded in §2.3 — an audit that hides its own false positives teaches the
next reader to trust all of it.

---

## 1. Framing correction

The brief asked for "Impeccable" and "TasteSkill" standards. Neither is defined anywhere in
this repository; there is no such artifact, doc or dependency. What this project defines is
`docs/DESIGN.md` (tokens, motion, "one job per section", the `x-admin.*` vocabulary) and
`docs/RULES.md` (minimal diffs, reuse components before writing new ones). Those are the
standards applied here, plus the three rules in the brief, stated as such rather than implied.

**Rule 1 was treated as a claim to test, not an instruction to obey.** "Remove context that
is not required for admin functionality" collides with this product's differentiator: the
README sells "explainable owner decisions", and the Owner Decision Board exists to price what
a decision displaces. Applied literally the rule deletes the feature. The line drawn here is
therefore:

- **remove** what restates something already on the screen, or states the obvious;
- **keep** the reasoning a staff member needs in order to read a figure correctly, and where
  it is long, fold it behind the pattern the codebase already invented for exactly this
  (`report-chart`'s `<details>` "Metric definition").

---

## 2. File and code audit

### 2.1 The dead component and the dead stylesheet

`resources/views/components/admin/column-chart.blade.php` had **zero references** in `app/`,
`routes/`, `resources/views/` or `database/`. Its only trace was an exception granted to it by
name in `SharedVocabularyTest::MONEY_ALLOWED` — an exception protecting a file nothing
rendered. It was superseded by `report-chart.blade.php`, which has five live call sites across
two report partials (`reports/partials/executive.blade.php`, `.../revenue.blade.php`).

Removed with it, from `resources/css/app.css` — **144 lines**, taking the stylesheet from 743
to 599 (`wc -l resources/css/app.css`), a claim checkable in one command:

| Block | Why it could go |
|---|---|
| `.admin-vchart` and its ten variants (`-y -plot -grid -gridline -bars -col -track -value -fill -label`) | Used **only** by `column-chart` |
| `@keyframes admin-vchart-grow`, `@keyframes admin-vchart-value-in` | Referenced only by the above |
| the `prefers-reduced-motion` block covering `.chart-col-*` and `.admin-vchart-*` | Covers only the above |
| `.chart-col`, `.chart-col-track`, `.chart-col-value`, `.chart-col-fill` (and both `.is-drawn` states) | **No consumer at all** — an older chart family, not even used by `column-chart` |
| `.admin-dialog-frame` | **No consumer** — the three dialog components build their own overlay inline |

The `column-chart` entry in `SharedVocabularyTest::MONEY_ALLOWED` went too, so the guard no
longer excuses a path that does not exist.

### 2.2 Leftovers from the removed TESDA module

Empty directories, all created by deleting files in a module that the product removed:
`resources/views/admin/training-applications/`, `resources/views/admin/training-batches/` (and
its `partials/`), and `app/Exceptions/Training/`. All four were removed.

Stated plainly, because it changes how much this finding is worth: **git cannot track an empty
directory**, so this was working-copy debris rather than repository content — the committed
tree never contained them. The migration `2026_09_07_000000_drop_training_tables` stays; it is
shipped history and `RULES.md` forbids editing a shipped migration.

### 2.3 Three findings that did not survive checking

1. **`chart-col` looked used.** It appeared in `column-chart.blade.php` — as a *substring* of
   `admin-vchart-col`. A word-boundary check showed `.chart-col`, `.chart-col-fill`,
   `.chart-col-track` and `.chart-col-value` have no consumer at all.
2. **A first orphan scan reported 47 unreached admin views.** It was searching for
   `customers/index` while controllers name views `admin.customers.index`. The corrected scan
   — dot notation, across `app/`, `routes/` and `resources/views` — returns **zero**. Every one
   of the 51 admin views is reachable, which is a positive result worth as much as the defects.
3. **"Two near-identical empty-state lines" was wrong.**   `reports/index.blade.php` and `operational-registry.blade.php` both close an empty state with "Widen the … range, or clear
   the filters, to see records." That is two screens sharing one house phrasing, not
   duplication, and nothing was removed. The phrasing is in fact wider than the pair that
   prompted the finding — `decision-board/index.blade.php` and `reservations/partials/results`
   open an empty state the same way — which makes it a house convention rather than an
   accident of two files. `empty.blade.php`'s own docblock states the rule:
   *"an empty screen is the one place a tool can explain itself, so every empty state names
   what would fill it rather than reporting that it is empty."*

---

## 3. Content: the two contradictions

These are factual defects, not verbosity, and a trim pass would never have found them.

**3.1 A chart described a period it does not plot.** `ReportCharts.php` titles the movement
chart "Arrivals and departures · next 14 days" and builds its columns from
`$today->addDays(0…13)`, yet passed a `comparison` of `'Comparison window: <14 days ago>–<yesterday> …'`.
`report-chart.blade.php` prints that string directly beneath the title, so staff read
*"Complete · Comparison window: Aug 26 – Sep 8, 2026"* under a forward-looking chart and would
reasonably take it for the period covered. It now says there is no comparison period, and why:
this chart looks forward and approval snapshots are not tracked. `x-admin.stat` already carries
a `comparisonMissing` state ("No comparable previous-period value"), so the honest treatment of
a missing comparison is established house behaviour rather than a new idea.

**3.2 A panel promised a week it does not show.** The dashboard paired the title "Next 7 days"
with the description "The next five stays, ordered by arrival", while
`AdminDashboardController` queries `check_in` within `[tomorrow, +7 days]` and then `take(5)`.
Each string carried half the truth, so neither told a staff member what the panel is. It now
reads "The next five stays in the coming week, ordered by arrival."

---

## 4. Content: consolidation, and what was removed

**One rule, one wording.** The arrivals rule existed in three different sentences across the
dashboard row, the reservations list header and the arrivals panel. All three matched
`Reservation::scopeArrivingOn` (pending + approved, not yet checked in, today), so none was
wrong; the dashboard row and the reservations header now share one sentence. Likewise the
capacity rule (`facilities/create`, `edit`, `show`) and the inactive-rates rule (`rates/create`,
`edit`, `show`) each spoke in two voices and now speak in one.

**Removed as clear filler** — seven pieces, each restating a label directly beside it or
stating the obvious:

| Where | Removed |
|---|---|
| Dashboard, "Needs attention" | "Review requests and payments, then welcome arriving guests." — the three rows below say exactly this |
| Dashboard, empty state | "New work appears here the moment it arrives." |
| Dashboard, arrivals empty | "The front desk has nothing scheduled to receive." — the title already says no arrivals |
| Dashboard, arrivals empty | "…Tomorrow's arrivals appear here when their date arrives." — tautology, reworded to name what fills the panel |
| `reservations/create` | "This is what the reservation will contain." |
| `reservations/show` | "Recorded for filing and security purposes." |
| `reports/partials/status-breakdown` | "Opens the reservation list filtered to that status." — describes the link under it |

**One separator, not two.** `·` is the house separator in the back office. Three joins were
still using a spaced hyphen where the rest of the application uses a middot: the dashboard's
"next 7 days" row (`facility · arrives 9:00 AM`), the reservations-list arrival row
(`facility · code` — literally the same join as the dashboard's, in a second file), and the
`<option>` label in `reservations/show` that joins a session to its hours and fee. The first
two are now middots, matching the dashboard's facility + code join and its
`occupied of total · pct%` row. The `<option>` label was left alone and is flagged here rather
than changed quietly: it separates a time *range* ("9:00 AM to 5:00 PM") from a fee, which is
not the peer-facts join the middot is for.

---

## 5. Kept on purpose

- **The long explanations.** The seasonality window, deposit-health and reports descriptions
  state a caveat a staff member must read to use the number correctly (a season cannot be read
  from a short range; only verified payments reduce a balance). They stay. Where one is long
  enough to crowd its panel it belongs behind the existing "Metric definition" `<details>`, not
  in the bin.
- **Every empty state's "what would fill it" line**, because `empty.blade.php` documents that
  as the back office's rule.
- **"Check guests in from the reservation once they reach the front desk."** The approved plan
  listed this for removal; on inspection it names *where* the front-desk action happens, which
  no row in that panel says. Kept, and flagged here rather than dropped silently.
- **"Internal only. Guests never see this."** and the seasonality note about scheduling
  "maintenance, training, and promos" — that is staff training, unrelated to the removed module.

---

## 6. The training claim on the public home page

`pages/home.blade.php` still advertised "TESDA-certified training in housekeeping and barista
skills" while `docs/PRD.md` lists "TESDA / training batch management (removed from product)",
`RULES.md` forbids reintroducing it, `SCHEMA.md` records the tables dropped, and
`PublicBrowsingTest` already asserted `$this->get('/training')->assertNotFound()`. The hero was
the last surface still selling it, so it now reads:

> Stay, celebrate, and unwind: cozy rooms and cottages, function halls for events, spring-fed
> pools, and day or overnight resort packages.

Nothing new is claimed: every item is either published in `config('resort.stats')` or already
described elsewhere on the page. "Learn" was replaced so the triad keeps a referent.

**This sentence is test-pinned**, which is why it could not be edited blind:
`PublicBrowsingTest` asserted `assertSee('TESDA-certified training')`. That assertion is now
`assertDontSee('TESDA')` — inverted rather than deleted, so the claim cannot return unnoticed —
and the opening-phrase assertion moved with the copy.

---

## 7. Verification, and one honest gap

`npm run build` (the stylesheet still compiles after 144 lines came out), `php artisan test`
(full suite — `PublicBrowsingTest` and `SharedVocabularyTest` are the two expected to move),
`npm run test:ui`. Two falsifiable greps act as guards:
`column-chart|admin-vchart|chart-col|admin-dialog-frame` returns nothing anywhere, and `TESDA`
returns only the inverted assertion.

**The gap that let §3 exist.** The guards enforce money, status, date and palette vocabulary,
and they are thorough about it — but **nothing checks copy against the data it describes**.
That is how a chart came to name a window it does not plot and a panel came to promise a week
it does not list, and both passed every test in the suite. A test asserting that a chart's
stated window matches its own data window is the natural follow-up; it is proposed, not built,
because it is new coverage rather than an audit finding.

**What this pass did not do:** rewrite descriptions wholesale. 144 `description` props remain
across 51 admin views (`grep -rho 'description=' resources/views/admin | wc -l`); the ones
removed were removed because they failed the test in §1, not to hit a number.
