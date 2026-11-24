import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import {
    describeInputChanges,
    inputsFrom,
    priceChange,
    priceTerms,
    stayNights,
} from '../../resources/js/price-change.js';

const form = (overrides = {}) => ({
    check_in: '2026-10-12 14:00',
    check_out: '2026-10-14 12:00',
    guestCount: 4,
    early_check_in_hours: 0,
    late_check_out_hours: 0,
    pax_tier: null,
    ...overrides,
});

test('pet fees are itemized once and child declarations invalidate quote snapshots', () => {
    const before = inputsFrom(form());
    const after = inputsFrom(form({ children_count: 1, pets_included: true }));
    assert.notDeepEqual(before, after);
    assert.ok(describeInputChanges(before, after).some((reason) => reason.includes('owner decides')));
    const terms = priceTerms({ base_amount: 250000, pet_fee: 50000, total_amount: 300000 }, { inputs: after });
    assert.equal(terms.find((term) => term.key === 'pet_fee').amount, 50000);
});

const quote = (overrides = {}) => ({
    base_amount: 500000,
    early_check_in_charge: 0,
    late_check_out_charge: 0,
    extra_person_charge: 200000,
    total_amount: 700000,
    ...overrides,
});

const snapshot = (total_amount, inputs, components = {}) => ({
    ...quote(components),
    total_amount,
    inputs,
});

test('a stay is the calendar days between check in and check out, at least one', () => {
    assert.equal(stayNights('2026-10-12 14:00', '2026-10-14 12:00'), 2);
    assert.equal(stayNights('2026-10-12 09:00', '2026-10-12 17:00'), 1, 'A day use is one night even when it does not cross midnight.');
    assert.equal(stayNights('2026-10-30 14:00', '2026-11-02 12:00'), 3, 'The count must cross month ends.');
    assert.equal(stayNights('', ''), 0, 'Nothing chosen is not one night.');
});

test('every line of a total carries the arithmetic behind it', () => {
    const terms = priceTerms(
        quote({ early_check_in_charge: 60000, late_check_out_charge: 40000, total_amount: 800000 }),
        {
            inputs: inputsFrom(form({ guestCount: 4, early_check_in_hours: 3, late_check_out_hours: 2 })),
            includedGuests: 2,
            words: { one: 'guest', many: 'guests' },
        }
    );

    assert.deepEqual(terms.map((row) => row.detail), [
        '₱2,500.00 × 2 nights',
        '2 extra guests × ₱500.00 × 2 nights',
        '3 hours × ₱200.00',
        '2 hours × ₱200.00',
    ]);
});

test('a line whose amount does not divide evenly is shown without invented arithmetic', () => {
    const terms = priceTerms(quote({ base_amount: 500001, extra_person_charge: 100001 }), {
        inputs: inputsFrom(form({ guestCount: 3 })),
        includedGuests: 2,
    });

    assert.equal(terms[0].detail, null, 'An amount that is not nights × a rate must not be dressed as one.');
    assert.equal(terms[1].detail, null);
    assert.equal(terms[1].amount, 100001);
});

test('charges the guest is not paying are not lines on the bill', () => {
    const terms = priceTerms(quote({ early_check_in_charge: 0, late_check_out_charge: 0, extra_person_charge: 0 }), {
        inputs: inputsFrom(form({ guestCount: 2 })),
        includedGuests: 2,
    });

    assert.deepEqual(terms.map((row) => row.key), ['base_amount']);
});

test('the reason for a change is the guest\'s own edit, in their words', () => {
    const before = inputsFrom(form({ guestCount: 2 }));
    const after = inputsFrom(form({ guestCount: 4, early_check_in_hours: 3 }));

    assert.deepEqual(
        describeInputChanges(before, after),
        ['party of 4 instead of 2', '3 hours before check in']
    );
});

test('hours put back to zero say so rather than going quiet', () => {
    const reasons = describeInputChanges(
        inputsFrom(form({ early_check_in_hours: 3 })),
        inputsFrom(form({ early_check_in_hours: 0 }))
    );

    assert.deepEqual(reasons, ['no extra hours before check in']);
});

test('date changes name the nights that moved', () => {
    const reasons = describeInputChanges(
        inputsFrom(form()),
        inputsFrom(form({ check_in: '2026-10-12 14:00', check_out: '2026-10-13 12:00' }))
    );

    assert.deepEqual(reasons, ['leaving Tue, Oct 13 instead of Wed, Oct 14', '1 night instead of 2']);
});

test('a total that did not move has nothing to explain', () => {
    const inputs = inputsFrom(form({ guestCount: 2 }));

    assert.equal(priceChange(snapshot(500000, inputs, { extra_person_charge: 0 }), snapshot(500000, inputs)), null);
    assert.equal(priceChange(null, snapshot(500000, inputs)), null, 'The first total is not a change.');
});

test('raising the party reports the new total and what moved it', () => {
    const change = priceChange(
        snapshot(500000, inputsFrom(form({ guestCount: 2 })), { extra_person_charge: 0 }),
        snapshot(700000, inputsFrom(form({ guestCount: 4 })))
    );

    assert.equal(change.delta, 200000);
    assert.equal(change.from, 500000);
    assert.equal(change.to, 700000);
    assert.deepEqual(change.reasons, ['party of 4 instead of 2']);
});

test('the resort moving a rate the guest never touched is named, not hidden', () => {
    // The same selection, priced by our estimate and then by the resort: the dates
    // did not change, the party did not change — only the rate did.
    const inputs = inputsFrom(form({ guestCount: 2 }));
    const change = priceChange(
        snapshot(500000, inputs, { extra_person_charge: 0 }),
        snapshot(620000, inputs, { extra_person_charge: 0, base_amount: 620000 })
    );

    assert.deepEqual(change.reasons, ["the resort's rate for those dates"]);
    assert.equal(change.delta, 120000);
});

test('an extra charge that appeared with the party unchanged is named too', () => {
    const inputs = inputsFrom(form({ guestCount: 4 }));
    const change = priceChange(
        snapshot(500000, inputs, { extra_person_charge: 0 }),
        snapshot(700000, inputs)
    );

    assert.deepEqual(change.reasons, ['the charge for extra guests']);
});

test('every field the price summary prints is one the terms actually carry', () => {
    // The partial reads `term.label`, `term.detail` and `term.amount` from here. A
    // renamed key would render blank lines beside a total instead of failing, so
    // the contract between the two is asserted rather than assumed.
    const partial = readFileSync(
        new URL('../../resources/views/customer/booking/partials/price-summary.blade.php', import.meta.url),
        'utf8'
    );
    const printed = new Set([...partial.matchAll(/term\.(\w+)/g)].map((match) => match[1]));
    const [row] = priceTerms(quote(), { inputs: inputsFrom(form()) });

    assert.ok(printed.size > 0, 'The price summary must render the terms it is given.');

    for (const field of printed) {
        assert.ok(field in row, `The price summary prints term.${field}, which the terms do not carry.`);
    }
});

test('the edit is named once, even when the rate behind it moved as well', () => {
    const change = priceChange(
        snapshot(500000, inputsFrom(form({ guestCount: 2 })), { extra_person_charge: 0 }),
        snapshot(760000, inputsFrom(form({ guestCount: 4 })), { extra_person_charge: 260000 })
    );

    assert.deepEqual(change.reasons, ['party of 4 instead of 2']);
});
