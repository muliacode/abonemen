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

`composer setup` installs the dependencies, creates your `.env` file, generates the application key **and the master key**, creates the SQLite database, runs the migrations and builds the assets. It is safe to run again: an existing key is never replaced.

Then start the app:

```bash
composer dev
```

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

## Existing local database

While Abonemen is unreleased the migrations are still being edited in place. If you have a database from an earlier version, rebuild it: `php artisan migrate:fresh`. There is nothing to migrate yet.

## Checks

```bash
composer test        # lint, static analysis and the test suite
composer lint        # fix code style
```

## Licence

[EUPL-1.2](LICENSE.md).
