# Abonemen: Product Requirements Document

Oct 10, 2026 · Noah Scharrenberg

## Scope and goals

This PRD defines what Abonemen 1.0 must do: the core features people expect from a subscription tracker, field-level encryption of sensitive data, and WCAG 2.2 AA conformance using the NL Design System. It follows the Abonemen Product Concept Document and comes before technical design.

**Priority key:** *Must* = required for the release named in the release plan; *Should* = expected in 1.0 but can slip; *Could* = later.

**Goals**

1. Users can see every recurring cost, its next payment and its monthly and yearly impact in one place.
2. Users never miss a renewal, trial end or cancellation deadline they care about.
3. A raw copy of the database, uploads or backups reveals no sensitive data.
4. Every feature is fully usable with keyboard, screen reader, 400% zoom and high contrast.
5. Anyone can install and run it themselves in minutes, on shared hosting, Docker or Kubernetes.

**Non-goals for 1.0**

- Bank connections, open-banking imports or automatic detection of subscriptions.
- Cancelling subscriptions on the user's behalf.
- Importers from Wallos or CSV; they follow as modules after 1.0.
- A public module and plugin API; 1.0 only keeps internal module boundaries clean.
- The multi-tenant hosted service, billing and marketing site.
- Native mobile apps (the web app is an installable PWA instead).
- Browser-side zero-knowledge encryption (see the concept document).

## Personas and key user journeys

Personas are defined in the concept document: Sanne (self-hoster), Daan (blind, screen reader), Ayu (mobile, multilingual), Mark (low vision) and the instance admin. Every journey below must succeed for all of them.

1. **First run (admin).** Install on shared hosting, Docker or Kubernetes, open the setup wizard, create the admin account with a passkey or password plus TOTP, back up the recovery key, set main currency and language, and choose whether to turn on exchange rates.
2. **Add a subscription (all).** From any screen, open "Add subscription", type a name (a logo is suggested if logo search is on), enter price and cycle, accept the default next payment date, save. Under 30 seconds.
3. **Weekly check (Sanne).** Open the dashboard and see what is due in the coming period (30 days by default, adjustable), this month's total and anything needing action, such as a trial ending.
4. **Get reminded (Ayu).** Receive an email or push notification 3 days before a renewal; follow the link to the subscription; mark it as cancelled.
5. **Review spending (Daan).** Go to Statistics by heading navigation; read cost by category as a sortable table; switch to yearly view.
6. **Add people to the household (Sanne).** Add her partner as a user, either by inviting a new account or by adding an existing one; the partner signs in and sees the shared subscriptions. Add her son as a member without a login, so she can manage his subscriptions.
7. **Leave (all).** Export everything as JSON and CSV; delete the account; receive confirmation that data and keys are destroyed.

## Functional requirements

Requirements are grouped by module. IDs are stable and referenced by tests and design.

### Accounts and households

| ID | Requirement | Priority |
| --- | --- | --- |
| ACC-01 | First-run wizard creates the admin, generates the master key and shows a one-time recovery key that must be confirmed before continuing | Must |
| ACC-02 | Sign in with email and password (Argon2id), with optional TOTP and passkeys (WebAuthn) | Must |
| ACC-03 | Sign in with OIDC; settings from the admin screen or environment variables; option to disable password login | Should |
| ACC-04 | A household groups people and shares subscriptions. A person joins either as a user who can sign in (a new account by invite, or an existing account), with role owner, editor or viewer for the whole household, or viewer on selected subscriptions only, or as a member without a login (such as a child) whose subscriptions others manage. Finer-grained permissions follow after 1.0 | Must |
| ACC-05 | Invite by single-use, expiring link; self-registration off by default and toggled by the admin | Must |
| ACC-06 | Password reset and email verification by signed, expiring links | Must |
| ACC-07 | Users see and revoke active sessions and API tokens | Should |
| ACC-08 | Admin screen: users, registration, SMTP, OIDC, outbound-connection allowlist, backups | Must |

### Subscriptions

| ID | Requirement | Priority |
| --- | --- | --- |
| SUB-01 | Create, view, edit, duplicate and delete subscriptions | Must |
| SUB-02 | Fields: name, price, currency, billing cycle (every N days, weeks, months or years), start date, next payment date, auto-renew, member, category, payment method, URL, notes, logo | Must |
| SUB-03 | Next payment date is calculated from start date and cycle, and rolls forward automatically after it passes, including month-end rules (31 Jan + 1 month = 28 or 29 Feb) | Must |
| SUB-04 | Status: active, paused, cancelled; cancelled items keep history and are hidden from totals | Must |
| SUB-05 | Free trial: trial end date and price after trial, with its own reminder | Should |
| SUB-06 | Cancellation window: notice period in days, weeks or months (e.g. 45 days or 3 months) and a computed "cancel by" date with its own reminder | Should |
| SUB-07 | Price changes are stored with an effective date; history is shown on the detail page | Should |
| SUB-08 | Tags, free text search, sort (name, price, next payment, category, member) and combinable filters, kept in the URL | Must |
| SUB-09 | Logo by upload (PNG, JPG, WebP, SVG sanitised; max 1 MB) the icon fetched from the subscription's own website, or optional logo search through an admin-chosen service: a SearXNG server or Google Images via SerpApi; no scraping of search engines | Must |
| SUB-10 | Bulk actions: change category, member or status for selected items | Could |

### Organisation and money

| ID | Requirement | Priority |
| --- | --- | --- |
| ORG-01 | Manage categories, household members (each optionally linked to a user account) and payment methods, with reorder by buttons, not drag only | Must |
| ORG-02 | Default categories and payment methods seeded in the user's language | Should |
| MON-01 | Any ISO 4217 currency per subscription; one main currency per household | Must |
| MON-02 | Exchange rates from the Frankfurter API (free ECB reference rates, no API key), turned on in the setup wizard; manual rates for currencies it does not cover; every converted amount shows the rate date | Must |
| MON-03 | Normalised totals per month and per year across cycles and currencies | Must |
| MON-04 | Monthly budget with spent, remaining and over-budget status | Should |
| MON-05 | Money uses exact decimal arithmetic, never floating point; amounts formatted by locale | Must |

### Overview, calendar and statistics

| ID | Requirement | Priority |
| --- | --- | --- |
| VIEW-01 | Dashboard: upcoming payments within a window the user sets (30 days by default, e.g. 60 or 90), this month's total, yearly total, budget use, and a "needs attention" list (trials ending, cancel-by dates, overdue), where each item appears as soon as its reminder period starts, even outside the upcoming window | Must |
| VIEW-02 | Calendar month view with a list view alternative showing the same data | Must |
| VIEW-03 | Private iCal feed per user with a revocable secret URL; event titles configurable to hide prices | Should |
| VIEW-04 | Statistics: cost by category, member and payment method; most expensive; yearly trend from weekly snapshots | Must |
| VIEW-05 | Every chart has an equivalent sortable data table and a text summary | Must |

### Reminders and notifications

| ID | Requirement | Priority |
| --- | --- | --- |
| NOT-01 | Per-user reminder settings: days before payment, per subscription override, quiet hours | Must |
| NOT-02 | Channels: email (SMTP) and web push; Telegram, Discord, Gotify, Pushover, ntfy and webhooks only where simple to add on the channel interface, otherwise after 1.0 | Must for email and web push; Should for others if simple |
| NOT-03 | Separate reminders for trial end and cancel-by dates | Should |
| NOT-04 | "Send test" for every channel, with a clear success or error message | Must |
| NOT-05 | Message content is minimal by default (name and date, no price) and configurable | Should |
| NOT-06 | Outbound URLs that resolve to private or loopback addresses are blocked unless allowlisted | Must |

### Data, API and extras

| ID | Requirement | Priority |
| --- | --- | --- |
| DATA-01 | Export all user and household data as JSON and CSV | Must |
| DATA-02 | Importers for Wallos and CSV, with preview, mapping and report, delivered as a module after 1.0 | Could |
| DATA-03 | Encrypted full backup and restore from the admin screen and the command line | Must |
| API-01 | REST API with personal access tokens, read-only or read-write scopes, rate limits and OpenAPI documentation | Should |
| AI-01 | Optional savings recommendations via Ollama or a hosted provider; off by default; shows exactly what data is sent before it is turned on | Could |
| PREF-01 | Preferences: language (nl, en, id, fr, de at launch), theme (system, light, dark, high contrast), date format, first day of week, reduced motion | Must |

### Extensibility

| ID | Requirement | Priority |
| --- | --- | --- |
| EXT-01 | Core code is organised in modules with clear boundaries (subscriptions, notifications, money, accounts), communicating through domain events | Must |
| EXT-02 | Notification channels and exchange-rate providers implement shared interfaces, so new ones can be added without core changes | Must |
| EXT-03 | Public module system after 1.0: modules are Laravel packages that register routes, settings pages, navigation items, migrations, translations and event listeners through a documented API | Could |
| EXT-04 | Candidate first-party modules: Wallos and CSV importers, multi-tenant hosting, enterprise SSO (SAML), zero-knowledge mode | Could |
| EXT-05 | Modules can be enabled and disabled by the admin; a module never gets access to decryption keys unless it declares and the admin grants that permission | Could |

## Security and privacy requirements

Sensitive data is encrypted at the field level with per-household keys, so a database dump, disk image or backup is unreadable without the master key held outside the database.

**Data classification**

| Class | Examples | Protection |
| --- | --- | --- |
| Secret | Passwords, TOTP secrets, recovery codes | One-way hashed (Argon2id) or encrypted with the user's key; never shown again |
| Sensitive | Subscription name, price, notes, URL, logo, member names, payment-method labels, notification tokens and webhook URLs, API keys for providers | Field-level encryption (AEAD, e.g. XChaCha20-Poly1305 or AES-256-GCM) with the household data key |
| Operational | IDs, dates, cycle, status, foreign keys, timestamps | Plain, needed for scheduling; covered by disk or database encryption if the operator enables it |

**Requirements**

| ID | Requirement | Priority |
| --- | --- | --- |
| SEC-01 | Envelope encryption: each household has a random data key, wrapped by a master key supplied via environment variable, file secret or external KMS, never stored in the database | Must |
| SEC-02 | Master-key and data-key rotation by command, without downtime and with re-encryption in the background | Must |
| SEC-03 | Blind indexes (keyed hashes) for exact-match lookups on encrypted fields where needed | Should |
| SEC-04 | Uploaded files and backups encrypted with the same scheme; served only through authorised routes | Must |
| SEC-05 | HTTPS only, TLS 1.2 minimum, 1.3 preferred; HSTS with preload option; secure, HttpOnly, SameSite=Lax cookies | Must |
| SEC-06 | Strict Content Security Policy with nonces, no inline scripts, frame-ancestors none, Referrer-Policy no-referrer, Permissions-Policy locked down | Must |
| SEC-07 | CSRF protection on all state changes; authorisation policies on every route and API endpoint, tested per role | Must |
| SEC-08 | Rate limiting on sign-in, password reset, invites and API; progressive lockout with notification | Must |
| SEC-09 | Server-side request forgery protection for all outbound calls (webhooks, SMTP, OIDC, logo search, rate providers) | Must |
| SEC-10 | Audit log of security events (sign-ins, key rotation, exports, role changes) visible to the user and admin, with no sensitive values | Must |
| SEC-11 | Logs, error pages and queued jobs never contain decrypted sensitive values | Must |
| SEC-12 | Dependency and container scanning in CI; signed images; SBOM published with each release | Should |
| SEC-13 | Threat model and security policy with a responsible-disclosure contact published; self-review against the OWASP ASVS level 2 checklist and an OWASP ZAP baseline scan in CI before 1.0. External penetration tests are welcome but not required | Must |
| PRIV-01 | No telemetry, analytics or third-party assets; fonts and icons self-hosted | Must |
| PRIV-02 | All outbound integrations off by default and listed on a connections page with what data each sends | Must |
| PRIV-03 | Account and household deletion destroys the data keys first (crypto-shredding), then the rows; backups older than the retention period are purged | Must |
| PRIV-04 | Session timeout configurable; re-authentication for exports, key operations and account deletion | Must |

The design aims for alignment with OWASP ASVS level 2 and the GDPR principles of data minimisation and privacy by design.

## Accessibility requirements

Every screen, in every theme and language, must conform to WCAG 2.2 level AA. That also covers the WCAG-derived web clauses of EN 301 549, which the European Accessibility Act relies on. Conformance is the target for every release, verified by automated tests and the developer's own manual checks; any known gap is listed in the accessibility statement rather than hidden.

**Criteria new in WCAG 2.2 and how Abonemen meets them**

| Criterion | What it means here |
| --- | --- |
| 2.4.11 Focus Not Obscured (AA) | Sticky header, toasts and the mobile bottom bar never cover the focused element; scroll padding set accordingly |
| 2.5.7 Dragging Movements (AA) | Reordering categories and calendar items always has buttons or a menu alternative |
| 2.5.8 Target Size (AA) | All targets at least 24 by 24 CSS pixels; Abonemen aims for 44 by 44 on touch |
| 3.2.6 Consistent Help (A) | Help link and contact details sit in the same place on every page |
| 3.3.7 Redundant Entry (A) | Values entered earlier in a flow (e.g. import mapping, duplicate subscription) are pre-filled |
| 3.3.8 Accessible Authentication (AA) | Passkeys supported; paste and password managers allowed; no cognitive tests or CAPTCHAs |

**Requirements**

| ID | Requirement | Priority |
| --- | --- | --- |
| A11Y-01 | Use NL Design System CSS components and design tokens; build missing components to the same patterns and contribute them back where useful | Must |
| A11Y-02 | Semantic HTML first: landmarks, one h1 per page, logical heading order, native controls before ARIA | Must |
| A11Y-03 | Full keyboard operation; visible focus indicator meeting 2.4.13 Focus Appearance (AAA) as a stretch goal; skip link | Must |
| A11Y-04 | Inertia page changes move focus to the new page's h1 and announce the page title via a live region | Must |
| A11Y-05 | Forms follow NL Design System form guidance: visible labels, hints, inline errors linked by aria-describedby, an error summary on submit, no placeholder-only labels | Must |
| A11Y-06 | Colour contrast at least 4.5:1 for text and 3:1 for UI and graphics in all themes; status never conveyed by colour alone | Must |
| A11Y-07 | Reflow at 320 CSS pixels and 400% zoom without loss; text spacing overrides do not break layout | Must |
| A11Y-08 | Respect prefers-reduced-motion, prefers-color-scheme and forced-colors (Windows high contrast) | Must |
| A11Y-09 | Charts: data table and text summary alternatives; calendar: list alternative; amounts read correctly ("12 euro 99 per month") | Must |
| A11Y-10 | Dialogs use native dialog with focus trap and return; toasts are polite live regions and never the only feedback | Must |
| A11Y-11 | No time limits on tasks; session timeout warns 2 minutes ahead and can be extended | Must |
| A11Y-12 | Content in plain language (B1 level for Dutch), correct lang attributes, consistent terms across languages | Should |
| A11Y-13 | Accessibility statement published in the Dutch government format, with known issues and a contact | Must |

**Testing**

- **Automated, every change:** axe-core in component tests and Playwright end-to-end tests on every page, in every theme; CI fails on any violation. Automated tools catch only part of WCAG, so the manual checks below are required too.
- **Manual, every release, by the developer:** keyboard only; NVDA with Firefox and Chrome; TalkBack on Android; 200% and 400% zoom; Windows high contrast; text-spacing overrides. JAWS in its free demo mode where practical.
- **Before 1.0:** a self-evaluation against every WCAG 2.2 A and AA success criterion, following the WCAG-EM method, with the results in the accessibility statement.
- **Welcome, not required:** VoiceOver on macOS and iOS, independent audits and sessions with disabled users, from volunteers or community feedback. Issues they raise are tracked and prioritised like any other bug.

## UX requirements

The interface should feel calm and obvious: the dashboard answers "what is due, what does it cost, what needs me" without scrolling on a laptop.

| ID | Requirement | Priority |
| --- | --- | --- |
| UX-01 | Primary navigation of five items: Overview, Subscriptions, Calendar, Statistics, Settings; bottom bar on small screens, side or top bar on large | Must |
| UX-02 | "Add subscription" reachable from every page in one action, including a keyboard shortcut that can be turned off (WCAG 2.1.4) | Must |
| UX-03 | Add form shows essentials first (name, price, currency, cycle, next payment); everything else under "More details" | Must |
| UX-04 | Smart defaults: currency = main currency, cycle = monthly, next payment = one cycle from today, member = current user | Must |
| UX-05 | Each subscription shows its price in its own currency and, if different, the converted monthly amount | Must |
| UX-06 | Empty states explain what to do next and link to a short getting-started guide | Must |
| UX-07 | Destructive actions confirm with the item's name; deletes can be undone for 10 seconds where safe, with undo also reachable by keyboard | Should |
| UX-08 | Errors say what happened and how to fix it, in plain language; no codes alone | Must |
| UX-09 | Lists and filters keep their state in the URL so back, refresh and sharing work | Must |
| UX-10 | Installable PWA with offline read-only view of the last loaded data (cached data encrypted or cleared on sign-out) | Could |
| UX-11 | Every new screen is walked through end to end with keyboard and NVDA by the developer; feedback from volunteer users is welcome and tracked as GitHub issues | Should |

The visual language follows the NL Design System guidance on typography, colour and icons, with an Abonemen theme expressed as design tokens. The design phase will define the theme, the component inventory and the gaps to build.

## Non-functional requirements

| ID | Area | Requirement | Priority |
| --- | --- | --- | --- |
| NFR-01 | Performance | Dashboard LCP under 2.0 s and INP under 200 ms on a mid-range phone with 200 subscriptions | Must |
| NFR-02 | Performance | Server response for list pages under 300 ms at p95 with 500 subscriptions per household, including decryption | Must |
| NFR-03 | Capacity | One instance supports at least 50 households on a 2 vCPU, 2 GB RAM host with SQLite in WAL mode; all database access goes through Eloquent and the query builder, so MySQL/MariaDB and PostgreSQL can be added after 1.0 without code changes | Should |
| NFR-04 | Reliability | Scheduled reminders are idempotent: no duplicates and no misses after restarts; failed sends retry with backoff | Must |
| NFR-05 | Time | All dates stored in UTC with the household's time zone; reminders fire in local time; correct across daylight-saving changes | Must |
| NFR-06 | Backup | Automatic daily encrypted backup with configurable retention; restore tested in CI | Must |
| NFR-07 | Operability | Three install paths: shared hosting (release zip, PHP with SQLite, one cron entry runs scheduler and queue, no daemons), Docker image (amd64 and arm64) with Compose, and a Helm chart for Kubernetes; health endpoint, structured logs, configuration by environment variables | Must |
| NFR-08 | Upgrades | Database migrations run on start after an automatic backup; rollback documented | Must |
| NFR-09 | i18n | All text in translation files; nl, en, id, fr and de complete at launch; right-to-left ready layout | Must |
| NFR-10 | Browsers | Last two versions of Chrome, Edge, Firefox, Safari (macOS and iOS), Samsung Internet | Must |
| NFR-11 | Quality | Automated test coverage of 80% on domain logic; end-to-end tests for every user journey | Should |
| NFR-12 | Maintainability | Stack: Laravel (current LTS-supported major), Vue 3 with TypeScript, [Inertia](https://inertiajs.com/); documented architecture decisions; every record scoped to a household, so a multi-tenant hosted edition needs no schema rework | Must |

## Conceptual data model

The household is the unit of sharing and of encryption: one data key per household protects every sensitive field below it.

| Entity | Belongs to | Encrypted | Plain (needed by the scheduler) |
| --- | --- | --- | --- |
| Household | — | — | Main currency, time zone, budget; holds its data key, wrapped by the master key |
| User | Household | — | Sign-in, role, 2FA; email stored with a blind index |
| Member | Household | Name | Optional link to a user (members may have no login) |
| Category | Household | Name | Order |
| Payment method | Household | Label (e.g. "Visa 42") | Order |
| Subscription | Household; one member, category and payment method | Name, price, currency, notes, URL | Cycle, dates, status, links |
| Price change | Subscription | Amount | Effective date |
| Reminder | Subscription | — | Days before, channel, dates |
| Tag | Household, linked to subscriptions | Name | — |
| Logo file | Subscription | Whole file | Served only through an authorised route |

Users also own notification channels, sessions and API tokens; the household also holds exchange rates and yearly cost snapshots. The physical schema is left to technical design.

## Release plan

The MVP covers the daily loop for one household; each later phase is gated on security and accessibility evidence, not on dates.

| Phase | Contents | Gate to the next phase |
| --- | --- | --- |
| MVP (private alpha) | Accounts with TOTP; subscriptions, categories, members, payment methods, multi-currency; dashboard, list view, email reminders, field encryption, backups; shared hosting and Docker; nl and en | A raw dump shows no sensitive data; axe reports 0 violations |
| Beta (public) | Calendar and iCal; statistics with tables; web push and extra notification channels if simple; trials, cancel-by dates, price history; passkeys, OIDC; id, fr and de; Helm chart | WCAG 2.2 AA self-evaluation passes; security scans show 0 open high findings |
| 1.0 (stable) | All Must and Should items; REST API; key rotation; admin screen; per-subscription viewers; accessibility statement, threat model and security policy published | — |
| Later | Module system: Wallos and CSV importers, multi-tenancy, enterprise SSO, zero-knowledge mode; hosted service; MySQL/MariaDB and PostgreSQL; granular permissions; AI tips; offline PWA; more languages | — |

Dates are set once technical design has sized the work.

## Acceptance criteria and open questions

**Release 1.0 is accepted when:**

- [ ] Every Must requirement is implemented and covered by automated tests.
- [ ] All seven user journeys pass end-to-end tests and manual walkthroughs with keyboard only and NVDA.
- [ ] axe-core reports 0 violations on every page in every theme, and the WCAG 2.2 AA self-evaluation has 0 open failures.
- [ ] A raw database dump, upload folder and backup contain no readable sensitive field (verified by an automated scan for known test values).
- [ ] Automated security scans (dependency audit, static analysis, ZAP baseline) have 0 open critical or high findings.
- [ ] Key rotation and full restore from backup succeed in CI.
- [ ] A fresh install succeeds from the docs alone on shared hosting, Docker and Kubernetes.
- [ ] Accessibility statement, security policy, threat model and privacy notice are published.

**Open questions**

Decided on 10 October 2026: EUPL-1.2 (changed from AGPL-3.0-or-later on 11 October 2026); Frankfurter for exchange rates; users and login-less members in one household; NL Design System tokens with Utrecht CSS components in the shared `@muliacode/ui` Vue library; no importer in 1.0; SQLite only in 1.0; roles owner, editor and viewer, plus viewer on selected subscriptions. Decided on 11 October 2026: logos come from an upload, the subscription's own website, or logo search through SearXNG or SerpApi (SUB-09).

Deferred until after 1.0: MySQL/MariaDB and PostgreSQL support, finer-grained permissions, and the order of first-party modules.

No open questions remain for 1.0.
