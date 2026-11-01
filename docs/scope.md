# Scope

## Out of scope

These are deliberate exclusions, to stop them creeping in.

- One-off purchases and lifetime deals: Abonemen tracks recurring costs only.
- Cost splitting between Members: a Subscription has one Holder (see ADR 0001).
- Bundles, where one Subscription is included in another.
- Bank, card or transaction data of any kind.
- AI recommendations. Revisit only if there is real demand, and then likely only for locally hosted models.

## Release tiers

**MVP**
- Households, Members, Users and Invites, with the roles Owner, Editor and Viewer.
- Subscriptions with every status, Price changes, Commitments, Categories and Payment methods.
- Dashboard and list, email Reminders to the Holder and Owners, totals grouped by currency.
- JSON and CSV export.

**1.0**
- The Contributor role, Visibility, Follows, Recorded mode and Tags.
- Calendar and personal calendar feed, statistics and Budgets.
- Exchange rates, web push, passkeys, TOTP and OIDC.
- Activity log, and account and Household deletion.

**After 1.0**
- Custom roles per Household (including renaming Owner), built on the scoped Permissions defined in 1.0.
- A module system, importers, the hosted service and zero-knowledge mode.
