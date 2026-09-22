#!/usr/bin/env bash
# Shared helper: make sure a local MySQL 8 server is initialized and running.
# Safe to call repeatedly (idempotent).
set -euo pipefail

ensure_mysql_running() {
    sudo mkdir -p /var/run/mysqld
    sudo chown -R mysql:mysql /var/run/mysqld

    # Initialize the data directory the first time only. The directory is
    # owned by the mysql user (mode 700), so the existence check needs sudo.
    if ! sudo test -d /var/lib/mysql/mysql; then
        sudo chown -R mysql:mysql /var/lib/mysql
        sudo mysqld --initialize-insecure --user=mysql
    fi

    # Start the server if it is not already answering.
    if ! sudo mysqladmin ping --silent >/dev/null 2>&1; then
        sudo service mysql start >/dev/null 2>&1 || true
    fi
    if ! sudo mysqladmin ping --silent >/dev/null 2>&1; then
        sudo mysqld_safe --user=mysql >/tmp/mysqld-safe.log 2>&1 &
    fi

    for _ in $(seq 1 60); do
        if sudo mysqladmin ping --silent >/dev/null 2>&1; then
            return 0
        fi
        sleep 1
    done

    echo "MySQL did not become ready in time" >&2
    return 1
}
