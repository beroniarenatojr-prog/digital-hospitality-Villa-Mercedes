import test from 'node:test';
import assert from 'node:assert/strict';
import { reservationSchedule } from '../../resources/js/reservation-schedule.js';

const policy = {mode: 'room', start: '14:00', end: '12:00', overnight: true, defaultDate: '2026-10-10', maxEarlyHours: 2, hallOpen: '09:00', hallClose: '22:00', standardHours: 4, slots: {day: {start: '09:00', end: '17:00'}, night: {start: '17:00', end: '22:00'}}, extensionHourCents: 500000};
const make = (config, initial = {}) => {
    const state = reservationSchedule({...policy, ...config}, initial);
    state.events = [];
    state.$dispatch = (...args) => state.events.push(args);
    state.init();
    return state;
};
test('rooms retain multi-night dates and review old early and late hours', () => {
    const state = make({}, {check_in: '2026-10-10 08:00', check_out: '2026-10-13 17:00', early_check_in_hours: 3, late_check_out_hours: 2});
    assert.equal(state.selection.check_in, '2026-10-10 14:00');
    assert.equal(state.selection.check_out, '2026-10-13 12:00');
    assert.equal(state.selection.early_check_in_hours, 2);
    assert.equal(state.selection.late_check_out_hours, 0);
    assert.ok(state.scheduleMessage);
});
test('same-day rooms preserve selected hours without early fees', () => {
    const state = make({}, {check_in: '2026-10-10 09:00', check_out: '2026-10-10 17:00', early_check_in_hours: 2});
    assert.equal(state.selection.check_in, '2026-10-10 09:00');
    assert.equal(state.selection.check_out, '2026-10-10 17:00');
    assert.equal(state.selection.early_check_in_hours, 0);
    state.startTime = '07:00'; state.roomEndTime = '11:00'; state.sync();
    assert.equal(state.selection.check_in, '2026-10-10 07:00');
    assert.equal(state.selection.check_out, '2026-10-10 11:00');
});
test('hall extensions stop at closing and preserve a valid resumed selection', () => {
    const state = make({mode: 'hall', overnight: false}, {check_in: '2026-10-10 16:00', check_out: '2026-10-10 22:00'});
    assert.equal(state.extensionHours, 2);
    assert.equal(state.maxExtension, 2);
    state.startTime = '18:00'; state.sync();
    assert.equal(state.extensionHours, 0);
    assert.equal(state.selection.check_out, '2026-10-10 22:00');
});
test('Opal offers exactly its two fixed same-day slots', () => {
    const state = make({mode: 'opal', overnight: false});
    assert.equal(state.selection.check_in, '2026-10-10 09:00');
    assert.equal(state.selection.check_out, '2026-10-10 17:00');
    state.slot = 'night'; state.sync();
    assert.equal(state.selection.check_in, '2026-10-10 17:00');
    assert.equal(state.selection.check_out, '2026-10-10 22:00');
});
test('packages retain their configured overnight schedule', () => {
    const state = make({mode: 'package', start: '15:00', end: '11:00'}, {check_in: '2026-10-10 15:00', check_out: '2026-10-12 11:00'});
    assert.equal(state.selection.check_out, '2026-10-12 11:00');
    assert.equal(state.selection.early_check_in_hours, 0);
});
