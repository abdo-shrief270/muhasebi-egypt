#!/usr/bin/env bash
# One-shot setup of the website's marketing values: Search Console / Bing verification, Google
# Analytics, Meta Pixel, WhatsApp + social links and the campaign bar (offer text / coupon / last
# day). It asks for each one (Enter keeps the current value, "-" clears it), writes them into
# .env, rebuilds the website and checks they show up on it. Safe to run again whenever a value
# changes (e.g. a new offer, or the Pixel ID later).
#
# Usage: ./setup-marketing.sh
set -euo pipefail
cd "$(dirname "$0")"

bold() { printf '\n\033[1m%s\033[0m\n' "$*"; }
ok() { printf '\033[32m✓ %s\033[0m\n' "$*"; }
warn() { printf '\033[33m! %s\033[0m\n' "$*" >&2; }
die() { printf '\033[31m✗ %s\033[0m\n' "$*" >&2; exit 1; }

[[ -f .env ]] || die "No .env here. Run ./init.sh <domain> <email> [nginx] first (see docs/DEPLOY.md)."
command -v docker >/dev/null || die "docker is not installed."
[[ -t 0 ]] || die "Run this in a terminal: it asks questions."

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

yes_no() {
  local answer default=${2:-y} hint="[Y/n]"
  [[ "$default" == "n" ]] && hint="[y/N]"
  read -r -p "$1 $hint: " answer
  answer=${answer:-$default}
  [[ "$answer" =~ ^[Yy] ]]
}

# ask_value KEY "Question" — Enter keeps the current value, "-" clears it. Sets VALUE.
ask_value() {
  local key=$1 question=$2 current answer
  current=$(env_get "$key")
  if [[ -n "$current" ]]; then
    read -r -p "$question [$current] (- = remove): " answer
  else
    read -r -p "$question [empty = skip]: " answer
  fi
  answer=$(printf '%s' "$answer" | sed 's/^[[:space:]]*//; s/[[:space:]]*$//')
  if [[ "$answer" == "-" ]]; then VALUE=""
  elif [[ -z "$answer" ]]; then VALUE=$current
  else VALUE=$answer
  fi
}

declare -A NEW=()

# collect KEY "Question" check_function — asks until the check passes (an empty value always passes).
collect() {
  local key=$1 question=$2 check=${3:-}
  while true; do
    ask_value "$key" "$question"
    if [[ -n "$VALUE" && -n "$check" ]] && ! "$check"; then continue; fi
    NEW[$key]=$VALUE
    return
  done
}

# Checks: may rewrite VALUE into its canonical form; print why and fail when it's wrong.
check_verification() {
  # Accept the whole <meta ... content="..."> line too.
  if [[ "$VALUE" == *content=* ]]; then
    VALUE=$(printf '%s' "$VALUE" | sed -E 's/.*content="?([^" >]+).*/\1/')
  fi
  [[ "$VALUE" =~ ^[A-Za-z0-9_-]{10,}$ ]] && return 0
  warn "Paste the content value only (or the whole <meta ...> line)."; return 1
}
check_ga() {
  VALUE=${VALUE^^}
  [[ "$VALUE" =~ ^G-[A-Z0-9]{6,}$ ]] && return 0
  warn "A GA4 Measurement ID looks like G-XXXXXXXXXX."; return 1
}
check_pixel() {
  [[ "$VALUE" =~ ^[0-9]{10,20}$ ]] && return 0
  warn "The Pixel / Dataset ID is a number of 15–16 digits."; return 1
}
check_whatsapp() {
  VALUE=$(printf '%s' "$VALUE" | tr -d ' +()-')
  [[ "$VALUE" =~ ^00 ]] && VALUE=${VALUE#00}
  [[ "$VALUE" =~ ^01[0125][0-9]{8}$ ]] && VALUE="2$VALUE"
  [[ "$VALUE" =~ ^201[0125][0-9]{8}$ ]] && return 0
  warn "An Egyptian mobile number, e.g. 01012345678."; return 1
}
check_url() {
  [[ "$VALUE" =~ ^[a-z]+\.[a-z]+/ || "$VALUE" =~ ^www\. ]] && VALUE="https://$VALUE"
  [[ "$VALUE" =~ ^https://[^[:space:]]+\.[a-z]{2,}/[^[:space:]]+$ ]] && return 0
  warn "A full link, e.g. https://facebook.com/muhasebi.eg"; return 1
}
check_code() {
  VALUE=${VALUE^^}
  [[ "$VALUE" =~ ^[A-Z0-9_-]{3,40}$ ]] && return 0
  warn "Letters and numbers only, e.g. FOUNDERS50."; return 1
}
check_date() {
  if [[ "$VALUE" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]] && date -d "$VALUE" >/dev/null 2>&1; then
    [[ "$VALUE" < "$(date +%F)" ]] && warn "That day has passed: the bar will stay hidden."
    return 0
  fi
  warn "A date like 2026-11-30."; return 1
}

bold "1/4  Search engines"
echo "Google Search Console → Add property → URL prefix → HTML tag: paste the content value (or the whole line)."
collect GOOGLE_SITE_VERIFICATION "Google verification" check_verification
collect BING_SITE_VERIFICATION "Bing verification (optional)" check_verification

bold "2/4  Measurement"
collect GA_ID "Google Analytics Measurement ID (G-…)" check_ga
collect META_PIXEL_ID "Meta Pixel / Dataset ID (optional, can be added later)" check_pixel

bold "3/4  Contact and social links"
collect CONTACT_WHATSAPP "WhatsApp number (e.g. 01012345678)" check_whatsapp
collect FACEBOOK_URL "Facebook page link" check_url
collect INSTAGRAM_URL "Instagram link (optional)" check_url
collect TIKTOK_URL "TikTok link (optional)" check_url
collect YOUTUBE_URL "YouTube link (optional)" check_url

bold "4/4  Campaign bar (top of every page)"
echo "The coupon code must exist in the admin panel → الكوبونات. Leave all empty for no bar."
collect OFFER_TEXT "Offer text (e.g. أول 100 محل: خصم 50% أول 3 شهور)"
collect OFFER_CODE "Coupon code" check_code
collect OFFER_UNTIL "Last day (YYYY-MM-DD)" check_date
if [[ -z "${NEW[OFFER_TEXT]}" && ( -n "${NEW[OFFER_CODE]}" || -n "${NEW[OFFER_UNTIL]}" ) ]]; then
  warn "No offer text: the bar won't show."
fi

KEYS=(GOOGLE_SITE_VERIFICATION BING_SITE_VERIFICATION GA_ID META_PIXEL_ID CONTACT_WHATSAPP FACEBOOK_URL
  INSTAGRAM_URL TIKTOK_URL YOUTUBE_URL OFFER_TEXT OFFER_CODE OFFER_UNTIL)

bold "Summary"
changed=0
for key in "${KEYS[@]}"; do
  old=$(env_get "$key")
  mark=" "
  if [[ "$old" != "${NEW[$key]}" ]]; then mark="*"; changed=1; fi
  printf ' %s %-26s %s\n' "$mark" "$key" "${NEW[$key]:-—}"
done
echo "   (* = changed)"
if [[ $changed == 0 ]]; then
  yes_no "Nothing changed. Rebuild the website anyway?" n || exit 0
else
  yes_no "Save and rebuild the website?" y || exit 1
fi

cp .env ".env.bak.$(date +%Y%m%d%H%M%S)"
for key in "${KEYS[@]}"; do env_set "$key" "${NEW[$key]}"; done
chmod 600 .env
ok ".env updated (old copy kept as .env.bak.*)"

# These values are baked into the website at build time (the caddy image), so it must be rebuilt.
if yes_no "Pull the latest code too? (No = only rebuild the website with the new values)" y; then
  ./deploy.sh
else
  docker compose build caddy
  docker compose up -d --remove-orphans
fi

bold "Checking the website"
LANDING_DOMAIN=$(env_get LANDING_DOMAIN)
if [[ -z "$LANDING_DOMAIN" ]]; then
  warn "LANDING_DOMAIN isn't set: run ./setup-landing.sh to put the website on its domain."
  exit 0
fi
html=""
for _ in $(seq 1 15); do
  html=$(curl -fsS --max-time 10 "https://${LANDING_DOMAIN}/?nocache=$RANDOM" 2>/dev/null || true)
  [[ -n "$html" ]] && break
  sleep 4
done
[[ -n "$html" ]] || die "https://${LANDING_DOMAIN} doesn't answer. Check: docker compose logs caddy"

# found KEY "label" — the value appears in the page.
found() {
  local value=${NEW[$1]}
  [[ -z "$value" ]] && return
  if grep -qF -- "$value" <<<"$html"; then ok "$2 is on the page"; else warn "$2 isn't on the page yet (cache? try again in a minute)"; fi
}
found GOOGLE_SITE_VERIFICATION "Google verification"
found BING_SITE_VERIFICATION "Bing verification"
found GA_ID "Google Analytics"
found META_PIXEL_ID "Meta Pixel"
found CONTACT_WHATSAPP "WhatsApp number"
found FACEBOOK_URL "Facebook link"
found OFFER_CODE "Offer code"

bold "Next"
[[ -n "${NEW[GOOGLE_SITE_VERIFICATION]}" ]] && echo "• Search Console: press Verify, then Sitemaps → sitemap.xml → Submit."
[[ -n "${NEW[GA_ID]}" ]] && echo "• Google Analytics → Reports → Realtime: open https://${LANDING_DOMAIN} on your phone and look for yourself."
[[ -n "${NEW[META_PIXEL_ID]}" ]] && echo "• Events Manager: PageView should show within a few minutes."
echo "Website: https://${LANDING_DOMAIN}"
