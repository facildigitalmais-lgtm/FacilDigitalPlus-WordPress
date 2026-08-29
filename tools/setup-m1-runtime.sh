#!/usr/bin/env bash

set -euo pipefail

ROOT="$(
  cd "$(
    dirname "${BASH_SOURCE[0]}"
  )/.." &&
  pwd
)"

cd "$ROOT"

[[ -f .env ]] || {
  echo "FAIL - .env ausente"
  exit 1
}

set -a
# shellcheck disable=SC1091
source .env
set +a

wpcli() {
  docker compose run \
    --rm \
    wpcli \
    wp \
    "$@"
}

if [[ "$(
  wpcli eval 'echo wp_get_environment_type();'
)" != "development" ]]; then
  echo "FAIL - setup M1 permitido somente em development"
  exit 1
fi

echo "=================================================="
echo "M1 - RUNTIME"
echo "=================================================="

echo
echo "=== MERCADO PAGO OFICIAL ==="

TARGET_MP_VERSION="8.9.3"

CURRENT_MP="$(
  wpcli plugin get \
    woocommerce-mercadopago \
    --field=version \
    2>/dev/null \
    || true
)"

if [[ "$CURRENT_MP" != "$TARGET_MP_VERSION" ]]; then
  wpcli plugin install \
    woocommerce-mercadopago \
    --version="$TARGET_MP_VERSION" \
    --force
fi

wpcli plugin activate \
  woocommerce-mercadopago

echo "PASS  Mercado Pago oficial $TARGET_MP_VERSION ativo"
echo
echo "Credenciais NAO foram configuradas."
echo "Sandbox real permanece para o macrolote M5."
