#!/usr/bin/env bash
# Shops' own domains for their online stores, when nginx holds the certificates (nginx mode).
# For each verified domain (php artisan online-store:domains) that points to this server and has no
# nginx site yet: write the site (proxying to Caddy, which serves the store), get its certificate
# with certbot --nginx, reload. A domain that's no longer listed loses its site. Safe to run again;
# setup-store.sh puts it in cron every 10 minutes. (With Caddy alone this isn't needed: Caddy gets
# the certificates itself.)
#
# Usage: ./sync-store-domains.sh [--quiet]
set -euo pipefail
cd "$(dirname "$0")"

QUIET=0
if [[ "${1:-}" == "--quiet" ]]; then QUIET=1; fi
say() { if [[ $QUIET == 0 ]]; then printf '%s\n' "$*"; fi; }
warn() { printf '! %s\n' "$*" >&2; }

[[ -f .env ]] || { warn "No .env here."; exit 1; }
env_get() { grep -E "^$1=" .env | tail -n1 | cut -d= -f2- | sed 's/^"\(.*\)"$/\1/' || true; }

SUDO=""
if [[ $EUID -ne 0 ]]; then SUDO="sudo"; fi
SITES=/etc/nginx/sites-available
ENABLED=/etc/nginx/sites-enabled
PREFIX=muhasebi-domain-
EDGE_PORT=$(env_get EDGE_PORT); EDGE_PORT=${EDGE_PORT:-8088}
EMAIL=$(env_get ACME_EMAIL)
STORE_HOST=$(env_get STORE_HOST)

command -v nginx >/dev/null || { warn "nginx isn't installed (this script is for nginx mode)."; exit 1; }
command -v certbot >/dev/null || { warn "certbot isn't installed: apt install certbot python3-certbot-nginx"; exit 1; }

# This server's addresses, as the stores' wildcard resolves (a shop's domain must lead here).
ours=$(getent ahostsv4 "check.${STORE_HOST}" 2>/dev/null | awk '{print $1}' | sort -u || true)

domains=$(docker compose exec -T api php artisan online-store:domains 2>/dev/null | tr -d '\r' || true)
changed=0

for domain in $domains; do
  [[ "$domain" =~ ^[a-z0-9.-]+\.[a-z]{2,}$ ]] || { warn "skipping odd name: $domain"; continue; }
  site="$SITES/$PREFIX$domain"
  if $SUDO test -f "$site"; then
    continue
  fi
  theirs=$(getent ahostsv4 "$domain" 2>/dev/null | awk '{print $1}' | sort -u || true)
  if [[ -z "$theirs" || ( -n "$ours" && "$theirs" != "$ours" ) ]]; then
    say "$domain: doesn't point here yet"
    continue
  fi
  say "$domain: adding the nginx site and its certificate"
  $SUDO tee "$site" >/dev/null <<NGINX
# A shop's own domain for its online store (sync-store-domains.sh). Caddy behind it serves the store.
server {
    listen 80;
    listen [::]:80;
    server_name $domain;

    client_max_body_size 1m;

    location / {
        proxy_pass http://127.0.0.1:$EDGE_PORT;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 30s;
    }
}
NGINX
  $SUDO ln -sf "$site" "$ENABLED/$PREFIX$domain"
  if ! $SUDO nginx -t 2>/dev/null; then
    warn "$domain: nginx rejected the site; removed it"
    $SUDO rm -f "$site" "$ENABLED/$PREFIX$domain"
    continue
  fi
  $SUDO systemctl reload nginx
  if $SUDO certbot --nginx --non-interactive --agree-tos ${EMAIL:+-m "$EMAIL"} --redirect -d "$domain" >/dev/null 2>&1; then
    say "$domain: HTTPS ready"
  else
    warn "$domain: certbot couldn't get a certificate yet (DNS still spreading?); will try again next run"
    $SUDO rm -f "$site" "$ENABLED/$PREFIX$domain"
    $SUDO systemctl reload nginx
  fi
  changed=1
done

# Domains no longer verified (removed, changed, or the store closed): their sites go.
for site in "$SITES/$PREFIX"*; do
  [[ -e "$site" ]] || continue
  domain=${site#"$SITES/$PREFIX"}
  if ! grep -qxF "$domain" <<<"$domains"; then
    say "$domain: no longer a shop's domain; removing its site"
    $SUDO rm -f "$site" "$ENABLED/$PREFIX$domain"
    changed=1
  fi
done

if [[ $changed == 1 ]]; then
  $SUDO nginx -t && $SUDO systemctl reload nginx
fi
