#!/usr/bin/env bash
# Per-boot reconciliation for the Villa Mercedes environment:
#   1. Ensure the MySQL server is up.
#   2. Serve the application (built assets) with the Laravel HTTP server.
# Idempotent: skips the server if it is already running, and returns promptly.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

# shellcheck source=/dev/null
source "$REPO_ROOT/.cursor/mysql.sh"

echo "==> Ensuring MySQL is running"
ensure_mysql_running

# Serve the production build (created by install.sh) rather than the Vite dev
# server: this keeps the app reachable through a single proxied port. Removing a
# stale hot file makes @vite fall back to the built manifest. Developers who want
# HMR can still run `npm run dev` manually.
rm -f public/hot

echo "==> Ensuring the Laravel server is running"
if (exec 3<>/dev/tcp/127.0.0.1/8000) 2>/dev/null; then
    exec 3>&- 3<&-
    echo "    Port 8000 already serving; leaving it alone"
else
    echo "    Starting Laravel server on :8000 (logs: /tmp/laravel-serve.log)"
    nohup php artisan serve --host=0.0.0.0 --port=8000 >/tmp/laravel-serve.log 2>&1 &
    disown || true
fi

echo "Environment ready (MySQL + Laravel :8000)"
