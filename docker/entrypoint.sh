#!/bin/sh
# SPIKE (#14): prepares /data, then runs the web server, the scheduler and the
# queue worker side by side. If any of them stops, the others are stopped too
# and the container exits, so Docker or Kubernetes restarts it whole.
set -eu

mkdir -p \
    /data/storage/app/private \
    /data/storage/framework/cache/data \
    /data/storage/framework/sessions \
    /data/storage/framework/views \
    /data/storage/logs \
    /data/caddy \
    /tmp/caddy-config \
    /tmp/laravel
[ -f "$DB_DATABASE" ] || touch "$DB_DATABASE"

php artisan migrate --force --no-interaction
php artisan config:cache
php artisan view:cache

frankenphp run --config /etc/frankenphp/Caddyfile &
web=$!
php artisan schedule:work &
scheduler=$!
php artisan queue:work --sleep=3 --tries=3 --max-time=3600 &
queue=$!

stop() {
    kill -TERM "$web" "$scheduler" "$queue" 2>/dev/null || true
    wait
}
trap 'stop; exit 0' TERM INT

# Supervise: exit as soon as one process is gone.
while kill -0 "$web" 2>/dev/null && kill -0 "$scheduler" 2>/dev/null && kill -0 "$queue" 2>/dev/null; do
    sleep 2
done

echo "abonemen: a process stopped; stopping the container" >&2
stop
exit 1
