// SPIKE (#13): passkeys as second factor and for passwordless sign-in, next to
// Fortify's password and TOTP flows. Chromium only: the virtual authenticator
// comes from the Chrome DevTools Protocol.
import { createHmac } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { expect, test, type Page } from '@playwright/test';

test.describe.configure({ mode: 'serial' });
test.skip(
    ({ browserName }) => browserName !== 'chromium',
    'Virtual authenticators need the Chrome DevTools Protocol.',
);

const password = 'correct horse battery staple';

function createUser(email: string): void {
    execFileSync(
        'php',
        [
            'artisan',
            'tinker',
            '--execute',
            `App\\Models\\User::create(['name' => 'Spike', 'email' => '${email}', 'password' => '${password}']);`,
        ],
        {
            env: {
                ...process.env,
                APP_ENV: 'testing',
                DB_CONNECTION: 'sqlite',
                DB_DATABASE: resolve('database/e2e.sqlite'),
            },
        },
    );
}

async function addVirtualAuthenticator(page: Page): Promise<void> {
    const cdp = await page.context().newCDPSession(page);
    await cdp.send('WebAuthn.enable');
    await cdp.send('WebAuthn.addVirtualAuthenticator', {
        options: {
            protocol: 'ctap2',
            transport: 'internal',
            hasResidentKey: true,
            hasUserVerification: true,
            isUserVerified: true,
            automaticPresenceSimulation: true,
        },
    });
}

async function signInWithPassword(page: Page, email: string): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
}

async function signOut(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'Sign out' }).click();
    await expect(page).not.toHaveURL(/dashboard/);
}

function totp(base32Secret: string, at = Date.now()): string {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const char of base32Secret.replace(/=+$/, '')) {
        bits += alphabet.indexOf(char).toString(2).padStart(5, '0');
    }
    const key = Buffer.from(
        bits.match(/.{8}/g)!.map((byte) => parseInt(byte, 2)),
    );
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(at / 30_000)));
    const hmac = createHmac('sha1', key).update(counter).digest();
    const offset = hmac[hmac.length - 1] & 0xf;
    const code = (hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000;

    return code.toString().padStart(6, '0');
}

test('a passkey works as second factor and for passwordless sign-in', async ({
    page,
}) => {
    const email = `passkey-${Date.now()}@example.com`;
    createUser(email);
    await addVirtualAuthenticator(page);

    // No second factor yet: the password is enough.
    await signInWithPassword(page, email);
    await expect(page).toHaveURL(/\/dashboard$/);

    await page
        .getByLabel('Name for the new passkey')
        .fill('Virtual authenticator');
    await page.getByRole('button', { name: 'Add a passkey' }).click();
    await expect(
        page.getByRole('listitem').filter({ hasText: 'Virtual authenticator' }),
    ).toBeVisible();
    await signOut(page);

    // With a passkey, a password sign-in asks for it as the second factor.
    await signInWithPassword(page, email);
    await expect(page).toHaveURL(/\/two-factor-challenge$/);
    await expect(
        page.getByLabel('Code from your authenticator app'),
    ).toHaveCount(0);
    await page.getByRole('button', { name: 'Use a passkey' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await signOut(page);

    // Passwordless: the passkey alone signs in.
    await page.goto('/login');
    await page.getByRole('button', { name: 'Sign in with a passkey' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.getByText(`Signed in as ${email}`)).toBeVisible();
});

test('TOTP and a passkey work side by side as second factor', async ({
    page,
}) => {
    const email = `totp-${Date.now()}@example.com`;
    createUser(email);
    await addVirtualAuthenticator(page);

    await signInWithPassword(page, email);
    await page
        .getByRole('button', { name: 'Set up an authenticator app' })
        .click();
    const { secretKey } = (await (
        await page.request.get('/user/two-factor-secret-key')
    ).json()) as { secretKey: string };
    await page
        .getByLabel('Code from your authenticator app')
        .fill(totp(secretKey));
    await page.getByRole('button', { name: 'Turn on' }).click();
    await expect(page.getByText('On', { exact: true })).toBeVisible();

    await page
        .getByLabel('Name for the new passkey')
        .fill('Virtual authenticator');
    await page.getByRole('button', { name: 'Add a passkey' }).click();
    await expect(
        page.getByRole('listitem').filter({ hasText: 'Virtual authenticator' }),
    ).toBeVisible();
    await signOut(page);

    // Both options are offered; a TOTP code answers the challenge. Fortify
    // refuses a code used before, so take the next time step's code.
    await signInWithPassword(page, email);
    await expect(page).toHaveURL(/\/two-factor-challenge$/);
    await expect(
        page.getByRole('button', { name: 'Use a passkey' }),
    ).toBeVisible();
    await page
        .getByLabel('Code from your authenticator app')
        .fill(totp(secretKey, Date.now() + 30_000));
    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
});
