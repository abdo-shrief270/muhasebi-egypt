#!/usr/bin/env bash
# Alerts to the platform team on Telegram: server errors (from the app itself) and outages (monitor.sh
# every 2 minutes from cron). Asks for the bot token and chat id, writes them to .env, restarts the
# app, sends a test message and installs the cron. Safe to run again.
#
# Usage: ./setup-monitoring.sh
set -euo pipefail
cd "$(dirname "$0")"

bold() { printf '\n\033[1m%s\033[0m\n' "$*"; }
ok() { printf '\033[32m✓ %s\033[0m\n' "$*"; }
die() { printf '\033[31m✗ %s\033[0m\n' "$*" >&2; exit 1; }

[[ -f .env ]] || die "No .env here. Run ./init.sh first (see docs/DEPLOY.md)."
[[ -t 0 ]] || die "Run this in a terminal: it asks questions."
SUDO=""
if [[ $EUID -ne 0 ]]; then SUDO="sudo"; fi

env_get() { grep -E "^$1=" .env | tail -n1 | cut -d= -f2- | sed 's/^"\(.*\)"$/\1/' || true; }
env_set() {
  local key=$1 value=$2 tmp
  tmp=$(mktemp)
  if grep -qE "^${key}=" .env; then
    awk -v k="$key" -v v="$value" 'BEGIN { FS = OFS = "=" } $1 == k { print k "=" v; next } { print }' .env > "$tmp"
  else
    cat .env > "$tmp"
    printf '%s=%s\n' "$key" "$value" >> "$tmp"
  fi
  cat "$tmp" > .env
  rm -f "$tmp"
}

bold "1/3  Telegram"
echo "1. In Telegram open @BotFather → /newbot → copy the token it gives you."
echo "2. Send any message to your new bot (or add it to a group), then open:"
echo "   https://api.telegram.org/bot<TOKEN>/getUpdates  and copy \"chat\":{\"id\": …}."
current=$(env_get TELEGRAM_BOT_TOKEN)
read -r -s -p "Bot token${current:+ (Enter = keep the current one)}: " token; echo
token=${token:-$current}
[[ "$token" =~ ^[0-9]+:[A-Za-z0-9_-]+$ ]] || die "That doesn't look like a bot token."
current_chat=$(env_get TELEGRAM_CHAT_ID)
read -r -p "Chat id${current_chat:+ [$current_chat]}: " chat
chat=${chat:-$current_chat}
[[ "$chat" =~ ^-?[0-9]+$ ]] || die "The chat id is a number (groups start with -)."
cp .env ".env.bak.$(date +%Y%m%d%H%M%S)"
env_set TELEGRAM_BOT_TOKEN "$token"
env_set TELEGRAM_CHAT_ID "$chat"
chmod 600 .env
ok ".env updated (old copy kept as .env.bak.*)"

bold "2/3  Restart and test"
docker compose up -d >/dev/null
echo "Waiting for the app to come up (queue and scheduler heartbeats take up to a minute)…"
for _ in $(seq 1 40); do
  docker compose exec -T api php artisan monitoring:check >/dev/null 2>&1 && break
  sleep 3
done
docker compose exec -T api php artisan list monitoring >/dev/null 2>&1 \
  || die "This server runs an older version without monitoring. Deploy first (./deploy.sh), then run this again."
docker compose exec -T api php artisan monitoring:check --test \
  || die "The test message didn't go through (the reason is above). Fix it and run ./setup-monitoring.sh again."
ok "Check Telegram for «تنبيهات محاسبي شغالة»."

bold "3/3  Watching from cron"
echo "*/2 * * * * root $(pwd)/monitor.sh --quiet >/dev/null 2>&1" | $SUDO tee /etc/cron.d/muhasebi-monitor >/dev/null
ok "cron: /etc/cron.d/muhasebi-monitor (every 2 minutes; a message only when something changes)"
./monitor.sh || true

bold "Done"
echo "If the whole server goes down nothing here can tell you: add a free outside monitor"
echo "(UptimeRobot / Better Stack) on https://$(env_get DOMAIN)/api/v1/health — alert when it isn't 200."
