/**
 * How a booking total got where it is.
 *
 * The wizard re-prices as the guest edits — party size, extra hours, the dates —
 * and a total that moves without saying why is a total the guest cannot check.
 * There is a second, quieter move underneath that: before the server has been
 * asked, the number on the page is *our* estimate from the published rates, and
 * the resort's answer can differ from it (a seasonal rate, a pax tier, a charge
 * this space does not have). So one edit can move the figure twice — once to our
 * arithmetic and once to theirs — and the guest is left wondering which number
 * they should believe.
 *
 * So every change is described. `priceChange` says what the total was, what it is
 * now, and what moved it, in the guest's own terms ("party of 4 instead of 2",
 * "2 nights instead of 1"), and — the part nothing else would catch — names the
 * resort's rate when a component moved that the guest did not touch.
 * `priceTerms` breaks the total into the arithmetic behind each line, and only when
 * the arithmetic is exact: showing "× 2 nights" beside an amount that is not
 * divisible by two would be inventing an explanation, which is worse than giving
 * none. It reads like this:
 * vocabulary-guard: a documentation example — every figure below is printed by money.js
 * "₱2,500.00 × 2 nights" · "3 hours × ₱200.00"
 *
 * Pure and unit-tested (`tests/js/price-change.test.mjs`). The wizard reaches it
 * through `window.bookingPrice`, because an inline Alpine expression cannot import
 * a module — the same reason `money.js` is exposed beside it.
 */

import { pesos, pesosFromCents } from './money.js';

/**
 * What each part of a total follows. `input` is the guest-editable quantity the
 * component tracks, so a component that moved while its input stood still is the
 * resort's number talking, and `reason` is how that is said out loud.
 */
const COMPONENTS = [
    { key: 'base_amount', input: 'nights', reason: "the resort's rate for those dates" },
    { key: 'extra_person_charge', input: 'guestCount', reason: 'the charge for extra guests' },
    { key: 'early_check_in_charge', input: 'early_check_in_hours', reason: 'the early check in charge' },
    { key: 'hall_extension_charge', input: 'hall_extension_hours', reason: 'the hall extension charge' },
    { key: 'late_check_out_charge', input: 'late_check_out_hours', reason: 'the late check out charge' },
];

const cents = (value) => Math.round(Number(value || 0));

const moment = (value) => {
    if (!value) {
        return null;
    }

    const parsed = new Date(String(value).replace(/-/g, '/'));

    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const plural = (count, one, many) => `${count} ${count === 1 ? one : many}`;

/**
 * How many nights a stay is: the calendar days between arrival and departure, at
 * least one. Same shape as the wizard's own count, because both are asked the same
 * question and only one of them should own the answer.
 */
export const stayNights = (checkIn, checkOut) => {
    const start = moment(checkIn);
    const end = moment(checkOut);

    if (!start || !end) {
        return 0;
    }

    const from = Date.UTC(start.getFullYear(), start.getMonth(), start.getDate());
    const to = Date.UTC(end.getFullYear(), end.getMonth(), end.getDate());

    return Math.max(1, Math.round((to - from) / 86400000));
};

const dayLabel = (value) => {
    const parsed = moment(value);

    return parsed
        ? parsed.toLocaleString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })
        : 'those dates';
};

/**
 * The quantities a total is built from, as one comparable snapshot.
 */
export const inputsFrom = (form = {}) => ({
    check_in: String(form.check_in || ''),
    check_out: String(form.check_out || ''),
    nights: stayNights(form.check_in, form.check_out),
    guestCount: Number(form.guestCount || 0),
    children_count: Number(form.children_count || 0),
    pets_included: Boolean(form.pets_included),
    early_check_in_hours: Number(form.early_check_in_hours || 0),
    late_check_out_hours: Number(form.late_check_out_hours || 0),
    hall_extension_hours: Number(form.hall_extension_hours || 0),
    pax_tier: form.pax_tier ? Number(form.pax_tier) : null,
});

const hoursReason = (hours, phase) => (hours === 0
    ? `no extra hours ${phase}`
    : `${plural(hours, 'hour', 'hours')} ${phase}`);

/**
 * What the guest changed, in their own words.
 *
 * Only what actually moved is named: an edit that leaves the price alone has
 * nothing to explain, and a note that recites the whole form every time is a note
 * nobody reads.
 *
 * @returns {string[]}
 */
export const describeInputChanges = (before, after = {}) => {
    if (!before) {
        return [];
    }

    const reasons = [];

    if (before.check_in !== after.check_in) {
        reasons.push(`arriving ${dayLabel(after.check_in)} instead of ${dayLabel(before.check_in)}`);
    } else if (before.check_out !== after.check_out) {
        reasons.push(`leaving ${dayLabel(after.check_out)} instead of ${dayLabel(before.check_out)}`);
    }

    if (before.nights !== after.nights && before.nights > 0 && after.nights > 0) {
        reasons.push(`${plural(after.nights, 'night', 'nights')} instead of ${before.nights}`);
    }

    if (before.guestCount !== after.guestCount) {
        reasons.push(`party of ${after.guestCount} instead of ${before.guestCount}`);
    }

    if (Boolean(before.pets_included) !== Boolean(after.pets_included)) {
        reasons.push(after.pets_included ? 'pets included (one fee per booking)' : 'pets removed');
    }
    if (Number(before.children_count || 0) !== Number(after.children_count || 0)) {
        reasons.push('older-child count updated; owner decides the charge');
    }

    if (before.early_check_in_hours !== after.early_check_in_hours) {
        reasons.push(hoursReason(after.early_check_in_hours, 'before check in'));
    }

    if (before.late_check_out_hours !== after.late_check_out_hours) {
        reasons.push(hoursReason(after.late_check_out_hours, 'after check out'));
    }

    if (before.hall_extension_hours !== after.hall_extension_hours) {
        reasons.push(`${plural(after.hall_extension_hours || 0, 'hall extension hour', 'hall extension hours')}`);
    }

    if (before.pax_tier !== after.pax_tier && after.pax_tier) {
        reasons.push(`the rate for ${plural(after.pax_tier, 'guest', 'guests')}`);
    }

    return [...new Set(reasons)];
};

/**
 * The movement between two totals, and why it happened.
 *
 * @param  {?object} before  `{ total_amount, ...components, inputs }`, or null for the first total
 * @param  {object}  after   the same shape, now
 * @return {?{from: number, to: number, delta: number, reasons: string[]}}
 */
export const priceChange = (before, after) => {
    if (!before || !after) {
        return null;
    }

    const from = cents(before.total_amount);
    const to = cents(after.total_amount);

    if (from === to) {
        return null;
    }

    const reasons = describeInputChanges(before.inputs, after.inputs);

    for (const { key, input, reason } of COMPONENTS) {
        if (cents(after[key]) === cents(before[key])) {
            continue;
        }

        // The guest moved this input, so the change is already named above. If the
        // input stood still, the resort's own rate is what moved — and that is
        // exactly the change a guest would otherwise never see.
        if (before.inputs?.[input] !== after.inputs?.[input]) {
            continue;
        }

        reasons.push(reason);
    }

    return { from, to, delta: to - from, reasons: [...new Set(reasons)] };
};

/**
 * The lines behind a total, in the order they are added up.
 *
 * Each line carries the arithmetic it is made of when that arithmetic is exact, so
 * the total can be checked rather than believed.
 * vocabulary-guard: a documentation example — the figures a guest reads are printed by money.js
 * "₱2,500.00 × 2 nights" · "2 extra guests × ₱500.00 × 2 nights"
 * When a line does not divide evenly (a rate this module cannot see the shape of),
 * `detail` is null and the line shows its amount alone.
 *
 * @param  {object} price  the priced components, in cents
 * @param  {object} options  `inputs`, `labels`, `words`, `unit`, `hideZero`
 * @return {Array<{key: string, label: string, detail: ?string, amount: number}>}
 */
export const priceTerms = (price = {}, options = {}) => {
    const inputs = options.inputs || {};
    const labels = options.labels || {};
    const words = { one: 'guest', many: 'guests', ...(options.words || {}) };
    const nights = Number(inputs.nights || 0);
    const perNight = (options.unit || 'per night') === 'per night';

    const divide = (amount, units) => {
        if (!units || units < 1 || amount <= 0 || amount % units !== 0) {
            return null;
        }

        return amounts(amount / units);
    };

    const amounts = (value) => pesosFromCents(Math.round(value));
    const term = (key, label, amount, detail = null) => ({ key, label, detail, amount: cents(amount) });

    const extraGuests = Math.max(0, Number(inputs.guestCount || 0) - Number(inputs.children_count || 0) - Number(options.includedGuests || 0));
    const earlyHours = Number(inputs.early_check_in_hours || 0);
    const lateHours = Number(inputs.late_check_out_hours || 0);

    const baseDetail = perNight && nights > 1 && price.base_amount % nights === 0
        ? `${amounts(price.base_amount / nights)} × ${plural(nights, 'night', 'nights')}`
        : null;

    const extraUnits = extraGuests * (perNight ? Math.max(1, nights) : 1);
    const extraUnit = divide(cents(price.extra_person_charge), extraUnits);
    const extraDetail = extraGuests > 0 && extraUnit
        ? `${plural(extraGuests, `extra ${words.one}`, `extra ${words.many}`)} × ${extraUnit}${perNight && nights > 1 ? ` × ${plural(nights, 'night', 'nights')}` : ''}`
        : null;

    const earlyUnit = divide(cents(price.early_check_in_charge), earlyHours);
    const lateUnit = divide(cents(price.late_check_out_charge), lateHours);

    const extensionHours = Number(inputs.hall_extension_hours || 0);
    const extensionUnit = divide(cents(price.hall_extension_charge), extensionHours);
    const terms = [
        term('base_amount', labels.base || 'Base amount', price.base_amount, baseDetail),
        ...(price.pet_fee ? [term('pet_fee', 'Pets (one fee per booking)', price.pet_fee)] : []),
        ...(price.hall_extension_charge ? [term('hall_extension_charge', 'Hall extension', price.hall_extension_charge, extensionUnit ? `${plural(extensionHours, 'hour', 'hours')} × ${extensionUnit}` : null)] : []),
        term(
            'extra_person_charge',
            `${labels.extraPerson || 'Extra guests'} (${extraGuests})`,
            price.extra_person_charge,
            extraDetail
        ),
        term(
            'early_check_in_charge',
            `${labels.early || 'Early check in'} (${plural(earlyHours, 'hr', 'hrs')})`,
            price.early_check_in_charge,
            earlyUnit ? `${plural(earlyHours, 'hour', 'hours')} × ${earlyUnit}` : null
        ),
        term(
            'late_check_out_charge',
            `${labels.late || 'Late check out'} (${plural(lateHours, 'hr', 'hrs')})`,
            price.late_check_out_charge,
            lateUnit ? `${plural(lateHours, 'hour', 'hours')} × ${lateUnit}` : null
        ),
    ];

    if (options.hideZero === false) {
        return terms;
    }

    // A charge the guest is not paying is not a line on their bill — the base is
    // the one that always belongs there, because it is what the stay *is*.
    return terms.filter((row) => row.amount !== 0 || row.key === 'base_amount');
};
