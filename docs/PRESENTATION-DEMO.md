# Administrator Presentation Demo

This is a synthetic, resettable 15–20 minute demonstration using the normal reservation, payment, notification, operations, and reporting paths. Never replace the local database with `migrate:fresh` before the presentation.

## Prepare the story

1. Set `DEMO_GUEST_EMAIL` in `.env` to an inbox you control. It must not be a real guest address.
2. Keep `PAYMENT_GATEWAY=manual`, `QUEUE_CONNECTION=database`, and the intended SMTP settings.
3. Run:

   ```powershell
   php artisan config:clear
   php artisan migrate:status
   php artisan demo:reset
   php artisan test tests/Feature/PresentationDemoTest.php
   npm run build
   php artisan queue:work --tries=1
   ```

4. Confirm the command manifest shows every `VM-SHOW-*` record and correct relative date.
5. Sign in as the administrator and verify the private proof opens for `VM-SHOW-PAYMENT`.
6. Send one disposable email through SMTP. Confirm both that the queue consumes the job and that the message reaches the controlled inbox. Queue completion alone does not prove delivery; check Sent, Inbox, and Spam/Junk.

The seed operation suppresses model observers and sends no email. Only actions performed during the live walkthrough enqueue notifications.

## Open these tabs before presenting

- Admin dashboard
- Decision board
- Reservation search for `VM-SHOW-CLEAN`
- Payment search for `SHOW-PAY-PRIMARY`
- Operations report and revenue/decision reports
- Controlled guest inbox and administrator inbox

## 15–20 minute walkthrough

### 1. Dashboard — about 2 minutes

Explain the attention counts, two arrivals, current in-house guest, departure due, pending decisions, and payment reviews. State that all presentation-prefixed names and receipts are synthetic.

### 2. Clean decision — about 2 minutes

Open `VM-SHOW-CLEAN` (**live action**). Show that its +7-day room has no collision, then approve it. Approval opens the deposit step and queues the controlled guest email.

### 3. Conflict decision — about 3 minutes

Open `VM-SHOW-CONFLICT` (**live action**). Its +10-day package includes Sapphire Hall, which `VM-SHOW-ANCHOR` (**supporting evidence—do not modify**) already occupies. Explain the collision and blocked value. Reject it using:

> Sapphire Hall is already committed to confirmed reservation VM-SHOW-ANCHOR for this time window.

### 4. Payment review — about 3 minutes

Search for `SHOW-PAY-PRIMARY` on `VM-SHOW-PAYMENT` (**live action**). Open the private synthetic PDF, point out that no real funds moved, confirm the reference and amount, tick the funds-received acknowledgement, and verify it.

`VM-SHOW-PAYMENT-BACKUP` / `SHOW-PAY-BACKUP` is **backup—do not modify** unless the primary example fails. Its proof is intentionally questionable and may be rejected with “Reference details are incomplete in the submitted proof.”

### 5. Notifications — about 2 minutes

Show the approval/payment messages in the controlled inbox. Explain the distinction between the application queuing a message, the worker handing it to SMTP, and the inbox actually receiving it.

### 6. Front desk — about 3 minutes

- Open `VM-SHOW-ARRIVAL-A` (**live action**), show its completed guest details and verified balance, then check it in and open/download the guest information sheet.
- Open `VM-SHOW-DEPARTURE` (**live action**) and check it out.
- Briefly show `VM-SHOW-CANCEL` and `VM-SHOW-RESCHEDULE` as **supporting evidence—do not modify**. Open only the reschedule preview so later figures remain stable.
- `VM-SHOW-ARRIVAL-B` and `VM-SHOW-IN-HOUSE` are supporting records and should remain unchanged.

### 7. Reports — about 3 minutes

Show current operations, deposit health, the booking/revenue history, payment-method mix, and facility performance. Be explicit that booking activity follows reservation creation date, operational value follows delivered stays/check-out, verified cash follows `payments.payment_date`, and awaiting-verification money is not verified cash.

### 8. Close — about 1 minute

Return to the dashboard and show that the decision, payment, arrival, and departure queues changed through normal administrative actions.

## Recovery and fallback

- After every rehearsal, run `php artisan demo:reset`; it replaces only tagged presentation records and their synthetic proof directory.
- Do not edit `VM-SHOW-ANCHOR`, `VM-SHOW-PAYMENT-BACKUP`, `VM-SHOW-ARRIVAL-B`, or `VM-SHOW-IN-HOUSE` during the main story.
- Capture fallback screenshots or a short recording of the conflict explanation, payment PDF, reports, and received email.
- If SMTP is slow, continue the admin story and return to the inbox later. Do not repeat an action that already changed state.
- If a live action is performed too early or incorrectly, reset the story and reopen the prepared tabs.
