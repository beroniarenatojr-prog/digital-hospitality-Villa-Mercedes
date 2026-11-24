# Front desk reports

Reviewed 17 November 2026. This is a workflow recommendation based on the current code and hospitality PMS references, not a staff-interview finding or a legal reporting requirement.

## Purpose and scope

Keep a small reporting workspace for printable guest contact records, actual check-in history and shift handover lists. Rename Overview to Front desk reports so staff can distinguish it from the management dashboard and financial overview.

- Reservation Calendar owns availability and booking schedules. Remove Available now from reporting and its printable bundle; old screen and export links redirect to the calendar.
- Checked-in owns current occupancy, remaining balances and checkout. Remove the duplicate occupancy tab; old screen links redirect to Checked-in. Its dedicated print export remains available.
- Guest registry retains reservation reference, lead guest, space, service type, recorded guest count, contact number/email, actual check-in and expected departure. Dates filter actual check-in timestamps, so label them Checked in from / Checked in until. No dates means current checked-in records; a range is history, not current presence.
- Today's action lists remain as a printable shift handover: arrivals, departures, walk-ins, missed arrivals, overdue departures and maintenance. The dashboard remains the daily work entry point. Historical filters are hidden and cleared for this live report.
- Print the selected report with its screen filters directly; date shortcuts support monthly/yearly registry selection without a separate export dialog.

Counts are per service reservation. A grouped booking can contain multiple rows, and the app records a lead guest rather than an individually verified list of everyone on site. Do not describe this registry as a complete emergency roll call. A verified muster list would need attendance and companion-level data beyond this cleanup.

## References and interpretation

- [Cloudbeds In-House Report](https://myfrontdesk.cloudbeds.com/hc/en-us/articles/25909820970651-In-House-Report): in-house reservations support staff follow-up on guest stays and balances. This supports keeping Checked-in as an operational workspace.
- [Cloudbeds night audit reporting](https://myfrontdesk.cloudbeds.com/hc/en-us/articles/219104088-Use-Cloudbeds-Reports-for-Night-Audit): in-house reporting includes room types, guest counts and contact information. This supports a printable guest contact registry.
- [Oracle OPERA arrivals reports](https://docs.oracle.com/en/industries/hospitality/opera-cloud/25.2/ocsuh/c_reports_arrival.htm): arrival and checked-in lists are established front-office reports. This supports retaining a shift handover report, without reproducing a second availability screen.

These products establish useful report purposes; they do not establish that Villa Mercedes staff need every available report. Consolidation decisions above come from comparison with this project's existing screens. No booking policies or stored guest records changed.
