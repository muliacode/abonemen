// SPIKE (#13): the passkey flows in Firefox. Playwright cannot give Firefox a
// virtual authenticator, but WebDriver's WebAuthn extension can (geckodriver).
//
// Usage: BASE_URL=http://localhost:8100 EMAIL=… PASSWORD=… node tests/e2e-firefox/passkeys.mjs
import assert from 'node:assert/strict';
import { Builder, By, until } from 'selenium-webdriver';
import firefox from 'selenium-webdriver/firefox.js';
import { Protocol, Transport, VirtualAuthenticatorOptions } from 'selenium-webdriver/lib/virtual_authenticator.js';

const baseUrl = process.env.BASE_URL ?? 'http://localhost:8100';
const email = process.env.EMAIL;
const password = process.env.PASSWORD;

const options = new firefox.Options()
    .addArguments('-headless')
    // Firefox's built-in software authenticator, in case the WebDriver one is not wired up.
    .setPreference('security.webauth.webauthn_enable_softtoken', true)
    .setPreference('security.webauth.webauthn_enable_usbtoken', false);
const driver = await new Builder().forBrowser('firefox').setFirefoxOptions(options).build();

async function waitForPath(path) {
    await driver.wait(async () => new URL(await driver.getCurrentUrl()).pathname === path, 10_000, `expected ${path}`);
}

async function byLabel(text) {
    const label = await driver.findElement(By.xpath(`//label[normalize-space()='${text}']`));
    return driver.findElement(By.id(await label.getAttribute('for')));
}

async function button(text) {
    const element = await driver.wait(until.elementLocated(By.xpath(`//button[normalize-space()='${text}']`)), 10_000);
    await driver.wait(until.elementIsEnabled(element), 10_000);
    return element;
}

async function signInWithPassword() {
    await driver.get(`${baseUrl}/login`);
    await (await byLabel('Email address')).sendKeys(email);
    await (await byLabel('Password')).sendKeys(password);
    await (await button('Sign in')).click();
}

async function signOut() {
    await (await button('Sign out')).click();
    await driver.wait(async () => !(await driver.getCurrentUrl()).includes('/dashboard'), 10_000);
}

try {
    const authenticator = new VirtualAuthenticatorOptions();
    authenticator.setProtocol(Protocol.CTAP2);
    authenticator.setTransport(Transport.INTERNAL);
    authenticator.setHasResidentKey(true);
    authenticator.setHasUserVerification(true);
    authenticator.setIsUserVerified(true);
    authenticator.setIsUserConsenting(true);
    await driver.addVirtualAuthenticator(authenticator);

    await signInWithPassword();
    await waitForPath('/dashboard');

    await (await byLabel('Name for the new passkey')).sendKeys('Virtual authenticator');
    await (await button('Add a passkey')).click();
    await driver.wait(
        until.elementLocated(By.xpath("//li[normalize-space()='Virtual authenticator'] | //*[@role='alert']")),
        70_000,
    );
    const alerts = await driver.findElements(By.css('[role=alert]'));
    if (alerts.length > 0) {
        throw new Error(`Registration failed: ${await alerts[0].getText()}`);
    }
    console.log('ok: registered a passkey');
    await signOut();

    await signInWithPassword();
    await waitForPath('/two-factor-challenge');
    await (await button('Use a passkey')).click();
    await waitForPath('/dashboard');
    console.log('ok: passkey as second factor');
    await signOut();

    await driver.get(`${baseUrl}/login`);
    await (await button('Sign in with a passkey')).click();
    await waitForPath('/dashboard');
    const body = await driver.findElement(By.css('main')).getText();
    assert.match(body, new RegExp(`Signed in as ${email.replace('.', '\\.')}`));
    console.log('ok: passwordless sign-in');
} catch (error) {
    console.error('FAILED at', await driver.getCurrentUrl());
    console.error('Page text:\n' + (await driver.findElement(By.css('body')).getText()));
    throw error;
} finally {
    await driver.quit();
}
