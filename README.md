# Abonemen

A privacy-first subscription tracker for households, built to be self-hosted. It never touches a bank account, it encrypts the personal data it stores, and it is built to work for everyone, whatever their abilities or device.

Abonemen is under development and has no release yet. The plan is in [`docs/scope.md`](docs/scope.md), the vocabulary in [`GLOSSARY.md`](GLOSSARY.md), and the decisions behind it in [`docs/adr/`](docs/adr/).

## Requirements

- PHP 8.3 or newer, with the SQLite extension
- [Composer](https://getcomposer.org)
- Node.js 22 or newer

## First-time setup

```bash
git clone https://github.com/muliacode/abonemen.git
cd abonemen
composer setup
```

`composer setup` installs the dependencies, creates your `.env` file, generates the application key, **the master key and the setup token**, creates the SQLite database, runs the migrations and builds the assets. It is safe to run again: an existing key or token is never replaced.

Then start the app:

```bash
composer dev
```

### Creating the first Instance Admin

A new instance has nobody who can run it, and registration is closed. Open `/setup`, enter the **setup token** (`ABONEMEN_SETUP_TOKEN` in your `.env`), the name, email address and password of the first Instance Admin, and two choices: whether the Instance Admin section requires a second factor (on, recommended) and whether anyone can register (off). That person is signed in, gets a Household of their own like anyone who registers, and `/setup` disappears for good: once an Instance Admin exists it answers 404, and it does not exist at all while no token is configured. Make a token with `php artisan setup-token:generate --write`.

### The Instance Admin section

An Instance Admin runs the instance. The section has five pages: **Households** (how many there are, nothing about what is inside), **Users** (find one by their exact email address, make or remove Instance Admins, create a User), **General** (registration, the second factor requirement and the defaults for new Households), **Email** and **Server**. An Instance Admin cannot read anything inside a Household, and being one gives no access to any of them ([ADR 0003](docs/adr/0003-instance-admin-boundary-is-policy-not-cryptography.md)).

- The section lives under `/instance`. Set `ABONEMEN_ADMIN_PATH` to put it somewhere else; the app refuses to start if the path would hide a page it already has. The path is a first line of quiet, not a lock: to everyone who is not an Instance Admin it answers 404, and the real protection is signing in plus a second factor.
- An Instance Admin confirms their password to enter it, as for Settings > Security (once, then not again for a few hours: `AUTH_PASSWORD_TIMEOUT`), and enters only after setting up two-factor authentication or a passkey under Settings > Security, unless the instance chose not to require it (General). Turning that requirement off asks for your password; it is your own security decision.
- **General** also holds the language, currency and time zone a new Household starts with. They begin as what the installer was using, and changing them never touches Households that exist.
- **Email** sets up SMTP so that password resets and verification emails reach people. Until it is set up, emails are only written to a log. What you save there wins over `MAIL_*` in `.env`, "Go back to .env" forgets it, and the password is stored protected with the master key and never shown again. A test email goes to your own address. If you run a queue worker, restart it after changing email settings.
- **Server** is read-only: the address you visit against `APP_URL`, the type of database, whether the keys are set (never their values), where email settings come from, and the queue and cache drivers. The database, `APP_URL` and the keys stay in `.env` on purpose: the app's own settings live in the database, and a different `APP_URL` silently breaks every passkey. The page warns when `APP_URL` does not match the address you are on, and shows the line to change.
- A **Get started** block on the Households page lists what is still open (email, an address mismatch) and goes away when nothing is left.
- Registration is closed by default. Until an Instance Admin opens it, `/register` explains that it is closed and the sign-up links are hidden. When it is open, a new User gets a Household of their own, with them as Owner.
- The last Instance Admin cannot be removed, and cannot delete their own account.

### The master key

Abonemen stores names and email addresses encrypted. The keys that protect them are themselves protected by one **master key**, which lives in your `.env` file as `ABONEMEN_MASTER_KEY` and nowhere else.

- **Back it up.** Copy the value somewhere safe, apart from the database and its backups. If the key is lost, the data it protects cannot be read by anyone, including you.
- **Keep it apart from the database.** Whoever has both the database and the master key can read everything.
- **Never replace it on a database that holds data.** A different key makes the existing data unreadable, and the app stops with a clear error instead of showing garbage. `php artisan master-key:generate --write` therefore leaves an existing key alone.

To make a key by hand, for example for a server's environment settings, run `php artisan master-key:generate` and copy the line it prints into `ABONEMEN_MASTER_KEY`. The app refuses to protect or read data without a valid key, and tells you how to make one.

### Sessions and mail in development

Sessions are encrypted (`SESSION_ENCRYPT=true`). In development, mail is written to the log (`MAIL_MAILER=log`), so password-reset and verification links, which contain the email address, appear in `storage/logs`. Configure a real mail provider before using the app with other people.

## Languages

Abonemen is available in English, Dutch (`nl`), Indonesian (`id`), French (`fr`) and German (`de`). Each person picks theirs under Settings > Language; until they do, the language their browser asks for is used, and emails go out in the recipient's language.

Texts are written in English where they are used, as `__('Some text')`, and translated in `lang/{code}.json`. A text missing from a language shows in English, and a test (`TranslationCompletenessTest`) fails and lists what is missing, so adding a screen means adding its texts to the four files. Messages from Laravel itself (validation, passwords, emails) come from the [`laravel-lang`](https://github.com/Laravel-Lang/lang) package: `php artisan lang:update` fetches improvements, and the files in `lang/` are generated, so they are left out of code-style checks.

## Accessibility

Abonemen meets WCAG 2.2 level AA, in the light and the dark theme. [`docs/accessibility.md`](docs/accessibility.md) holds the patterns to build screens with, the definition of done for a new screen, the Flux views we override and the manual checklist. Structure, forms, colour contrast and the Flux overrides are checked by tests, so a screen added without following the patterns fails them.

## Existing local database

While Abonemen is unreleased the migrations are still being edited in place. If you have a database from an earlier version, rebuild it: `php artisan migrate:fresh`. There is nothing to migrate yet.

## Checks

```bash
composer test        # lint, static analysis and the test suite
composer lint        # fix code style
```

## Licence

[EUPL-1.2](LICENSE.md).
