# Calendar

A month of payments as a grid, always with a list twin showing the same data. Abonemen component (`mc-cal`) on a `table`; Utrecht Calendar is a date picker, not an agenda, so it is not used here.

- **The consumer provides:** the month, the days with their payments and links to each subscription.
- **Rules:** a real `table` with weekday headers; each payment is a link with its amount; today is marked in words for screen readers; the list view is the default on phones.
