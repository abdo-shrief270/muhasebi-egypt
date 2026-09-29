#!/usr/bin/env bash
# Take a database backup now. Daily backups run by themselves in the `backup` service
# (compose.yml, docs/DEPLOY.md): same script, same folder (backups/), same retention and off-site copy.
set -euo pipefail
cd "$(dirname "$0")"

[[ -f .env ]] || { echo "No .env — run ./init.sh <domain> <email> first." >&2; exit 1; }

if [[ -n "$(docker compose ps --status running -q backup 2>/dev/null)" ]]; then
  docker compose exec -T backup muhasebi-backup
else
  docker compose run --rm -T backup muhasebi-backup
fi
