# Abonemen

Abonemen is a self-hostable, privacy-first subscription tracker. It shows what you pay for, when it renews and when a trial or cancellation window closes, without ever touching your bank account.

Three things set it apart:

- **Encrypted.** Names, prices, notes, URLs and notification secrets are encrypted at rest with a per-household key, and everything is served over HTTPS only.
- **Accessible.** Every screen targets WCAG 2.2 AA in light, dark and high-contrast themes, built on [NL Design System](https://nldesignsystem.nl/) components.
- **Calm and fast.** Adding a subscription takes under 30 seconds, and every total shows its currency, period and exchange-rate date.

The name is the Indonesian word for subscription, borrowed from the Dutch *abonnement*.

## Status

Abonemen is under construction. The product, technical and design documents are written and the Laravel application is scaffolded, but there are no features yet. Nothing here is ready to install.

## Documentation

All documents live in [`docs/`](docs/README.md):

| Document | What it covers |
| --- | --- |
| [Product concept](docs/product/concept.md) | Why Abonemen exists, who it is for, principles and decisions |
| [Product requirements](docs/product/requirements.md) | What 1.0 must do, by requirement ID, with acceptance criteria |
| [Technical design](docs/product/technical-design.md) | Architecture, encryption and keys, data model, security, deployment, testing |
| [Design system](docs/design/design-system/README.md) | Brand book, tokens, the Abonemen theme, components and icons |
| [Wireframes](docs/design/wireframes/README.md) | Every screen and its states, plus the screen map |

## Built with

Laravel 13, Vue 3 and Inertia 3 with TypeScript and Vite, on PHP 8.3 or newer. See the [technical design](docs/product/technical-design.md) for the full stack.

## Development

You need PHP 8.3 or newer, Composer and the Node version in [`.nvmrc`](.nvmrc).

```sh
composer setup      # install dependencies, create .env, migrate, build assets
composer dev        # run the server, queue worker and Vite
composer ci:check   # the checks CI runs: lint, types and tests
```

## Contributing and security

Abonemen is built by a volunteer, and help is welcome. Read [CONTRIBUTING.md](CONTRIBUTING.md) first. Everyone taking part is expected to follow the [Code of Conduct](CODE_OF_CONDUCT.md).

To report a vulnerability, follow [SECURITY.md](SECURITY.md). Please do not open a public issue for it.

## Licence

Licensed under the EUPL, version 1.2. See [LICENSE](LICENSE) for the text.

The EUPL is a copyleft licence written for EU law. It treats offering the software over a network as distribution, so hosted forks stay open.
