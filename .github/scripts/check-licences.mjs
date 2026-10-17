#!/usr/bin/env node
//
// Fails when a Composer or npm dependency has a licence that cannot be
// combined with the EUPL-1.2 (SEC-12). The allowed licences and reviewed
// exceptions are in .github/licence-policy.json.
//
// Usage: node .github/scripts/check-licences.mjs [composer|npm]...
// Without arguments both ecosystems are checked.

import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

/** Old or shorthand identifiers, mapped to their SPDX 3 form. */
const aliases = {
    'Apache 2.0': 'Apache-2.0',
    Apache2: 'Apache-2.0',
    BSD: 'BSD-3-Clause',
    'LGPL-2.1': 'LGPL-2.1-only',
    'LGPL-2.1+': 'LGPL-2.1-or-later',
    'LGPL-3.0': 'LGPL-3.0-only',
    'LGPL-3.0+': 'LGPL-3.0-or-later',
    'GPL-2.0': 'GPL-2.0-only',
    'GPL-2.0+': 'GPL-2.0-or-later',
    'GPL-3.0': 'GPL-3.0-only',
    'GPL-3.0+': 'GPL-3.0-or-later',
    'AGPL-3.0': 'AGPL-3.0-only',
};

function tokenize(expression) {
    return expression
        .replace(/\(/g, ' ( ')
        .replace(/\)/g, ' ) ')
        .trim()
        .split(/\s+/)
        .filter(Boolean);
}

/**
 * Whether an SPDX licence expression is allowed: every licence joined by AND
 * must be allowed, and at least one side of each OR. A licence WITH an
 * exception counts as the licence itself.
 */
export function isAllowed(expression, allowed) {
    if (typeof expression !== 'string' || expression.trim() === '') {
        return false;
    }

    const allowedSet = new Set(allowed);
    const tokens = tokenize(expression);
    let position = 0;

    const peek = () => tokens[position];
    const next = () => tokens[position++];

    function parseOr() {
        let result = parseAnd();
        while (peek()?.toUpperCase() === 'OR') {
            next();
            const right = parseAnd();
            result = result || right;
        }
        return result;
    }

    function parseAnd() {
        let result = parseAtom();
        while (peek()?.toUpperCase() === 'AND') {
            next();
            const right = parseAtom();
            result = result && right;
        }
        return result;
    }

    function parseAtom() {
        const token = next();
        if (token === undefined) {
            throw new Error(`Unexpected end of "${expression}"`);
        }
        if (token === '(') {
            const result = parseOr();
            if (next() !== ')') {
                throw new Error(`Missing ")" in "${expression}"`);
            }
            return result;
        }
        if (peek()?.toUpperCase() === 'WITH') {
            next();
            next();
        }
        return allowedSet.has(aliases[token] ?? token);
    }

    try {
        const result = parseOr();
        return position === tokens.length && result;
    } catch {
        return false;
    }
}

/** @returns {{name: string, version: string, licence: string}[]} */
function composerPackages() {
    const output = execFileSync(
        'composer',
        ['licenses', '--format=json', '--no-interaction'],
        { encoding: 'utf8' },
    );
    const { dependencies } = JSON.parse(output);

    // Composer lists dual licences as an array: any one of them may be used.
    return Object.entries(dependencies).map(([name, info]) => ({
        name,
        version: info.version,
        licence:
            info.license.length > 1
                ? `(${info.license.join(' OR ')})`
                : (info.license[0] ?? ''),
    }));
}

/** @returns {{name: string, version: string, licence: string}[]} */
function npmPackages() {
    const output = execFileSync('npm', ['query', '*'], {
        encoding: 'utf8',
        maxBuffer: 64 * 1024 * 1024,
    });

    return JSON.parse(output)
        .filter((pkg) => pkg.location !== '')
        .map((pkg) => {
            let licence = pkg.license ?? '';
            if (typeof licence === 'object') {
                licence = licence.type ?? '';
            }
            return { name: pkg.name, version: pkg.version, licence };
        });
}

export function check(ecosystem, packages, policy) {
    const exceptions = policy.exceptions?.[ecosystem] ?? {};
    const seen = new Set();
    const violations = [];

    for (const pkg of packages) {
        const key = `${pkg.name}@${pkg.version}`;
        if (seen.has(key)) {
            continue;
        }
        seen.add(key);

        if (pkg.name in exceptions || isAllowed(pkg.licence, policy.allowed)) {
            continue;
        }

        violations.push(
            `${ecosystem}: ${key} is licensed "${pkg.licence || 'unknown'}"`,
        );
    }

    return { checked: seen.size, violations };
}

function main() {
    const policyPath = fileURLToPath(
        new URL('../licence-policy.json', import.meta.url),
    );
    const policy = JSON.parse(readFileSync(policyPath, 'utf8'));
    const ecosystems = process.argv.slice(2);
    const selected = ecosystems.length > 0 ? ecosystems : ['composer', 'npm'];
    const sources = { composer: composerPackages, npm: npmPackages };

    let failed = false;
    for (const ecosystem of selected) {
        if (!(ecosystem in sources)) {
            console.error(`Unknown ecosystem "${ecosystem}".`);
            process.exit(2);
        }

        const { checked, violations } = check(
            ecosystem,
            sources[ecosystem](),
            policy,
        );

        if (violations.length === 0) {
            console.log(`${ecosystem}: all ${checked} packages have an allowed licence.`);
            continue;
        }

        failed = true;
        for (const violation of violations) {
            console.log(`::error::${violation}, which cannot be combined with the EUPL-1.2.`);
        }
    }

    if (failed) {
        console.log(
            'Replace the package, or add a reviewed exception with a reason to .github/licence-policy.json.',
        );
        process.exit(1);
    }
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
    main();
}
