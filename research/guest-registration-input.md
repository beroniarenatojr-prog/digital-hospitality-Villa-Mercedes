# Guest registration inputs for hotels and resorts

## Decision brief

A resort should treat **account creation**, **reservation request**, and **arrival registration** as separate collection events. The recommended default is to ask for a verified email, name, contact number, and password when creating an account; ask for stay or visit details when the guest requests a booking; and defer identity and arrival details until a documented operational or legal need exists. This is a product recommendation grounded in the Philippine Data Privacy Act's requirements that data be collected for specified purposes and be adequate but not excessive. It is not a claim that every hotel is legally required to collect a particular guest field.[1]

For a resort serving both overnight and day visitors, the form should branch by service. A hotel-room stay can justify different arrival checks from a cottage or pool visit. Booking intent should survive sign-up and take the guest back to the selected facility, dates, and party size. Requiring people to re-enter data during one process conflicts with WCAG 2.2's redundant-entry criterion unless an exception applies.[5]

## Recommended collection by stage

| Stage | Generally justified inputs | Conditional inputs | Inputs to avoid as a default |
| --- | --- | --- | --- |
| Browse and check availability | Service, date or date range, session, party size | Child/adult counts where admission or capacity differs | Account, ID, gender, full address |
| Create guest account | Full name, verified email, password; mobile number if the actual contact workflow requires it | Preferred contact channel | Government ID number or image, nationality, gender, signature, birth date, company |
| Request a reservation | Lead guest/contact, selected facility, arrival and departure or visit session, party size, service-specific requests; acknowledge applicable terms | Companion names if necessary for capacity or arrival operations; accessibility needs as an optional, narrowly scoped request | Re-entering known account fields without prefill; all check-in identity fields for every service |
| Pre-arrival or check-in | Identity **verification** and any record fields the resort has substantiated as necessary | ID type or last digits, nationality, origin, vehicle or emergency contact only where a documented purpose exists | Routine ID photo/copy, full ID number, gender, signature, or health information without a specific basis and controls |

The table describes an implementation starting point, not a universal legal checklist. Philippine law allows processing on several grounds, including consent, steps toward a contract, and legal obligation, but the applicable basis must be determined for each purpose. Sensitive personal information has a separate, stricter rule.[1] A resort should document the source of any asserted statutory check-in record requirement before making the field mandatory.

## Field-level decisions

**Name.** Use one clearly labelled lead-guest name at account creation or booking. If the guest books for someone else, allow a separate staying-guest name at the reservation stage. Avoid forcing a first/middle/last split unless a downstream record requires it; complex or international names do not always fit that structure.

**Email and mobile.** Email works for account recovery and reservation updates; verify ownership before relying on it. A mobile number can support time-sensitive arrival contact, but its required status should match actual operations. For a purely browsing guest, neither is necessary. Explain which channel receives reservation notices, and keep marketing preference separate from operational contact.[2][7]

**Password and OTP.** Treat email OTP as verification of email control, not as a substitute for a strong account password or comprehensive multi-factor authentication. Current NIST guidance uses a 15-character minimum for a password serving as the sole factor, permits at least 64 characters, and rejects mandatory character-mix rules. OWASP recommends password-manager compatibility, paste support, throttling, and protection against account enumeration. These are security benchmarks, not Philippine statutory password requirements.[8][9]

**Booking details.** Date/session, selected room or service, and guest count should be collected when the guest starts a reservation, not during general account creation. Price and availability must be recalculated server-side when those inputs change. Prepopulate name, email, and phone from the account but let the guest correct the lead-guest information for that particular booking.[5]

**Government ID.** Showing an ID at the front desk may be sufficient for verification. Recording a full number, copying, or photographing the ID is a different and more intrusive processing activity. The National Privacy Commission specifically flags hotel receptionists photographing IDs with personal phones and calls for a prior notice, appropriate lawful basis, secure storage/transmission, restricted access, and disposal when the purpose ends.[3] Do not add ID upload merely for convenience. If a full ID record is demonstrably required, restrict it to trained staff and exclude it from arrival passes, printed summaries, analytics, and routine logs.

**Nationality, origin, gender, signature, company.** These should not be universal account fields. For each, identify the exact decision or record it supports, who uses it, whether a less detailed alternative works, and when it is deleted. Company or agency may be useful for corporate bookings and should otherwise be optional. A signature can be requested when a specific check-in acknowledgement or agreement needs it, with the signed text shown at the point of signing.

**Children and companions.** Count adults and children separately if the service's capacity or admission model needs it. Collect names of companions only if operationally necessary, and consider whether the lead guest can supply them at arrival. Avoid collecting each child's birth date or ID when age band or count is enough. Provide a clear privacy notice for information about companions, and review any additional obligations before designing a flow for minors.

**Accessibility and special requests.** Use an optional free-text request with a prompt such as “Anything staff should know to help with your visit?” Warn against sending medical details unless required to arrange a requested accommodation. Route the request to appropriate staff and avoid putting it in broad reservation exports or marketing tools.

## Privacy and form design

At each collection point, show a short purpose statement and link to a complete, activity-specific privacy notice. The notice should name the operator, categories collected, purposes, lawful basis where processing is not based on consent, recipients, retention period, and how to exercise rights. The NPC says a privacy notice is distinct from obtaining consent; a checkbox saying “I agree to the privacy policy” does not itself establish consent for every purpose.[1][2] An optional marketing checkbox should be separate and unchecked by default, with its own purpose and withdrawal path.[2][7]

Set retention periods by category and purpose rather than promising one universal period. The Data Privacy Act and NPC guidance require retention only as long as necessary for the purpose, legal claims, legitimate business purposes, or another applicable law; the NPC has said the Act does not supply one fixed period for all records.[1][4] Keep account data, reservation records, identity details, and abandoned OTP sessions on distinct schedules. Document who can view, export, edit, and erase each class of data.

Use visible labels, mark required fields, explain formats before input, preserve answers after validation errors, and show text errors next to the relevant field plus an overall summary where useful. Set semantic `autocomplete` values for name, email, telephone, and password. WCAG 2.2 calls for programmatically identifiable common input purposes, textual error identification, and reduced redundant entry in multi-step flows.[5][6]

## Villa Mercedes application: source inspection

The current Laravel sign-up requires full name, Philippine mobile number, verified email, password, and password confirmation (`app/Http/Requests/Auth/RegisterRequest.php`; `resources/views/auth/register.blade.php`). This is a relatively narrow account form. Requiring the mobile number is a reasonable operational choice only if the resort actually uses it for booking or urgent contact; otherwise it can be deferred to the first reservation. The password hint currently emphasizes eight characters and a letter/number mix. That differs from the current NIST single-factor benchmark, so the product should decide its authentication policy deliberately rather than present the meter as a security standard.[8]

The room reservation's guest-information step currently requires name, gender, place of origin, nationality, full valid-ID number, phone, email, and later a signature (`resources/views/customer/booking/create.blade.php`). The page says these are for hotel records and arrival verification. Before retaining the mandatory status of gender, origin, nationality, and full ID number, management should document the purpose and any applicable recordkeeping rule for each. Consider moving identity verification to check-in, and making the online booking request ask only for what is needed to contact the lead guest and process the reservation. This recommendation is an inference from the proportionality principle and NPC's ID-handling guidance, not a determination that the current form is unlawful.[1][3]

The project's staged browse → authenticate → reservation journey is aligned with the recommended separation. Preserve the selected facility and search inputs through login or sign-up, then prefill account contact data in the reservation form. Do not apply the room guest sheet wholesale to cottages, halls, resort packages, or future pool admission; each needs a service-specific field list and purpose review.

## Suggested copy

**Account step:** “Create an account to continue your reservation. We use your name and contact details to manage your requests and send account and reservation updates. Read our Privacy Notice.”

**Reservation step:** “Please confirm the lead guest and visit details. We will use these to review your request and contact you about it. This request is not yet a confirmed booking.”

**Check-in step:** “Please present a valid ID so staff can match you to the reservation. If we need to record any ID details, we will explain which details, why, and how long we keep them before collecting them.”

## Open decisions for the operator

1. Which service types actually require a mobile number at account creation rather than at booking?
2. Which hotel check-in fields are required by a current, applicable rule, and what is the exact source? A general claim of “hotel records” is too broad to settle this.
3. Can staff verify an ID by sight and record a verification status instead of storing the full number or image?
4. What retention and access schedule applies to each class of guest data, and who owns deletion?
5. Which notifications are operational and which are optional marketing?

## Sources

1. Republic of the Philippines, [Republic Act No. 10173, Data Privacy Act of 2012](https://privacy.gov.ph/data-privacy-act/), especially sections 11–13.
2. National Privacy Commission, [Circular No. 2023-04, Guidelines on Consent](https://privacy.gov.ph/wp-content/uploads/2023/11/NPC-Circular-No.-2023-04_Guidelines-on-Consent_07Nov2023.pdf), 2023.
3. National Privacy Commission, [Reminder on taking pictures of identification cards](https://privacy.gov.ph/wp-content/uploads/2023/08/Reminder_Taking-of-Pictures-of-ID.pdf), 2023.
4. National Privacy Commission, [Advisory Opinion No. 2017-024 on retention](https://privacy.gov.ph/wp-content/uploads/2022/01/NPC_AdvisoryOpinionNo._2017-024.pdf), 2017.
5. World Wide Web Consortium, [WCAG 2.2 Understanding 3.3.7: Redundant Entry](https://www.w3.org/WAI/WCAG22/Understanding/redundant-entry.html).
6. World Wide Web Consortium, [Forms Tutorial](https://www.w3.org/WAI/tutorials/forms/) and [Understanding 1.3.5: Identify Input Purpose](https://www.w3.org/WAI/WCAG22/Understanding/identify-input-purpose.html).
7. National Privacy Commission, [The right to be informed](https://privacy.gov.ph/the-right-to-be-informed/) and [Advisory Opinion No. 2023-016 on marketing consent](https://privacy.gov.ph/wp-content/uploads/2023/11/Advisory-Opinion-No.-2023-016.pdf).
8. U.S. National Institute of Standards and Technology, [SP 800-63B-4: Authenticators](https://pages.nist.gov/800-63-4/sp800-63b/authenticators/), 2025.
9. OWASP, [Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html) and [Email Validation and Verification Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Email_Validation_and_Verification_Cheat_Sheet.html).
