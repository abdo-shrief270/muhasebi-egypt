#!/bin/sh
# The backup service's main process: one backup every day at BACKUP_TIME (HH:MM, in TZ),
# plus one right away when the newest dump is more than a day old (first start, server was off).
set -eu

dir="${BACKUP_DIR:-/backups}"
at="${BACKUP_TIME:-03:00}"

log() { echo "$(date -Iseconds) $*"; }

case "$at" in
  [01][0-9]:[0-5][0-9]|2[0-3]:[0-5][0-9]) ;;
  *) log "BACKUP_TIME must be HH:MM (got '$at')"; exit 2 ;;
esac

run() {
  # A failed backup is logged and retried at the next slot; the healthcheck turns red meanwhile.
  muhasebi-backup || log "backup FAILED (exit $?)"
}

log "daily backups at ${at} (${TZ:-UTC}), keeping ${BACKUP_KEEP_DAYS:-14} days in ${dir}${BACKUP_RCLONE_REMOTE:+, off-site to ${BACKUP_RCLONE_REMOTE}}"

mkdir -p "$dir"
if [ -z "$(find "$dir" -maxdepth 1 -name 'muhasebi-*.dump' -type f -mmin -1440 | head -n 1)" ]; then
  log "no backup from the last 24 hours, taking one now"
  run
fi

# Stop promptly on `docker compose stop` (sleep runs in the background so the trap fires).
trap 'exit 0' TERM INT

last=""
while true; do
  today="$(date +%F)"
  if [ "$(date +%H:%M)" = "$at" ] && [ "$last" != "$today" ]; then
    last="$today"
    run
  fi
  sleep 20 &
  wait $!
done
