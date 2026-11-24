import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const template = readFileSync(new URL('../../resources/views/customer/booking/unified-workspace.blade.php', import.meta.url), 'utf8');
const script = template.match(/<script>\s*([\s\S]*?)<\/script>/)[1];

function workspace(guestProfile, paymentDestinations = []) {
    let factory;
    runInNewContext(script, {
        document: {
            addEventListener: (_, callback) => callback(),
            getElementById: () => ({ textContent: JSON.stringify({ guestProfile, paymentDestinations, sessions: [], facilities: [] }) }),
        },
        Alpine: { data: (_, callback) => { factory = callback; } },
    });
    return factory();
}

test('booking initializes every guest field from the account prefill contract', () => {
    const profile = {
        guest_name: 'Maria Santos', guest_email: 'maria@example.test',
        guest_phone: '09171234567', guest_place_of_origin: 'Ilagan, Isabela',
        guest_nationality: 'Filipino', guest_company: 'Example Company',
        guest_valid_id: 'umid', guest_gender: 'female',
    };
    assert.deepEqual(JSON.parse(JSON.stringify(workspace(profile).guest)), profile);
});

test('missing account details stay empty for the guest to supply', () => {
    const { guest } = workspace({ guest_name: 'Maria Santos', guest_email: 'maria@example.test' });
    assert.equal(guest.guest_name, 'Maria Santos');
    assert.equal(guest.guest_email, 'maria@example.test');
    for (const key of ['guest_phone', 'guest_place_of_origin', 'guest_nationality', 'guest_company', 'guest_valid_id', 'guest_gender']) {
        assert.equal(guest[key], '');
    }
});

test('checkout requires an explicit selection when multiple receiving accounts are available', () => {
    const first = { id: 1 };
    const second = { id: 2 };
    assert.equal(workspace({}, [first]).currentDestination.id, 1);
    assert.equal(workspace({}, [first, second]).currentDestination, undefined);
});

test('a changed price keeps the guest in review until they confirm the updated amount', async () => {
    const state = workspace({});
    state.step = 3;
    state.lines = [{ facility_id: 1, check_in: '2026-12-01 14:00', check_out: '2026-12-02 12:00', guest_count: 1 }];
    state.evaluation = { all_available: true, lines: [{ quoted_amount: 1000 }] };
    state.checkAvailability = async () => {
        state.evaluation.lines[0].quoted_amount = 1200;
        state.versionToken = 'fresh-token';
    };
    state.showBookingError = message => { state.errorMessage = message; };
    state.goToStep = step => { state.step = step; };

    await state.continueToPayment();
    assert.equal(state.step, 3);
    assert.match(state.errorMessage, /Review the updated amounts/);

    await state.continueToPayment();
    assert.equal(state.step, 4);
});

test('a room becomes same-day when check-in is picked after check-out', () => {
    const state = workspace({});
    state.onInputChanged = () => {};
    const line = { check_in: '', check_out: '', early_check_in_hours: 1 };
    assert.equal(state.isSameDayRoom(line), false);

    state.setRoomDate(line, 'check_out', '2026-12-02');
    state.setRoomDate(line, 'check_in', '2026-12-02');
    assert.equal(line.check_in, '2026-12-02 09:00');
    assert.equal(line.check_out, '2026-12-02 17:00');
    assert.equal(line.early_check_in_hours, 0);

    state.setRoomDate(line, 'check_out', '2026-12-03');
    assert.equal(line.check_in, '2026-12-02 14:00');
    assert.equal(line.check_out, '2026-12-03 12:00');
});

test('clearing a hall or package date clears its submitted schedule', () => {
    const state = workspace({});
    for (const clearDate of [
        line => state.setHallSlot(line, '', '09:00|13:00'),
        line => state.setPackageDate(line, ''),
    ]) {
        const line = { facility_id: 1, check_in: '2026-12-02 09:00', check_out: '2026-12-02 13:00' };
        clearDate(line);
        assert.equal(line.check_in, '');
        assert.equal(line.check_out, '');
    }
});
