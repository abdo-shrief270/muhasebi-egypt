#!/usr/bin/env bash
# The marketplace's search (Elasticsearch) on THIS server, next to the app: a capped container no one
# outside can reach. Writes .env (COMPOSE_PROFILES=search, SEARCH_*), sets the kernel setting it
# needs, offers swap on a small machine, starts it and creates the indices. Safe to run again.
# (A bigger shop count later → its own machine with infra/search/setup.sh; only SEARCH_* change.)
#
# Usage: ./setup-search.sh
set -euo pipefail
cd "$(dirname "$0")"

bold() { printf '\n\033[1m%s\033[0m\n' "$*"; }
ok() { printf '\033[32m✓ %s\033[0m\n' "$*"; }
warn() { printf '\033[33m! %s\033[0m\n' "$*"; }
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

bold "1/4  Memory"
ram_mb=$(awk '/MemTotal/ { printf "%d", $2 / 1024 }' /proc/meminfo)
swap_mb=$(awk '/SwapTotal/ { printf "%d", $2 / 1024 }' /proc/meminfo)
echo "RAM: ${ram_mb} MB, swap: ${swap_mb} MB"
if (( ram_mb >= 7500 )); then heap=1g; limit=2g
else heap=512m; limit=1g
fi
current=$(env_get ES_HEAP)
read -r -p "Elasticsearch heap [${current:-$heap}]: " answer
heap=${answer:-${current:-$heap}}
[[ "$heap" =~ ^[0-9]+[mg]$ ]] || die "Write it like 512m or 1g."
current=$(env_get ES_MEM_LIMIT)
read -r -p "Hard memory cap for the container [${current:-$limit}]: " answer
limit=${answer:-${current:-$limit}}
[[ "$limit" =~ ^[0-9]+[mg]$ ]] || die "Write it like 1g or 1536m."
if (( ram_mb < 7500 && swap_mb < 1024 )); then
  warn "On ${ram_mb} MB with no swap a busy moment can kill a container. A 2 GB swap file is a cheap safety net."
  read -r -p "Create /swapfile (2 GB) now? [Y/n] " answer
  if [[ ! "$answer" =~ ^[Nn] ]]; then
    if [[ -f /swapfile ]]; then
      $SUDO swapon /swapfile 2>/dev/null || true
    else
      $SUDO fallocate -l 2G /swapfile
      $SUDO chmod 600 /swapfile
      $SUDO mkswap /swapfile >/dev/null
      $SUDO swapon /swapfile
    fi
    grep -q '^/swapfile ' /etc/fstab || echo '/swapfile none swap sw 0 0' | $SUDO tee -a /etc/fstab >/dev/null
    echo 'vm.swappiness=10' | $SUDO tee /etc/sysctl.d/99-swappiness.conf >/dev/null
    $SUDO sysctl -qw vm.swappiness=10
    ok "2 GB swap on (kept after reboot, used only under pressure)"
  fi
fi
$SUDO sysctl -qw vm.max_map_count=262144
echo 'vm.max_map_count=262144' | $SUDO tee /etc/sysctl.d/99-elasticsearch.conf >/dev/null
ok "vm.max_map_count=262144 (Elasticsearch needs it; kept after reboot)"

bold "2/4  .env"
cp .env ".env.bak.$(date +%Y%m%d%H%M%S)"
password=$(env_get SEARCH_PASSWORD)
if [[ -z "$password" ]]; then
  password=$(tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 32 || true)
fi
profiles=$(env_get COMPOSE_PROFILES)
if [[ ",${profiles}," != *",search,"* ]]; then profiles=${profiles:+${profiles},}search; fi
env_set COMPOSE_PROFILES "$profiles"
env_set ES_HEAP "$heap"
env_set ES_MEM_LIMIT "$limit"
env_set SEARCH_URL "http://search:9200"
env_set SEARCH_USERNAME elastic
env_set SEARCH_PASSWORD "$password"
env_set SEARCH_API_KEY ""
chmod 600 .env
ok ".env updated (old copy kept as .env.bak.*)"

bold "3/4  Start"
docker compose up -d search
echo "Waiting for Elasticsearch (the first start takes a minute or two on a small server)…"
for _ in $(seq 1 60); do
  [[ "$(docker inspect -f '{{.State.Health.Status}}' "$(docker compose ps -q search)" 2>/dev/null)" == healthy ]] && break
  sleep 5
done
[[ "$(docker inspect -f '{{.State.Health.Status}}' "$(docker compose ps -q search)" 2>/dev/null)" == healthy ]] \
  || die "Elasticsearch didn't come up. See: docker compose logs --tail=80 search"
ok "Elasticsearch is up"
# The app containers pick up the new SEARCH_* values.
docker compose up -d

bold "4/4  Indices"
for _ in $(seq 1 30); do
  docker compose exec -T api php artisan list search >/dev/null 2>&1 && break
  sleep 3
done
docker compose exec -T api php artisan list search >/dev/null 2>&1 \
  || die "This server runs an older version without search. Deploy first (./deploy.sh), then run this again."
docker compose exec -T api php artisan search:setup
docker compose exec -T api php artisan search:check

bold "Done"
docker stats --no-stream --format 'table {{.Name}}\t{{.MemUsage}}' | grep -E 'NAME|search' || true
