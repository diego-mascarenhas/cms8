#!/usr/bin/env bash

# Import a PostgreSQL plain-text dump (.sql or .sql.gz) into the local Humano database.
#
# Usage (from anywhere):
#   ./scripts/import-pgsql-backup.sh ~/Downloads/humano.sql.gz
#
# Options:
#   --skip-pm2     Do not stop/start PM2 workers
#   --no-roles     Skip creating common production roles (forge, read)
#   --help         Show help

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
ENV_FILE="${PROJECT_ROOT}/.env"
ECOSYSTEM_FILE="${PROJECT_ROOT}/ecosystem.queue.config.cjs"

SKIP_PM2=0
SKIP_ROLES=0
BACKUP_FILE=""

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

info() { echo -e "${BLUE}→${NC} $*"; }
ok() { echo -e "${GREEN}✓${NC} $*"; }
warn() { echo -e "${YELLOW}!${NC} $*"; }
fail() { echo -e "${RED}✗${NC} $*" >&2; exit 1; }

usage() {
    sed -n '2,12p' "$0" | sed 's/^# \{0,1\}//'
    exit "${1:-0}"
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --skip-pm2) SKIP_PM2=1; shift ;;
        --no-roles) SKIP_ROLES=1; shift ;;
        --help|-h) usage 0 ;;
        *)
            if [[ -z "${BACKUP_FILE}" ]]; then
                BACKUP_FILE="$1"
                shift
            else
                fail "Unknown argument: $1 (see --help)"
            fi
            ;;
    esac
done

[[ -n "${BACKUP_FILE}" ]] || usage 1
[[ -f "${BACKUP_FILE}" ]] || fail "Backup file not found: ${BACKUP_FILE}"
[[ -f "${ENV_FILE}" ]] || fail ".env not found at ${ENV_FILE}"

read_env() {
    local key="$1"
    local default="${2:-}"
    local line value

    line="$(grep -E "^${key}=" "${ENV_FILE}" | tail -n 1 || true)"
    if [[ -z "${line}" ]]; then
        echo "${default}"
        return
    fi

    value="${line#*=}"
    value="${value%$'\r'}"
    value="${value#\"}"
    value="${value%\"}"
    value="${value#\'}"
    value="${value%\'}"
    echo "${value}"
}

DB_CONNECTION="$(read_env DB_CONNECTION pgsql)"
DB_HOST="$(read_env DB_HOST 127.0.0.1)"
DB_PORT="$(read_env DB_PORT 5432)"
DB_DATABASE="$(read_env DB_DATABASE humano)"
DB_USERNAME="$(read_env DB_USERNAME magoo)"
DB_PASSWORD="$(read_env DB_PASSWORD "")"

[[ "${DB_CONNECTION}" == "pgsql" ]] || fail "DB_CONNECTION must be pgsql (got: ${DB_CONNECTION})"

find_psql() {
    local candidate
    for candidate in \
        "${PSQL_BIN:-}" \
        "/Applications/Postgres.app/Contents/Versions/latest/bin/psql" \
        "${HOME}/Library/Application Support/Herd/bin/psql" \
        "$(command -v psql 2>/dev/null || true)"
    do
        if [[ -n "${candidate}" && -x "${candidate}" ]]; then
            echo "${candidate}"
            return 0
        fi
    done
    fail "psql not found. Install Postgres.app or add psql to PATH."
}

PSQL="$(find_psql)"
PSQL_VERSION="$("${PSQL}" --version | awk '{print $3}')"
ok "Using ${PSQL} (PostgreSQL ${PSQL_VERSION})"

export PGHOST="${DB_HOST}"
export PGPORT="${DB_PORT}"
export PGUSER="${DB_USERNAME}"
export PGPASSWORD="${DB_PASSWORD}"

run_psql() {
    local database="$1"
    shift
    "${PSQL}" -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USERNAME}" -d "${database}" "$@"
}

ensure_role() {
    local role="$1"
    local attrs="$2"

    run_psql postgres -v ON_ERROR_STOP=1 -c "
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '${role}') THEN
        EXECUTE 'CREATE ROLE ${role} ${attrs}';
    END IF;
END
\$\$;"
}

terminate_db_connections() {
    info "Closing open connections to ${DB_DATABASE}..."
    run_psql postgres -v ON_ERROR_STOP=1 -c "
SELECT pg_terminate_backend(pid)
FROM pg_stat_activity
WHERE datname = '${DB_DATABASE}'
  AND pid <> pg_backend_pid();"
}

stop_pm2() {
    if [[ "${SKIP_PM2}" -eq 1 ]]; then
        warn "Skipping PM2 stop (--skip-pm2)"
        return
    fi

    if ! command -v pm2 >/dev/null 2>&1; then
        warn "pm2 not found; skipping worker stop"
        return
    fi

    if [[ ! -f "${ECOSYSTEM_FILE}" ]]; then
        warn "PM2 ecosystem file not found; skipping worker stop"
        return
    fi

    info "Stopping PM2 workers..."
    pm2 stop "${ECOSYSTEM_FILE}" >/dev/null 2>&1 || pm2 stop all >/dev/null 2>&1 || true
    ok "PM2 stopped"
}

start_pm2() {
    if [[ "${SKIP_PM2}" -eq 1 ]]; then
        warn "Skipping PM2 start (--skip-pm2)"
        return
    fi

    if ! command -v pm2 >/dev/null 2>&1 || [[ ! -f "${ECOSYSTEM_FILE}" ]]; then
        return
    fi

    info "Starting PM2 workers..."
    pm2 start "${ECOSYSTEM_FILE}" >/dev/null
    ok "PM2 started"
}

create_roles_from_dump() {
    local source_file="$1"
    local roles

    info "Detecting roles referenced in dump..."
    if [[ "${source_file}" == *.gz ]]; then
        roles="$(
            gunzip -c "${source_file}" \
                | grep -oE '(OWNER TO|GRANT[^;]* TO|ALTER ROLE) [a-zA-Z0-9_]+' \
                | awk '{print $NF}' \
                | sort -u \
                | grep -Ev "^(PUBLIC|postgres|${DB_USERNAME})$" || true
        )"
    else
        roles="$(
            grep -oE '(OWNER TO|GRANT[^;]* TO|ALTER ROLE) [a-zA-Z0-9_]+' "${source_file}" \
                | awk '{print $NF}' \
                | sort -u \
                | grep -Ev "^(PUBLIC|postgres|${DB_USERNAME})$" || true
        )"
    fi

    ensure_role forge "WITH LOGIN SUPERUSER"
    ensure_role read "WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE"

    if [[ -z "${roles}" ]]; then
        warn "No additional roles detected in dump"
        return
    fi

    while IFS= read -r role; do
        [[ -n "${role}" ]] || continue
        [[ "${role}" == "forge" || "${role}" == "read" ]] && continue
        info "Ensuring role exists: ${role}"
        ensure_role "${role}" "WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE"
    done <<< "${roles}"
}

if [[ "${SKIP_ROLES}" -eq 0 ]]; then
    create_roles_from_dump "${BACKUP_FILE}"
else
    warn "Skipping role creation (--no-roles)"
fi

stop_pm2
terminate_db_connections

info "Importing ${BACKUP_FILE} into PostgreSQL (via database: postgres)..."
info "Target database name from .env: ${DB_DATABASE}"

if [[ "${BACKUP_FILE}" == *.gz ]]; then
    gunzip -c "${BACKUP_FILE}" | run_psql postgres -v ON_ERROR_STOP=1
else
    run_psql postgres -v ON_ERROR_STOP=1 -f "${BACKUP_FILE}"
fi

ok "Import finished successfully"
start_pm2

echo ""
ok "Done. Local database \"${DB_DATABASE}\" restored from backup."
