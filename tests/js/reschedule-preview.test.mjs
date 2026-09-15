import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const template = readFileSync(new URL('../../resources/views/customer/reservations/partials/reschedule-modal.blade.php', import.meta.url), 'utf8');
const expression = [...template.matchAll(/x-text="([^"]+)"/g)]
    .map(match => match[1])
    .find(value => value.includes('previewData.total_amount'));

for (const [centavos, display] of [[900000, '9,000.00'], [123456, '1,234.56'], [0, '0.00']]) {
    test(`customer preview displays ${centavos} centavos as PHP ${display}`, () => {
        assert.ok(expression, 'The reschedule total must have a display expression.');
        assert.equal(runInNewContext(expression, { previewData: { total_amount: centavos } }), display);
    });
}
