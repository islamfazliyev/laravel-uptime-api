#!/usr/bin/env bash
set -e

# Run this from the parent folder that contains both uptime-tracker/ and uptime-frontend/
# e.g. ~/Documents/GitHub/laravel-uptime-api/
API_DIR="uptime-tracker"
API_PORT=8000

if [ ! -d "$API_DIR" ]; then
    echo "Error: run this script from the folder containing both '$API_DIR' "
    exit 1
fi

PIDS=()

cleanup() {
    echo ""
    echo "Stopping all processes..."
    for pid in "${PIDS[@]}"; do
        kill "$pid" 2>/dev/null || true
    done
    exit 0
}
trap cleanup SIGINT SIGTERM

echo "Starting Laravel API on http://127.0.0.1:${API_PORT} ..."
(cd "$API_DIR" && php artisan serve --port="$API_PORT") &
PIDS+=($!)

echo "Starting scheduler (uptime:check every minute) ..."
(cd "$API_DIR" && php artisan schedule:work) &
PIDS+=($!)

echo "Starting queue worker ..."
(cd "$API_DIR" && php artisan queue:work) &
PIDS+=($!)

echo ""
echo "All processes running. Press Ctrl+C to stop everything."
echo "  API:      http://127.0.0.1:${API_PORT}"


wait
