# Confirmed Villa Mercedes booking practices

Policy: `2026-10-confirmed-v1`, publication date: 2026-10-12.

## Acceptance criteria

- Clarified on 2026-11-14: an available overnight room may be requested after 2 PM on its check in date (Philippine time). Standard overnight pricing and noon check out remain unchanged. Past check in dates, elapsed same-day-use windows, and elapsed schedules for other services remain invalid. Availability, cleaning release, payment verification, and staff approval still apply. For these room requests, the configured deposit window is capped at check out rather than the already-passed standard check in time; the remaining balance is still due before physical check in.

- Overnight rooms remain 2 PM–noon. Online early requests: 0–2 hours, ₱200 per room hour. Staff review actual arrivals: 19 minutes free, 20 and 60 minutes one hour, 61 minutes two hours. Earlier arrangements go to staff.
- Same-day rooms use selected arrival/departure and one full stored base rate, with no automatic early/late surcharge. Submission and approval check occupancy conflicts again.
- Departures, including automatic checkout and package room components, create a cleaning hold. Staff explicitly mark cleaning complete on the facility record; operational closures and reservations still apply afterward.
- Approved late checkout adds ₱300 per room hour only after a locked, fresh conflict check. Saved base prices are not repriced.
- A single grouped booking and deposit cover all selected services. Guests select 50% or 100% online; uploaded proofs remain pending until verification. Balance is due at physical check-in.
- Cancellation/full-refund eligibility and rescheduling end at actual `checked_in_at`, not scheduled arrival. No-show is not fabricated into a physical check-in by stale-booking cleanup.
- Refund request → owner approval → funds returned are distinct auditable stages. No payment rows are erased. Pending proofs must be reconciled first. Owner return reference and exact verified amount are required.
- Under-one infants are declared separately, free and excluded from room/package capacity. Older children remain in capacity but do not receive an inferred fixed child fee. Administrators record the owner-decided later charge; disputes hold approval and check-in for recorded staff discussion.
- Pets add ₱500 once per grouped booking. Arrival discounts require staff reason. Overpayment stays visible; no automatic refund or credit is invented.
- Replacement/reschedule quotes remain proposals until guest acceptance and fresh staff confirmation. Changed rates require a new proposal and acceptance. The existing booking ID, payments, saved policy and old/new audit snapshots remain traceable.
- Single-item cancellation within a group is not a self-service operation: discuss with staff and obtain an owner-directed refund decision.

## Staff guidance

Use **Booking discussions** on the reservation/group record for cancellations, owner decisions, revised service quotes, guest acceptance, child charges/disputes, and arrival discounts. Review actual early-arrival charges before collecting any revised balance and recording physical check-in. Check out records departure only; use **Room cleaning release → Mark cleaning complete** on the facility record after cleaning.

Administrators manage the workflow in the existing back office. They record the owner's refund approval with a required instruction/approval note, then record actual returned funds separately with an exact amount and reference. They also record owner-decided child charges with the instruction and reason. A separate Owner login is not required. The audit identifies the administrator who recorded each decision; it does not claim that the owner logged in.

Verified totals mean funds received, not funds already returned. Refund state and the return reference show the separate outflow. A receipt is an acknowledgment, not a tax invoice.

## Historical records

The new migration adds nullable workflow and cleaning fields. It does not rewrite existing prices, pricing breakdowns, payments, `policy_version`, or acceptance timestamps. Current rates are starting points only. Rate edits never reprice an existing booking. Existing cutoff enforcement is retained for historical self-service flows; new bookings use the discussion workflow. Confirmed practices for physical arrival/cleaning protect current operations without changing historical quote values.

## Activation

### Local workspace activation on 2026-10-13

The two pending feature migrations (`2026_09_30_000000_add_panelist_workflow_gaps` and `2026_10_12_000000_add_confirmed_booking_practices`) were applied to the local MySQL application database after a verified SQL backup. The backup is kept privately under `storage/app/private/backups/before-confirmed-policy-20261013-124931.sql` (SHA256 `aa2036748ac0c0d5740245016ed1c869979987ddb54df30b296c9ed129419e57`). Reservation count (227), payment count (188), group count (226), and all corresponding totals were checked before and after and remained identical. Following the user clarification, administrators record owner decisions; no Owner account assignment is required. This local activation does not deploy a hosted environment.

The customer reservation card now identifies owner-approved refunds awaiting return and funds already returned, instead of labeling their zero collectible balance as fully paid. Pending proofs that would cover the balance show awaiting verification. The admin payment summary also shows cancelled bookings with no collectible arrival balance. The 50%/100% selection no longer depends on a receiving account's QR image, and missing accounts have explicit instructions to contact staff.

The focused policy/late-checkout run passed 41 tests. Following the instruction to keep owner decisions on the admin side, the affected policy/group/dashboard run passed **59 tests and 458 assertions**; the final refund-display regression passed **1 test and 30 assertions**. All 34 JavaScript tests, the production asset build, and Blade compilation passed. The browser checked the synthetic guest's returned-funds card, a same-day 9 AM–5 PM room quote at the full stored base rate, one ₱500 pet fee, and the cancelled admin payment summary. These checks do not replace the previously documented full-suite failure report below.

The additive `2026_10_13_000000_publish_admin_recorded_owner_decisions` migration was also applied locally to correct the known FAQ wording. It changes no reservation or payment amounts. Administrative refund approval now requires an owner-approval note, while returned funds require a separate exact verified amount and reference. Dashboard request/payment/arrival drawer bodies now use valid Alpine template conditions; a fresh synthetic dashboard navigation had no console errors.

1. Review the local/live stored base and seasonal room rates with the owner; there is no standalone resort-issued rate sheet in this project.
2. Run `php artisan resort:reconcile-room-hour-rates` in dry-run mode on the target environment. All locally configured early/late rates matched ₱200/₱300 when checked during implementation. No live rates were changed.
3. Back up the target database and apply pending migrations under normal release controls. Do not run all catalog seeders over live records. The migration updates known booking-policy, payment, pet, and schedule FAQ answers in place and adds child/same-day/payment answers where their existing categories are configured. It does not reseed the catalog or alter accepted bookings.
4. Administrators record owner decisions in Booking discussions; no separate Owner account assignment is required. Verify queue/scheduler workers and receiving accounts.
5. Build assets and run the regression and browser checks before public activation. Local activation is recorded above; production deployment remains separate. Owner decisions are recorded through administrators, with no separate Owner account required.

## Checks

### Follow-up verification on 2026-10-13

**Latest complete result: 1,036 tests passed, 5,400 assertions, zero failures, errors or skipped tests.** The serial MySQL run exited successfully; evidence is in `tmp/policy-followup-complete-junit.xml` and `tmp/policy-followup-complete.log`. This result supersedes the earlier failed-suite reports retained below as historical evidence. The final run includes the payment-proof return-destination fix. No additional production changes followed this run.

The policy and affected integration run now passes **200 tests and 850 assertions** (`tmp/policy-followup-final-junit.xml`). Historical-contract tests explicitly retain their saved policy version and deterministic dates. Current rescheduling integration tests exercise proposal, guest acceptance and staff confirmation; approval fixtures include a verified deposit. Customer booking and availability assertions follow the existing booking workspace, current schedule controls and account identity rules.

A grouped arrival discount may exceed one service's price while remaining within the booking total. It is distributed across service lines without negative line totals; one booking adjustment and audit event are recorded, verified funds remain unchanged, and excess discounts are rejected atomically. The new regression exercises these accounting checks.

The same-day booking form hides overnight notices, the next-day departure field and the early-arrival selector. Its summary labels the stay **Same-day use**. Browser verification in the synthetic preview confirmed October 14, 9 AM–5 PM at the stored full base rate of ₱2,500, with no early/late surcharge and no captured browser warnings or errors. Screenshot: `tmp/confirmed-policy-same-day-followup.png`.

Submitting a payment proof now returns a pending request to **Review pending**, where its record and under-review proof remain visible. Already confirmed reservations retain the Confirmed destination. The customer journey checks this visible handoff and verifies that the booking stays pending until verification and approval. Both destination cases passed with the full-payment endpoint in a three-test, 66-assertion run (`tmp/policy-followup-payment-handoff-junit.xml`).

All **34 JavaScript tests**, the production asset build and Blade compilation passed again. Markdown mail templates intentionally retain two trailing spaces for line breaks.

The first complete follow-up run had **1,008 passes and 28 failures** out of 1,036 tests. Those remaining fixtures were repaired: static October examples use a deterministic earlier clock; authentication checks preserve selection through the grouped workspace; online guest-sheet identity comes from the account; package contention uses a valid package occupancy window; approvals use a valid room, party and verified deposit; cottage rescheduling exercises proposal/acceptance/confirmation; report assertions use the current labels and identify the actual filter form rather than the export dialog. Production security is retained: catalog seeding does not create a shared customer account. No production validation or approval requirement was relaxed to satisfy these tests. The affected 171-test rerun initially had three failures; those last three passed their separate rerun with 78 assertions. The complete passing run above replaces this interim result.

`ConfirmedBookingPracticesTest` covers same-day windows/conflicts, manual and automatic departure/cleaning release, early boundaries, no-show refund stages and authorization, checked-in rejection, accepted replacement audit/history, group and single-service pet fees, child counts/disputes, discounts/overpayments, and 50%/100% pending/verified payments. It also verifies historical discussion-endpoint protection, deployed FAQ publication, scheduler preservation of group proofs/disputes, and quotation email policy text. `PanelistWorkflowGapsTest` covers existing late-checkout conflicts and receipt itemization. JavaScript tests cover room, hall, Opal and package controls and pet/child quote snapshots.

### Earlier verification on 2026-10-13 (superseded by the complete follow-up pass)

- MySQL focused regression run: **145 passed, 1,082 assertions** before the final quotation-email-only change. The final affected policy/group/copy rerun passed **55 tests, 397 assertions** afterward.
- JavaScript: **34 passed**. Production asset build and Blade compilation passed. No standalone static typecheck is configured for this PHP/JavaScript project.
- Full suite attempted: **932 passed, 101 failed (48 errors and 53 assertion failures)**. This is not a release-ready full-suite pass. Failures include fixed September dates now in the past, fixtures expecting approval before payment or changes without guest acceptance, report markup assertions, and other integration regressions. Do not suppress these failures or treat them as verified behavior. The later focused run verifies the repaired customer-shell and policy workflows, not all unrelated full-suite failures.
- An auxiliary SQLite run had 143 passes and two failures: confirmation-message wording (subsequently corrected and passed on MySQL) and a MySQL-specific date-column assertion comparing a date with SQLite's datetime representation. MySQL is the project's configured test database.
- Browser review used only the dedicated synthetic SQLite preview. Same-day 9 AM–5 PM quoted the stored full base rate; selecting pets added exactly ₱500. Customer receipt and staff discussion records rendered saved policy, verified funds, refund stage and return reference. The later local-activation review corrected the dashboard's null request/deposit/arrival initialization errors and verified a fresh dashboard with a clean console.
- The read-only audit inspected 26 configured rooms; all 52 early/late hourly rows matched ₱200/₱300. Stored base rates remain subject to owner review. No live rate reconciliation, production migration, owner assignment, external email delivery, deployment, or real money movement was performed.
