# Security policy

Abonemen stores financial details about a household, so security reports are taken seriously. The threat model and the security design are in the [technical design](docs/product/technical-design.md).

## Reporting a vulnerability

Report it privately through GitHub: open the [Security tab](https://github.com/muliacode/abonemen/security) and choose **Report a vulnerability**. Only the maintainers can see the report.

Please do not open a public issue, pull request or discussion for a vulnerability.

A useful report includes:

- what is affected and which version or commit you tested
- steps to reproduce, or a proof of concept
- the impact as you understand it

## What to expect

Abonemen is maintained by one volunteer, so these are targets, not guarantees:

- an acknowledgement within 7 days
- a first assessment within 14 days
- a fix and a coordinated disclosure date agreed with you once the issue is confirmed

Reporters are credited in the release notes unless they prefer to stay anonymous.

## Supported versions

Abonemen has no release yet. Until 1.0, fixes go to the `main` branch. After 1.0, this section will list the supported release lines.

## Scope

In scope: the Abonemen application and its release artefacts, including encryption and key handling, authentication, and the container images.

Out of scope: vulnerabilities in third-party dependencies that are not reachable through Abonemen (report those upstream), and issues that need a compromised server or administrator account to begin with.
