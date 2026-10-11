# Navigation

Primary navigation: a bottom bar under 48rem, a side bar from 48rem, always the same five items. Abonemen component (`mc-nav`); settings sections use `mc-subnav`.

- **Items:** Overview, Subscriptions, Calendar, Statistics, Settings, each with an icon and a visible label.
- **The consumer provides:** the current route; that item gets `aria-current="page"`.
- **Rules:** a `nav` landmark labelled "Main"; the bottom bar never covers focused content (WCAG 2.4.11), so pages reserve its height with `scroll-padding-bottom`.
