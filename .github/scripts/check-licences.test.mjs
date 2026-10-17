// Run with: node --test .github/scripts/
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { check, isAllowed } from './check-licences.mjs';

const policy = JSON.parse(
    readFileSync(new URL('../licence-policy.json', import.meta.url), 'utf8'),
);

void test('allows the licences that combine with the EUPL-1.2', () => {
    for (const licence of [
        'MIT',
        'BSD-3-Clause',
        'ISC',
        'Apache-2.0',
        'MPL-2.0',
        'LGPL-3.0-or-later',
        'EUPL-1.2',
        'OFL-1.1',
    ]) {
        assert.equal(isAllowed(licence, policy.allowed), true, licence);
    }
});

void test('rejects GPL-only and AGPL licences', () => {
    for (const licence of [
        'GPL-2.0-only',
        'GPL-2.0-or-later',
        'GPL-3.0-only',
        'GPL-3.0',
        'GPL-2.0+',
        'AGPL-3.0-only',
        'AGPL-3.0-or-later',
        'AGPL-3.0',
    ]) {
        assert.equal(isAllowed(licence, policy.allowed), false, licence);
    }
});

void test('rejects a missing, unknown or malformed licence', () => {
    for (const licence of ['', 'UNLICENSED', 'SEE LICENSE IN LICENSE', '(MIT', 'MIT OR']) {
        assert.equal(isAllowed(licence, policy.allowed), false, licence);
    }
    assert.equal(isAllowed(undefined, policy.allowed), false);
});

void test('reads SPDX expressions', () => {
    assert.equal(isAllowed('(MIT OR GPL-3.0-only)', policy.allowed), true);
    assert.equal(isAllowed('MIT AND ISC', policy.allowed), true);
    assert.equal(isAllowed('MIT AND GPL-3.0-only', policy.allowed), false);
    assert.equal(
        isAllowed('(BSD-3-Clause OR GPL-2.0-only OR GPL-3.0-only)', policy.allowed),
        true,
    );
    assert.equal(isAllowed('(MIT OR Apache-2.0) AND ISC', policy.allowed), true);
    assert.equal(isAllowed('Apache-2.0 WITH LLVM-exception', policy.allowed), true);
    assert.equal(isAllowed('GPL-2.0-only WITH Classpath-exception-2.0', policy.allowed), false);
});

void test('reports a disallowed package and honours exceptions', () => {
    const result = check(
        'npm',
        [
            { name: 'fine', version: '1.0.0', licence: 'MIT' },
            { name: 'copyleft', version: '2.0.0', licence: 'AGPL-3.0-only' },
            { name: 'reviewed', version: '1.0.0', licence: '' },
        ],
        { allowed: policy.allowed, exceptions: { npm: { reviewed: 'checked by hand' } } },
    );

    assert.deepEqual(result.violations, [
        'npm: copyleft@2.0.0 is licensed "AGPL-3.0-only"',
    ]);
});
