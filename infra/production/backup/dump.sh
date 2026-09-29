#!/bin/sh
# One backup: pg_dump → /backups/muhasebi-YYYYmmdd-HHMMSS.dump, drop local dumps older than
# BACKUP_KEEP_DAYS, and copy it off the server when BACKUP_RCLONE_REMOTE is set.
# Runs inside the backup container (see Dockerfile.backup); PG* variables come from compose.
set -eu

dir="${BACKUP_DIR:-/backups}"
keep_days="${BACKUP_KEEP_DAYS:-14}"
remote="${BACKUP_RCLONE_REMOTE:-}"
remote_keep_days="${BACKUP_REMOTE_KEEP_DAYS:-$keep_days}"

log() { echo "$(date -Iseconds) $*"; }

case "$keep_days" in
  ''|*[!0-9]*|0) log "BACKUP_KEEP_DAYS must be a whole number of days, 1 or more (got '$keep_days')"; exit 2 ;;
esac

mkdir -p "$dir"
# Leftovers of a dump that was cut off (container stopped mid-way).
find "$dir" -maxdepth 1 -name '.muhasebi-*.partial' -type f -mmin +60 -delete
name="muhasebi-$(date +%Y%m%d-%H%M%S).dump"
tmp="$dir/.$name.partial"
trap 'rm -f "$tmp"' EXIT

# Custom format (-Fc): compressed, and restore.sh can pg_restore it with --clean.
pg_dump --format=custom --no-owner --file="$tmp"
# A dump that pg_restore can't read is not a backup.
pg_restore --list "$tmp" > /dev/null
mv "$tmp" "$dir/$name"
trap - EXIT
log "backup ok: $name ($(du -h "$dir/$name" | cut -f1))"

# Keep the last N days here (always keeping at least the newest dump).
find "$dir" -maxdepth 1 -name 'muhasebi-*.dump' -type f -mmin +"$((keep_days * 1440))" ! -name "$name" -print -delete \
  | sed 's|.*/|removed old backup: |'

if [ -n "$remote" ]; then
  rclone copyto --no-traverse "$dir/$name" "$remote/$name"
  log "copied off-site: $remote/$name"
  if [ "$remote_keep_days" -gt 0 ] 2>/dev/null; then
    rclone delete --min-age "${remote_keep_days}d" --include 'muhasebi-*.dump' "$remote" \
      || log "warning: could not prune old off-site backups"
  fi
fi

date -Iseconds > "$dir/.last-success"
