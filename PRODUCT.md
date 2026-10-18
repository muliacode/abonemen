# Product

<!-- impeccable:product-schema 1 -->

The confirmed source for everything here is [`docs/product/`](docs/product/): the [concept](docs/product/concept.md), [requirements](docs/product/requirements.md) and [technical design](docs/product/technical-design.md), all dated 10 October 2026. This file summarises them for design work; where they disagree, the docs win and this file is stale.

## Platform

web

A responsive web app, installable as a PWA. Native mobile apps are a non-goal for 1.0.

## Users

The primary user is a privacy-conscious individual or household that self-hosts, or is willing to. The secondary user is anyone who relies on assistive technology and is badly served by today's trackers. Every key journey must succeed for every persona:

- **Sanne, 34, self-hoster.** Runs a home server; tracks about 25 subscriptions for herself and her partner. Wants one shared view, reminders before renewals, and data that never leaves her server.
- **Daan, 41, blind accountant.** NVDA and keyboard only; manages family finances. Needs predictable headings and landmarks, accessible tables and charts, clear form errors.
- **Ayu, 27, mobile-first.** Bilingual (Indonesian, Dutch, English); checks costs on her phone. Needs fast mobile capture, multi-currency, reminders by push or email.
- **Mark, 58, low vision.** 200 to 400% zoom and high-contrast mode. Needs reflow without horizontal scrolling, large targets, no time limits.
- **Instance admin.** Hosts Abonemen for family or a small community. Needs a simple install, user management, OIDC, backups and key rotation.

Core journeys: first run (admin), add a subscription in under 30 seconds, the weekly check of what is due, acting on a reminder, reviewing spending by table, adding household members (with or without a login), and leaving with a full export.

## Product Purpose

A self-hostable, privacy-first subscription tracker. It shows what you pay for, when it renews and when a trial or cancellation window closes, without ever touching your bank account.

Vision: everyone knows exactly what they pay for, when, and why, without giving up their privacy. Success for 1.0: users see every recurring cost and its monthly and yearly impact in one place, never miss a deadline they care about, a raw database copy reveals nothing sensitive, every feature works with keyboard, screen reader, 400% zoom and high contrast, and anyone can install it in minutes.

## Positioning

For privacy-conscious households who want to stay on top of recurring costs, Abonemen is a self-hosted subscription tracker that encrypts sensitive data and works for everyone. Unlike bank-linked apps, it never needs access to your bank account. Unlike other trackers, commercial or self-hosted (Wallos stores plaintext; accessibility is rarely a goal anywhere), it treats field-level encryption and WCAG 2.2 AA as core features. It is household-first, with members who have no login, such as children.

## Operating Context

- Self-hosted on shared hosting (release zip, PHP with SQLite, one cron entry), Docker, or Kubernetes. A multi-tenant hosted service follows after 1.0.
- Used on laptops for the weekly check and on phones for quick capture and acting on reminders.
- Reminders arrive by email and web push and link straight to the subscription.
- Used with NVDA, TalkBack, JAWS, keyboard only, 400% zoom and Windows high contrast.
- Languages at launch: Dutch, English, Indonesian, French and German. Dates and numbers follow the user's locale.

## Capabilities and Constraints

- **Core object:** the subscription: name, logo, price, currency, cycle, next payment, trial end, notice period and "cancel by" date, price history, owner, payment method, category, tags.
- **Views:** Overview (what is due, monthly cost, budget use, what needs action), Subscriptions list, Calendar with a private iCal feed, Statistics. Primary navigation is those four plus Settings: a bottom bar on small screens, a side bar on large ones.
- **Money:** multi-currency with dated ECB rates; every total shows its currency, period and rate date. Exact decimal arithmetic.
- **Privacy:** no telemetry or tracking cookies; every outbound call (exchange rates, logo search, AI, notification channels) is off until turned on and listed on a connections page. Payment methods are user-typed labels, never card numbers.
- **Security:** field-level encryption with per-household keys; HTTPS only, strict CSP; Argon2id, TOTP, passkeys, OIDC.
- **UX rules:** "Add subscription" one action away from every page; essentials first, the rest under "More details"; smart defaults; list and filter state in the URL; destructive actions confirm by name, with undo where safe.
- **Stack:** Laravel 13, Vue 3, Inertia 3, TypeScript, Vite; UI through `@muliacode/ui` on Utrecht CSS with the Abonemen theme.
- **Non-goals for 1.0:** bank connections or auto-detection, cancelling on the user's behalf, importers, a public plugin API, the hosted service and marketing site, native apps, zero-knowledge mode.
- **Status:** scaffolded, no features yet. The release plan is phased (MVP private alpha first), and each phase is gated on security and accessibility evidence rather than dates.

## Brand Commitments

- **Name:** Indonesian for "subscription", borrowed from the Dutch *abonnement*. That Dutch and Indonesian lineage is part of the story.
- **Voice:** calm, plain and specific, at language level B1. Address the reader as "you" (Dutch "je", Indonesian "kamu"). Sentence case; no exclamation marks, emoji or ALL CAPS. Buttons start with a verb and name the object. Errors say what happened and how to fix it.
- **Built on NL Design System** (Utrecht CSS components, design tokens), themed per app through `@muliacode/ui`.
- **No logo yet.** The name is set as text.
- **Licence:** EUPL-1.2.

## Evidence on Hand

- Product, requirements and technical design documents in `docs/product/`.
- A design system with tokens, the Abonemen theme, component guidelines and icons in `docs/design/design-system/`.
- 70 wireframes covering every screen and its states in `docs/design/wireframes/`.
- No users, testimonials, audits, benchmarks or install numbers exist yet. Abonemen is new and unproven; future work must not invent any of these.

## Product Principles

In priority order, used to settle trade-offs:

1. **Private by default.** Collect the minimum, encrypt what matters, call nothing outside unless the user turns it on.
2. **Accessible by construction.** WCAG 2.2 AA is a release gate. If a pattern cannot be made accessible, it does not ship.
3. **Calm over clever.** The default screen answers three questions: what is due soon, what does it cost me per month, what should I act on.
4. **Fast to capture.** Adding a subscription takes under 30 seconds; detail is optional.
5. **Honest numbers.** Every total shows its currency, period and exchange-rate date. No silent rounding or hidden conversions.
6. **Yours to leave.** Full export in open formats at any time.

## Accessibility & Inclusion

WCAG 2.2 AA on every screen, in every theme (light, dark, high contrast) and language, which also covers the WCAG-derived web clauses of EN 301 549. Every chart has a data-table alternative, every drag action has a button alternative, and colour alone never carries meaning. Reflow at 320px wide, 44px minimum targets, no time limits, `forced-colors` respected, reduced motion honoured. CI enforces zero axe violations; the developer tests with NVDA, TalkBack, JAWS, keyboard only, 400% zoom and Windows high contrast. A public accessibility statement lists any known gaps.
