# SubscriptionItem

One subscription in a list: logo, name, status, next payment and price. Abonemen component (`mc-sub`), using Utrecht Status badge.

- **The consumer provides:** name, logo or initials, price with currency and cycle, the converted monthly amount when the currency differs, next payment, status and the detail URL.
- **Rules:** the name is the only link (one tab stop per item); price with tabular figures; a converted amount shows "≈"; items sit in a `ul`.
