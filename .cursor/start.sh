#!/usr/bin/env bash
# Per-boot reconciliation: bring the MySQL server back up. Returns once ready.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# shellcheck source=/dev/null
source "$REPO_ROOT/.cursor/mysql.sh"

ensure_mysql_running
echo "MySQL is ready"
