import assert from 'node:assert/strict';
import test from 'node:test';

import {
    isSoundEnabled,
    setSoundEnabled,
    triggerShiftAlert,
} from '../../resources/js/admin-alert-audio.js';

test('sound alert preference persists to localStorage', () => {
    // Mock localStorage in Node test environment if not globally present
    const store = new Map();
    global.localStorage = {
        getItem: (k) => store.get(k) ?? null,
        setItem: (k, v) => store.set(k, String(v)),
        removeItem: (k) => store.delete(k),
        clear: () => store.clear(),
    };

    assert.equal(isSoundEnabled(), true, 'Defaults to enabled');
    setSoundEnabled(false);
    assert.equal(isSoundEnabled(), false, 'Can be disabled');
    setSoundEnabled(true);
    assert.equal(isSoundEnabled(), true, 'Can be re-enabled');
});

test('triggerShiftAlert operates safely even in headless/Node environments', () => {
    // Should not throw or crash when Web Audio / Notification APIs are unavailable
    assert.doesNotThrow(() => {
        triggerShiftAlert({ title: 'Test Request', body: 'New booking arrived' });
    });
});
