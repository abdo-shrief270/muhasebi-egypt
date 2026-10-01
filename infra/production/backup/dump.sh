#!/bin/sh
# One backup, two files with the same timestamp:
#   /backups/muhasebi-YYYYmmdd-HHMMSS.dump            pg_dump of the database
#   /backups/muhasebi-YYYYmmdd-HHMMSS-files.tar.gz    the app's stored files (used-device photos,
#                                                      payment proofs, feedback screenshots…)
# then drop local backups older than BACKUP_KEEP_DAYS, and copy both off the server when
# BACKUP_RCLONE_REMOTE is set. Runs inside the backup container (see Dockerfile.backup); PG*
# variables come from compose, the files are the api's storage volume mounted read-only.
set -eu

dir="${BACKUP_DIR:-/backups}"
files_src="${BACKUP_FILES_DIR:-/storage/app}"
with_files="${BACKUP_FILES:-1}"
keep_days="${BACKUP_KEEP_DAYS:-14}"
remote="${BACKUP_RCLONE_REMOTE:-}"
remote_keep_days="${BACKUP_REMOTE_KEEP_DAYS:-$keep_days}"

log() { echo "$(date -Iseconds) $*"; }

case "$keep_days" in
  ''|*[!0-9]*|0) log "BACKUP_KEEP_DAYS must be a whole number of days, 1 or more (got '$keep_days')"; exit 2 ;;
esac

mkdir -p "$dir"
# Leftovers of a backup that was cut off (container stopped mid-way).
find "$dir" -maxdepth 1 -name '.muhasebi-*.partial' -type f -mmin +60 -delete
base="muhasebi-$(date +%Y%m%d-%H%M%S)"
name="$base.dump"
files="$base-files.tar.gz"
tmp="$dir/.$name.partial"
ftmp="$dir/.$files.partial"
trap 'rm -f "$tmp" "$ftmp"' EXIT

# Custom format (-Fc): compressed, and restore.sh can pg_restore it with --clean.
pg_dump --format=custom --no-owner --file="$tmp"
# A dump that pg_restore can't read is not a backup.
pg_restore --list "$tmp" > /dev/null
mv "$tmp" "$dir/$name"
log "backup ok: $name ($(du -h "$dir/$name" | cut -f1))"

# The files the database points at. Encrypted ones (ID photos) need the same APP_KEY to be read
# back, which lives in .env: keep a copy of .env somewhere safe too.
if [ "$with_files" != "0" ]; then
  if [ -d "$files_src" ]; then
    tar -czf "$ftmp" -C "$files_src" .
    # An archive that doesn't list is not a backup.
    tar -tzf "$ftmp" > /dev/null
    mv "$ftmp" "$dir/$files"
    log "files ok: $files ($(du -h "$dir/$files" | cut -f1))"
  else
    log "warning: no stored files at $files_src (is the storage volume mounted?) — database only"
    files=""
  fi
else
  files=""
fi
trap - EXIT

# Keep the last N days here (always keeping the newest backup).
find "$dir" -maxdepth 1 \( -name 'muhasebi-*.dump' -o -name 'muhasebi-*-files.tar.gz' \) -type f -mmin +"$((keep_days * 1440))" \
  ! -name "$name" ! -name "${files:-$name}" -print -delete \
  | sed 's|.*/|removed old backup: |'

if [ -n "$remote" ]; then
  rclone copyto --no-traverse "$dir/$name" "$remote/$name"
  log "copied off-site: $remote/$name"
  if [ -n "$files" ]; then
    rclone copyto --no-traverse "$dir/$files" "$remote/$files"
    log "copied off-site: $remote/$files"
  fi
  if [ "$remote_keep_days" -gt 0 ] 2>/dev/null; then
    rclone delete --min-age "${remote_keep_days}d" --include 'muhasebi-*.dump' --include 'muhasebi-*-files.tar.gz' "$remote" \
      || log "warning: could not prune old off-site backups"
  fi
fi

date -Iseconds > "$dir/.last-success"
