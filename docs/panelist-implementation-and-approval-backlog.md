# Panelist suggestions: implemented gaps and separate approval backlog

## Current status after resort confirmation (2026-10-13)

This document retains the first implementation's historical scope and checks below. The later user-confirmed rules, local activation and current verification are documented in [CONFIRMED-BOOKING-PRACTICES.md](CONFIRMED-BOOKING-PRACTICES.md). Where they differ, that confirmed-practices document is current. The earlier statements about no local migrations and SQLite-only testing no longer describe the current workspace.

The user approved full verified-payment refunds and rescheduling before physical check-in, without a notice deadline; both stop after physical check-in. Administrators record owner refund instructions and the actual returned funds separately. These specific rules are implemented; a general configurable policy-builder module is still outside the confirmed scope. The proposed blanket ban on moving dates was not adopted.

## Guardrails

Panelist sheets are suggestions, not authority to change resort policy. The first implementation does not ban rescheduling, alter cancellations/refunds/penalties, add bed prices, or create an early check-out penalty. ₱300 means **late check-out per room hour**, with staff approval. Diamond Hall's existing **250-person capacity** is unchanged.

No migration, rate reconciliation, or booking mutation was applied to the resort database during implementation. Tests use an isolated SQLite in-memory database because the configured MySQL test connection timed out.

## Implemented

- Availability results distinguish Available, Fully booked, Unavailable and Temporarily closed. Fully booked is calculated from overlapping blocking reservations (including early/late occupancy and package contention), never stored as a staff-controlled facility status. Maintenance maps to Temporarily closed; an unavailable or inactive facility stays Unavailable. Pool entrance retains its existing walk-in-only behavior rather than inventing bookable pool capacity.
- Facility create/edit accepts explicit, escaped, multiline exclusions; public details show them separately from inclusions. No hall amenities, exclusions or capacities were automatically replaced.
- Approved, unfinished **room** bookings offer additional whole-hour late check-out requests within the existing additional-hours technical limit. The guest reviews the exact ₱300/hour fee and current availability before requesting. A quote/request does not hold extra time or charge money. Staff see the amount and current availability, can approve after a locked current conflict check, or decline with a reason without a charge.
- Approval preserves the agreed base/early price and adds only the new late fee to additional charges, reservation total and grouped total. Existing verified funds remain unchanged, so the outstanding balance rises by the fee. Existing deposit requirements/deadlines are not restarted or silently changed. Approved departure appears in the guest record and printable guest sheet. Requests and decisions are audited; duplicate approval cannot charge twice. Booking changes after a request require a fresh request rather than stale approval.
- New/rescheduled reservations save separate pricing components in centavos. Old reservations are not repriced with today's rates: unknown historical components remain labeled as previously agreed additional charges. Staff-quoted adjustments reconcile itemization with the agreed total.
- Customers can open/print an ownership-protected itemized receipt only for verified payments, including direct group payments. It identifies booking/payment status, the paid amount, early/approved late charges, current verified totals, proof amounts under review and remaining balance. It is a payment acknowledgment, **not a tax invoice**. Current booking itemization is clearly distinguished from the amount paid by that particular payment.
- Verified-payment acknowledgments and late-checkout decisions are queued. Existing reservation/status mail and arrival passes remain. Queueing/sending never proves delivery; neither a proof upload nor a fee approval records payment.

## Activation and rate reconciliation

On the intended environment, first back up the database and review/apply the new additive migration using the normal deployment process. Keep a queue worker running. Commands below are documented for an operator; they were **not** executed against resort data here.

```bash
php artisan migrate
# Read-only audit (default):
php artisan resort:reconcile-room-hour-rates
# Explicitly apply only after reviewing the audit:
php artisan resort:reconcile-room-hour-rates --apply
```

The reconciliation command checks all room records for one active Early Check-in rate at **₱200 per hour** and one active Late Check-out rate at **₱300 per hour**. Apply updates/creates the canonical room rates and deactivates duplicate named rates, with activity records. It does not change hall rates or reprice saved reservations. Late-checkout quotes fail safely while the live late rate is missing, duplicated or inconsistent with ₱300/hour.

## Hall catalog reconciliation — publication deferred

The user chose to defer publication until the actual resort-approved sheet list is supplied. Do not invent inclusion/exclusion wording.

For **each** function hall, record its current name, description, standard/max capacity, amenities/inclusions, exclusions and rates. Compare each line with the approved sheet, resolve contradictions with the resort, and only then publish through the existing facility/amenity editor. Include Diamond, Emerald, Sapphire, Opal, and any inventory-only hall without accidentally making the latter a standalone listing. Preserve Diamond at 250 until the resort separately confirms a capacity change. Empty exclusions do not imply that everything is included.

## Separate approval backlog — not implemented

| Proposal | Decision needed before implementation |
| --- | --- |
| Blanket prohibition on moving bookings to another day | Not adopted: the user confirmed rescheduling before physical check-in with guest acceptance and staff confirmation |
| General configurable cancellation/refund policy builder | The specific confirmed full-refund workflow is implemented; an arbitrary policy-builder module would require separate approval |
| Overtime/penalty policy changes | Approved services, triggers, units, amounts and staff exception process |
| Customer booking extensions beyond room late departure | Service-specific occupancy, pricing and approval decisions |
| Wider early check-out feature | Service-specific refund/pricing decision; no penalty inferred from the sheet's inconsistent wording |
| Additional bed types/prices | Approved accommodation catalog, inventory and rate definitions |
| Diamond Hall capacity reduction to 100 | Separate resort capacity check; existing 250 remains |
| Hall inclusions/exclusions publication | Exact approved list and per-hall catalog reconciliation |

## Verification

Focused automated checks cover standard room time, one/two early hours, a free late hour, touching/non-touching next-guest early occupancy, a conflict added after quoting, decline without charges, duplicate/stale approval, non-room/closed booking rejection, unauthorized access, grouped balances/deposit preservation, historical itemization, editable escaped exclusions, availability labels and queued mail content separately from delivery. Guest/staff views are rendered in isolated tests.

Passing focused regression run: **133 tests / 552 assertions** (availability, pricing, payment, grouped workflow, schedules, automatic checkout, notifications and new panelist cases). JavaScript: **32 tests passed**. Vite production build passed. PHP syntax checks passed for the new service/model/controller.

Additional sampled deposit/catalog/admin suites: 53 passed, with one SQLite-only date-storage assertion mismatch (`payment_date` stored with midnight vs the MySQL date string). Broader sampled legacy suites also report failures in old hard-coded past-date booking fixtures and a source-based mobile test that expects date inputs now supplied by a schedule component. These were not weakened to make the new workflow pass. MySQL-specific locking/race tests and actual mail-provider delivery remain environment validation tasks; SQLite checks cannot prove MySQL concurrency behavior.

Browser visual review uses synthetic HTML rendered by the tests, not resort-booking mutations. Staff approval controls rendered cleanly at a 390px viewport; guest controls were present in the accessibility tree. Synthetic pages cannot submit authenticated requests (419 CSRF responses are expected), so backend HTTP tests provide the request/decision coverage. Temporary public review HTML was removed after review. Backend request/decision/receipt behavior is verified by HTTP feature tests; browser static review does not establish live authenticated end-to-end behavior or delivery.
