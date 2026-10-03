#!/usr/bin/env bash
# One-shot setup of the shops' online stores on subdomains: every shop at <its-name>.<host>, e.g.
# elnour.muhasebi.com. One DNS record (*.<host>, Cloudflare) for all of them. It asks for the host,
# checks the wildcard DNS, writes STORE_HOST / STORE_SITE_ADDRESS / STORE_URL / STORE_TLS into .env,
# and for HTTPS:
#   - behind nginx: one wildcard certificate from Let's Encrypt through Cloudflare's DNS (certbot +
#     python3-certbot-dns-cloudflare, a Cloudflare API token), renewed by certbot by itself, and the
#     nginx site for *.<host>;
#   - Caddy alone: a certificate per store on its first visit (on-demand TLS, only for real stores).
# Then deploys and checks a store subdomain answers. Safe to run again.
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
CF_CREDENTIALS=/root/.secrets/muhasebi-cloudflare.ini
CERT_NAME=muhasebi-stores

bold "1/4  Stores host"
echo "Every shop's store opens at <its-name>.<host> (e.g. elnour.muhasebi.com)."
echo "In Cloudflare → DNS, add ONE record: type A, name *, content = this server's IP, proxy OFF (grey cloud)."
default_host=$(env_get STORE_HOST)
# app.example.com → example.com
[[ -z "$default_host" && "$MAIN_DOMAIN" == *.*.* ]] && default_host=${MAIN_DOMAIN#*.}
while true; do
  ask STORE_HOST "Stores host (stores will be <name>.<host>)" "$default_host"
  STORE_HOST=${STORE_HOST#\*.}
  if [[ "$STORE_HOST" =~ ^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?\.[A-Za-z]{2,}$ ]]; then break; fi
  warn "That doesn't look like a domain (e.g. muhasebi.com)."
done

server_ip=$(curl -fsS4 --max-time 5 https://ifconfig.me 2>/dev/null || true)
probe="dns-check-$RANDOM.$STORE_HOST"
ip=$(resolve "$probe")
if [[ -z "$ip" ]]; then
  warn "*.$STORE_HOST doesn't resolve yet ($probe has no address)."
  yes_no "Continue anyway? (HTTPS will fail until the * record is there)" n || exit 1
elif [[ -n "$server_ip" && "$ip" != "$server_ip" ]]; then
  warn "*.$STORE_HOST points to $ip but this server is $server_ip (Cloudflare proxy on? turn it off for the * record)."
  yes_no "Continue anyway?" n || exit 1
else
  ok "*.$STORE_HOST → $ip"
fi

bold "2/4  Settings"
cp .env ".env.bak.$(date +%Y%m%d%H%M%S)"
env_set STORE_HOST "$STORE_HOST"
env_set STORE_URL "https://{slug}.$STORE_HOST"
if [[ $NGINX_MODE == 1 ]]; then
  env_set STORE_SITE_ADDRESS "http://*.$STORE_HOST"
  env_set STORE_TLS none
else
  env_set STORE_SITE_ADDRESS "*.$STORE_HOST"
  env_set STORE_TLS on-demand
fi
chmod 600 .env
ok ".env updated (old copy kept as .env.bak.*)"

bold "3/4  HTTPS"
if [[ $NGINX_MODE == 1 ]]; then
  command -v nginx >/dev/null || die "COMPOSE_FILE says nginx mode but nginx isn't installed."
  if ! command -v certbot >/dev/null || ! python3 -c "import certbot_dns_cloudflare" 2>/dev/null; then
    if yes_no "Install certbot with its Cloudflare plugin (apt install certbot python3-certbot-dns-cloudflare)?" y; then
      $SUDO apt-get update -qq && $SUDO apt-get install -y -qq certbot python3-certbot-dns-cloudflare
    else
      die "certbot + python3-certbot-dns-cloudflare are needed for the wildcard certificate."
    fi
  fi
  if $SUDO test -f "$CF_CREDENTIALS"; then
    ok "Cloudflare token already saved ($CF_CREDENTIALS)"
  else
    echo "Cloudflare → My Profile → API Tokens → Create Token → \"Edit zone DNS\" template, zone = $STORE_HOST."
    read -r -s -p "Paste the API token (hidden): " cf_token; echo
    [[ -n "$cf_token" ]] || die "No token given."
    $SUDO mkdir -p "$(dirname "$CF_CREDENTIALS")"
    $SUDO chmod 700 "$(dirname "$CF_CREDENTIALS")"
    printf 'dns_cloudflare_api_token = %s\n' "$cf_token" | $SUDO tee "$CF_CREDENTIALS" >/dev/null
    $SUDO chmod 600 "$CF_CREDENTIALS"
    unset cf_token
    ok "Token saved in $CF_CREDENTIALS (root only)"
  fi
  if $SUDO test -d "/etc/letsencrypt/live/$CERT_NAME"; then
    ok "Wildcard certificate already there ($CERT_NAME)"
  else
    acme_email=$(env_get ACME_EMAIL)
    $SUDO certbot certonly --non-interactive --agree-tos ${acme_email:+-m "$acme_email"} \
      --dns-cloudflare --dns-cloudflare-credentials "$CF_CREDENTIALS" --dns-cloudflare-propagation-seconds 30 \
      --cert-name "$CERT_NAME" -d "*.$STORE_HOST" --deploy-hook "systemctl reload nginx" \
      || die "certbot couldn't get *.$STORE_HOST (token rights? zone name?). Fix and run again."
    ok "Wildcard certificate for *.$STORE_HOST (certbot renews it by itself)"
  fi
  $SUDO test -f /etc/letsencrypt/options-ssl-nginx.conf \
    || $SUDO curl -fsSL -o /etc/letsencrypt/options-ssl-nginx.conf \
      https://raw.githubusercontent.com/certbot/certbot/main/certbot-nginx/src/certbot_nginx/_internal/tls_configs/options-ssl-nginx.conf
  site=/etc/nginx/sites-available/muhasebi-store
  awk '/^# The shops. online stores/ { on = 1 } /^# The public website/ { on = 0 } on' nginx-site.conf \
    | sed "s/\*\.example\.com/*.${STORE_HOST}/g; s/-d '\*\.example\.com'/-d '*.${STORE_HOST}'/" \
    | $SUDO tee "$site" >/dev/null
  [[ -d /etc/nginx/sites-enabled ]] && $SUDO ln -sf "$site" /etc/nginx/sites-enabled/muhasebi-store
  $SUDO nginx -t
  $SUDO systemctl reload nginx
  ok "nginx site for *.$STORE_HOST: $site"
else
  ok "Caddy fetches each store's certificate on its first visit (only for stores that exist)."
fi

if yes_no "Pull the latest code and rebuild now? (No = only restart with the new settings)" y; then
  ./deploy.sh
else
  docker compose up -d --remove-orphans
fi

bold "4/4  Checking"
status=""
for _ in $(seq 1 15); do
  status=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "https://${probe}/robots.txt" || true)
  [[ "$status" == "200" ]] && break
  sleep 4
done
if [[ "$status" == "200" ]]; then
  ok "https://<name>.$STORE_HOST answers"
elif [[ $NGINX_MODE == 0 ]]; then
  ok "Caddy only gets a certificate for a real store, so the test name can't answer over HTTPS. Open a shop's store to check."
else
  warn "https://${probe} answered '${status:-nothing}'. Check: docker compose logs caddy store --tail 50"
fi

bold "Done"
echo "Each shop opens its store from «المتجر الأونلاين» in the app: <its-name>.$STORE_HOST"
