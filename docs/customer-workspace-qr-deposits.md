# Customer workspace, QR deposits, and post-stay feedback

## Operation

Customers use **My Reservations**, **New Reservation**, **My Inquiries**, notifications, and account tools. **Browse the resort** returns to the public marketing pages. The selection page passes dates, guest count, and cottage session to the existing authenticated booking form. Public discovery and the existing login/signup continuation remain available.

New standard online bookings save their server-calculated price and a 50% deposit, rounded up to one centavo. Their unpaid hold ends at the earlier of 24 hours after submission or the visit's start. QR accounts must be activated by an administrator before these holds can be created. Function halls retain staff quotation and approval; historical reservations have no new expiry deadline.

The database continues to store payment instants in the application timezone. New deposit deadlines interpret booking dates as Philippine resort wall-clock times; deadlines and new payment-date controls display Philippine time. Historical reservation dates are not rewritten.

### Staff payment procedure

1. Open **Resort QR accounts**. Upload the actual resort PNG/JPG, account name, account number, and bank where relevant. Check the recipient in the payment app and explicitly attest that the account belongs to the resort before activation. Replace by uploading another account and disabling the old one. Old payment snapshots remain intact. Disabled QR images are not offered for new transfers; eligible existing bookings can still report a transfer made before the account was retired.
2. Open **Payment review**. The oldest waiting proofs come first. A warning identifies proofs waiting more than 24 hours. Inspect the proof (read the transfer reference and date from the uploaded slip when needed), submitted amount, required deposit, receiving-account snapshot, deadline, and duplicate-reference/receipt warnings.
3. Verify only after matching the credit and amount in the resort's own receiving account. Verifying enough deposit confirms a standard booking. Verification can occur after the original deadline when the proof was timely; the space remains held during review.
4. Verify a real partial transfer as its actual submitted amount. A remaining deposit shortfall is shown to the customer. **Request deposit top-up** sends instructions and a deadline capped at the visit start. Never mark an unsupported larger amount as received.
5. Reject with a reason only after confirming the claimed funds were not received. Uncertain or potentially received transfers must stay pending while staff reconcile them. Rejected proofs can be corrected within the updated deadline. A partial verified deposit prevents automatic release even when its top-up deadline passes.
6. Use **Record on-site payment** inside a reservation for money actually collected. Record amount, method, collection time, transfer reference where applicable, and the collecting administrator. The remaining reservation balance must be recorded before **Check in guest** succeeds. Do not add food, damage, or other incidentals to the original reservation price; these remain in the resort's separate incidental-charge register. This change does not introduce a point-of-sale system.
7. For late transfers to expired/cancelled bookings, use the same staff payment record with a reconciliation note. The money stays in history without silently restoring the expired booking or claiming dates now held by someone else. Staff must arrange the actual refund or a new available booking outside this automated confirmation path.
8. Pending proofs prevent cancellation/rejection that would release availability. Received deposits require staff reconciliation before cancellation. Paid bookings do not offer customer date changes; staff must reconcile the existing payment before arranging a changed booking.

Customer proof submissions are capped by the required deposit less verified and pending amounts. Customers cannot submit their remaining balance through the proof endpoint, and new gateway checkout is disabled. Existing gateway return/reconciliation paths and payment history remain available.

## Post-stay feedback

**Check out & complete** makes the reservation eligible for an optional rating. A reservation lock and `review_invited_at` marker prevent repeated checkout/dispatch calls from creating another invitation. One database notification is recorded, with one queued mail invitation when outbound delivery is configured. Log/array mailers do not count as live outbound delivery. The mail notification allows one automatic attempt to avoid resending an ambiguously delivered SMTP message; staff must inspect delivery failures before any manual retry. SMTP cannot provide an absolute exactly-once delivery guarantee.

Customers can submit one 1–5 rating per completed, owned reservation. Comments are optional. The visibility choice is required: **staff only** or **staff may publish with first name and last initial**. Customers may edit until moderation. Reviews are unpublished by default and use the existing GuestReview model and public Reviews page. Published historical reviews remain published.

**Guest reviews** is the moderation queue. Staff can publish eligible feedback or mark it reviewed privately. Removing a published review requires a reason. Low ratings alone are not a removal reason. Staff controls do not edit customer ratings or words. Private feedback cannot be published through a direct request.

## Activation checklist

- Apply the additive `2026_09_13_100000_add_customer_deposit_workflow.php` migration after backing up each target database. It has been applied to the main local Villa Mercedes database with unchanged historical reservation, payment, and published-review counts and totals. Other deployments still need their own backup and migration. Do not reseed production data.
- Keep `PAYMENT_DEPOSIT_WORKFLOW=true` for the new workflow. The false mode exists to preserve historical regression fixtures and is not the intended customer payment policy. Gateway checkout remains disabled in the production configuration.
- Obtain and independently verify the real resort QR codes and receiving accounts. No test account should be activated in the real database.
- Run Laravel's scheduler every minute (`php artisan schedule:run`, using the application's normal host scheduler). The scheduled command is `reservations:expire-unpaid`. For development, `php artisan schedule:work` is an alternative. Verify that the host runs it while the resort is accepting bookings.
- Run the configured queue worker (`php artisan queue:work`) and test the configured outbound mail transport with an authorized recipient. A queued invitation is not proof of email delivery.
- Train staff on pending proofs, overdue review warnings, reconciliation, arrival collection, and review moderation before releasing online reservations.

## Verification

The dedicated `CustomerDepositWorkflowTest` and `CustomerReviewWorkflowTest` explicitly enable the new policy. Existing tests use their historical workflow configuration through `phpunit.xml`; `OnlinePaymentCheckoutTest` explicitly enables its historical gateway fixture. `ReservationConcurrencyTest` includes real competing processes for new holds and deposit submissions.

The full local Laravel suite passed with 699 tests and 2,728 assertions. After the final admin wording change, the focused deposit, review, and decision-board checks passed with 29 tests and 137 assertions. After removing payment-number fallbacks, another 52 customer payment and historical checkout tests passed with 145 assertions. The Vite build, Blade compilation, and three JavaScript UI tests passed. Desktop/mobile customer browser checks and the administrator proof-verification, on-site collection, check-in, checkout, private moderation, and QR-preview walkthrough used an isolated database with conspicuously labelled non-payment fixtures. They did not transfer real funds or send live mail. Verify the host scheduler, queue, mail transport, genuine receiving accounts, and staff procedure before enabling reservations outside this local environment.
