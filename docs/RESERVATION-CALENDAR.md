# Reservation Calendar

The former Property calendar layout is restored at the existing admin.decision-board URL. The module remains named Reservation Calendar under Front Desk, and the decision queue and financial cards remain removed.

The page uses the previous From/To date window (14 days by default), facility filter, color legend, proportional timeline bars and direct reservation links. The new week/day selector, sticky scrolling grid and detail dialog have been removed at the user's request.

Current facility restrictions remain labeled, and empty rows say "No reservations shown" rather than promising availability. Calendar requests do not compute summary() or pendingQueue(). Existing occupancy and linked-booking logic is unchanged.

Reservation details also omit the booking-value analysis and extra-hour advice panels. The controller no longer computes either advisory payload. Actual booking and late-checkout availability, pricing, and approval services remain unchanged.
