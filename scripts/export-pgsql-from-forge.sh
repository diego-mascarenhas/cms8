#!/usr/bin/env bash

# Dump production PostgreSQL on the Forge VPS over SSH, then download it locally.
# Pair with: ./scripts/import-pgsql-backup.sh ~/Downloads/humano-prod-….sql.gz
#
# Default flow:
#   1) ssh forge@HOST
#   2) pg_dump on the VPS (using the site .env DB_* credentials)
#   3) scp the .sql.gz to ~/Downloads
#
# Usage:
#   ./scripts/export-pgsql-from-forge.sh
#   ./scripts/export-pgsql-from-forge.sh --site /home/forge/humano.app
#   ./scripts/export-pgsql-from-forge.sh --via-prod-read   # fallback: dump from Mac with DB_PROD_READ_*
#
# .env (local):
#   FORGE_SSH_HOST=geri.revisionalpha.cloud   # or omit → uses DB_PROD_READ_HOST
#   FORGE_SSH_USER=forge
#   FORGE_SSH_PORT=22
#   FORGE_SITE_PATH=/home/forge/tu-sitio.app   # optional; auto-detected if only one site
#   FORGE_SSH_IDENTITY=~/.ssh/id_rsa            # optional
#
# Options:
#   --host / --user / --port / --site / --identity
#   --output PATH
#   --keep-remote
#   --plain
#   --via-prod-read
#   --help

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
ENV_FILE="${PROJECT_ROOT}/.env"

MODE="forge-ssh"
SSH_HOST=""
SSH_USER="forge"
SSH_PORT="22"
SSH_IDENTITY=""
SITE_PATH=""
OUTPUT_FILE=""
KEEP_REMOTE=0
PLAIN=0

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
    sed -n '2,28p' "$0" | sed 's/^# \{0,1\}//'
    exit "${1:-0}"
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --via-ssh) MODE="forge-ssh"; shift ;;
        --via-prod-read) MODE="prod-read"; shift ;;
        --host) SSH_HOST="${2:-}"; shift 2 ;;
        --user) SSH_USER="${2:-}"; shift 2 ;;
        --port) SSH_PORT="${2:-}"; shift 2 ;;
        --site) SITE_PATH="${2:-}"; shift 2 ;;
        --identity) SSH_IDENTITY="${2:-}"; shift 2 ;;
        --output|-o) OUTPUT_FILE="${2:-}"; shift 2 ;;
        --keep-remote) KEEP_REMOTE=1; shift ;;
        --plain) PLAIN=1; shift ;;
        --help|-h) usage 0 ;;
        *) fail "Unknown argument: $1 (see --help)" ;;
    esac
done

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

find_pg_bin() {
    local bin_name="$1"
    local env_override=""
    local candidate herd_bin

    case "${bin_name}" in
        pg_dump) env_override="${PG_DUMP_BIN:-}" ;;
        psql) env_override="${PSQL_BIN:-}" ;;
    esac

    herd_bin="${HOME}/Library/Application Support/Herd/bin/${bin_name}"

    for candidate in \
        "${env_override}" \
        "/Applications/Postgres.app/Contents/Versions/latest/bin/${bin_name}" \
        "${herd_bin}" \
        "$(command -v "${bin_name}" 2>/dev/null || true)"
    do
        if [[ -n "${candidate}" && -x "${candidate}" ]]; then
            echo "${candidate}"
            return 0
        fi
    done
    return 1
}

expand_path() {
    local path="$1"
    if [[ "${path}" == ~* ]]; then
        path="${path/#\~/${HOME}}"
    fi
    echo "${path}"
}

[[ -n "${SSH_HOST}" ]] || SSH_HOST="$(read_env FORGE_SSH_HOST "")"
[[ -n "${SSH_HOST}" ]] || SSH_HOST="$(read_env DB_PROD_READ_HOST "")"
if [[ -z "${SSH_USER}" || "${SSH_USER}" == "forge" ]]; then
    SSH_USER="$(read_env FORGE_SSH_USER forge)"
fi
SSH_PORT="$(read_env FORGE_SSH_PORT "${SSH_PORT}")"
[[ -n "${SITE_PATH}" ]] || SITE_PATH="$(read_env FORGE_SITE_PATH "")"
[[ -n "${SSH_IDENTITY}" ]] || SSH_IDENTITY="$(read_env FORGE_SSH_IDENTITY "")"
SSH_IDENTITY="$(expand_path "${SSH_IDENTITY}")"

PROD_HOST="$(read_env DB_PROD_READ_HOST "")"
PROD_PORT="$(read_env DB_PROD_READ_PORT 5432)"
PROD_DATABASE="$(read_env DB_PROD_READ_DATABASE "")"
PROD_USERNAME="$(read_env DB_PROD_READ_USERNAME "")"
PROD_PASSWORD="$(read_env DB_PROD_READ_PASSWORD "")"
PROD_SSLMODE="$(read_env DB_PROD_READ_SSLMODE prefer)"

TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
if [[ -z "${OUTPUT_FILE}" ]]; then
    if [[ "${PLAIN}" -eq 1 ]]; then
        OUTPUT_FILE="${HOME}/Downloads/humano-prod-${TIMESTAMP}.sql"
    else
        OUTPUT_FILE="${HOME}/Downloads/humano-prod-${TIMESTAMP}.sql.gz"
    fi
fi
OUTPUT_FILE="$(expand_path "${OUTPUT_FILE}")"
mkdir -p "$(dirname "${OUTPUT_FILE}")"

ssh_opts() {
    local opts=(-p "${SSH_PORT}" -o StrictHostKeyChecking=accept-new -o ConnectTimeout=15)
    if [[ -n "${SSH_IDENTITY}" ]]; then
        [[ -f "${SSH_IDENTITY}" ]] || fail "SSH identity not found: ${SSH_IDENTITY}"
        opts+=(-i "${SSH_IDENTITY}")
    fi
    printf '%s\n' "${opts[@]}"
}

resolve_remote_site_path() {
    local detected

    if [[ -n "${SITE_PATH}" ]]; then
        echo "${SITE_PATH}"
        return 0
    fi

    info "FORGE_SITE_PATH not set; detecting site under /home/forge …"
    local opts=()
    while IFS= read -r line; do
        [[ -n "${line}" ]] && opts+=("${line}")
    done < <(ssh_opts)

    detected="$(ssh "${opts[@]}" "${SSH_USER}@${SSH_HOST}" bash -s <<'REMOTE'
set -euo pipefail
matches=()
for candidate in /home/forge/*/.env /home/forge/*/current/.env; do
    [[ -f "${candidate}" ]] || continue
    dir="$(dirname "${candidate}")"
    [[ "$(basename "${dir}")" == "current" ]] && dir="$(dirname "${dir}")"
    matches+=("${dir}")
done
printf '%s\n' "${matches[@]}" | awk 'NF' | sort -u
REMOTE
)"

    local count
    count="$(printf '%s\n' "${detected}" | awk 'NF' | wc -l | tr -d ' ')"
    if [[ "${count}" -eq 0 ]]; then
        fail "No site .env found under /home/forge. Pass --site /home/forge/your-site.app"
    fi
    if [[ "${count}" -gt 1 ]]; then
        warn "Multiple sites found:"
        printf '%s\n' "${detected}" | sed 's/^/  - /'
        fail "Set FORGE_SITE_PATH in .env or pass --site"
    fi

    echo "$(printf '%s\n' "${detected}" | awk 'NF' | head -n 1)"
}

export_via_forge_ssh() {
    [[ -n "${SSH_HOST}" ]] || fail "Missing SSH host. Set FORGE_SSH_HOST or DB_PROD_READ_HOST (e.g. geri.revisionalpha.cloud)"

    local opts=()
    while IFS= read -r line; do
        [[ -n "${line}" ]] && opts+=("${line}")
    done < <(ssh_opts)

    SITE_PATH="$(resolve_remote_site_path)"

    local remote_tmp="/tmp/humano-forge-export-${TIMESTAMP}.sql"
    if [[ "${PLAIN}" -eq 0 ]]; then
        remote_tmp="${remote_tmp}.gz"
    fi

    info "Mode: remote dump on Forge VPS + scp to local"
    info "SSH  ${SSH_USER}@${SSH_HOST}:${SSH_PORT}"
    info "Site ${SITE_PATH}"
    info "Dump → ${OUTPUT_FILE}"

    info "Running pg_dump on remote…"
    ssh "${opts[@]}" "${SSH_USER}@${SSH_HOST}" bash -s -- "${SITE_PATH}" "${remote_tmp}" "${PLAIN}" <<'REMOTE'
set -euo pipefail

SITE_PATH="$1"
REMOTE_TMP="$2"
PLAIN="$3"

resolve_env_file() {
    local site="$1"
    local candidate
    for candidate in "${site}/.env" "${site}/current/.env"; do
        if [[ -f "${candidate}" ]]; then
            echo "${candidate}"
            return 0
        fi
    done
    echo ""
    return 1
}

ENV_FILE="$(resolve_env_file "${SITE_PATH}" || true)"
[[ -n "${ENV_FILE}" && -f "${ENV_FILE}" ]] || {
    echo "Remote .env not found under ${SITE_PATH}" >&2
    exit 1
}

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
DB_DATABASE="$(read_env DB_DATABASE)"
DB_USERNAME="$(read_env DB_USERNAME)"
DB_PASSWORD="$(read_env DB_PASSWORD "")"

[[ "${DB_CONNECTION}" == "pgsql" ]] || { echo "Remote DB_CONNECTION must be pgsql (got: ${DB_CONNECTION})" >&2; exit 1; }
[[ -n "${DB_DATABASE}" && -n "${DB_USERNAME}" ]] || { echo "Remote DB credentials incomplete in ${ENV_FILE}" >&2; exit 1; }

PG_DUMP_BIN="$(command -v pg_dump || true)"
if [[ -z "${PG_DUMP_BIN}" ]]; then
    for candidate in /usr/lib/postgresql/*/bin/pg_dump /usr/pgsql-*/bin/pg_dump; do
        if [[ -x "${candidate}" ]]; then
            PG_DUMP_BIN="${candidate}"
            break
        fi
    done
fi
[[ -n "${PG_DUMP_BIN}" && -x "${PG_DUMP_BIN}" ]] || { echo "pg_dump not found on VPS" >&2; exit 1; }

echo "→ Dumping ${DB_DATABASE}@${DB_HOST}:${DB_PORT} as ${DB_USERNAME}"
export PGPASSWORD="${DB_PASSWORD}"
DUMP_ARGS=(
    --host="${DB_HOST}"
    --port="${DB_PORT}"
    --username="${DB_USERNAME}"
    --dbname="${DB_DATABASE}"
    --no-owner
    --no-acl
    --clean
    --if-exists
)

if [[ "${PLAIN}" -eq 1 ]]; then
    "${PG_DUMP_BIN}" "${DUMP_ARGS[@]}" > "${REMOTE_TMP}"
else
    "${PG_DUMP_BIN}" "${DUMP_ARGS[@]}" | gzip -c > "${REMOTE_TMP}"
fi
unset PGPASSWORD
ls -lh "${REMOTE_TMP}"
echo "REMOTE_TMP=${REMOTE_TMP}"
REMOTE

    info "Copying dump to local…"
    scp -P "${SSH_PORT}" \
        ${SSH_IDENTITY:+-i "${SSH_IDENTITY}"} \
        -o StrictHostKeyChecking=accept-new \
        "${SSH_USER}@${SSH_HOST}:${remote_tmp}" \
        "${OUTPUT_FILE}"

    if [[ "${KEEP_REMOTE}" -eq 0 ]]; then
        info "Removing remote temp file…"
        ssh "${opts[@]}" "${SSH_USER}@${SSH_HOST}" "rm -f '${remote_tmp}'"
    else
        warn "Remote dump kept at ${remote_tmp}"
    fi
}

export_via_prod_read() {
    local pg_dump_bin

    [[ -n "${PROD_HOST}" ]] || fail "DB_PROD_READ_HOST is empty in .env"
    [[ -n "${PROD_DATABASE}" ]] || fail "DB_PROD_READ_DATABASE is empty in .env"
    [[ -n "${PROD_USERNAME}" ]] || fail "DB_PROD_READ_USERNAME is empty in .env"

    pg_dump_bin="$(find_pg_bin pg_dump)" || fail "pg_dump not found locally"

    info "Mode: prod-read (direct from Mac — fallback)"
    info "Source: ${PROD_USERNAME}@${PROD_HOST}:${PROD_PORT}/${PROD_DATABASE}"
    info "Dump  → ${OUTPUT_FILE}"

    export PGPASSWORD="${PROD_PASSWORD}"
    export PGSSLMODE="${PROD_SSLMODE}"

    local dump_args=(
        --host="${PROD_HOST}"
        --port="${PROD_PORT}"
        --username="${PROD_USERNAME}"
        --dbname="${PROD_DATABASE}"
        --no-owner
        --no-acl
        --clean
        --if-exists
    )

    if [[ "${PLAIN}" -eq 1 ]]; then
        "${pg_dump_bin}" "${dump_args[@]}" > "${OUTPUT_FILE}"
    else
        "${pg_dump_bin}" "${dump_args[@]}" | gzip -c > "${OUTPUT_FILE}"
    fi

    unset PGPASSWORD
    unset PGSSLMODE
}

case "${MODE}" in
    forge-ssh) export_via_forge_ssh ;;
    prod-read) export_via_prod_read ;;
    *) fail "Unknown mode: ${MODE}" ;;
esac

ok "Dump ready: ${OUTPUT_FILE}"
echo
info "Import locally with:"
echo "  ./scripts/import-pgsql-backup.sh \"${OUTPUT_FILE}\""
