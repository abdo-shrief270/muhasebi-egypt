#!/usr/bin/env bash
# First-time setup: creates .env with strong random secrets.
# Usage: ./init.sh app.yourdomain.com you@email.com [nginx]
#   nginx: the server already runs nginx on 80/443 (see compose.nginx.yml, nginx-site.conf).
set -euo pipefail
cd "$(dirname "$0")"

DOMAIN="${1:?usage: ./init.sh <domain> <email>}"
EMAIL="${2:?usage: ./init.sh <domain> <email>}"
EDGE="${3:-caddy}"

if [[ -f .env ]]; then
  echo ".env already exists — not touching it." >&2
  exit 1
fi

rand() { openssl rand -hex "$1"; }

sed \
  -e "s|^DOMAIN=.*|DOMAIN=${DOMAIN}|" \
  -e "s|^ACME_EMAIL=.*|ACME_EMAIL=${EMAIL}|" \
  -e "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" \
  -e "s|^FRONTEND_URL=.*|FRONTEND_URL=https://${DOMAIN}|" \
  -e "s|^REVERB_HOST=.*|REVERB_HOST=${DOMAIN}|" \
  -e "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" \
  -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$(rand 24)|" \
  -e "s|^REDIS_PASSWORD=.*|REDIS_PASSWORD=$(rand 24)|" \
  -e "s|^REVERB_APP_ID=.*|REVERB_APP_ID=$(( RANDOM * RANDOM ))|" \
  -e "s|^REVERB_APP_KEY=.*|REVERB_APP_KEY=$(rand 16)|" \
  -e "s|^REVERB_APP_SECRET=.*|REVERB_APP_SECRET=$(rand 24)|" \
  .env.example > .env

if [[ "$EDGE" == "nginx" ]]; then
  printf '\n# Behind the host nginx (Caddy on 127.0.0.1:8088 only)\nCOMPOSE_FILE=compose.yml:compose.nginx.yml\n' >> .env
fi

chmod 600 .env
echo "Created .env for ${DOMAIN}. Keep a copy of it somewhere safe (it holds your APP_KEY and DB password)."
