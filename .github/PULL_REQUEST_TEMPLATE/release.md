<!-- abonemen:release-checklist -->
<!--
Release pull request. The "Release checklist" check fails while any box below is unticked, so the release cannot be merged.
Tick a box only after doing the check on this release candidate. If an item does not pass, open an issue and fix it before releasing.
Do not remove the first line of this template: the check uses it to recognise a release.
-->

# Release vX.Y.Z

## Release notes

<!-- What changed, breaking changes, upgrade notes and credits. -->

## Manual accessibility checks (A11Y-03, UX-11)

Walk through every user journey from the requirements on the release candidate. Note the browser, assistive technology and versions you used.

- [ ] **Keyboard only**: every action works without a mouse; focus is always visible and never trapped; the skip link works
- [ ] **NVDA** with Firefox or Chrome on Windows: names, roles, states, errors and page-change announcements are read correctly
- [ ] **TalkBack** with Chrome on Android: the same, with touch gestures
- [ ] **200% zoom**: nothing is cut off or overlaps
- [ ] **400% zoom** (320 CSS px): content reflows into one column with no horizontal scrolling and no loss of content or function
- [ ] **Windows high contrast** (a contrast theme in forced-colours mode): borders, focus rings, icons and states stay visible
- [ ] **Text spacing** (WCAG 1.4.12 bookmarklet or extension): no text is cut off or overlaps

Tested with:

<!-- For example: NVDA 2026.2 + Firefox 140 on Windows 11; TalkBack 15 + Chrome on Android 16 -->

## Release

- [ ] The release notes above are complete, including breaking changes and upgrade steps
- [ ] `docs/` matches the behaviour of this release
