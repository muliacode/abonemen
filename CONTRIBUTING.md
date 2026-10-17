# Contributing

Abonemen is built by a volunteer with no budget, and contributions are welcome: code, bug reports, translations, accessibility testing and documentation. This guide explains how to take part.

By taking part you agree to follow the [Code of Conduct](CODE_OF_CONDUCT.md).

## Licence of contributions

Abonemen is licensed under the [EUPL-1.2](LICENSE). By submitting a contribution you agree that it is licensed under the EUPL-1.2 as well. There is no separate contributor agreement, and you keep the copyright to what you write.

Only submit work you wrote yourself, or work under a licence that is compatible with the EUPL-1.2 (see the Appendix of the [LICENSE](LICENSE)). Do not copy code from GPL-only or AGPL projects, including other subscription trackers: it cannot be relicensed under the EUPL-1.2. Pull requests that do this will be closed. The same goes for new dependencies: a licence check in CI rejects any whose licence cannot be combined with the EUPL-1.2.

## Reporting a security problem

Do not open a public issue. Follow [SECURITY.md](SECURITY.md).

## Before you start

1. Read the [concept](docs/product/concept.md) and the [requirements](docs/product/requirements.md). Work is tracked as GitHub issues that refer to requirement IDs (such as `SUB-03`) and to screens by their image name in the [wireframes](docs/design/wireframes/README.md).
2. Look for an existing issue. For anything bigger than a small fix, comment on the issue or open one first, so nobody builds something that will not be merged.
3. Pick up one issue at a time, and say so on the issue.

## Workflow

1. Fork the repository and create a branch from `main`. Name it after the issue, for example `feat/42-bulk-actions`.
2. Keep the change small and focused on one issue.
3. Run `composer ci:check` (formatting, linting, types and tests for PHP and the frontend) and any checks listed in the issue. CI runs the same checks, plus PHP coverage, which must stay at 80% or more. A pull request that fails CI is not reviewed until it passes and cannot be merged.
4. Open a pull request that links the issue (`Closes #42`) and says what changed and how you tested it.
5. A maintainer reviews it. Expect questions; this is a volunteer project, so replies can take a few days.

## Standards

These come from the [requirements](docs/product/requirements.md) and apply to every change:

- **Accessibility.** Every screen conforms to WCAG 2.2 AA. Use the [design system](docs/design/design-system/README.md) rather than inventing components. Walk new screens through with the keyboard and a screen reader, and never convey status by colour alone.
- **Privacy.** No telemetry, analytics or third-party assets. Fonts and icons are self-hosted.
- **Encryption.** Sensitive fields are encrypted as described in the [technical design](docs/product/technical-design.md). Do not log or store them in plaintext.
- **Tests.** New behaviour comes with tests, and a bug fix starts with a test that fails.
- **Documentation.** If a change alters behaviour described in `docs/`, update the document in the same pull request.

## Other ways to help

- Test with VoiceOver, JAWS or other assistive technology and report what you find as an issue.
- Review the [wireframes](docs/design/wireframes/README.md) and the requirements and tell us what is missing.
- Translate the interface once translations open up.
