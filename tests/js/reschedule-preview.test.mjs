import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

import { pesos, pesosFromCents } from '../../resources/js/money.js';

const template = readFileSync(new URL('../../resources/views/customer/reservations/partials/reschedule-modal.blade.php', import.meta.url), 'utf8');
const expression = [...template.matchAll(/x-text="([^"]+)"/g)]
    .map(match => match[1])
    .find(value => value.includes('previewData.total_amount'));

// The expression is evaluated with the real money module in scope, so this fails
// when the template and the shared formatter disagree — which is the reason the
// formatter left the template in the first place. This endpoint returns cents.
for (const [centavos, display] of [[900000, '₱9,000.00'], [123456, '₱1,234.56'], [0, '₱0.00'], [123456789, '₱1,234,567.89']]) {
    test(`customer preview displays ${centavos} centavos as PHP ${display}`, () => {
        assert.ok(expression, 'The reschedule total must have a display expression.');
        assert.equal(runInNewContext(expression, { previewData: { total_amount: centavos }, pesos, pesosFromCents }), display);
    });
}

test('the preview prints the peso sign once', () => {
    assert.ok(expression, 'The reschedule total must have a display expression.');

    const rendered = runInNewContext(expression, { previewData: { total_amount: 900000 }, pesos, pesosFromCents });
    // The template used to write "₱" next to a formatter that already produced
    // one, so a total could read "₱₱9,000.00".
    assert.equal((rendered.match(/₱/g) ?? []).length, 1);
});
