#!/usr/bin/env bash
# One-shot setup of the shops' online stores on their own domain, e.g. store.muhasebi.com (each
# shop gets store.muhasebi.com/{its-name}). It asks for the domain, checks DNS, writes STORE_DOMAIN /
# STORE_SITE_ADDRESS / STORE_URL into .env, adds the nginx site + HTTPS certificate when the server
# runs nginx in front, deploys, and checks the stores answer. Safe to run again.
#
# Usage: ./setup-store.sh
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

# The IPv4 a name points to: this server's resolver first, then public DNS (what Let's Encrypt
# sees; the server may still remember "no such name" from before the record existed).
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

bold "1/3  Stores domain"
echo "Every shop's store opens at <domain>/<its name>. Point a DNS A record for it to this server first."
default_domain=$(env_get STORE_DOMAIN)
# app.example.com → store.example.com
[[ -z "$default_domain" && "$MAIN_DOMAIN" == *.*.* ]] && default_domain="store.${MAIN_DOMAIN#*.}"
while true; do
  ask STORE_DOMAIN "Stores domain" "$default_domain"
  if [[ "$STORE_DOMAIN" =~ ^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?\.[A-Za-z]{2,}$ ]]; then
    [[ "$STORE_DOMAIN" != "$MAIN_DOMAIN" && "$STORE_DOMAIN" != "$(env_get ADMIN_DOMAIN)" && "$STORE_DOMAIN" != "$(env_get LANDING_DOMAIN)" ]] && break
    warn "It must differ from the app's, the admin panel's and the website's domains."
  else
    warn "That doesn't look like a domain (e.g. store.example.com)."
  fi
done

server_ip=$(curl -fsS4 --max-time 5 https://ifconfig.me 2>/dev/null || true)
ip=$(resolve "$STORE_DOMAIN")
if [[ -z "$ip" ]]; then
  warn "$STORE_DOMAIN doesn't resolve yet."
  yes_no "Continue anyway? (the HTTPS certificate will fail until DNS is ready)" n || exit 1
elif [[ -n "$server_ip" && "$ip" != "$server_ip" ]]; then
  warn "$STORE_DOMAIN points to $ip but this server is $server_ip."
  yes_no "Continue anyway?" n || exit 1
else
  ok "$STORE_DOMAIN → $ip"
fi

bold "2/3  Applying"
cp .env ".env.bak.$(date +%Y%m%d%H%M%S)"
env_set STORE_DOMAIN "$STORE_DOMAIN"
env_set STORE_SITE_ADDRESS "$STORE_DOMAIN"
env_set STORE_URL "https://$STORE_DOMAIN"
chmod 600 .env
ok ".env updated (old copy kept as .env.bak.*)"

if [[ $NGINX_MODE == 1 ]]; then
  command -v nginx >/dev/null || die "COMPOSE_FILE says nginx mode but nginx isn't installed."
  if $SUDO grep -rqsE "server_name[^;]*[[:space:]]${STORE_DOMAIN//./\\.}[[:space:];]" /etc/nginx/; then
    ok "nginx already has a site for $STORE_DOMAIN"
  else
    site=/etc/nginx/sites-available/muhasebi-store
    awk '/^# The shops. online stores/ { on = 1 } /^# The public website/ { on = 0 } on' nginx-site.conf \
      | sed "s/server_name store\.example\.com;/server_name ${STORE_DOMAIN};/" \
      | $SUDO tee "$site" >/dev/null
    [[ -d /etc/nginx/sites-enabled ]] && $SUDO ln -sf "$site" /etc/nginx/sites-enabled/muhasebi-store
    $SUDO nginx -t
    $SUDO systemctl reload nginx
    ok "nginx site added: $site"
  fi
  if $SUDO test -d "/etc/letsencrypt/live/${STORE_DOMAIN}"; then
    ok "HTTPS certificate already exists for $STORE_DOMAIN"
  elif command -v certbot >/dev/null; then
    acme_email=$(env_get ACME_EMAIL)
    $SUDO certbot --nginx --non-interactive --agree-tos --redirect ${acme_email:+-m "$acme_email"} -d "$STORE_DOMAIN" \
      || warn "certbot failed (DNS not ready?). Run later: sudo certbot --nginx -d $STORE_DOMAIN"
  else
    warn "certbot isn't installed: sudo apt install certbot python3-certbot-nginx"
  fi
fi

if yes_no "Pull the latest code and rebuild now? (No = only restart with the new settings)" y; then
  ./deploy.sh
else
  docker compose up -d --remove-orphans
fi

bold "3/3  Checking the stores"
scheme=https
[[ $NGINX_MODE == 1 ]] && ! $SUDO test -d "/etc/letsencrypt/live/${STORE_DOMAIN}" && scheme=http
status=""
for _ in $(seq 1 15); do
  status=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "${scheme}://${STORE_DOMAIN}/" || true)
  [[ "$status" == "200" ]] && break
  sleep 4
done
if [[ "$status" == "200" ]]; then
  ok "${scheme}://${STORE_DOMAIN} answers"
else
  warn "${scheme}://${STORE_DOMAIN} answered '${status:-nothing}'. Check DNS, then: docker compose logs caddy store"
fi

bold "Done"
echo "Stores: ${scheme}://${STORE_DOMAIN}/<shop>. Each shop opens its own from «المتجر الأونلاين» in the app."
