/**
 * The browser half of the resort's one money format.
 *
 * `App\Support\Money` is the definition and `x-amount` renders it on the server.
 * Anything the page recalculates live — a booking preview, a reschedule quote —
 * has to print the same string, because the guest watches the figure change as
 * they type and then reads the same figure on their reservation. A live preview
 * that disagrees with the saved total is a support call.
 *
 * PHP and the browser cannot share code, so this mirrors the server: two
 * decimals, thousands separators, `en-PH` grouping. `MoneyFormatTest` asserts the
 * two agree on a spread of values, including a whole peso and a large amount, so
 * the mirror cannot drift silently.
 *
 * The units are part of the name on purpose. Two endpoints in this app return
 * the same field name in different units — the staff reschedule preview returns
 * pesos, the guest one returns cents — and an unnamed `/ 100` somewhere in a
 * template is how that becomes a hundred-fold error.
 */

const FORMAT = {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
};

/** A peso amount already in pesos: `pesos(1250.5)` → "₱1,250.50". */
export const pesos = (amount) => `₱${Number(amount || 0).toLocaleString('en-PH', FORMAT)}`;

/** The same, for an amount the API returns in cents: `pesosFromCents(125050)` → "₱1,250.50". */
export const pesosFromCents = (cents) => pesos(Number(cents || 0) / 100);
