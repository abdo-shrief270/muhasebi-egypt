#!/usr/bin/env bash
# One-shot setup of the platform admin panel (super admin) on this server. It asks for what it
# needs, then: writes ADMIN_DOMAIN / ADMIN_ALLOWED_IPS / InstaPay details into .env, adds the
# nginx site + HTTPS certificate when the server runs nginx in front, deploys, creates (or
# resets) your admin account and sets up its authenticator app, and checks the panel answers.
# Safe to run again (e.g. to change the domain, reset the password or replace the authenticator).
#
# Usage: ./setup-admin.sh
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

# ask VAR "Question" "default" — empty answer keeps the default.
ask() {
  local __var=$1 question=$2 default=${3:-} answer
  if [[ -n "$default" ]]; then
    read -r -p "$question [$default]: " answer
  else
    read -r -p "$question: " answer
  fi
  printf -v "$__var" '%s' "${answer:-$default}"
}

# yes_no "Question" y|n — returns 0 for yes.
yes_no() {
  local answer default=${2:-y} hint="[Y/n]"
  [[ "$default" == "n" ]] && hint="[y/N]"
  read -r -p "$1 $hint: " answer
  answer=${answer:-$default}
  [[ "$answer" =~ ^[Yy] ]]
}

MAIN_DOMAIN=$(env_get DOMAIN)
NGINX_MODE=0
if env_get COMPOSE_FILE | grep -q "compose.nginx.yml"; then NGINX_MODE=1; fi

bold "1/5  Admin panel domain"
echo "The panel lives on its own domain (never inside the shops' app)."
echo "Point a DNS A record for it to this server's IP before continuing."
default_domain=$(env_get ADMIN_DOMAIN)
[[ -z "$default_domain" && -n "$MAIN_DOMAIN" ]] && default_domain="admin.${MAIN_DOMAIN}"
while true; do
  ask ADMIN_DOMAIN "Admin domain" "$default_domain"
  if [[ "$ADMIN_DOMAIN" =~ ^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?\.[A-Za-z]{2,}$ ]]; then
    [[ "$ADMIN_DOMAIN" != "$MAIN_DOMAIN" ]] && break
    warn "It must differ from the shops' domain ($MAIN_DOMAIN)."
  else
    warn "That doesn't look like a domain (e.g. admin.example.com)."
  fi
done

server_ip=$(curl -fsS4 --max-time 5 https://ifconfig.me 2>/dev/null || true)
dns_ip=$(getent ahostsv4 "$ADMIN_DOMAIN" 2>/dev/null | awk 'NR == 1 { print $1 }' || true)
# This server may still remember "no such name" from before the record existed; ask public DNS
# (what Let's Encrypt sees) too.
if [[ -z "$dns_ip" ]] && command -v dig >/dev/null; then
  for resolver in 1.1.1.1 8.8.8.8; do
    dns_ip=$(dig +short +time=3 +tries=1 A "$ADMIN_DOMAIN" "@$resolver" 2>/dev/null | grep -E '^[0-9.]+$' | head -n1 || true)
    [[ -n "$dns_ip" ]] && break
  done
fi
if [[ -z "$dns_ip" ]]; then
  warn "$ADMIN_DOMAIN doesn't resolve yet. The HTTPS certificate will fail until the DNS record exists."
  yes_no "Continue anyway?" n || exit 1
elif [[ -n "$server_ip" && "$dns_ip" != "$server_ip" ]]; then
  warn "$ADMIN_DOMAIN points to $dns_ip but this server is $server_ip."
  yes_no "Continue anyway?" n || exit 1
else
  ok "$ADMIN_DOMAIN → ${dns_ip}"
fi

bold "2/5  Who may open the panel"
echo "Optional: limit the panel to fixed IP addresses (comma-separated). Everyone else gets 404."
echo "Leave empty to allow any IP (you still need password + authenticator code)."
my_ip=""
if [[ -n "${SSH_CLIENT:-}" ]]; then my_ip=${SSH_CLIENT%% *}; echo "You are connecting from: $my_ip"; fi
ask ADMIN_ALLOWED_IPS "Allowed IPs" "$(env_get ADMIN_ALLOWED_IPS)"
if [[ -n "$ADMIN_ALLOWED_IPS" && -n "$my_ip" && ",${ADMIN_ALLOWED_IPS// /}," != *",${my_ip},"* ]]; then
  warn "Your current IP ($my_ip) is not in the list; you won't reach the panel from here."
fi

bold "3/5  InstaPay details shown to shops on their billing page"
ask INSTAPAY_ADDRESS "InstaPay address (e.g. name@instapay)" "$(env_get BILLING_INSTAPAY_ADDRESS)"
ask INSTAPAY_NAME "Account holder name" "$(env_get BILLING_INSTAPAY_NAME)"
ask INSTAPAY_PHONE "Phone linked to InstaPay" "$(env_get BILLING_INSTAPAY_PHONE)"

bold "4/5  Your admin account"
ask ADMIN_EMAIL "Admin email" ""
while [[ ! "$ADMIN_EMAIL" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]]; do
  warn "Enter a valid email."
  ask ADMIN_EMAIL "Admin email" ""
done
ask ADMIN_NAME "Your name" "Admin"
while true; do
  read -r -s -p "Password (10+ characters): " ADMIN_PASSWORD; echo
  if [[ ${#ADMIN_PASSWORD} -lt 10 ]]; then warn "At least 10 characters."; continue; fi
  read -r -s -p "Password again: " again; echo
  [[ "$ADMIN_PASSWORD" == "$again" ]] && break
  warn "The two passwords don't match."
done

bold "Summary"
echo "  Admin domain:   $ADMIN_DOMAIN"
echo "  Allowed IPs:    ${ADMIN_ALLOWED_IPS:-any}"
echo "  InstaPay:       ${INSTAPAY_ADDRESS:-—} / ${INSTAPAY_NAME:-—} / ${INSTAPAY_PHONE:-—}"
echo "  Admin account:  $ADMIN_EMAIL ($ADMIN_NAME)"
echo "  Edge:           $([[ $NGINX_MODE == 1 ]] && echo "host nginx → Caddy" || echo "Caddy (automatic HTTPS)")"
yes_no "Apply this and deploy?" y || exit 1

bold "5/5  Applying"
cp .env ".env.bak.$(date +%Y%m%d%H%M%S)"
env_set ADMIN_DOMAIN "$ADMIN_DOMAIN"
env_set ADMIN_ALLOWED_IPS "${ADMIN_ALLOWED_IPS// /}"
env_set BILLING_INSTAPAY_ADDRESS "$INSTAPAY_ADDRESS"
env_set BILLING_INSTAPAY_NAME "$INSTAPAY_NAME"
env_set BILLING_INSTAPAY_PHONE "$INSTAPAY_PHONE"
chmod 600 .env
ok ".env updated (old copy kept as .env.bak.*)"

if [[ $NGINX_MODE == 1 ]]; then
  command -v nginx >/dev/null || die "COMPOSE_FILE says nginx mode but nginx isn't installed."
  if $SUDO grep -rqsE "server_name[^;]*[[:space:]]${ADMIN_DOMAIN//./\\.}[[:space:];]" /etc/nginx/; then
    ok "nginx already has a site for $ADMIN_DOMAIN"
  else
    site=/etc/nginx/sites-available/muhasebi-admin
    awk '/^# The admin panel/ { on = 1 } on' nginx-site.conf \
      | sed "s/admin\.example\.com/${ADMIN_DOMAIN}/g" \
      | $SUDO tee "$site" >/dev/null
    [[ -d /etc/nginx/sites-enabled ]] && $SUDO ln -sf "$site" /etc/nginx/sites-enabled/muhasebi-admin
    $SUDO nginx -t
    $SUDO systemctl reload nginx
    ok "nginx site added: $site"
  fi
  if $SUDO test -d "/etc/letsencrypt/live/${ADMIN_DOMAIN}"; then
    ok "HTTPS certificate already exists for $ADMIN_DOMAIN"
  elif command -v certbot >/dev/null; then
    acme_email=$(env_get ACME_EMAIL)
    $SUDO certbot --nginx --non-interactive --agree-tos --redirect ${acme_email:+-m "$acme_email"} -d "$ADMIN_DOMAIN" \
      || warn "certbot failed (DNS not ready?). Run later: sudo certbot --nginx -d $ADMIN_DOMAIN"
  else
    warn "certbot isn't installed: sudo apt install certbot python3-certbot-nginx && sudo certbot --nginx -d $ADMIN_DOMAIN"
  fi
fi

if yes_no "Pull the latest code and rebuild now? (No = only restart with the new settings)" y; then
  ./deploy.sh
else
  docker compose up -d --remove-orphans
fi

printf 'Waiting for the API'
for _ in $(seq 1 60); do
  if docker compose exec -T api php artisan --version >/dev/null 2>&1; then break; fi
  printf '.'; sleep 2
done
echo
docker compose exec -T api php artisan config:clear >/dev/null 2>&1 || true

printf '%s\n' "$ADMIN_PASSWORD" | docker compose exec -T api php artisan billing:admin "$ADMIN_EMAIL" "$ADMIN_NAME" --password-stdin >/dev/null
unset ADMIN_PASSWORD again
ok "Admin account ready: $ADMIN_EMAIL"

bold "Authenticator app (required to sign in)"
echo "Open Google Authenticator / Authy / 1Password → add an account → enter the key shown below,"
echo "then type the 6-digit code it shows."
until docker compose exec api php artisan billing:admin-2fa "$ADMIN_EMAIL"; do
  yes_no "Try again?" y || { warn "No authenticator yet; you can't sign in until you run: docker compose exec api php artisan billing:admin-2fa $ADMIN_EMAIL"; break; }
done

bold "Checking the panel"
scheme=https
[[ $NGINX_MODE == 1 ]] && ! $SUDO test -d "/etc/letsencrypt/live/${ADMIN_DOMAIN}" && scheme=http
status=""
for _ in $(seq 1 15); do
  status=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "${scheme}://${ADMIN_DOMAIN}/" || true)
  [[ "$status" == "200" ]] && break
  sleep 4
done
if [[ "$status" == "200" ]]; then
  ok "${scheme}://${ADMIN_DOMAIN} answers"
elif [[ "$status" == "404" && -n "$ADMIN_ALLOWED_IPS" ]]; then
  ok "${scheme}://${ADMIN_DOMAIN} is up (404 from this server is expected: its IP isn't in the allowed list)"
else
  warn "${scheme}://${ADMIN_DOMAIN} answered '${status:-nothing}'. Check DNS, then: docker compose logs caddy"
fi

bold "Done"
echo "Sign in at ${scheme}://${ADMIN_DOMAIN} with $ADMIN_EMAIL, your password and the authenticator code."
docker compose ps --format '{{.Service}}: {{.Status}}' 2>/dev/null || docker compose ps
