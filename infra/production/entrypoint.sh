#!/bin/sh
# Runs before every PHP role (api, worker, scheduler, reverb).
set -e

mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs

# Cache config/routes/events/views from the real environment of this container.
php artisan optimize --quiet

exec "$@"
