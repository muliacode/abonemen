# SkipLink

The skip link is the first focusable element on every page and jumps to the main content. Utrecht Skip link (`utrecht-skip-link utrecht-skip-link--visible-on-focus`), shown here visible.

- **The consumer provides:** nothing; the app shell renders it with `href="#main"`.
- **Rules:** hidden until focused; the target `main` element has `tabindex="-1"`.
