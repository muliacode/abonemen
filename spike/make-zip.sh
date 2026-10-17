#!/bin/sh
# SPIKE (#15): builds abonemen-spike.zip for upload to a shared host: the app
# with production dependencies in vendor/, without dev tools, tests or secrets.
set -eu

cd "$(dirname "$0")/.."

composer install --no-dev --optimize-autoloader --no-interaction
rm -f abonemen-spike.zip
zip -qr abonemen-spike.zip . \
    -x '.git/*' '.github/*' 'node_modules/*' 'tests/*' 'docs/*' '.env' \
       'database/*.sqlite*' 'storage/logs/*.log' 'storage/framework/*/*' 'abonemen-spike.zip'

# Put the dev dependencies back for local work.
composer install --no-interaction > /dev/null

echo "Wrote abonemen-spike.zip ($(du -h abonemen-spike.zip | cut -f1))"
