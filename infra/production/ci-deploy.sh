#!/usr/bin/env bash
# Deploy entry point for GitHub Actions (see .github/workflows/ci.yml, docs/DEPLOY.md).
#
# The CI SSH key is locked to this script in ~/.ssh/authorized_keys:
#   restrict,command="/opt/muhasebi/infra/production/ci-deploy.sh" ssh-ed25519 AAAA... github-deploy
# so whoever holds that key can only ask for "deploy <commit sha>" of a commit already on the
# deploy branch; nothing else runs.
set -euo pipefail

BRANCH="${DEPLOY_BRANCH:-main}"

main() {
  cd "$(dirname "$(readlink -f "$0")")"

  local sha="${SSH_ORIGINAL_COMMAND:-${1:-}}"
  sha="${sha#deploy }"
  if [[ ! "$sha" =~ ^[0-9a-f]{40}$ ]]; then
    echo "usage: deploy <40-char commit sha>" >&2
    exit 2
  fi

  # One deploy at a time; a second push waits for the first to finish.
  exec 9>/tmp/muhasebi-deploy.lock
  flock 9

  local repo
  repo="$(git rev-parse --show-toplevel)"
  git -C "$repo" fetch --quiet origin "$BRANCH"
  if ! git -C "$repo" merge-base --is-ancestor "$sha" "origin/$BRANCH"; then
    echo "commit $sha is not on origin/$BRANCH — refusing to deploy it." >&2
    exit 3
  fi

  echo "==> Deploying $sha ($BRANCH)"
  git -C "$repo" checkout --quiet -B "$BRANCH" "$sha"
  git -C "$repo" branch --quiet --set-upstream-to="origin/$BRANCH" "$BRANCH"

  SKIP_PULL=1 ./deploy.sh

  echo "==> Waiting for the API to become healthy"
  local status=""
  for _ in $(seq 1 40); do
    status="$(docker inspect -f '{{.State.Health.Status}}' "$(docker compose ps -q api)" 2>/dev/null || true)"
    [[ "$status" == "healthy" ]] && { echo "==> Deployed $sha"; return 0; }
    sleep 3
  done
  echo "API is not healthy (status: ${status:-unknown}). Last logs:" >&2
  docker compose logs api --tail 60 >&2 || true
  exit 1
}

# Everything runs inside main(): this file is replaced by the checkout above while it runs.
main "$@"; exit $?
