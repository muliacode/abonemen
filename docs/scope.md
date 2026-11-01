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
- A minimal Instance Admin: first-run setup, open or closed registration (closed by default), Household and User administration, and a connections page.
- The email connection and its two-level switch (Instance Admin makes it available, an Owner turns it on for the Household).
- Subscriptions with every status, Price changes, Commitments, Categories and Payment methods.
- Dashboard and list, email Reminders to the Holder and Owners, totals grouped by currency.
- JSON and CSV export.

**1.0**
- The Contributor role, Visibility, Follows, Recorded mode and Tags.
- Calendar and personal calendar feed, statistics and Budgets.
- Exchange rates, web push, passkeys, TOTP and OIDC.
- Activity log, and account and Household deletion.
- API tokens, each bound to one User in one Household with read or write scope.
- Logos: upload, and the website icon fetched as a connection.
- ntfy as a notification channel, alongside email and web push.
- Platform-wide encrypted backup and restore, first-run key setup with a forced recovery-key step, and password-reset mode.

**After 1.0**
- Custom roles per Household (including renaming Owner), built on the scoped Permissions defined in 1.0.
- A module system, importers, the hosted service and zero-knowledge mode.
- Telegram and Discord notification channels, and logo search (SearXNG, SerpApi), as modules.
