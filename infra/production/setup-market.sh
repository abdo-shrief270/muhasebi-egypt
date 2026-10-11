#!/usr/bin/env bash
# One-shot setup of «سوق محاسبي» (apps/market) on its own name, e.g. souq.muhasebi.com. It asks for
# the name, checks DNS, writes MARKET_DOMAIN / MARKET_SITE_ADDRESS / MARKET_URL into .env (the app
# then links to the market instead of «قريباً»), adds the nginx site + HTTPS certificate when the
# server runs nginx in front (unless the stores' wildcard site already covers the name), deploys,
# and checks the market answers. Elasticsearch must be set up first (setup-search.sh). Safe to run again.
#
# Usage: ./setup-market.sh
set -euo pipefail
cd "$(dirname "$0")"


bold() { printf '\n\033[1m%s\033[0m\n' "$*"; }
ok() { printf '\033[32m✓ %s\033[0m\n' "$*"; }
warn() { printf '\033[33m! %s\033[0m\n' "$*" >&2; }
die() { printf '\033[31m✗ %s\033[0m\n' "$*" >&2; exit 1; }

[[ -f .env ]] || die "No .env here. Run ./init.sh <domain> <email> [nginx] first (see docs/DEPLOY.md)."
command -v docker >/dev/null || die "docker is not installed."
[[ -t 0 ]] || die "Run this in a terminal: it asks questions."

SUDO=""
if [[ $EUID -ne 0 ]]; then SUDO="sudo"; fi

env_get() { grep -E "^$1=" .env | tail -n1 | cut -d= -f2- | sed 's/^"\(.*\)"$/\1/' || true; }

# Set KEY=value in .env (replace the line, or append it).
env_set() {
  local key=$1 value=$2 tmp
  if [[ "$value" == *[[:space:]]* ]]; then value="\"${value//\"/}\""; fi
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

ask() {
  local __var=$1 question=$2 default=${3:-} answer
  if [[ -n "$default" ]]; then
    read -r -p "$question [$default]: " answer
  else
    read -r -p "$question: " answer
  fi
  printf -v "$__var" '%s' "${answer:-$default}"
}

yes_no() {
  local answer default=${2:-y} hint="[Y/n]"
  [[ "$default" == "n" ]] && hint="[y/N]"
  read -r -p "$1 $hint: " answer
  answer=${answer:-$default}
  [[ "$answer" =~ ^[Yy] ]]
}

# The IPv4 a name points to: this server's resolver first, then public DNS.
resolve() {
  local ip
  ip=$(getent ahostsv4 "$1" 2>/dev/null | awk 'NR == 1 { print $1 }' || true)
  if [[ -z "$ip" ]] && command -v dig >/dev/null; then
    for resolver in 1.1.1.1 8.8.8.8; do
      ip=$(dig +short +time=3 +tries=1 A "$1" "@$resolver" 2>/dev/null | grep -E '^[0-9.]+$' | head -n1 || true)
      [[ -n "$ip" ]] && break
    done
  fi
  printf '%s' "$ip"
}

MAIN_DOMAIN=$(env_get DOMAIN)
NGINX_MODE=0
if env_get COMPOSE_FILE | grep -q "compose.nginx.yml"; then NGINX_MODE=1; fi
[[ -n "$(env_get SEARCH_URL)" ]] || warn "SEARCH_URL isn't set: the market needs Elasticsearch (./setup-search.sh). It will show «واقف دقيقة» until then."

bold "1/3  Market domain"
echo "Point a DNS A record for it to this server's IP before continuing (a *.domain record covers it too)."
default_domain=$(env_get MARKET_DOMAIN)
base=$(env_get LANDING_DOMAIN)
[[ -z "$base" && "$MAIN_DOMAIN" == *.*.* ]] && base=${MAIN_DOMAIN#*.}
[[ -z "$default_domain" && -n "$base" ]] && default_domain="souq.$base"
while true; do
  ask MARKET_DOMAIN "Market domain" "$default_domain"
  if [[ "$MARKET_DOMAIN" =~ ^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?\.[A-Za-z]{2,}$ ]]; then
    [[ "$MARKET_DOMAIN" != "$MAIN_DOMAIN" && "$MARKET_DOMAIN" != "$(env_get ADMIN_DOMAIN)" && "$MARKET_DOMAIN" != "$(env_get LANDING_DOMAIN)" ]] && break
    warn "It must differ from the app's, the admin panel's and the website's domains."
  else
    warn "That doesn't look like a domain (e.g. souq.example.com)."
  fi
done

server_ip=$(curl -fsS4 --max-time 5 https://ifconfig.me 2>/dev/null || true)
ip=$(resolve "$MARKET_DOMAIN")
if [[ -z "$ip" ]]; then
  warn "$MARKET_DOMAIN doesn't resolve yet."
  yes_no "Continue anyway? (the HTTPS certificate will fail until DNS is ready)" n || exit 1
elif [[ -n "$server_ip" && "$ip" != "$server_ip" ]]; then
  warn "$MARKET_DOMAIN points to $ip but this server is $server_ip."
  yes_no "Continue anyway?" n || exit 1
else
  ok "$MARKET_DOMAIN → $ip"
fi

bold "2/3  Applying"
cp .env ".env.bak.$(date +%Y%m%d%H%M%S)"
env_set MARKET_DOMAIN "$MARKET_DOMAIN"
env_set MARKET_URL "https://$MARKET_DOMAIN"
if [[ $NGINX_MODE == 1 ]]; then
  env_set MARKET_SITE_ADDRESS "http://$MARKET_DOMAIN"
else
  env_set MARKET_SITE_ADDRESS "$MARKET_DOMAIN"
fi
chmod 600 .env
ok ".env updated (old copy kept as .env.bak.*)"

if [[ $NGINX_MODE == 1 ]]; then
  command -v nginx >/dev/null || die "COMPOSE_FILE says nginx mode but nginx isn't installed."
  parent=${MARKET_DOMAIN#*.}
  if $SUDO grep -rqsE "server_name[^;]*[[:space:]](${MARKET_DOMAIN//./\\.}|\*\.${parent//./\\.})[[:space:];]" /etc/nginx/; then
    ok "nginx already serves $MARKET_DOMAIN (its own site or the stores' *.$parent)"
  else
    site=/etc/nginx/sites-available/muhasebi-market
    $SUDO tee "$site" >/dev/null <<NGINX
# «سوق محاسبي»: the same Caddy behind it (MARKET_SITE_ADDRESS=http://$MARKET_DOMAIN).
server {
    listen 80;
    listen [::]:80;
    server_name $MARKET_DOMAIN;

    client_max_body_size 1m;

    location / {
        proxy_pass http://127.0.0.1:8088;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 30s;
    }
}
NGINX
    [[ -d /etc/nginx/sites-enabled ]] && $SUDO ln -sf "$site" /etc/nginx/sites-enabled/muhasebi-market
    $SUDO nginx -t
    $SUDO systemctl reload nginx
    ok "nginx site added: $site"
    if $SUDO test -d "/etc/letsencrypt/live/${MARKET_DOMAIN}"; then
      ok "HTTPS certificate already exists for $MARKET_DOMAIN"
    elif command -v certbot >/dev/null; then
      acme_email=$(env_get ACME_EMAIL)
      $SUDO certbot --nginx --non-interactive --agree-tos --redirect ${acme_email:+-m "$acme_email"} -d "$MARKET_DOMAIN" \
        || warn "certbot failed (DNS not ready?). Run later: sudo certbot --nginx -d $MARKET_DOMAIN"
    else
      warn "certbot isn't installed: sudo apt install certbot python3-certbot-nginx"
    fi
  fi
fi

if yes_no "Pull the latest code and rebuild now? (No = only restart with the new settings)" y; then
  ./deploy.sh
else
  docker compose up -d --remove-orphans
fi

bold "3/3  Checking the market"
status=""
for _ in $(seq 1 15); do
  status=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "https://${MARKET_DOMAIN}/" || true)
  [[ "$status" == "200" ]] && break
  sleep 4
done
if [[ "$status" == "200" ]]; then
  ok "https://${MARKET_DOMAIN} answers"
  shops=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "https://${MARKET_DOMAIN}/api/market/shops" || true)
  if [[ "$shops" == "200" ]]; then ok "shops load from the search"; else warn "the shops list answered '$shops' (is search set up? docker compose exec api php artisan search:check)"; fi
else
  warn "https://${MARKET_DOMAIN} answered '${status:-nothing}'. Check DNS, then: docker compose logs caddy market"
fi

bold "Done"
echo "Market: https://${MARKET_DOMAIN}"
