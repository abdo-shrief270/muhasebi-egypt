#!/usr/bin/env bash
# Build and (re)start the stack with the latest code. Safe to run again for every update.
set -euo pipefail
cd "$(dirname "$0")"

[[ -f .env ]] || { echo "No .env — run ./init.sh <domain> <email> first." >&2; exit 1; }

if git -C ../.. rev-parse --git-dir >/dev/null 2>&1 && [[ "${SKIP_PULL:-0}" != "1" ]]; then
  git -C ../.. pull --ff-only
fi

docker compose build --pull
docker compose up -d pgsql redis
docker compose run --rm --no-deps api php artisan migrate --force
docker compose up -d --remove-orphans

# Queue workers keep old code in memory until told to restart.
docker compose exec -T worker php artisan horizon:terminate || true

docker image prune -f >/dev/null
docker compose ps
echo "Deployed. Check: https://$(grep '^DOMAIN=' .env | cut -d= -f2)/up"
