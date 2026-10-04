#!/usr/bin/env bash
# Watches the running stack from the host (cron, every 2 minutes): the API's health check (database,
# cache, queue workers, scheduler, disk) and the containers. Sends a Telegram message when something
# goes wrong and again when it's back — once per change, never every run. When the whole server is
# down this can't report it: pair it with an outside uptime monitor on https://<domain>/api/v1/health.
#
# Usage: ./monitor.sh [--quiet]     (setup-monitoring.sh installs the cron)
set -euo pipefail
cd "$(dirname "$0")"

QUIET=0
if [[ "${1:-}" == "--quiet" ]]; then QUIET=1; fi
say() { if [[ $QUIET == 0 ]]; then printf '%s\n' "$*"; fi; }

[[ -f .env ]] || { echo "No .env here." >&2; exit 1; }
env_get() { grep -E "^$1=" .env | tail -n1 | cut -d= -f2- | sed 's/^"\(.*\)"$/\1/' || true; }

TOKEN=$(env_get TELEGRAM_BOT_TOKEN)
CHAT=$(env_get TELEGRAM_CHAT_ID)
DOMAIN=$(env_get DOMAIN)
STATE=${MONITOR_STATE:-/var/tmp/muhasebi-monitor.state}

send() {
  if [[ -z "$TOKEN" || -z "$CHAT" ]]; then
    say "(no Telegram set up) $1"
    return 0
  fi
  curl -fsS --max-time 10 -o /dev/null "https://api.telegram.org/bot${TOKEN}/sendMessage" \
    --data-urlencode "chat_id=${CHAT}" --data-urlencode "text=[محاسبي ${DOMAIN}] $1" || true
}

problems=()

# The app's own checks (database, cache, queue workers, scheduler, disk), from inside it.
if ! check=$(docker compose exec -T api php artisan monitoring:check 2>&1); then
  failing=$(grep '^✗' <<<"$check" | sed 's/^✗ //' | paste -sd ';' - || true)
  problems+=("التطبيق: ${failing:-مش بيرد}")
fi

# The way in: HTTPS on this server's port 443 (Caddy, or nginx in front of it), as the shops see it.
code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 --resolve "${DOMAIN}:443:127.0.0.1" "https://${DOMAIN}/up" || true)
if [[ "$code" != "200" ]]; then
  problems+=("https://${DOMAIN}/up رد بـ ${code:-مفيش رد}")
fi

# Containers that stopped or are unhealthy (the backup one turns unhealthy when the last dump is old).
bad=$(docker compose ps --all --format '{{.Service}} {{.State}} {{.Health}}' 2>/dev/null | awk '$2 != "running" || $3 == "unhealthy" { print $1 " (" $2 ($3 != "" ? ", " $3 : "") ")" }' | paste -sd '،' - || true)
if [[ -n "$bad" ]]; then
  problems+=("حاويات: ${bad}")
fi

now=$(printf '%s\n' "${problems[@]:-}" | sed '/^$/d' | sort)
before=$(cat "$STATE" 2>/dev/null || true)
if [[ "$now" != "$before" ]]; then
  if [[ -n "$now" ]]; then
    send "🔴 فيه مشكلة:
${now}"
  else
    send "✅ كله رجع شغال."
  fi
  printf '%s' "$now" > "$STATE"
fi

if [[ -n "$now" ]]; then
  say "$now"
  exit 1
fi
say "OK"
