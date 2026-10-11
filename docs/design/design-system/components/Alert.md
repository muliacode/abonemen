# Alert

An alert tells the user something about the whole page or a recent action. Utrecht Alert (`utrecht-alert`) with `--info`, `--ok`, `--warning` or `--error`.

- **The consumer provides:** the type, a short title in bold and one or two sentences.
- **Rules:** every alert has an icon and a title, so colour is never the only signal; alerts after an action use `role="status"`; only blocking errors use `role="alert"`; a toast may echo an alert but is never the only feedback.
