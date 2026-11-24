# Inquiry feature retired

The internal customer/staff inquiry workflow was removed on November 13, 2026 at the resort owner's request. Contact and FAQ pages now direct questions to the configured Messenger URL, falling back to the resort Facebook page. The existing Messenger support launcher remains available.

Customer and admin inquiry routes, forms, reply/status actions, services, and navigation entries are removed. Old inquiry URLs return 404. Reservations, booking discussions, payments, notifications, and FAQ management remain available.

Existing inquiry records, migrations, model/status definitions, and account-deletion safeguards are retained for historical data integrity. Historical activity records show their subject without a link to the retired screen. No historical customer data is deleted by this change.

On deployment, rebuild application caches as usual so stale route/view caches do not retain the retired feature.
