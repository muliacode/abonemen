# Spike 4: cron mode on a real shared host

Issue: [#15](https://github.com/muliacode/abonemen/issues/15). Requirement: NFR-07. Decision: ADR 7.

**Outcome so far: the kit is ready and passes a dry run; the real-host run is still to do.** The design's cron mode drains 100 jobs within one cron run in a bare `php:8.3-apache` container, and the server check spots what such a host lacks. Whether a real shared host passes depends on its PHP build, which only running the kit there can show. The kit is on the [`spike/15-shared-hosting`](https://github.com/muliacode/abonemen/tree/spike/15-shared-hosting) branch and is not merged; its [README](https://github.com/muliacode/abonemen/blob/spike/15-shared-hosting/spike/README.md) has the install steps.

## The kit

| Part | What it does |
| --- | --- |
| Server check | `php artisan spike:server-check`, and the same table on a page at `/spike/check?token=…` (404 without the token). It checks PHP ≥ 8.3, the required extensions (ctype, curl, dom, fileinfo, filter, gd, intl, mbstring, openssl, pdo_sqlite, session, sodium, tokenizer, xml, zip) and the recommended ones (bcmath, OPcache, pcntl), Argon2id by hashing and verifying, libsodium by an XChaCha20-Poly1305 round trip and a sealed box, an AES-256 zip by writing and reading one, SQLite ≥ 3.35 with WAL and JSON, writable folders, memory and time limits, disabled functions, and outbound HTTPS to the exchange-rate API. The page also checks that `public/` is the document root |
| Cron mode | With `ABONEMEN_RUN_MODE=cron`, one `* * * * * php artisan schedule:run` entry records a heartbeat, then runs `queue:work --stop-when-empty --max-time=50 --tries=3` under `withoutOverlapping`, as the design describes |
| Queue test | `spike:queue-fill` queues 100 jobs of 0.5 s each, about the time one reminder email takes. `spike:queue-report` (or the page) shows how many are done, each cron run with the jobs waiting at its start and the PHP version cron used |
| Upload | `spike/make-zip.sh` builds a zip with production `vendor/` for hosts without SSH or Composer |
| Dry run | `.github/workflows/spike-shared-hosting.yml` runs all of it in the stock `php:8.3-apache` image with `public/` as document root |

## Results

### Dry run in `php:8.3-apache`

- **Server check:** PHP 8.3.35, Argon2id, libsodium 1.0.18 and SQLite 3.46.1 with WAL and JSON pass. It reports `gd`, `intl` and `zip` as missing (so no AES-256 zip), and `bcmath` and `pcntl` as recommended but missing. OPcache is off.
- **Document root:** with `public/` as the document root, `/.env` returns 404 and `/../.env` is rejected by Apache (400).
- **Queue:** 100 jobs of 0.5 s drained in the first `schedule:run`, which took 51 s. The next two runs found an empty queue and took 3 s each.

### Real host

To fill in after running the kit, following its README:

| | Result |
| --- | --- |
| Host and plan | |
| Web PHP (version, SAPI) | |
| Cron PHP (version, path used in the cron entry) | |
| Server check, web | |
| Server check, CLI | |
| Document root can be set to `public/`; `.env` unreachable | |
| Cron runs until all 100 jobs were done | |
| Limits hit (CPU time, process count, cron interval) | |

### Done when

- [ ] **100 jobs drain within 3 cron runs** on a real host. The dry run drains them in 1.
- [ ] **Missing extensions are listed** for that host. The check does this; the dry run lists `gd`, `intl` and `zip`.
- [x] **Kit and outcome written.**

## Findings

- **100 jobs of 0.5 s is one run's capacity.** `--max-time=50` lets one run work 50 s, which is exactly 100 such jobs; the dry run's first run took 51 s. Anything that sends more than about 100 emails a minute spills into the next run, which is fine for households but should be noted in the admin docs.
- **`schedule:run` runs its tasks one after another.** A drain that takes 50 s delays every task due after it in the same minute. The real scheduler should start the drain with `runInBackground()`, or register it last, so reminders and backups start on time.
- **A stock PHP build lacks `intl`, `gd` and `zip`.** Most shared hosts enable them, but the first-run wizard (ACC-01, #25) must check for them, with the same list as the kit, and say which to switch on in the host's PHP selector.
- **AES-256 zip support depends on the host's libzip.** If a real host has `zip` without encryption, backups need another format, for example a libsodium `secretstream` file around the plain zip. Decide this only if the real-host run shows the problem.
- **Cron's PHP can differ from the web's.** On many hosts `php` in cron is an older default version; the kit records cron's version so the install docs (#97) can tell people which full path to use.
- **OPcache was off in the stock image.** The check reports it, and the admin screen should warn about it, since it costs most of the response-time budget (NFR-02).

## Not covered

The release zip (#97), upgrades (#99) and backups (#138) on shared hosting, and hosts that cannot set the document root, which the design does not support.
