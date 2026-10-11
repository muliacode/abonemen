# Button

Buttons start an action on this page; links go somewhere else. Utrecht Button (`utrecht-button`), themed by Abonemen.

- **Appearances:** `utrecht-button--primary-action` (one per view), `utrecht-button--secondary-action`, `utrecht-button--subtle`; add the hint `utrecht-button--danger` for destructive actions, always confirmed by a Dialog that names the item.
- **The consumer provides:** a verb-first label ("Add subscription"), the appearance and the action. A link styled as a button uses Utrecht Button link (`utrecht-button-link`).
- **Rules:** at least `target-min` (44px) high; icon-only buttons get an accessible name and a tooltip; prefer an enabled button that explains what is missing over a disabled one; when disabling is unavoidable use `aria-disabled="true"` so it stays focusable.
