#!/usr/bin/env bash
# Build and (re)start the stack with the latest code. Safe to run again for every update.
set -euo pipefail
cd "$(dirname "$0")"

[[ -f .env ]] || { echo "No .env — run ./init.sh <domain> <email> first." >&2; exit 1; }

if git -C ../.. rev-parse --git-dir >/dev/null 2>&1 && [[ "${SKIP_PULL:-0}" != "1" ]]; then
  git -C ../.. pull --ff-only
fi

# The web app sends this with feedback and error reports (NUXT_PUBLIC_APP_VERSION).
APP_VERSION="$(git -C ../.. rev-parse --short HEAD 2>/dev/null || echo unknown)"
export APP_VERSION
# On a small server the marketplace search (Elasticsearch) and the image builds don't fit in memory
# together: pause it while building; `up -d` below starts it again.
mem_mb=$(awk '/MemTotal/ { print int($2 / 1024) }' /proc/meminfo)
if (( mem_mb < 7500 )) && [[ -n "$(docker compose ps -q search 2>/dev/null)" ]]; then
  echo "==> Pausing search while building (${mem_mb} MB RAM)"
  docker compose stop search
fi
docker compose build --pull
docker compose up -d pgsql redis

# A backup right before the migrations (SKIP_BACKUP=1 to skip). A failure only warns.
if [[ "${SKIP_BACKUP:-0}" != "1" ]]; then
  docker compose run --rm -T backup muhasebi-backup || echo "WARNING: pre-deploy backup failed, continuing." >&2
fi

docker compose run --rm --no-deps api php artisan migrate --force
docker compose up -d --remove-orphans

# Queue workers keep old code in memory until told to restart.
docker compose exec -T worker php artisan horizon:terminate || true

docker image prune -f >/dev/null
docker compose ps
echo "Deployed. Check: https://$(grep '^DOMAIN=' .env | cut -d= -f2)/up"
