import { test } from 'node:test';
import assert from 'node:assert/strict';
import { formatSriLankaTime } from '../resources/js/lib/sri-lanka-time.ts';

test('formats UTC query and ISO timestamps including day rollover', () => {
    assert.equal(formatSriLankaTime('2026-11-26 07:04:43'), '2026-11-26 12:34:43');
    assert.equal(formatSriLankaTime('2026-11-26T23:45:00.000Z'), '2026-11-27 05:15:00');
    assert.equal(formatSriLankaTime('2026-11-26T12:34:43+05:30'), '2026-11-26 12:34:43');
});
test('preserves unsupported and invalid inputs', () => {
    assert.equal(formatSriLankaTime('not a timestamp'), 'not a timestamp');
    assert.equal(formatSriLankaTime(null), '—');
    assert.equal(formatSriLankaTime('2026-11-26'), '2026-11-26');
});
