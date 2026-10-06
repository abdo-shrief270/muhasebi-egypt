#!/usr/bin/env bash
# Sets up the marketplace's search server on a fresh machine (Docker installed): Elasticsearch with
# security on, Caddy in front (HTTPS for the domain, only the allowed IPs), then an API key that can
# only touch the app's indices. Prints the lines to put in the app server's .env. Safe to run again
# (keeps the password and data; each run makes a new API key).
#
# Usage: ./setup.sh
set -euo pipefail
cd "$(dirname "$0")"

bold() { printf '\n\033[1m%s\033[0m\n' "$*"; }
ok() { printf '\033[32m✓ %s\033[0m\n' "$*"; }
die() { printf '\033[31m✗ %s\033[0m\n' "$*" >&2; exit 1; }

[[ -t 0 ]] || die "Run this in a terminal: it asks questions."
command -v docker >/dev/null || die "Install Docker first: curl -fsSL https://get.docker.com | sh"
SUDO=""
if [[ $EUID -ne 0 ]]; then SUDO="sudo"; fi
touch .env
chmod 600 .env

env_get() { grep -E "^$1=" .env | tail -n1 | cut -d= -f2- || true; }
env_set() {
  local key=$1 value=$2 tmp
  tmp=$(mktemp)
  grep -vE "^${key}=" .env > "$tmp" || true
  printf '%s=%s\n' "$key" "$value" >> "$tmp"
  cat "$tmp" > .env
  rm -f "$tmp"
}

bold "1/4  Questions"
current=$(env_get SEARCH_DOMAIN)
read -r -p "Domain for this server (an A record pointing here, e.g. search.muhasebi.com)${current:+ [$current]}: " domain
domain=${domain:-$current}
[[ "$domain" =~ ^[a-z0-9.-]+\.[a-z]{2,}$ ]] || die "That doesn't look like a domain."
current=$(env_get ALLOWED_IPS)
read -r -p "The app server's public IP(s), space separated${current:+ [$current]}: " ips
ips=${ips:-$current}
[[ -n "$ips" ]] || die "Give at least the app server's IP (\"any\" opens it to everyone — not recommended)."
if [[ "$ips" == "any" ]]; then ips="0.0.0.0/0 ::/0"; fi
ram_gb=$(awk '/MemTotal/ { printf "%d", $2 / 1024 / 1024 }' /proc/meminfo)
suggested="$(( ram_gb / 2 > 4 ? 4 : (ram_gb / 2 < 1 ? 1 : ram_gb / 2) ))g"
current=$(env_get ES_HEAP)
read -r -p "Elasticsearch memory (half the RAM, at most 31g) [${current:-$suggested}]: " heap
heap=${heap:-${current:-$suggested}}
[[ "$heap" =~ ^[0-9]+[mg]$ ]] || die "Write it like 2g or 1536m."

env_set SEARCH_DOMAIN "$domain"
env_set ALLOWED_IPS "$ips"
env_set ES_HEAP "$heap"
[[ -n "$(env_get ES_VERSION)" ]] || env_set ES_VERSION 9.1.0
password=$(env_get ELASTIC_PASSWORD)
if [[ -z "$password" ]]; then
  password=$(tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 32 || true)
  env_set ELASTIC_PASSWORD "$password"
fi
ok ".env written (the elastic password stays on this machine)"

bold "2/4  Kernel setting Elasticsearch needs"
$SUDO sysctl -qw vm.max_map_count=262144
echo 'vm.max_map_count=262144' | $SUDO tee /etc/sysctl.d/99-elasticsearch.conf >/dev/null
ok "vm.max_map_count=262144 (kept after reboot)"

bold "3/4  Start"
docker compose up -d
echo "Waiting for Elasticsearch (first start takes a minute)…"
es() { docker compose exec -T elasticsearch curl -fsS -u "elastic:$password" -H 'Content-Type: application/json' "$@"; }
for _ in $(seq 1 60); do
  es 'http://localhost:9200/_cluster/health?wait_for_status=yellow&timeout=5s' >/dev/null 2>&1 && break
  sleep 5
done
es 'http://localhost:9200/_cluster/health' >/dev/null || die "Elasticsearch didn't come up: docker compose logs elasticsearch"
version=$(es http://localhost:9200/ | sed -n 's/.*"number" *: *"\([^"]*\)".*/\1/p' | head -n1)
ok "Elasticsearch ${version} is up"

bold "4/4  API key for the app (only the muhasebi_* indices)"
key_json=$(es -X POST http://localhost:9200/_security/api_key -d "{
  \"name\": \"muhasebi-app-$(date +%Y%m%d%H%M%S)\",
  \"role_descriptors\": {
    \"muhasebi_app\": {
      \"cluster\": [\"monitor\"],
      \"indices\": [{ \"names\": [\"muhasebi_*\"], \"privileges\": [\"all\"] }]
    }
  }
}")
api_key=$(printf '%s' "$key_json" | sed -n 's/.*"encoded" *: *"\([^"]*\)".*/\1/p')
[[ -n "$api_key" ]] || die "Couldn't create the API key: $key_json"
ok "API key created"

echo "Checking HTTPS on https://${domain} (the certificate can take a minute)…"
code=000
for _ in $(seq 1 20); do
  code=$(curl -s -o /dev/null -w '%{http_code}' "https://${domain}/" || true)
  [[ "$code" != 000 ]] && break
  sleep 5
done
case "$code" in
  401|200) ok "https://${domain} answers (this machine is allowed)";;
  403) ok "https://${domain} answers and refuses this machine — only the allowed IPs get through";;
  *) printf '\033[33m! https://%s gave %s: check the DNS A record and that ports 80/443 are open.\033[0m\n' "$domain" "$code";;
esac

bold "Send these to the app (they go in infra/production/.env on the app server):"
cat <<OUT

SEARCH_URL=https://${domain}
SEARCH_API_KEY=${api_key}

OUT
echo "Then on the app server: docker compose up -d api && docker compose exec api php artisan search:setup && docker compose exec api php artisan search:check"
echo "(Keep the key private. To revoke it later: docker compose exec elasticsearch curl -u elastic:… -X DELETE localhost:9200/_security/api_key -H 'Content-Type: application/json' -d '{\"name\":\"…\"}')"
