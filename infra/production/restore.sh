#!/usr/bin/env bash
# Restore a backup made by backup.sh:  ./restore.sh backups/muhasebi-YYYYmmdd-HHMMSS.dump
set -euo pipefail
cd "$(dirname "$0")"
set -a; source .env; set +a

file="${1:?usage: ./restore.sh <dump file>}"
read -r -p "This REPLACES the current database with ${file}. Type 'restore' to continue: " answer
[[ "$answer" == "restore" ]] || exit 1

docker compose stop api worker scheduler reverb
docker compose exec -T pgsql pg_restore -U "$DB_USERNAME" -d "$DB_DATABASE" --clean --if-exists --no-owner < "$file"
docker compose up -d
