# Spike 3: FrankenPHP image on amd64 and arm64

Issue: [#14](https://github.com/muliacode/abonemen/issues/14). Decision: ADR 10.

**Outcome: ADR 10 is accepted.** One FrankenPHP image builds natively on amd64 and arm64, stays under 200 MB, and runs as a non-root user with a read-only root file system and every Linux capability dropped. The proof of concept is on the [`spike/14-frankenphp`](https://github.com/muliacode/abonemen/tree/spike/14-frankenphp) branch and is not merged; #98 builds the real image.

## What was built

| Part | How |
| --- | --- |
| Build | Multi-stage `Dockerfile`. The official `dunglas/frankenphp:1.13.0-php8.4-alpine` image adds `intl`, `gd`, `zip`, `pcntl` and `bcmath`; separate stages install Composer's production dependencies and build the front end. The runtime stage is plain `alpine:3.24` with FrankenPHP, `libphp`, the extensions and only the shared libraries they link against, found with `scanelf` |
| User | uid and gid 10001, listening on :8080, so no capability is needed to bind a port |
| File system | Read-only root. All state is in the `/data` volume (SQLite database, `storage/` through a symlink, Caddy's data); config and Caddy's autosave go to a `/tmp` tmpfs. Routes and events are cached at build time, config and views at start |
| Processes | The entrypoint runs migrations, then FrankenPHP, `schedule:work` and `queue:work`. It checks all three every 2 seconds and stops the container when one exits, so Docker or Kubernetes restarts it whole. `SIGTERM` stops all three cleanly |
| Health | `HEALTHCHECK` on Laravel's `/up` |
| CI | `.github/workflows/spike-container.yml` builds on `ubuntu-24.04` and `ubuntu-24.04-arm`. It measures the image and runs it with `--read-only --tmpfs /tmp --cap-drop ALL --security-opt no-new-privileges`. It then checks the three processes and the uid, requests `/up`, `/` and a built asset, shows that `/app`, `/usr/local/bin` and `/etc` cannot be written, runs a queued job, and stops the container |

## Results

### Done when

- [x] **The image builds on amd64 and arm64**, each natively, in under two minutes without a cache.
- [x] **Under 200 MB** on both.
- [x] **Runs as non-root with a read-only root file system**, with all capabilities dropped and no new privileges. The web server, scheduler and queue worker all run, and a queued job completes.
- [x] **Outcome written.**

### Size and build

| | amd64 | arm64 |
| --- | --- | --- |
| Uncompressed (`docker image inspect`) | 186.8 MB | 179.5 MB |
| Compressed (`docker save`, gzip -6) | 58.2 MB | 55.5 MB |
| Build without cache | 101 s | 84 s |

Both run PHP 8.4.26 with Argon2id, AES-256 zip support, `intl`, `sodium` and OPcache, and both stop with exit code 0 on `docker stop`. The official FrankenPHP image is not used as the base: it carries PHP's build toolchain, which would add about 100 MB.

## Findings

- **The official FrankenPHP binary has a file capability** (`cap_net_bind_service=ep`), so that it can bind port 80 as a normal user. Under `--cap-drop ALL` with `no-new-privileges` the kernel refuses to run it ("Operation not permitted"). Since the image listens on :8080, the build removes the capability with `setcap -r`. #98 must keep this, or a hardened run fails at start.
- **A new named volume takes its owner from the image.** `/data` has to exist in the image and belong to uid 10001; otherwise a fresh volume is owned by root and the app cannot create its database. A bind mount does not get this, so the compose file and docs (#98) should say to `chown 10001` a host folder.
- **Laravel's caches need a writable place.** With a read-only `/app`, `APP_CONFIG_CACHE` and `APP_SERVICES_CACHE` point into `/tmp`, while `packages.php` is built into the image. Config and views are cached at start, because they depend on the environment.
- **Caddy writes its config and certificates.** `XDG_CONFIG_HOME` goes to `/tmp` and `XDG_DATA_HOME` to `/data/caddy`, so automatic certificates survive a restart when Caddy terminates TLS itself.
- **`docker top` needs a PID column.** It fails with custom columns that leave it out; only the CI checks were affected.
- **Closures cannot be queued from `tinker`.** The test queues a named Artisan command instead.

## Not covered

The compose file and release image (#98), the Helm chart (#128), image scanning, the SBOM and signing (#129), and install tests for all three paths (#156).
