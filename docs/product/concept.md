# Abonemen: Product Concept Document

Oct 10, 2026 · Noah Scharrenberg

## Summary

Abonemen is a self-hostable, privacy-first subscription tracker that covers what people expect from today's subscription tools, then raises the bar on three fronts: encryption of all sensitive data at rest and in transit, full WCAG 2.2 AA conformance built on the NL Design System, and a calmer, faster UX. It is built with Laravel, Vue and Inertia.

The name comes from the Indonesian *abonemen* (subscription), itself borrowed from the Dutch *abonnement*. That lineage fits the product: Dutch public-sector design and accessibility standards, applied to a tool for everyday household finances.

## Problem and opportunity

Households pay for more recurring services than they can name, and the cost of forgetting is concrete: unwanted renewals, missed cancellation windows and trials that silently convert to paid plans.

Existing tools force a trade-off:

- **Bank and fintech apps** such as Rocket Money, Dyme and the spending overviews in banking apps find subscriptions automatically, but only by reading your transactions, and they only see the accounts you link.
- **Subscription tracker apps and SaaS** such as Bobby, Subby and TrackMySubs are quick to use, but keep data on one phone or in a vendor's cloud, and few are built for a whole household.
- **App store subscription lists** (Apple, Google Play) are accurate, but only cover what was bought through that store.
- **Spreadsheets** are flexible but manual, offer no reminders and are hard to share with a partner.
- **Self-hosted open-source tools** keep data at home. Wallos is the best-known dedicated tracker; finance suites such as Firefly III and Actual Budget handle recurring bills as part of full budgeting. Wallos stores its data in plaintext on disk, the suites are more than a household needs to see what renews when, and accessibility is rarely a stated goal, so screen-reader, keyboard-only and low-vision users are poorly served.

The opportunity is a tracker that combines the privacy of self-hosting with the ease people know from commercial apps, without ever touching a bank account, and adds what the field mostly lacks: defence in depth for financial data (a stolen disk or backup reveals nothing useful), and an interface that people with disabilities can use as easily as anyone else. In the EU, the European Accessibility Act and EN 301 549 make that second point a growing expectation, not a niche.

## Vision, mission and principles

**Vision:** everyone knows exactly what they pay for, when, and why, without giving up their privacy.

**Mission:** give households a calm, trustworthy place to track recurring costs, that encrypts what matters and works for every user, whatever their abilities or device.

**Product principles** (used to settle trade-offs, in priority order):

1. **Private by default.** Collect the minimum. Encrypt sensitive fields at rest. No telemetry, no third-party calls unless the user turns them on.
2. **Accessible by construction.** WCAG 2.2 AA is a release gate, not a later audit. If a pattern cannot be made accessible, we do not ship it.
3. **Calm over clever.** The default screen answers three questions: what is due soon, what does it cost me per month, what should I act on.
4. **Fast to capture.** Adding a subscription takes under 30 seconds with sensible defaults; detail is optional.
5. **Honest numbers.** Every total shows its currency, period and exchange-rate date. No silent rounding or hidden conversions.
6. **Yours to leave.** Full export in open formats at any time; importers for Wallos and CSV follow as modules after 1.0.

## Target users and personas

The primary user is a privacy-conscious individual or household that self-hosts, or is willing to. The secondary user is anyone who relies on assistive technology and is badly served by today's trackers.

| Persona | Context | Needs | Pain today |
| --- | --- | --- | --- |
| **Sanne, 34, self-hoster** | Runs a home server with Docker; tracks 25 subscriptions for herself and her partner | One shared view, reminders before renewals, data never leaves her server | Wallos works, but the database is a plaintext file in every backup |
| **Daan, 41, blind accountant** | Uses NVDA and keyboard only; manages family finances | Predictable headings and landmarks, accessible tables and charts, clear form errors | Most trackers rely on colour, icons and drag-and-drop with no alternatives |
| **Ayu, 27, mobile-first** | Bilingual (Indonesian, Dutch, English); checks costs on her phone | Fast capture on mobile, multi-currency, reminders by push or email | Apps feel cluttered; totals in mixed currencies confuse her |
| **Mark, 58, low vision** | Uses 200 to 400% zoom and high-contrast mode | Reflow without horizontal scrolling, large targets, no time limits | Layouts break at high zoom; low-contrast text |
| **Instance admin** | Hosts Abonemen for family or a small community | Simple install, user management, OIDC, backups, key rotation | Encryption and key management are usually left to the operator |

## Value proposition and positioning

**For** privacy-conscious households **who** want to stay on top of recurring costs, **Abonemen is** a self-hosted subscription tracker **that** encrypts sensitive data and works for everyone. **Unlike** bank-linked apps, it never needs access to your bank account; **unlike** other trackers, commercial or self-hosted, it treats encryption and accessibility as core features rather than afterthoughts.

| Option | Examples | Strong at | Where it falls short |
| --- | --- | --- | --- |
| Bank and fintech apps | Rocket Money, Dyme, banking-app spending overviews | Finds subscriptions automatically; help with cancelling | Needs your transaction data; sees only linked accounts; key features often paid |
| Tracker apps and SaaS | Bobby, Subby, TrackMySubs | Quick capture, polished mobile UX, reminders | Data on one device or in a vendor's cloud; little household sharing; accessibility varies |
| App store lists | Apple and Google Play subscription settings | Accurate, built in | Only covers purchases made in that store |
| Spreadsheets | Excel, Google Sheets, LibreOffice Calc | Flexible, familiar, free | Manual; no reminders; privacy depends on where the file lives |
| Self-hosted trackers | [Wallos](https://github.com/ellite/wallos) | Rich feature set, data on your own server, free (GPL-3.0) | Plaintext data at rest; accessibility not a stated goal |
| Self-hosted finance suites | Firefly III, Actual Budget | Full budgeting, recurring transactions | Built for budgeting rather than renewals; more to set up and learn |
| **Abonemen** |  | Private by default, encrypted at rest, WCAG 2.2 AA, household-first, no bank access | Manual entry (no bank sync); new and unproven |

Abonemen is a new implementation on its own stack. It is written from scratch and does not copy code from other trackers or finance tools; code under GPL-only or AGPL licences cannot be relicensed under Abonemen's EUPL-1.2.

## Core concept and key capabilities

The core object is a **subscription**: something you pay for repeatedly, owned by a household member, paid with a payment method, in a currency, on a cycle. Everything else either organises subscriptions or tells you something about them.

| Capability | What the user gets | Expected in the category |
| --- | --- | --- |
| Subscriptions | Name, logo, price, currency, cycle (daily to yearly, or every N units), start and next payment date, auto-renew, notes, URL, active or cancelled | Yes |
| Trials and cancellation windows | Trial end date, notice period, "cancel by" date with its own reminder | Less common |
| Price history | Every price change kept with its date; totals over time stay correct | Less common |
| Organisation | Categories, household members, payment methods, tags, search, sort, filters | Yes, tags new |
| Money | Multi-currency, a main currency per household, conversion with dated ECB rates from the Frankfurter API, monthly and yearly normalised totals, budget | Yes |
| Overview | Dashboard of upcoming payments, monthly cost, budget use, items needing action | Yes, redesigned |
| Calendar | Month view of payments, plus a private iCal feed | Yes |
| Statistics | Cost by category, member and payment method; yearly cost trend | Yes |
| Reminders | Days-before reminders by email and web push; other channels such as Telegram, Discord and ntfy where simple, otherwise after 1.0 | Yes |
| Logos | Upload, the icon from the subscription's website, or optional logo search (SearXNG or SerpApi) | Yes |
| Recommendations | Optional suggestions for savings via a local model (Ollama) or a hosted AI provider, off by default | Yes |
| Accounts | Households with users who sign in and members without a login (such as children); roles, TOTP, passkeys, OIDC | Yes, passkeys new |
| Data | Export to CSV, JSON and encrypted backup; REST API with scoped tokens; importers as modules after 1.0 | Yes, import later |
| Personalisation | Light, dark and high-contrast themes; language; date and number formats | Yes, high contrast new |

## Privacy, security and accessibility pillars

Three pillars set Abonemen apart; each has a concrete promise the user can check.

**Privacy**

- No telemetry, analytics or tracking cookies. Only strictly necessary session cookies.
- Every outbound call (exchange rates, logo search, AI, notification channels) is off until the user or admin turns it on, and is listed on a "connections" page.
- Data minimisation: no real names, bank details or card numbers are needed. Payment methods are labels such as "Visa ending 42", typed by the user.
- GDPR rights built in: export everything, delete account and household, see an activity log.

**Security**

- *In transit:* HTTPS only, TLS 1.2 or higher (1.3 preferred), HSTS, strict Content Security Policy, secure and same-site cookies.
- *At rest:* field-level encryption of sensitive data (names, prices, notes, URLs, member names, notification secrets) with a per-household data key, itself wrapped by a master key kept outside the database. Uploaded logos and backups are encrypted too.
- Strong sign-in: Argon2id password hashing, TOTP, passkeys (WebAuthn), OIDC, rate limiting and lockout.
- Secure by default operation: signed releases, dependency scanning, a security policy and a documented threat model.

**Accessibility**

- WCAG 2.2 level AA conformance across every screen, in light, dark and high-contrast themes.
- Built on [NL Design System](https://nldesignsystem.nl/) CSS components and design tokens, which are built and tested with accessibility in mind, with an Abonemen theme on top.
- Every chart has a data-table alternative; every drag action has a button alternative; nothing relies on colour alone.
- Tested by the developer with NVDA, TalkBack, keyboard only, 400% zoom and Windows high-contrast mode, plus JAWS in its free demo mode. VoiceOver and independent audits are welcome from volunteers. A public accessibility statement lists any known issues.

## Deployment and business model

Abonemen ships first as free, open-source software that people host themselves. After 1.0, a multi-tenant hosted service lets non-technical users sign up without installing anything.

- **Shared hosting:** a release zip with built assets and no Composer or Node step on the server. Runs on PHP with SQLite, so no database server is needed. A web installer creates the admin and writes the master key to the environment file. One cron entry runs the scheduler, which also drains the queue, so no daemons are needed.
- **Docker:** one image (amd64 and arm64) with a Compose file; scheduler and queue run inside the container.
- **Kubernetes:** a Helm chart with one pod running web, scheduler and queue, SQLite on a persistent volume and the master key as a Secret. Separate scaled deployments arrive with MySQL/MariaDB and PostgreSQL support after 1.0.
- **Every install:** a first-run wizard that generates the master key and forces a recovery-key backup step; migrations run after an automatic encrypted backup.
- **Hosted service (after 1.0):** multi-tenant, delivered as a module on the same codebase, with its own privacy, legal and key-management review. Zero-knowledge mode is a strong candidate here.
- **Sustainability:** donations or sponsorship for the open-source edition; for the hosted service, a free tier by default and a paid tier that funds development.

**Extensibility (after 1.0).** A module system lets first-party and community extensions plug in without touching core: importers (Wallos, CSV), multi-tenancy, enterprise SSO, extra notification channels, zero-knowledge mode. Modules are Laravel packages that register through service providers and hook into defined events, routes, settings pages and navigation slots. 1.0 prepares for this by keeping clear module boundaries in its own code.

## Success metrics

Without telemetry or a budget for outside audits, Abonemen measures success through automated tests, the developer's own manual checks, voluntary feedback and public signals.

| Area | Metric | Target |
| --- | --- | --- |
| Accessibility | Automated axe violations on every page in CI | 0 |
| Accessibility | Self-evaluation against every WCAG 2.2 A and AA criterion before 1.0, all themes | 0 open AA failures |
| Accessibility | Core tasks (add, edit, find next payment) completed end to end with NVDA and TalkBack | All, every release |
| Security | Sensitive fields readable in a raw database dump | 0 |
| Security | Critical or high findings open from automated security scans (dependency audit, static analysis, OWASP ZAP baseline) at release | 0 |
| UX | Time to add a subscription with only the required fields, in end-to-end walkthroughs | Under 30 seconds |
| UX | System Usability Scale score from voluntary feedback | 80 or more, once there are responses |
| Performance | Largest Contentful Paint on the dashboard with 200 subscriptions, mid-range phone | Under 2.0 seconds |
| Adoption | Fresh installs that succeed from the docs alone on shared hosting, Docker and Kubernetes | All three, every release |
| Community | GitHub stars and Docker pulls 12 months after 1.0 | Tracked, no target yet |

## Risks, assumptions and open questions

**Assumptions**

- Self-hosted first on shared hosting, Docker or Kubernetes; a multi-tenant hosted service follows after 1.0.
- Encryption is server-side: the server holds keys so it can send reminders, convert currencies and compute statistics. A stolen database or backup is useless without the master key, but an attacker with full control of the running server could read data.
- Dutch, English, Indonesian, French and German at launch; other languages via community translation.

**Resourcing.** Abonemen is built by one volunteer developer with no budget. Quality therefore rests on automation and self-evaluation. Penetration tests, independent accessibility audits and user research are welcome when volunteers or funding appear, but no requirement or release depends on them.

**Risks**

| Risk | Impact | Mitigation |
| --- | --- | --- |
| NL Design System Vue components are limited and still alpha ([npm](https://npmjs.com/package/@utrecht/component-library-vue)) | Gaps in ready-made components; API churn | Use the stable Utrecht CSS components and design tokens, wrapped in the shared @muliacode/ui library; contribute back |
| Encrypted fields cannot be sorted, filtered or summed in SQL | Slower lists and stats; complex queries | Decrypt per household in memory (a household has hundreds of rows, not millions); blind indexes for exact-match search |
| Lost master key means lost data | Users lose everything | Setup wizard forces a key backup step; recovery key printed once; documented rotation |
| Charts and calendars are hard to make fully accessible | AA failures in key views | Table alternatives first; charts are progressive enhancement |
| Baseline feature scope is large | Slow path to a useful release | Phased release; MVP covers the daily loop only |

**Decisions (10 October 2026)**

| Topic | Decision | Why |
| --- | --- | --- |
| Licence | EUPL-1.2 | The European Union Public Licence: a copyleft licence written for EU law, with equally valid texts in every official EU language. It counts offering the software over a network as distribution, so hosted forks stay open, and it is the licence of the NL Design System's Utrecht components. Trade-off: code under GPL-only or AGPL licences cannot be copied in, and dependencies must use a compatible licence. Changed from AGPL-3.0-or-later on 11 October 2026 |
| Design system | NL Design System design tokens plus the Utrecht CSS components, wrapped in a shared, brand-neutral Vue 3 component library for all Muliacode apps (`@muliacode/ui`), with an Abonemen theme | Fast start on proven, tested CSS; the Vue wrappers are ours from day one, so the library grows into a reusable one without a later rewrite |
| Deployment | Self-hosted first on shared hosting, Docker or Kubernetes; a multi-tenant hosted service for non-technical users follows after 1.0 | Matches the first audience; the data model is household-scoped from the start so multi-tenancy needs no schema rework |
| Extensibility | A module system (Laravel packages with defined extension points) after 1.0, for importers, multi-tenancy, enterprise SSO and community extensions | Keeps 1.0 small; Laravel's service providers and events make this a natural fit |

**What a zero-knowledge mode would mean**

In zero-knowledge mode, data is encrypted and decrypted only in the user's browser. The server stores ciphertext and never holds a key that can open it, so even the operator (including a future hosted Abonemen) cannot read anything.

|  | Server-side encryption (1.0) | Zero-knowledge mode (later) |
| --- | --- | --- |
| Who holds the keys | Server, via the master key outside the database | Only users' devices; keys derived from a passphrase or a passkey |
| Protects against | Stolen database, disk or backup | All of those, plus a curious or compromised operator |
| Reminders | Full text, such as "Netflix, 15.99 euro, Friday" | Only what the server can see, such as "A payment is due Friday", unless reminder text is pre-encrypted per device |
| Totals, statistics, currency conversion | Computed on the server | Computed in the browser after decrypting |
| Sharing with a household | Automatic | Each user has a key pair; the household key is wrapped for every member, and invites need a key exchange |
| Forgotten passphrase | Admin can reset the password; data stays readable | Data is lost without a recovery key |
| Search, API, integrations | Work normally | Limited to unencrypted fields; the API returns ciphertext |

Recommendation: ship server-side encryption in 1.0, but design the key hierarchy (per-household data keys, wrapped keys) so zero-knowledge can arrive later as a module. It is most valuable for the hosted service, where "we cannot read your data" is a strong promise.

**Deferred until after 1.0**

- **Zero-knowledge mode:** not in 1.0; a possible extension later.
- **Module priorities:** which first-party modules come first is decided once 1.0 is out.
- **Hosted service model:** a free tier by default plus a paid tier that funds development. GDPR processor obligations still need research before launch.
