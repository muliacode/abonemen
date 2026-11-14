# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

A **Household** member who wants one calm, shared place to see what renews when, what it costs per month and what needs action. They are not technical. The Household is mixed: a parent who runs the money (Owner), a partner or adult child who manages their own Subscriptions (Editor, Contributor), and a child or older relative who can only look (Viewer), possibly with low vision. Their job is a quick check: what is coming, what does it cost, is anything about to renew that I meant to cancel.

The **Instance Admin** is a technical self-hoster. They meet the interface mainly at first-run and administration. Those screens get no look of their own: they inherit the Household look.

## Product Purpose

Households pay for more recurring services than they can name. Unwanted renewals, missed cancellation windows and trials that silently convert to paid plans cost real money. Abonemen lets a Household see what recurring services it pays for, when, and why, without touching a bank account.

Success is a Household member who opens the dashboard, understands in seconds what renews next and what needs action, and closes it again.

## Positioning

A calm, shared household view with no bank link, no telemetry, protected storage and self-hosting, accessible by standard. Bank-linked apps need transactions, SaaS trackers keep data in a vendor's cloud, spreadsheets have no reminders, and other self-hosted trackers store plaintext and rarely treat accessibility as a goal. The product has to look and read like privacy and calm, not like a finance dashboard.

## Operating Context

Self-hosted and open source (Laravel). A platform has one or many Households. People use it at a desk or on a phone, in short visits, and receive email Reminders. The interface is available in English, Dutch, Indonesian, French and German, so layouts must take text of different lengths.

## Capabilities and Constraints

- Built on Laravel, Livewire, Livewire Flux and Tailwind 4. Flux stays the component base: the look comes from tokens, type, spacing, `app.css` and the Flux view overrides in `resources/views/flux`, not from replacing components.
- Vocabulary is fixed by `GLOSSARY.md` (Household, Member, User, Subscription, Holder, Monthly equivalent, Needs action, and so on). Interface text avoids the glossary's "avoid" terms.
- Money always shows amount, currency code and period (#34). Totals are grouped by currency.
- No bank, card or transaction data of any kind. No telemetry, analytics or third-party calls by default.
- Release tiers and exclusions are in `docs/scope.md`.

## Brand Commitments

- The name is Abonemen.
- The line on the welcome page is the current statement of purpose: "Know exactly what you pay for, when, and why, without giving up your privacy."
- The app's mark, favicon and touch icon are still Laravel's starter-kit shapes and are to be replaced with an Abonemen mark (#52). Logos of Subscriptions are a separate ticket (#46).

## Evidence on Hand

No testimonials, customers, benchmarks or screenshots of real use exist, and none may be invented. Real content lives in `docs/scope.md`, `GLOSSARY.md`, the ADRs and the product requirements issue (#2).

## Product Principles

1. **Calm over urgent.** The dashboard answers "what needs action" and nothing else. Nothing shouts unless money is about to leave.
2. **Private by construction.** The interface never implies a link to a bank or a vendor's cloud, and never asks for more than a Household needs.
3. **Everyone in the Household can use it.** A child, an older relative and a person with low vision are first-class users, not edge cases.
4. **Say what it costs, plainly.** Amount, currency code and period are always together; no figure is shown without its meaning.
5. **One vocabulary.** The words of `GLOSSARY.md`, in every language, on every screen.

## Accessibility & Inclusion

WCAG 2.2 level AA in the light and the dark theme, as set out in `docs/accessibility.md`: keyboard operable, visible focus, reflow at 400% zoom, touch targets of 44px on touch screens, nothing conveyed by colour alone. The colour roles are checked by `tests/Unit/ColourTokensTest.php`; the look must keep passing it. A high-contrast theme and per-User theme choice belong to #34.
