#!/usr/bin/env bash

set -euo pipefail

ROOT="$(
  cd "$(
    dirname "${BASH_SOURCE[0]}"
  )/.." &&
  pwd
)"

ENV_FILE="$ROOT/.env"

if [[ -f "$ENV_FILE" ]]; then
  echo "ℹ .env ja existe."
  echo "   Nenhuma credencial DEV foi alterada."
  exit 0
fi

random_hex() {
  openssl rand -hex 24
}

WORDPRESS_PORT="${WORDPRESS_PORT:-8080}"

if [[
  -n "${CODESPACE_NAME:-}" &&
  -n "${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-}"
]]; then
  WORDPRESS_URL="https://${CODESPACE_NAME}-${WORDPRESS_PORT}.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
else
  WORDPRESS_URL="http://localhost:${WORDPRESS_PORT}"
fi

LOCAL_UID="$(
  id -u
)"

LOCAL_GID="$(
  id -g
)"

DB_PASSWORD="$(
  random_hex
)"

ROOT_PASSWORD="$(
  random_hex
)"

ADMIN_PASSWORD="$(
  random_hex
)"

cat > "$ENV_FILE" <<ENV
WORDPRESS_PORT=${WORDPRESS_PORT}
WORDPRESS_URL=${WORDPRESS_URL}

WORDPRESS_DB_NAME=facil_digital
WORDPRESS_DB_USER=facil_digital
WORDPRESS_DB_PASSWORD=${DB_PASSWORD}
MARIADB_ROOT_PASSWORD=${ROOT_PASSWORD}

WORDPRESS_TABLE_PREFIX=fdwp_

WORDPRESS_ADMIN_USER=fdadmin
WORDPRESS_ADMIN_PASSWORD=${ADMIN_PASSWORD}
WORDPRESS_ADMIN_EMAIL=admin@example.test

WORDPRESS_TIMEZONE=America/Sao_Paulo

LOCAL_UID=${LOCAL_UID}
LOCAL_GID=${LOCAL_GID}
ENV

chmod 600 "$ENV_FILE"

echo "✅ .env DEV criado."
echo
echo "URL:"
echo "  ${WORDPRESS_URL}"
echo
echo "Usuario admin DEV:"
echo "  fdadmin"
echo
echo "A senha DEV foi gravada apenas em:"
echo "  ${ENV_FILE}"
echo
echo "Nao envie o conteudo de .env para o chat."
