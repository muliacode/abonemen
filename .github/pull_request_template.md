<!--
Thanks for contributing! Keep the pull request focused on one issue.
Preparing a release? Use the release template instead: add ?template=release.md to the URL of the "Open a pull request" page.
-->

Closes #

## What changed

<!-- What does this change do, and why? Mention requirement IDs (such as SUB-03) and decisions a reviewer should check. -->

## How I tested it

<!-- Commands you ran, and what you checked by hand. -->

## Checklist

- [ ] `composer ci:check` passes locally
- [ ] New behaviour has tests; a bug fix starts with a test that failed before the fix
- [ ] Documents in `docs/` are updated if this changes behaviour they describe
- [ ] No new dependency, or its licence can be combined with the EUPL-1.2

### Accessibility (for changes to the interface)

<!-- Leave this section out if nothing visible changed. -->

- [ ] Uses `@muliacode/ui` components rather than new ones
- [ ] Walked through with the keyboard only: everything reachable, focus visible, logical order
- [ ] Walked through with a screen reader (UX-11; say which one)
- [ ] Works at 320 px width and 400% zoom
- [ ] Status is never conveyed by colour alone
