#!/usr/bin/env bash
# Release the committed code to the GoDaddy cPanel host (invora.alhamaad.com + invora-demo.alhamaad.com).
# Run from the app folder on the dev machine after committing: bash deploy/cpanel-release.sh [live|demo|both]
# The super admin password is asked once and sent on stdin (never stored). Server-side: deploy/cpanel-install.sh.
set -euo pipefail
cd "$(dirname "$0")/.."
WHICH="${1:-both}"; KEY="$HOME/.ssh/godaddy_alhamaad"; HOST="${DEPLOY_HOST:-xoq51g298wef@92.205.172.200}"
SSH=(env -i HOME="$HOME" PATH="$PATH" LANG=C LC_ALL=C TERM=dumb ssh -i "$KEY" -o BatchMode=yes "$HOST")
SCP=(env -i HOME="$HOME" PATH="$PATH" LANG=C LC_ALL=C scp -q -i "$KEY" -o BatchMode=yes)
[ -z "$(git status --porcelain --untracked-files=no)" ] || { echo "Commit first: the release is git HEAD."; exit 1; }
read -rsp "Super admin password (kept as is if unchanged): " PW; echo
npm run build >/dev/null
TMP="$(mktemp -d)"; git archive --format=tar HEAD > "$TMP/r.tar" && tar -rf "$TMP/r.tar" public/build && gzip "$TMP/r.tar"
"${SCP[@]}" "$TMP/r.tar.gz" "$HOST:~/invora-release.tar.gz"
"${SCP[@]}" deploy/cpanel-install.sh "$HOST:~/invora-install.sh"
"${SCP[@]}" deploy/cpanel-smoke.php "$HOST:~/invora-smoke.php"
for e in live demo; do
  if [ "$WHICH" = both ] || [ "$WHICH" = "$e" ]; then printf '%s\n' "$PW" | "${SSH[@]}" "bash ~/invora-install.sh $e" 2>/dev/null | tail -2; fi
done
"${SSH[@]}" 'for d in invora.alhamaad.com invora-demo.alhamaad.com; do /usr/local/bin/php ~/invora-smoke.php ~/$d | tail -1; done' 2>/dev/null
rm -rf "$TMP"
