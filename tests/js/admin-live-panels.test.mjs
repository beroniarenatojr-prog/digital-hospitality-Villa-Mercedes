import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import {
    COOLDOWN_MS,
    DEFAULT_INTERVAL_MS,
    MAX_INTERVAL_MS,
    MIN_INTERVAL_MS,
    isQuietConnection,
    newRowIds,
    nextInterval,
    nextMarkedIds,
    shouldCheck,
    statusText,
} from '../../resources/js/admin-live-panels.js';

test('a poll is only worth making when somebody is there to see it', () => {
    assert.equal(shouldCheck(), true, 'A staff member on a desk connection is the case this exists for.');
    assert.equal(shouldCheck({ hidden: true }), false, 'A background tab is nobody looking.');
    assert.equal(shouldCheck({ onLine: false }), false, 'There is no network to check over.');
    assert.equal(shouldCheck({ connection: { saveData: true } }), false, 'Data saver asked not to spend bytes.');
    assert.equal(shouldCheck({ connection: { effectiveType: '2g' } }), false);
    assert.equal(shouldCheck({ connection: { effectiveType: 'slow-2g' } }), false);
    assert.equal(shouldCheck({ connection: { effectiveType: '4g' } }), true);
    assert.equal(
        shouldCheck({ connection: null }),
        true,
        'Safari has no Network Information API, and that must not switch the panel off.'
    );
    assert.equal(shouldCheck({ onLine: undefined }), true, 'An unreadable connection state is not an offline one.');
});

test('a metered connection is the same answer however the browser words it', () => {
    assert.equal(isQuietConnection({ saveData: true }), true);
    assert.equal(isQuietConnection({ effectiveType: '3g' }), false, '3g is slow but still usable at this size.');
    assert.equal(isQuietConnection(undefined), false);
    assert.equal(isQuietConnection(null), false);
});

test('a failed check backs off without ever giving up', () => {
    const base = DEFAULT_INTERVAL_MS;

    assert.equal(nextInterval({ base, interval: base, failed: false }), base, 'A check that worked returns to the cadence.');

    const first = nextInterval({ base, interval: base, failed: true });
    const second = nextInterval({ base, interval: first, failed: true });

    assert.ok(first > base, 'A second failure should wait longer than the first.');
    assert.ok(second > first);

    let interval = base;
    for (let attempt = 0; attempt < 20; attempt += 1) {
        interval = nextInterval({ base, interval, failed: true });
    }

    assert.equal(interval, MAX_INTERVAL_MS, 'Backing off is not the same as stopping.');
    assert.equal(
        nextInterval({ base, interval, failed: false }),
        base,
        'The first check that works puts the panel back on its cadence rather than leaving it slow.'
    );
});

test('only rows that were not on the screen a moment ago count as new', () => {
    assert.deepEqual(
        newRowIds({ previous: ['7', '9'], current: ['7', '9', '12', '13'] }),
        ['12', '13'],
        'The panel order is the wait order, so the oldest check in is the one marked first.'
    );
    assert.deepEqual(newRowIds({ previous: ['7'], current: ['7'] }), [], 'A quiet poll introduces nothing.');
    assert.deepEqual(
        newRowIds({ previous: [], current: ['3'] }),
        ['3'],
        'Against nothing, everything is new — which is why the first render compares against the rows already on the page rather than against an empty list, and why opening the dashboard marks nothing.'
    );
    assert.deepEqual(
        newRowIds({ previous: ['3', '4'], current: ['3'] }),
        [],
        'A row leaving the panel is not a row arriving in it.'
    );
    assert.deepEqual(newRowIds({}), []);
});

test('a row marked new stays marked while the screen is open', () => {
    const afterFirstArrival = nextMarkedIds({ marked: [], added: ['12'] });
    const afterSecondArrival = nextMarkedIds({ marked: afterFirstArrival, added: ['13'] });
    const afterAQuietPoll = nextMarkedIds({ marked: afterSecondArrival, added: [] });

    assert.deepEqual(afterFirstArrival, ['12']);
    assert.deepEqual(
        afterSecondArrival,
        ['12', '13'],
        'A second check in must not unmark the first, which the count in the status line is still naming.'
    );
    assert.deepEqual(afterAQuietPoll, ['12', '13'], 'A poll that redraws the panel keeps the markers already shown.');
    assert.deepEqual(
        nextMarkedIds({ marked: ['12', '13'], added: ['12'] }),
        ['12', '13'],
        'A row cannot arrive twice.'
    );
});

test('the panel says what it is doing in words a staff member can act on', () => {
    assert.equal(statusText({ newCount: 0 }), 'Live');
    assert.equal(statusText(), 'Live');
    assert.equal(statusText({ newCount: 1, noun: 'request' }), '1 new request since you opened');
    assert.equal(statusText({ newCount: 3, noun: 'request' }), '3 new requests since you opened');
    assert.equal(
        statusText({ failed: true }),
        'Could not check just now — retrying',
        'A poll that failed says so, because a panel nobody can trust is worse than one that is late.'
    );
    assert.equal(
        statusText({ failed: true, newCount: 2 }),
        'Could not check just now — retrying',
        'What is not known yet outranks what was known before it.'
    );
});

test('the cadence the module ships with is a desk-sitting one', () => {
    assert.ok(DEFAULT_INTERVAL_MS >= MIN_INTERVAL_MS, 'The floor is below the cadence, so the floor is not what runs.');
    assert.ok(DEFAULT_INTERVAL_MS <= 60_000, 'A booking that has to wait a minute for a screen nobody has touched is stale by the time it appears.');
    assert.ok(MAX_INTERVAL_MS > DEFAULT_INTERVAL_MS);
    assert.ok(COOLDOWN_MS < DEFAULT_INTERVAL_MS, 'The cooldown must never hold back the scheduled poll.');
});

test('every attribute the module reads is one the views actually write', () => {
    // The wiring lives in markup and the timing lives here, so the two halves
    // only meet if the names agree — and a renamed attribute would otherwise
    // fail silently: a panel that simply stops refreshing looks exactly like a
    // quiet shift.
    const module_ = read(source('../../resources/js/admin-live-panels.js'));
    const dashboard = read(source('../../resources/views/admin/dashboard.blade.php'));
    const fragments = [
        read(source('../../resources/views/admin/dashboard/partials/attention.blade.php')),
        read(source('../../resources/views/admin/dashboard/partials/new-requests.blade.php')),
    ].join('\n');
    // The count beside a panel title is the panel component's, not the page's,
    // so the markup a browser receives is all three files together.
    const rendered = [
        dashboard,
        fragments,
        read(source('../../resources/views/components/admin/panel.blade.php')),
    ].join('\n');

    const kebab = (name) => `data-${name.replace(/[A-Z]/g, (letter) => `-${letter.toLowerCase()}`)}`;

    // What the page wires up…
    const wired = [...module_.matchAll(/root\.dataset\.(\w+)/g)].map((match) => kebab(match[1]));
    assert.ok(wired.length >= 3, 'The module reads nothing from the panel it is handed.');

    for (const attribute of wired) {
        assert.ok(
            dashboard.includes(attribute),
            `The module reads ${attribute} from the panel, and the dashboard never writes it.`
        );
    }

    // …and what the server answers with.
    const answered = [...module_.matchAll(/(?:incoming|body)\.dataset\.(\w+)/g)].map((match) => kebab(match[1]));

    for (const attribute of answered) {
        assert.ok(
            fragments.includes(attribute),
            `The module reads ${attribute} out of the answer, and no fragment writes it.`
        );
    }

    // Selectors are checked the same way, against whichever side has to carry them.
    const selected = [...new Set([...module_.matchAll(/\[data-[a-z-]+\]/g)].map((match) => match[0].slice(1, -1)))];
    assert.ok(selected.length >= 3, 'The module looks for nothing in the markup.');

    for (const attribute of selected) {
        assert.ok(
            rendered.includes(attribute),
            `The module looks for ${attribute}, which nothing in the dashboard, its fragments or the panel it is built from renders.`
        );
    }

    // The panel and the answer are not the same element: wiring one on the
    // fragment would leave the browser looking for it inside what it replaces.
    assert.ok(dashboard.includes('data-live-panel'), 'The dashboard wires no live panel at all.');
    assert.ok(fragments.includes('data-live-panel-body'), 'No fragment carries the contents of a panel.');
});

const source = (relative) => new URL(relative, import.meta.url);

const read = (url) => readFileSync(url, 'utf8');
