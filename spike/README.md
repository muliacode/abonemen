# Spike 4: shared-hosting test kit

For [#15](https://github.com/muliacode/abonemen/issues/15). This branch is the Abonemen scaffold plus a server check and a cron-mode queue test. Run it on a real shared host, then copy the results into the outcome (`docs/spikes/4-shared-hosting.md` on `main`).

It answers:

- Does the host have PHP 8.3+, the required extensions, Argon2id, libsodium, AES-256 zip support and a recent SQLite with WAL?
- Can the document root point at `public/`, with `.env` unreachable?
- Does one cron entry drain a queue of 100 jobs within 3 cron runs?
- Does cron use the same PHP version as the web server?

## 1. Build the upload

On your own computer, with PHP 8.3 and Composer:

```sh
git clone --branch spike/15-shared-hosting https://github.com/muliacode/abonemen.git abonemen-spike
cd abonemen-spike
sh spike/make-zip.sh        # writes abonemen-spike.zip, with vendor/ and without dev tools
```

No front-end build is needed: the check page is plain HTML.

## 2. Install on the host

1. Upload and unzip `abonemen-spike.zip` **outside** the public web folder, for example `~/abonemen-spike`.
2. Point the (sub)domain's **document root** at `~/abonemen-spike/public`. In cPanel this is under *Domains*; in DirectAdmin under *Domain setup*. If the host cannot change the document root, note that in the outcome; it is the main finding.
3. Copy `.env.example` to `.env` and set:
   - `APP_KEY`: run `php artisan key:generate` if you have SSH; otherwise ask me for one, or generate one with `php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"` on your computer
   - `APP_URL`: the site's address
   - `SPIKE_TOKEN`: a long random string; the check page answers 404 without it
   - `ABONEMEN_RUN_MODE=cron`
4. Make `storage/`, `bootstrap/cache/` and `database/` writable by the web server (usually already the case).

## 3. Run the server check

- **Web:** open `https://your-site/spike/check?token=YOUR_TOKEN` and click *Prepare the database* the first time.
- **SSH, if you have it:** `php artisan spike:server-check`. Cron usually runs this CLI PHP, which can be a different version or have other extensions than the web PHP. Compare the two.
- Also open `https://your-site/.env` and `https://your-site/../.env`: both must fail.

## 4. Set up the one cron entry

In the host's cron settings, every minute:

```text
* * * * * cd ~/abonemen-spike && php artisan schedule:run >> /dev/null 2>&1
```

Some hosts need the full path to PHP, such as `/usr/local/bin/php` or `/opt/cpanel/ea-php83/root/usr/bin/php`. The check page's *Cron-mode queue test* table shows a row for every cron run, with the PHP version cron used.

## 5. Run the queue test

1. On the check page, click *Queue 100 jobs of 0.5 s* (or `php artisan spike:queue-fill`). Each job sleeps half a second, about the time sending one reminder email takes.
2. Wait 3 to 4 minutes, then reload the page (or `php artisan spike:queue-report`).
3. **Pass:** all 100 jobs are done within 3 cron runs. Each run works the queue for up to 50 seconds (`queue:work --stop-when-empty --max-time=50`), so 100 jobs of 0.5 s need one run, two if the host is slow or limits CPU time.

## 6. Report

Copy into the outcome: the host and plan, both server-check tables (web and CLI), the document-root result, the cron table and whether the queue drained in time. Then delete the site: the kit is not a real Abonemen install.

## What a dry run showed

In CI, the kit runs in the stock `php:8.3-apache` image, which is what a basic shared host looks like (see `.github/workflows/spike-shared-hosting.yml`):

- the check lists the missing extensions (`intl`, `gd`, `zip`);
- with `public/` as document root, `/.env` returns 404;
- 100 jobs drain within the first `schedule:run`.
