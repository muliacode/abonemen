# Spike 2: laravel/passkeys alongside Fortify and Inertia v3

Issue: [#13](https://github.com/muliacode/abonemen/issues/13). Requirement: ACC-02. Decision: ADR 11.

**Outcome: ADR 11 holds, and Fortify makes it simpler than planned.** Fortify 1.41 depends on `laravel/passkeys` and wires it in itself. Passwordless sign-in works out of the box. A passkey as the *second* factor needs about 80 lines of our own code, because Fortify's two-factor challenge only knows TOTP. Both flows work in Chromium and Firefox, next to password and TOTP sign-in. The proof of concept is on the [`spike/13-passkeys`](https://github.com/muliacode/abonemen/tree/spike/13-passkeys) branch and is not merged.

## What was built

| Part | How |
| --- | --- |
| Passwordless sign-in | Fortify's `Features::passkeys()`: routes for login options, login, confirmation and passkey management, all from `laravel/passkeys`. The user model implements Fortify's `PasskeyUser` contract with its `PasskeyAuthenticatable` trait |
| Passkey as second factor | `RedirectIfSecondFactorRequired` extends Fortify's step that sends a password sign-in to the challenge: it also asks for the second factor when the user has a passkey, not only a confirmed TOTP secret. `PasskeyChallengeController` answers the challenge: it offers only the challenged user's own credentials (`allowCredentials`), stores the options under its own session key, and checks ownership again through `VerifyPasskey($credential, $options, $user)` |
| Pages | Inertia v3 pages for sign-in, the challenge (TOTP code and/or "Use a passkey") and security settings, using `@laravel/passkeys`' Vue helpers `usePasskeyVerify` and `usePasskeyRegister`. The challenge page uses the helper's route overrides to call our endpoints |
| Tests | Playwright in Chromium with a CDP virtual authenticator; Firefox through Selenium and geckodriver, with Firefox's built-in software authenticator. Both run in a workflow on the spike branch |

## Results

### Done when

- [x] **Both flows work in Chromium and Firefox.**

  | Browser | Register a passkey | Passkey as second factor | Passwordless sign-in | TOTP next to a passkey |
  | --- | --- | --- | --- | --- |
  | Chromium (Playwright, CDP virtual authenticator) | Passes | Passes | Passes | Passes |
  | Firefox 157 (geckodriver 0.37) | Passes | Passes | Passes | Not tested |

- [x] **Outcome written.**

## Findings

- **Fortify does most of the work.** Since 1.41, Fortify requires `laravel/passkeys` and configures its relying party, origins, user-handle secret, guard, middleware and throttle from Fortify's own config. Use Fortify's routes; do not register the package's routes as well (Fortify calls `Passkeys::ignoreRoutes()`).
- **A passkey as second factor is ours to build.** Fortify's challenge only checks TOTP and recovery codes. The two classes above are the whole addition. The security rule that matters is that only the challenged user's passkeys are accepted: passwordless sign-in accepts any registered passkey, which is fine on its own but must not complete someone else's password sign-in.
- **The relying party cannot be an IP address.** WebAuthn requires a domain, so the end-to-end suite serves the app on `localhost` instead of `127.0.0.1`. Self-hosters reaching Abonemen by IP address cannot use passkeys; the setup check (#26) should say so.
- **Firefox's WebDriver virtual authenticator did not answer.** With only a WebDriver virtual authenticator, registration in headless Firefox 157 never completed. With Firefox's built-in software token switched on (`security.webauth.webauthn_enable_softtoken`), all flows passed. That is a test-tooling gap, not an app problem. CI can keep the Firefox check with that preference.
- **Rate limits are per IP and shared by every guest.** The `passkeys` limiter (10 a minute per IP in the spike) covers login options, login and management together. An end-to-end run from one address exceeds it, so the spike raises the limits in the `testing` environment. #33 should key the limiters deliberately (per email, per session, per IP) and give tests a way around them.
- **TOTP replay protection works.** Fortify refuses a code already used in its window, so tests need the next time step's code.
- **Fortify makes eight recovery codes, not ten.** The technical design says ten; the PoC got Fortify's default of eight. #34 should either configure ten or change the design.
- **Two tooling fixes for `main`** came out of this spike and are in #175: the accessibility linter's label rule rejected ordinary `for`/`id` labels, and the page harness visited JSON endpoints.

## Not covered

Confirming the password before managing factors (#37; switched off in the spike), passkey autofill in the email field, recovery codes as a fallback in the challenge page, and NVDA/TalkBack checks of the new pages.
