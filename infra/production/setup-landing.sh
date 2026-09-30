#!/usr/bin/env bash
# One-shot setup of the public website (landing page, pricing, guide) on the root domain, e.g.
# muhasebi.com + www.muhasebi.com. It asks for the domain, checks DNS, writes LANDING_DOMAIN /
# LANDING_SITE_ADDRESS into .env, adds the nginx site + HTTPS certificate when the server runs nginx
# in front, deploys, and checks the site answers. Safe to run again.
#
# Usage: ./setup-landing.sh
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

bold "1/3  Website domain"
echo "The public website (landing page, prices, guide) usually lives on the root domain."
echo "Point DNS A records for it AND for www to this server's IP before continuing."
default_domain=$(env_get LANDING_DOMAIN)
# app.example.com → example.com
[[ -z "$default_domain" && "$MAIN_DOMAIN" == *.*.* ]] && default_domain=${MAIN_DOMAIN#*.}
while true; do
  ask LANDING_DOMAIN "Website domain (without www)" "$default_domain"
  LANDING_DOMAIN=${LANDING_DOMAIN#www.}
  if [[ "$LANDING_DOMAIN" =~ ^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?\.[A-Za-z]{2,}$ ]]; then
    [[ "$LANDING_DOMAIN" != "$MAIN_DOMAIN" && "$LANDING_DOMAIN" != "$(env_get ADMIN_DOMAIN)" ]] && break
    warn "It must differ from the app's and the admin panel's domains."
  else
    warn "That doesn't look like a domain (e.g. example.com)."
  fi
done

server_ip=$(curl -fsS4 --max-time 5 https://ifconfig.me 2>/dev/null || true)
WITH_WWW=1
for name in "$LANDING_DOMAIN" "www.$LANDING_DOMAIN"; do
  ip=$(resolve "$name")
  if [[ -z "$ip" ]]; then
    warn "$name doesn't resolve yet."
    if [[ "$name" == www.* ]] && yes_no "Set up the site without www?" n; then WITH_WWW=0; continue; fi
    yes_no "Continue anyway? (the HTTPS certificate will fail until DNS is ready)" n || exit 1
  elif [[ -n "$server_ip" && "$ip" != "$server_ip" ]]; then
    warn "$name points to $ip but this server is $server_ip (a parking page or an old host?)."
    yes_no "Continue anyway?" n || exit 1
  else
    ok "$name → $ip"
  fi
done

names=("$LANDING_DOMAIN")
[[ $WITH_WWW == 1 ]] && names+=("www.$LANDING_DOMAIN")

bold "2/3  Applying"
cp .env ".env.bak.$(date +%Y%m%d%H%M%S)"
env_set LANDING_DOMAIN "$LANDING_DOMAIN"
if [[ $WITH_WWW == 1 ]]; then
  env_set LANDING_SITE_ADDRESS "$LANDING_DOMAIN, www.$LANDING_DOMAIN"
else
  env_set LANDING_SITE_ADDRESS "$LANDING_DOMAIN"
fi
chmod 600 .env
ok ".env updated (old copy kept as .env.bak.*)"

if [[ $NGINX_MODE == 1 ]]; then
  command -v nginx >/dev/null || die "COMPOSE_FILE says nginx mode but nginx isn't installed."
  if $SUDO grep -rqsE "server_name[^;]*[[:space:]]${LANDING_DOMAIN//./\\.}[[:space:];]" /etc/nginx/; then
    ok "nginx already has a site for $LANDING_DOMAIN"
  else
    site=/etc/nginx/sites-available/muhasebi-landing
    awk '/^# The public website/ { on = 1 } on' nginx-site.conf \
      | sed "s/server_name example\.com www\.example\.com;/server_name ${names[*]};/" \
      | $SUDO tee "$site" >/dev/null
    [[ -d /etc/nginx/sites-enabled ]] && $SUDO ln -sf "$site" /etc/nginx/sites-enabled/muhasebi-landing
    $SUDO nginx -t
    $SUDO systemctl reload nginx
    ok "nginx site added: $site"
  fi
  if $SUDO test -d "/etc/letsencrypt/live/${LANDING_DOMAIN}"; then
    ok "HTTPS certificate already exists for $LANDING_DOMAIN"
  elif command -v certbot >/dev/null; then
    acme_email=$(env_get ACME_EMAIL)
    domains=()
    for n in "${names[@]}"; do domains+=(-d "$n"); done
    $SUDO certbot --nginx --non-interactive --agree-tos --redirect ${acme_email:+-m "$acme_email"} "${domains[@]}" \
      || warn "certbot failed (DNS not ready?). Run later: sudo certbot --nginx ${domains[*]}"
  else
    warn "certbot isn't installed: sudo apt install certbot python3-certbot-nginx"
  fi
fi

if yes_no "Pull the latest code and rebuild now? (No = only restart with the new settings)" y; then
  ./deploy.sh
else
  docker compose up -d --remove-orphans
fi

bold "3/3  Checking the website"
scheme=https
[[ $NGINX_MODE == 1 ]] && ! $SUDO test -d "/etc/letsencrypt/live/${LANDING_DOMAIN}" && scheme=http
status=""
for _ in $(seq 1 15); do
  status=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "${scheme}://${LANDING_DOMAIN}/" || true)
  [[ "$status" == "200" ]] && break
  sleep 4
done
if [[ "$status" == "200" ]]; then
  ok "${scheme}://${LANDING_DOMAIN} answers"
  plans=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "${scheme}://${LANDING_DOMAIN}/api/v1/public/plans" || true)
  if [[ "$plans" == "200" ]]; then ok "prices load from the API"; else warn "the prices API answered '$plans'"; fi
else
  warn "${scheme}://${LANDING_DOMAIN} answered '${status:-nothing}'. Check DNS, then: docker compose logs caddy"
fi

bold "Done"
echo "Website: ${scheme}://${LANDING_DOMAIN}"
