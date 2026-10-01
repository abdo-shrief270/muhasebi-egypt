#!/usr/bin/env bash
# Restore a backup made by backup.sh:
#   ./restore.sh backups/muhasebi-YYYYmmdd-HHMMSS.dump [backups/muhasebi-YYYYmmdd-HHMMSS-files.tar.gz]
# The files archive of the same backup is picked up by itself when it sits next to the dump.
# Files are extracted over the current ones (newer files are left alone).
set -euo pipefail
cd "$(dirname "$0")"
set -a
# shellcheck source=/dev/null
source .env
set +a

file="${1:?usage: ./restore.sh <dump file> [files archive]}"
files="${2:-${file%.dump}-files.tar.gz}"
[[ -f "$file" ]] || { echo "No such dump: $file" >&2; exit 1; }
if [[ -f "$files" ]]; then
  echo "Files: $files"
else
  echo "No files archive ($files): only the database will be restored."
  files=""
fi
read -r -p "This REPLACES the current database with ${file}. Type 'restore' to continue: " answer
[[ "$answer" == "restore" ]] || exit 1

docker compose stop api worker scheduler reverb backup
docker compose exec -T pgsql pg_restore -U "$DB_USERNAME" -d "$DB_DATABASE" --clean --if-exists --no-owner < "$file"
if [[ -n "$files" ]]; then
  docker compose run --rm -T --no-deps --entrypoint sh api -c 'mkdir -p /app/storage/app && tar -xzpf - -C /app/storage/app' < "$files"
  echo "Files restored."
fi
docker compose up -d
