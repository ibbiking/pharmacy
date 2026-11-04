#!/usr/bin/env bash
#
# Start everything the app needs locally, in one terminal:
#   web server (http://127.0.0.1:8080), queue worker (emails, FBR, imports…), scheduler.
# Press Ctrl+C once to stop all three.
#
#   bash start.sh            # default port 8080
#   PORT=8090 bash start.sh  # another port

set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")"

PHP="php8.3"
command -v "$PHP" >/dev/null 2>&1 || PHP="php"
PORT="${PORT:-8080}"

pids=()
cleanup() { echo; echo "Stopping…"; kill "${pids[@]}" 2>/dev/null; wait 2>/dev/null; exit 0; }
trap cleanup INT TERM

"$PHP" artisan serve --host=127.0.0.1 --port="$PORT" & pids+=($!)
"$PHP" artisan queue:work --tries=3 --backoff=10 --sleep=2 & pids+=($!)
"$PHP" artisan schedule:work & pids+=($!)

echo "App running: http://127.0.0.1:${PORT}/admin/login   (Ctrl+C to stop)"
wait
