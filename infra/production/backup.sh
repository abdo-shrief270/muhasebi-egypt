#!/usr/bin/env bash
# Daily database backup (keep 14 days). Cron example (as root, 3am):
#   0 3 * * * /opt/muhasebi/infra/production/backup.sh >> /var/log/muhasebi-backup.log 2>&1
set -euo pipefail
cd "$(dirname "$0")"
set -a; source .env; set +a

mkdir -p backups
file="backups/muhasebi-$(date +%Y%m%d-%H%M%S).dump"

docker compose exec -T pgsql pg_dump -U "$DB_USERNAME" -d "$DB_DATABASE" -Fc > "$file"
find backups -name 'muhasebi-*.dump' -mtime +14 -delete

echo "$(date -Is) backup ok: $file ($(du -h "$file" | cut -f1))"
# Copy it off the server too (e.g. rclone to Cloudflare R2 / Google Drive) — a backup on the same disk isn't enough.
