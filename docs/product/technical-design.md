# Abonemen: Technical Design

Oct 10, 2026 · Noah Scharrenberg

## Overview

Abonemen 1.0 is a single Laravel 13 application with a Vue 3 front end served through Inertia, storing data in SQLite with every sensitive field encrypted under a per-household key. This document turns the [Product Concept Document](concept.md) and [Product Requirements Document](requirements.md) into a buildable design. Requirement IDs (SEC-01, A11Y-04, ...) refer to the PRD.

**Design drivers**, in the order they settle conflicts:

1. **Encrypted at rest.** A copy of the database, uploads or backups must reveal nothing sensitive (SEC-01 to SEC-04).
2. **Accessible by construction.** Every component and page change works with keyboard and screen reader (A11Y-01 to A11Y-13).
3. **Runs on shared hosting.** No daemons, no Node on the server, one cron entry, SQLite (NFR-07).
4. **One maintainer.** Few moving parts, mainstream Laravel conventions, automation over manual checks.
5. **Ready to grow.** Household-scoped data and module boundaries, so multi-tenancy, other databases and plugins come later without rework (EXT-01, NFR-12).

## Architecture overview

The browser talks to one Laravel process group over HTTPS; inside it, eight domains share one HTTP layer and one scheduler, and only `Crypto` can turn ciphertext back into data.

| Layer | Contents |
| --- | --- |
| Browser | Vue 3, Inertia v3, `@muliacode/ui`; renders pages and manages focus; never holds keys |
| Transport | HTTPS only, CSP nonces, session cookie |
| Laravel 13 app: HTTP layer | Security headers, sign-in, household scope, policies |
| Laravel 13 app: domains | Accounts, Subscriptions, Money, Reminders, Insights, Data, Platform, Crypto (only Crypto holds keys); domains talk only through actions and events |
| Laravel 13 app: background | Scheduler and queue worker: cron mode on shared hosting, daemons in containers |
| SQLite database (via Eloquent) | Ciphertext for names and amounts; plain dates |
| Files and backups (via Crypto) | Encrypted logos and backup archives |
| Master key file (read at boot) | Outside the database and the web root |
| Outside services (via OutboundHttp) | SMTP, web push, Frankfurter, logo search |

Only dates, cycles and status are plain, so the scheduler never needs to decrypt.

A request passes the HTTP layer (headers, sign-in, household scope, policy), reaches one module's action, which reads rows through Eloquent; encrypted attributes are decrypted by `Crypto` with the current household's key, and Inertia sends only the props the page needs.

## Technology stack

The stack is mainstream Laravel and Vue, with one deliberate addition: [CipherSweet](https://ciphersweet.paragonie.com/) for field encryption and blind indexes, because writing that layer ourselves is the riskiest code in the project. Items marked *spike* get a short proof of concept before they are locked in.

| Layer | Choice | Why |
| --- | --- | --- |
| Runtime | PHP 8.3 minimum, 8.4 targeted; extensions `sodium`, `pdo_sqlite`, `intl`, `mbstring`, `gd` | [Laravel 13](https://laravel.com/docs/13.x/releases) supports PHP 8.3 to 8.5; 8.3 is widely offered on shared hosting |
| Framework | Laravel 13.x | Released 17 March 2026; security fixes until 17 March 2028 |
| Glue | [Inertia v3](https://inertiajs.com/docs) (`inertiajs/inertia-laravel`, `@inertiajs/vue3` ^3) | Server-side routing and policies, SPA feel, no separate API for the UI |
| Front end | Vue 3 with TypeScript, Vite | Requested stack; Vite builds assets at release time, so no Node on the server |
| Design system | `@muliacode/ui`: brand-neutral Vue wrappers over `@utrecht/component-library-css` and NL Design System tokens, themed per app; built in muliacode/design-system (v2) and installed from npm | Shared across Muliacode apps; Abonemen supplies its own theme |
| Database | SQLite 3 in WAL mode, via Eloquent and the query builder only | Shared-hosting friendly; MySQL/MariaDB and PostgreSQL later without code changes |
| Field encryption | `spatie/laravel-ciphersweet` for account-level fields and their blind indexes; a small cast on `paragonie/ciphersweet`'s multi-tenant mode for household fields. Both use libsodium (XChaCha20-Poly1305, BLAKE2b blind indexes) | Spatie's package covers the common case (one key, `whereBlind`, unique rules) but its key provider has no tenant context, so household fields use the multi-tenant mode it is built on (*spike*) |
| File and backup encryption | Logos: libsodium secretstream (XChaCha20-Poly1305). Backups: `spatie/laravel-backup` with AES-256 zip encryption | Secretstream encrypts large files in authenticated chunks; laravel-backup handles archiving, AES-256 and retention |
| Passwords | Argon2id via Laravel's `Hash`; bcrypt fallback when PHP lacks Argon2 | PRD ACC-02; some shared hosts build PHP without Argon2 |
| Auth backend | Laravel Fortify (sign-in, reset, verification, TOTP, recovery codes); `laravel/passkeys` with `@laravel/passkeys` for passkeys | Headless, works with Inertia; both first-party (*spike*: passkeys alongside Fortify) |
| Roles and permissions | spatie/laravel-permission with its teams feature, one team per household | Household-scoped roles now; granular permissions after 1.0 become configuration, not a redesign |
| OIDC | Laravel Socialite with a generic OpenID Connect provider | PRD ACC-03, beta phase |
| Money | `brick/money` and `brick/math` | Exact decimal arithmetic and ISO 4217 data (MON-05) |
| Exchange rates | [Frankfurter](https://frankfurter.dev/) v2 API, `https://api.frankfurter.dev/v2/` | Free, no key; now 223 currencies from many central banks, not only the ECB; can be self-hosted |
| Logo search | One `LogoProvider` interface with three sources: the icon from the subscription's own website (apple-touch-icon, web manifest icons, favicon); a SearXNG server's JSON API (`/search?format=json&categories=images`); Google Images through SerpApi | Works without any third party by default; SearXNG is open source and self-hostable; SerpApi has an official API (free plan 250 searches a month, ZeroTrace mode). The admin picks the search service, each user turns it on (PRIV-02); all calls go through `OutboundHttp` |
| Web push | `minishlink/web-push` with VAPID keys | Standard Web Push, no third-party push service |
| Security headers | `spatie/laravel-csp` with per-request nonces, plus a small headers middleware | SEC-05, SEC-06 |
| Tests and quality | Pest, Larastan, Vitest with Testing Library and `vitest-axe`, Playwright with `@axe-core/playwright` | See Testing and CI |

The PRD's MON-02 mentions ECB reference rates; Frankfurter v2 now draws on more sources. The design stores the source and date with every rate, so totals stay honest whichever source Frankfurter uses.

## Code organisation

The app keeps Laravel's standard folder layout and groups code by domain in subfolders, so `artisan make:*` commands, auto-discovery and upgrade guides work unchanged. Domain boundaries are namespaces, checked by architecture tests; after 1.0 they become package boundaries for plugins (EXT-01).

| Domain | Owns | Publishes |
| --- | --- | --- |
| `Crypto` | Key hierarchy, CipherSweet setup, encrypted casts, file encryption, key rotation | `HouseholdKeyRotated` |
| `Accounts` | Users, households, roles, members, invites, sessions, 2FA, passkeys, OIDC | `HouseholdCreated`, `MemberAdded`, `UserJoinedHousehold` |
| `Subscriptions` | Subscriptions, categories, payment methods, tags, price changes, logos, billing-cycle maths | `SubscriptionCreated`, `SubscriptionRenewed`, `PriceChanged`, `SubscriptionCancelled` |
| `Money` | Currencies, exchange rates, conversion, normalised totals, budget | `RatesUpdated` |
| `Reminders` | Reminder rules, due-reminder scan, channels (email, web push, later others), delivery log | `ReminderSent`, `ReminderFailed` |
| `Insights` | Dashboard, statistics, yearly snapshots, calendar, iCal feed | none |
| `Data` | Export, encrypted backup and restore | `BackupCompleted` |
| `Platform` | Settings, admin screen, audit log, outbound HTTP with SSRF guard, security headers, health check | `SecurityEventRecorded` |

Rules, enforced by Pest architecture tests in CI:

- Models and policies stay flat in `app/Models` and `app/Policies`, where Laravel's generators and policy auto-discovery expect them.
- Everything else sits in a domain subfolder, such as `App\Actions\Subscriptions` or `App\Jobs\Reminders`; `php artisan make:controller Subscriptions/SubscriptionController` works as is.
- A domain calls another only through its actions and events, never by writing another domain's models directly.
- Only `App\Support\Crypto` touches keys or encrypted plaintext outside a model cast.
- Every model with a `household_id` uses a `BelongsToHousehold` global scope, so a query can never cross households by accident.

The tests live in `tests/Arch`. Because models are flat, `tests/Arch/Support/Domains.php` records which domain owns each one: a new model needs an entry there, or the build fails. A domain's actions, events, controllers, requests and jobs may not use another domain's models at all, which is stricter than "never write" because Pest cannot tell reads from writes. The household rule checks the migrated database for a `household_id` column, and `App\Models\Concerns\BelongsToHousehold` fails closed: with no household bound, a query matches nothing.

```text
app/
  Actions/        Accounts/ Crypto/ Data/ Insights/ Money/ Platform/ Reminders/ Subscriptions/
  Events/         same domain folders
  Http/
    Controllers/  same domain folders
    Middleware/
    Requests/     same domain folders
  Jobs/           same domain folders
  Models/         flat, as Laravel expects
  Policies/       flat, as Laravel expects
  Providers/      AppServiceProvider plus a provider per domain that needs wiring
  Support/Crypto/ key hierarchy, casts, file encryption
resources/js/
  pages/          one folder per domain (lowercase, as in the Laravel Vue starter kit)
  layouts/
resources/css/
  abonemen-theme.css  the Abonemen theme: Utrecht component tokens
```

## Encryption and key management

Every sensitive field is encrypted with a key that belongs to its household; those keys are themselves encrypted (wrapped) by a master key that never enters the database. Rotating the master key therefore only rewraps a few dozen small keys, and deleting a household destroys its key first (SEC-01 to SEC-04, PRIV-03).

| Level | Key | Notes |
| --- | --- | --- |
| Root | Master key (MK) | Env or secret file, never in the database |
| Root | Recovery key (RK) | Key pair: public half on the server, private half with the admin |
| Data keys (each wrapped by MK, sealed to RK) | Global data key (GDK) | One per instance; protects account fields: email, name, TOTP secrets, recovery codes, email index |
| Data keys | Household data key (HDK) | One per household, versioned; derives field keys per column (CipherSweet) and file keys per upload (secretstream), which protect subscriptions, members, tags, price history and logos |
| Data keys | Backup password | Random, one per archive; protects the AES-256 zip made by laravel-backup; its key file holds both wraps |

Rotating the MK rewraps the three data keys only; no data is re-encrypted.

Either root key alone can unwrap every data key, so losing one is recoverable; losing both loses the data.

### Keys

| Key | Size and form | Where it lives | Protects |
| --- | --- | --- | --- |
| Master key (MK) | 256-bit random, base64 | `ABONEMEN_MASTER_KEY` or `ABONEMEN_MASTER_KEY_FILE` (Docker or Kubernetes secret); never in the database or backups | Wraps every data key |
| Recovery key (RK) | X25519 key pair made at setup; the private half is shown once, as groups of base32 | Public half in `settings`; private half printed or saved by the admin, never stored on the server | Every data key is also sealed to the public half, so keys made after setup are recoverable too |
| Global data key (GDK) | 256-bit random | `data_keys` table, wrapped by MK and sealed to RK | Account-level fields: user email and name, TOTP secrets, recovery codes, OIDC and SMTP secrets, plus the email blind index |
| Household data key (HDK) | 256-bit random, one per household, versioned | `data_keys` table, wrapped by MK and sealed to RK | Every sensitive field and file of that household |
| `APP_KEY` | Laravel's own key | Environment | Sessions, cookies and signed URLs only, never data |

Wrapping uses XChaCha20-Poly1305 with the key's id, owner and version as associated data, so a wrapped key cannot be moved to another household. Sealing to the RK uses a libsodium sealed box, so the server can protect each new key for recovery without ever holding the private half.

### Fields

Encrypted columns use one Eloquent cast, `Encrypted::class`, backed by CipherSweet's multi-tenant mode with the HDK as the tenant root key. CipherSweet derives a separate key per table and column. Each ciphertext is bound to its row's ULID through associated data, so copying a ciphertext into another row fails to decrypt. ULIDs are generated before insert for this reason.

| Encrypted (sensitive) | Plain (operational) |
| --- | --- |
| Subscription name, price, currency, notes, URL; price-change amounts; member names; category and tag names; payment-method labels; notification channel settings (webhook URLs, tokens); web-push endpoints; yearly cost snapshots; user email and display name; TOTP secrets and recovery codes | IDs, `household_id`, billing cycle, start and next payment dates, status, reminder offsets, timestamps, roles, key versions |

The plain columns are what the scheduler needs without decrypting anything. They do reveal patterns (how many subscriptions, when payments fall). That trade-off is accepted and listed in the threat model.

**Search and totals.** A household holds hundreds of rows, not millions, so lists, search, sorting and totals decrypt the household's rows in PHP and work in memory. Only two lookups need a blind index: user email at sign-in and invite email at acceptance. Both use a CipherSweet blind index under the GDK, truncated to 32 bits, so matches are confirmed by decrypting the candidates.

### Files and backups

- **Logos** are sanitised (SVG through `rhukster/dom-sanitizer` in its SVG mode, MIT-licensed), then encrypted with libsodium secretstream under a key derived from the HDK and the file's ULID. They are stored outside the web root and served only by an authorised route with `Cache-Control: private, no-store`.
- **Backups** take a consistent SQLite copy with `VACUUM INTO` and hand it, with the encrypted files, to `spatie/laravel-backup`, which zips them with AES-256 under a random password per backup. That password is wrapped by the MK and sealed to the RK in a small key file beside the archive, so a backup restores with either. The database inside is still field-encrypted, so a backup is protected twice. Restore is our own command, since laravel-backup has none.

### Rotation and recovery

| Operation | Command | What happens |
| --- | --- | --- |
| Rotate the master key | `php artisan abonemen:keys:rotate-master` | All data keys are unwrapped with the old MK and rewrapped with the new one in one transaction; no data is re-encrypted; seconds |
| Rotate a household key | `php artisan abonemen:keys:rotate-household {id?}` | A new HDK version is created; a queued job re-encrypts the household's rows in chunks; rows carry `key_version`, so reads work during the job; the old version is destroyed when done |
| Recover from a lost MK | Recovery screen or `php artisan abonemen:keys:recover` | The admin enters the RK's private half; data keys are unsealed with it and rewrapped under a newly generated MK |
| Delete a household | Account settings, re-authentication required | Its data keys are deleted first (crypto-shredding), then its rows and files; copies in older backups expire with backup retention (30 days by default) |

### Plaintext handling

Decrypted values exist only in memory during a request or job. Queued jobs carry IDs, never plaintext. Encrypted attributes are hidden from model serialisation and logs, and the exception handler redacts them (SEC-11). Decrypted data sent to the browser is limited to what the current page shows.

### What this protects against

| Threat | Protected in 1.0? |
| --- | --- |
| Stolen disk, database file or backup | Yes |
| SQL injection that reads tables | Yes: the attacker gets ciphertext and blind-index fragments only |
| Ciphertext copied between rows or households | Yes: associated data binds it to its row and household |
| Traffic interception | Yes: TLS only, HSTS |
| Attacker with control of the running server and its environment | No: the server can decrypt. Out of scope until zero-knowledge mode |
| A curious instance operator | No, same reason; documented in the privacy notice |

## Authentication, households and authorisation

A user signs in once and may belong to several households; each request runs in one current household, held in the session, and every query is scoped to it. Policies decide what the user's role in that household allows.

### Signing in

- **Passwords** through Laravel Fortify, hashed with Argon2id. Paste and password managers are always allowed (WCAG 3.3.8).
- **Second factor:** TOTP with ten one-time recovery codes, or a passkey. The TOTP secret and codes are stored under the GDK, not `APP_KEY`, by overriding Fortify's two-factor storage.
- **Passkeys** (WebAuthn, beta phase) work as a second factor or as passwordless sign-in, through the first-party `laravel/passkeys` package and its `@laravel/passkeys` browser helper.
- **OIDC** (beta phase) through Socialite. Accounts link only on a verified email. Auto-create and "disable password login" are admin settings, also settable by environment variable.
- **Rate limits:** 5 sign-in attempts per minute per email and IP, then an increasing lockout and an email to the account owner. Reset and invite requests: 3 per hour per email (SEC-08).

### Sessions

Sessions use the database driver with `SESSION_ENCRYPT=true`, secure HttpOnly cookies and `SameSite=Lax`. Defaults: 30 minutes idle, 12 hours absolute, both admin-configurable. The page warns 2 minutes before the idle timeout and offers to extend (A11Y-11). Users see and end their sessions from their profile (ACC-07). Exports, key operations and account deletion require a fresh password or passkey check within 10 minutes (PRIV-04).

### Households, users and members

A **user** can sign in. A **member** is a person subscriptions are paid for, such as a child, and needs no login. A member can be linked to a user, so "Sanne" the user and "Sanne" the member are the same person in statistics.

- **Invites** are 32-byte random tokens, stored only as a SHA-256 hash, single-use, valid for 7 days (ACC-05). An invite to an existing account adds the household to that account after the user accepts.
- **Registration** is off by default; when on, each new user gets their own household.

### Roles

| Action | Owner | Editor | Viewer | Limited viewer |
| --- | --- | --- | --- | --- |
| See subscriptions, totals, calendar, statistics | All | All | All | Only subscriptions shared with them |
| Add, edit, cancel, delete subscriptions | Yes | Yes | No | No |
| Manage categories, members, payment methods, tags | Yes | Yes | No | No |
| Set household currency, budget, time zone | Yes | No | No | No |
| Invite people, change roles, share single subscriptions | Yes | No | No | No |
| Export or delete the household, rotate its key | Yes | No | No | No |

Roles and permissions use `spatie/laravel-permission` with its teams feature, the household being the team. A middleware sets the current household as the team on every request, and policies check permissions such as `subscriptions.edit` rather than role names, so the PRD's post-1.0 granular permissions become a configuration change.

The limited viewer is the PRD's "viewer on selected subscriptions only": a role without household-wide view permission, plus per-subscription grants in a `subscription_viewers` table that the policy checks, because laravel-permission does not grant access per record. Each household keeps at least one owner. Instance-wide administration (users, registration, SMTP, OIDC, allowlist, backups) is a global `admin` role without a team, not a household role. Every row of this table is a Pest test per role (SEC-07).

### API tokens

The REST API (API-01, 1.0) uses Laravel Sanctum personal access tokens with `read` or `read-write` abilities, scoped to one household, shown once, stored hashed, with a last-used date and a 60 requests per minute limit.

## Data model

About two dozen application tables, all keyed by ULIDs, all with a `household_id` except the instance-level ones. Encrypted columns are `TEXT` holding CipherSweet ciphertext; money inside them is a decimal string such as `"15.99"`, never a float. Laravel's own tables (`sessions`, `cache`, `jobs`, `failed_jobs`, `personal_access_tokens`) and `spatie/laravel-permission`'s tables (`roles`, `permissions` and their pivots, with `household_id` as the team column) are standard.

| Table | Holds | Encrypted columns | Main plain columns |
| --- | --- | --- | --- |
| `households` | One household | `name`, `budget_amount` | `main_currency`, `timezone`, `key_version` |
| `data_keys` | Wrapped GDK and HDKs | (wrapped key material) | `owner_type`, `owner_id`, `version`, `wrapped_mk`, `wrapped_rk`, `status` |
| `users` | Accounts | `email`, `name`, `two_factor_secret`, `two_factor_recovery_codes` | `email_bidx`, `password`, `locale`, `theme`, `upcoming_window_days` (default 30), `email_verified_at` |
| `household_user` | Membership | none | `household_id`, `user_id`; roles live in the permission tables |
| `invites` | Pending invites | `email` | `email_bidx`, `token_hash`, `role`, `expires_at`, `accepted_at` |
| `members` | People paid for | `name` | `user_id` (nullable), `sort` |
| `categories` | Categories | `name` | `sort` |
| `payment_methods` | Payment labels | `label` | `sort` |
| `tags`, `subscription_tag` | Tags | `name` | link columns |
| `subscriptions` | The core record | `name`, `price`, `currency`, `url`, `notes` | `member_id`, `category_id`, `payment_method_id`, `logo_file_id`, `cycle_unit`, `cycle_interval`, `anchor_on`, `next_payment_on`, `auto_renew`, `status`, `trial_ends_on`, `notice_value`, `notice_unit`, `cancel_by_on`, `key_version` |
| `price_changes` | Price history | `amount`, `currency` | `subscription_id`, `effective_on` |
| `subscription_viewers` | Limited-viewer grants | none | `subscription_id`, `user_id` |
| `reminder_rules` | Per user and household | none | `kind` (payment, trial, cancel-by), `days_before`, `channel_id`, `quiet_from`, `quiet_until` |
| `reminder_overrides` | Per subscription | none | `subscription_id`, `user_id`, `kind`, `days_before` or `muted` |
| `reminder_deliveries` | Sent and pending reminders | none | `subscription_id`, `user_id`, `channel_id`, `kind`, `due_on`, `status`, `attempts`, `sent_at`; unique on the first five |
| `notification_channels` | A user's channels | `config` (address, token, webhook URL) | `user_id`, `type`, `verified_at` |
| `push_subscriptions` | Browser push endpoints | `endpoint`, `keys` | `user_id`, `user_agent_family` |
| `files` | Encrypted uploads | none (the file itself is encrypted) | `mime`, `size`, `path`, `key_version` |
| `exchange_rates` | Public rates | none | `base`, `quote`, `rate` (decimal string), `rate_on`, `source` |
| `cost_snapshots` | Weekly totals for trends | `monthly_total`, `yearly_total` | `week_start` |
| `audit_events` | Security log | none (no sensitive values by design) | `user_id`, `type`, `ip_prefix`, `created_at` |
| `settings` | Instance settings | secret values (SMTP password, OIDC secret, VAPID private key) | `key`, `is_secret` |

`cancel_by_on` and `next_payment_on` are stored so the scheduler can find due items with an indexed date query, without decrypting. Indexes: `(household_id, next_payment_on)`, `(household_id, status)`, `(cancel_by_on)`, `(trial_ends_on)`.

## Billing cycles, money and time

Payment dates are always computed from the subscription's anchor date, never from the previous payment, so month-end dates do not drift. Money is exact decimal arithmetic throughout, rounded only for display.

### Billing cycles

A cycle is `cycle_interval` × `cycle_unit` (day, week, month, year). The *k*-th payment falls on the anchor date plus *k* × interval units, using Carbon's no-overflow month arithmetic (SUB-03):

| Anchor | Cycle | Payments |
| --- | --- | --- |
| 31 Jan 2026 | 1 month | 28 Feb, 31 Mar, 30 Apr 2026 |
| 29 Feb 2024 | 1 year | 28 Feb 2025, 28 Feb 2026, 29 Feb 2028 |
| 15 Oct 2026 | 2 weeks | 29 Oct, 12 Nov, 26 Nov 2026 |

- **Rolling forward:** a daily job sets `next_payment_on` to the first payment on or after today, in the household's time zone. A subscription with `auto_renew` off whose date has passed moves to "needs attention" instead of rolling.
- **Trials:** `trial_ends_on` with a price change effective that day, so totals switch automatically (SUB-05).
- **Cancel-by date:** `cancel_by_on` = `next_payment_on` minus the notice period (any number of days, weeks or months), recomputed whenever the payment date moves (SUB-06).

### Money and totals

- Amounts are `brick/money` values in the currency's minor units, with the price in force on each date taken from `price_changes` (SUB-07).
- **Monthly equivalent** = price × payments per year ÷ 12, with payments per year = 365.2425 ÷ (7 × interval) for weeks, 12 ÷ interval for months, and so on, kept as an exact fraction until display. Normalised amounts are shown with "≈" and the method is explained in a tooltip and in help text.
- **Conversion:** to the household's main currency using the latest stored rate on or before the date, through the source's base currency. Every converted amount carries its rate date (MON-02). A currency with no rate is excluded from totals, and the total says how many items were left out.
- **Rounding:** half-up to the currency's minor unit, once, at display.

### Time

- Calendar dates (anchor, next payment, trial end, cancel-by) are stored as `DATE` and mean the household's local calendar day. Instants (`created_at`, `sent_at`) are UTC timestamps (NFR-05).
- Reminders are due at 09:00 in the recipient's own time zone by default, moved out of quiet hours, and computed with Carbon in that zone, so daylight-saving changes neither skip nor double them.

## Scheduling, queues and reminders

All background work runs through Laravel's scheduler and a database-backed queue, so shared hosting needs exactly one cron entry and no daemon (NFR-07):

```text
* * * * * php /path/to/abonemen/artisan schedule:run >> /dev/null 2>&1
```

In "cron mode" (shared hosting) the scheduler also drains the queue each minute with `queue:work --stop-when-empty --max-time=50`, guarded by `withoutOverlapping`. In Docker and Kubernetes a long-running `schedule:work` and `queue:work` take over, and cron mode is off.

### Scheduled tasks

| Task | When | Notes |
| --- | --- | --- |
| Drain queue | Every minute, cron mode only | Max 50 s per run |
| Scheduler heartbeat | Every minute | Admin screen warns if no heartbeat for 5 minutes, which catches a missing cron entry |
| Find due reminders | Every 15 minutes | Creates delivery rows, then queues a send job per row |
| Roll payment dates forward, recompute cancel-by dates | Daily, 00:15 per household time zone | Indexed date query, no decryption needed |
| Fetch exchange rates | Daily, 17:00 Europe/Amsterdam, if enabled | After central banks publish; one request for all currencies in use |
| Cost snapshot | Weekly, Monday 01:30 | Feeds the yearly trend (VIEW-04) |
| Backup | Daily, 03:00, if enabled | Then prunes backups past retention |
| Prune | Daily, 04:00 | Expired invites, sessions, old delivery rows, failed jobs over 30 days |

### Reminder pipeline

1. The scan selects subscriptions whose payment, trial end or cancel-by date falls within each user's reminder rule, using plain date columns only. New users start with one rule: 3 days before each payment, at 09:00.
2. It inserts one `reminder_deliveries` row per subscription, user, channel, kind and due date. The unique key makes repeated scans, restarts and overlapping runs harmless: no duplicates and no misses (NFR-04).
3. A queued job per row decrypts only the fields the message needs, builds it in the user's language and sends it.
4. Failures retry after 1 minute, 10 minutes and 1 hour, then the row is marked failed and shown in the user's notification settings.

### Channels

Each channel implements one `ReminderChannel` contract (`send`, `test`, `validateConfig`), so new channels are added without touching the pipeline (EXT-02).

- **Email** through Laravel Mail over SMTP, plain text plus simple accessible HTML.
- **Web push** through VAPID. Payloads are end-to-end encrypted by the Web Push standard (RFC 8291), so the browser vendor's push service cannot read them.
- **Later, if simple:** Telegram, Discord, Gotify, Pushover, ntfy and webhooks, each a small class behind the SSRF-guarded HTTP client.

Messages are minimal by default, for example "Netflix renews on Friday 17 October" with a link and no price (NOT-05); users can opt in to include the amount. "Send test" exists for every channel (NOT-04).

## Front end

The UI is a client-rendered Inertia v3 app in Vue 3 and TypeScript, built from `@muliacode/ui` components, with focus management and live announcements handled once in the app shell, not per page. There is no server-side rendering in 1.0: Inertia SSR needs a Node process in production, which shared hosting cannot run.

### App shell and page changes

On every Inertia navigation the shell (A11Y-04):

1. sets `document.title` to "Page title · Abonemen";
2. moves focus to the page's `h1` (`tabindex="-1"`), or to the error summary if the page returned validation errors;
3. announces the new title through a polite live region;
4. restores scroll position on back and forward, and scrolls to top otherwise.

The shell also holds the skip link, the landmarks (`header`, `nav`, `main`, `footer`), the primary navigation (bottom bar under 48 rem, side bar above) and the help link in the same place on every page (WCAG 3.2.6).

### Component library

`@muliacode/ui` lives in its own repository, `muliacode/design-system`, and Abonemen installs it from npm at a pinned version. That repository's earlier design system stays on a `v1` branch; the NL Design System based v2 is built on `main`, with Storybook and tests. It is brand-neutral, so other Muliacode apps can use it, and ships a neutral default theme; each app supplies its own, Abonemen in `resources/css/abonemen-theme.css`. Components are Vue single-file components that render the class names of `@utrecht/component-library-css`. Styling comes only from design tokens.

| Comes from Utrecht CSS, wrapped | Built by us, same patterns |
| --- | --- |
| Button, Link, Heading, Paragraph, Form field, Text box, Select, Checkbox, Radio group, Field error message, Alert, Table, Badge, Skip link, Page header and footer | Error summary, sortable Table, Dialog (native `dialog`), Navigation bar, Bottom bar, Month calendar (grid with roving focus and a list twin), Chart (SVG with a table twin), Money input, Toast (never the only feedback), Session timeout warning |

The exact split is checked against the current Utrecht component list in the design phase.

### Theming

The Abonemen theme is a set of NL Design System tokens (light, dark and high contrast) compiled with Style Dictionary into CSS custom properties under `.abonemen-theme`. A `forced-colors` layer keeps borders and focus rings visible in Windows high contrast. `prefers-reduced-motion` turns off all transitions (A11Y-08). Contrast pairs are checked automatically in CI (A11Y-06).

### Forms

Forms use Inertia's `useForm`. Fields always have a visible label and an optional hint. Errors appear inline, linked with `aria-describedby` and `aria-invalid`, and on submit an error summary at the top lists every error as a link to its field (A11Y-05). The add-subscription form shows five fields first and the rest under a disclosure (UX-03).

### Charts and calendar

Charts are our own small SVG components: no chart library, which keeps the bundle small and the Content Security Policy strict. Each chart renders its data table and a one-sentence summary first, with the picture as an enhancement (A11Y-09, VIEW-05). The calendar is a `table`-based month grid with a list view showing the same data (VIEW-02).

### Languages and formatting

- Laravel's `lang/{locale}.json` files are the single source for both server and Vue, read in Vue through `laravel-vue-i18n`. Launch locales: nl, en, id, fr, de (NFR-09).
- Numbers, money and dates use `Intl` in the user's locale. Amounts carry visually hidden context where needed, so a screen reader says "12 euro 99 per month" (A11Y-09).
- Each page sets `lang` on `html`; text in another language gets its own `lang` attribute.

### Performance

Pages are split per route with `import.meta.glob`. Budget: dashboard JavaScript under 150 kB compressed and CSS under 40 kB, checked in CI, toward the LCP target of 2.0 s (NFR-01).

## Security controls

Apart from encryption, the security work is mostly strict defaults applied in one place each: one headers middleware, one outbound HTTP client, one upload pipeline, one log redactor. Each control below has an automated test.

### Transport and headers

| Control | Setting |
| --- | --- |
| HTTPS | Required; the app generates only `https` URLs and redirects plain HTTP; trusted proxies configurable for Docker and Kubernetes ingress |
| HSTS | `max-age=31536000; includeSubDomains`, `preload` as an admin opt-in (SEC-05) |
| Content Security Policy | `default-src 'self'; script-src 'self' 'nonce-…'; style-src 'self' 'nonce-…'; img-src 'self' data:; connect-src 'self'; font-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'` (SEC-06); Vue's runtime-only build, so no `eval` |
| Other headers | `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer`, `Cross-Origin-Opener-Policy: same-origin`, `Cross-Origin-Resource-Policy: same-origin`, `Permissions-Policy` denying camera, microphone, geolocation and payment |
| Request forgery | Laravel 13's origin-aware `PreventRequestForgery` plus CSRF tokens on every state change (SEC-07) |
| Cookies | `Secure`, `HttpOnly`, `SameSite=Lax`, `__Host-` prefix for the session cookie |

### Outbound requests

Every outbound call (exchange rates, logo search, SMTP, OIDC, push and future channels) goes through one `OutboundHttp` client (SEC-09, NOT-06). It:

- allows HTTPS only, except hosts on the admin allowlist or `SSRF_ALLOWLIST`;
- resolves DNS itself, rejects loopback, private, link-local, multicast and IPv6 unique-local addresses, then pins the connection to the checked IP, so DNS rebinding cannot switch it;
- follows no redirect to another host, times out after 10 s and stops reading after 1 MB;
- is off for any integration the admin has not enabled, and every call shows on the connections page (PRIV-02).

### Uploads

Logos: at most 1 MB, type checked from the file content, not the name. Raster images are re-encoded with GD, which also strips metadata; SVGs are sanitised. They are then encrypted (see Encryption) and served with `Content-Security-Policy: sandbox` and `nosniff` (SUB-09).

### Logs and audit trail

- Application logs are structured JSON. A Monolog processor redacts known sensitive keys (password, token, secret, email, name, price, url) and no request bodies are logged (SEC-11).
- The audit log records sign-ins, failed sign-ins, 2FA changes, role changes, invites, exports, backups, key operations and deletions, with the user, time and a truncated IP (/24 for IPv4, /48 for IPv6). It never stores the values involved (SEC-10).

### Supply chain

`composer audit` and `npm audit` fail CI on high-severity advisories; Renovate keeps dependencies current, and a licence check fails CI on any dependency whose licence cannot be combined with Abonemen's EUPL-1.2, such as GPL-only packages. Release images are scanned with Grype, ship an SBOM made with Syft (both from Anchore), and are signed with cosign (SEC-12). Every GitHub Action is pinned to a full commit SHA, no workflow uses `pull_request_target`, and tokens are repository-scoped with the fewest permissions needed. The repository has a `SECURITY.md` and the app serves `/.well-known/security.txt` with the disclosure contact (SEC-13).

## Deployment and operations

One codebase ships three ways from the same release pipeline; they differ only in how processes run and where the master key comes from. In every case one instance runs one SQLite database, so there is exactly one app process writing.

|  | Shared hosting | Docker | Kubernetes |
| --- | --- | --- | --- |
| Artifact | Release zip with `vendor/` and built assets | One image, amd64 and arm64 | Helm chart using the same image |
| Web server | The host's Apache or nginx, document root set to `public/` | FrankenPHP, Caddy built in (*spike*) | FrankenPHP behind the cluster ingress |
| Background work | One cron entry, cron mode | `schedule:work` and `queue:work` in the container | Same, in the single pod |
| Data | `storage/` outside the web root | Volume at `/data` | Persistent volume, `ReadWriteOnce`, `Recreate` strategy, 1 replica |
| Master key | File outside the web root, path in `.env` | Docker secret via `ABONEMEN_MASTER_KEY_FILE` | Kubernetes Secret mounted as a file |
| TLS | The host's certificate | Caddy's automatic certificates, or a proxy in front | Ingress |

### First run

When no master key is configured, the app serves only the setup wizard (ACC-01). It checks PHP extensions and folder permissions, generates `APP_KEY`, the MK and the RK, and writes the MK to the configured file. It then shows the RK once and requires the admin to type back two of its groups before creating the admin account. After setup the wizard route is gone.

### Upgrades

Docker and Kubernetes upgrade on container start; shared hosting after uploading a new zip and opening the admin screen, or with `php artisan abonemen:upgrade`. Each upgrade takes an encrypted backup, runs migrations, then rebuilds caches. Migrations are forward-only; rolling back means restoring the backup taken just before (NFR-08).

### Backups and health

- Daily encrypted backups go to `storage/backups` and are kept for 30 days by default, with no longer-term tiers, so a deleted household is fully gone from backups after 30 days. Copying them off the server is the admin's job, and the docs show how (NFR-06).
- Restore works from the admin screen or `php artisan abonemen:backup:restore`, and needs the MK or the RK.
- `GET /health` returns only `ok` or `fail` to anonymous callers. Admins see the details: database reachable, keys loaded, scheduler heartbeat age, queue backlog.

### Main settings

| Variable | Purpose |
| --- | --- |
| `APP_URL`, `APP_KEY` | Laravel basics |
| `ABONEMEN_MASTER_KEY` or `ABONEMEN_MASTER_KEY_FILE` | The master key, the file form preferred |
| `ABONEMEN_RUN_MODE` | `cron` (shared hosting) or `daemon` (containers) |
| `DB_DATABASE` | Path of the SQLite file |
| `MAIL_*` | SMTP for invites, resets and reminders |
| `OIDC_*` | Optional; overrides admin settings |
| `SSRF_ALLOWLIST` | Optional; hosts allowed on private addresses |
| `BACKUP_RETENTION_DAYS` | Default 30 |

## Testing and CI

With one maintainer and no budget for audits, CI is the quality team: every PRD acceptance criterion that can be automated is a CI job on GitHub Actions, and a pull request cannot merge while any of them fails.

| Check | Tool | Runs on | Proves |
| --- | --- | --- | --- |
| Unit and feature tests | Pest | Every push | Behaviour; at least 80% coverage of domain code (NFR-11) |
| Architecture rules | Pest `arch()` | Every push | Module boundaries, household scoping, no debug calls |
| Static analysis and style | Larastan, Pint, `vue-tsc`, ESLint with `eslint-plugin-vuejs-accessibility` | Every push | Type safety, consistent code, common a11y mistakes |
| Component tests | Vitest, Testing Library, `vitest-axe` | Every push | Each `@muliacode/ui` component works by keyboard and has no axe violations |
| End-to-end journeys | Playwright on Chromium, Firefox and WebKit | Every push | All seven PRD journeys |
| Page accessibility | `@axe-core/playwright` on every page, in light, dark and high contrast, at 320 px width and with forced colours | Every push | 0 axe violations (A11Y acceptance criterion) |
| Encryption leak test | Seeds canary values, then searches the raw SQLite file, uploads folder and a backup archive for them | Every push | No readable sensitive field at rest |
| Crypto tests | Known-answer vectors, ciphertext swapped between rows, wrong household, master-key and household-key rotation round trips, restore with the RK | Every push | Encryption behaves as designed |
| Date maths | Table-driven cases: month ends, leap years, daylight-saving changes, every cycle unit | Every push | SUB-03, NFR-05 |
| Dependency audit | `composer audit`, `npm audit` | Every push and daily | No known high-severity advisories |
| Dynamic scan | OWASP ZAP baseline against the app running in CI | Nightly and before release | No high findings (SEC-13) |
| Image scan and SBOM | Grype, Syft, cosign | Release | SEC-12 |
| Install tests | Release zip on a plain Apache and PHP container; `docker compose up`; Helm install on a `kind` cluster; each followed by a smoke test | Release | Fresh installs work on all three paths |
| Size budget | Build output check | Every push | NFR-01 budget |

Manual checks the PRD asks for (keyboard only, NVDA, TalkBack, 400% zoom, high contrast) are a checklist in the release pull request template, so a release cannot be tagged without ticking them.

The checks that run on every push live in `.github/workflows/ci.yml`. Its last job, **CI passed**, succeeds only when every other job did, so it is the one status check `main` requires. ESLint runs only `eslint-plugin-vuejs-accessibility`; general linting and formatting come from Vite+ (oxlint and oxfmt). PHP coverage counts `app/` without the service providers and fails below 80%; the report is in the job summary and as a build artefact.

The Playwright suite lives in `tests/e2e` and runs from `.github/workflows/e2e.yml`, one job per browser. `forEveryPage()` in `tests/e2e/support/accessibility.ts` reads the page routes from `php artisan route:list`, so a new page is checked without touching the tests; a route with parameters fails the suite until it has example values in `tests/e2e/support/routes.ts`. Each page is checked in four themes (light, dark, high contrast and forced colours) at 320 px and at desktop width: axe with the WCAG 2.2 A and AA rules, no horizontal scrolling at 320 px, and exactly one `h1`. A theme emulates the media features (`prefers-color-scheme`, `prefers-contrast`, `forced-colors`, reduced motion) and sets `data-theme` on `<html>`. Engines that cannot emulate forced colours skip that theme and say so in the report.

The encryption leak test is `tests/Feature/Crypto/EncryptionLeakTest.php`, part of the normal Pest run. It points the app at a SQLite file and storage folders in a temporary directory, seeds canaries through `tests/Support/Leaks/Canaries.php`, and searches every byte written there, including the WAL file, logs, and the contents of unencrypted zip entries and gzip files. Each text canary is also searched for in its lowercase, JSON, URL, HTML, base64 and UTF-16 forms, and each amount with a decimal point, a decimal comma and in minor units. Every new sensitive field adds a canary in the same pull request. Fields not encrypted yet are listed in `Canaries::PLAINTEXT_UNTIL_FIXED` with their issue; the test fails once one of them stops leaking, so the list only shrinks. The backup part runs as soon as `backup:run` exists.

## Decisions and open questions

Fifteen architecture decisions are recorded; short spikes confirm three of them before building starts.

| # | Decision | Rejected alternatives | Status |
| --- | --- | --- | --- |
| 1 | Modular monolith in Laravel 13: eight domains in Laravel's standard folder layout | Separate API and SPA; a custom `app/Modules` tree or `nwidart/laravel-modules` | Accepted |
| 2 | Inertia v3, client-rendered, no SSR in 1.0 | SSR, which needs Node in production | Accepted |
| 3 | Field encryption: `spatie/laravel-ciphersweet` for account fields; a cast on CipherSweet's multi-tenant mode for household fields | One app-wide key for everything, which loses per-household rotation and crypto-shredding; Laravel's encrypted casts; hand-written libsodium code | Proposed, spike 1 |
| 4 | Envelope encryption: data keys wrapped by the MK and sealed to an X25519 recovery key pair | MK only, which makes a lost key fatal; a symmetric recovery key, which would have to stay on the server to protect new keys | Accepted |
| 5 | Search, sort and totals in memory per household | Blind indexes on every field; plaintext amounts for SQL sums | Accepted |
| 6 | SQLite only in 1.0, all access through Eloquent and the query builder | MySQL or PostgreSQL from day one | Accepted (PRD) |
| 7 | Database queue, drained by the scheduler in cron mode | Redis, which shared hosting rarely offers | Accepted |
| 8 | Own SVG chart components with table twins | Chart.js, ECharts | Accepted |
| 9 | Frankfurter v2 as the exchange-rate source | Fixer (needs a key) | Accepted (PRD) |
| 10 | FrankenPHP as the container web server | nginx with php-fpm and a process supervisor | Proposed, spike 3 |
| 11 | First-party `laravel/passkeys` for passkeys | `laragear/webauthn`, archived in May 2026 in favour of it | Accepted, spike 2 checks Fortify |
| 12 | `spatie/laravel-backup` for backups, 30-day retention, own restore command | Own backup code | Accepted |
| 13 | `@muliacode/ui`: a brand-neutral component library for all Muliacode apps, built as v2 of muliacode/design-system (v1 kept on its own branch) | An Abonemen-only library; a package inside the Abonemen repository | Accepted |
| 14 | spatie/laravel-permission with teams for household roles; own table for per-subscription grants | A hand-written role column and checks | Accepted |
| 15 | Logos come from an upload, the subscription's own website, or logo search through SearXNG or SerpApi, chosen by the admin | Scraping DuckDuckGo's unofficial image endpoint and Brave's results page, as Wallos does by default: no public API, breaks when they change, likely against their terms. A European image search: none found with a public API | Accepted |

### Spikes before building

- [ ] **1. Encryption:** `spatie/laravel-ciphersweet` with a custom key provider that supplies the GDK, plus a multi-tenant CipherSweet cast with per-household HDKs and row-bound associated data. Decrypt 500 subscriptions in under 100 ms, leaving room in the 300 ms budget (NFR-02). Include storing Fortify's TOTP secret under the GDK.
- [ ] **2. Passkeys:** `laravel/passkeys` alongside Fortify and Inertia v3, as second factor and passwordless sign-in.
- [ ] **3. Container:** FrankenPHP image on amd64 and arm64, non-root, read-only root file system.
- [ ] **4. Shared hosting:** cron mode on a real shared host: queue drains in time, Argon2 and AES-256 zip support available, document root set to `public/`.
- [ ] **5. Translations:** `laravel-vue-i18n` working with Inertia v3 and five locales.

### Open questions

Resolved on 10 October 2026: plaintext payment dates, cycles and status are accepted; the default reminder is 3 days before at 09:00; backups are kept for 30 days by default. No open questions remain.
