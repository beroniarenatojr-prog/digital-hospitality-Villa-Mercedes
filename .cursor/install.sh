#!/usr/bin/env bash
# Repository bootstrap for the Villa Mercedes Digital Hospitality System.
# Idempotent: safe to run on a fresh checkout or an already-prepared tree.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_ROOT"

# shellcheck source=/dev/null
source "$REPO_ROOT/.cursor/mysql.sh"

DB_NAME="digital_hospitality_antigravity"
DB_TEST_NAME="digital_hospitality_antigravity_test"
DB_USER="laravel"
DB_PASS="secret"

echo "==> Ensuring MySQL is running"
ensure_mysql_running

echo "==> Ensuring databases and application user exist"
sudo mysql <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS ${DB_TEST_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED WITH mysql_native_password BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost', '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON ${DB_TEST_NAME}.* TO '${DB_USER}'@'localhost', '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

echo "==> Installing PHP dependencies"
composer install --no-interaction --prefer-dist

echo "==> Installing Node dependencies"
npm install --no-audit --no-fund

echo "==> Preparing .env"
if [ ! -f .env ]; then
    cp .env.example .env
    sed -i 's/^DB_CONNECTION=sqlite/DB_CONNECTION=mysql/' .env
    sed -i "s/^# DB_HOST=127.0.0.1/DB_HOST=127.0.0.1/" .env
    sed -i "s/^# DB_PORT=3306/DB_PORT=3306/" .env
    sed -i "s/^# DB_DATABASE=laravel/DB_DATABASE=${DB_NAME}/" .env
    sed -i "s/^# DB_USERNAME=root/DB_USERNAME=${DB_USER}/" .env
    sed -i "s/^# DB_PASSWORD=/DB_PASSWORD=${DB_PASS}/" .env
    sed -i 's/^APP_NAME=Laravel/APP_NAME="Villa Mercedes"/' .env
fi

grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force

echo "==> Running database migrations"
php artisan migrate --force

echo "==> Seeding database (only when empty)"
FACILITY_COUNT="$(sudo mysql -N -B -e "SELECT COUNT(*) FROM ${DB_NAME}.facilities;" 2>/dev/null || echo 0)"
if [ "${FACILITY_COUNT:-0}" = "0" ]; then
    php artisan db:seed --force
else
    echo "    Facilities already present (${FACILITY_COUNT}); skipping seed"
fi

echo "==> Linking public storage"
if [ ! -e public/storage ]; then
    php artisan storage:link
fi

echo "==> Building front-end assets"
npm run build

echo "==> Install complete"
