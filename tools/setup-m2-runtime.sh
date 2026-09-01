#!/usr/bin/env bash

set -euo pipefail

ROOT="$(
  cd "$(dirname "${BASH_SOURCE[0]}")/.." &&
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
  docker compose run --rm wpcli wp "$@"
}

if [[ "$(wpcli eval 'echo wp_get_environment_type();')" != "development" ]]; then
  echo "FAIL - setup M2 permitido somente em development"
  exit 1
fi

PRIVATE_DIR="/workspace/.runtime/facil-digital-private"

#
# O WordPress e o WP-CLI executam como www-data (33:33).
# O storage privado precisa pertencer ao processo PHP, e nao
# ao usuario do host/Codespace.
#
# Criamos/corrigimos a arvore como root dentro do container
# e depois entregamos ownership exclusivamente ao www-data.
#
docker compose run \
  --rm \
  --user 0:0 \
  --entrypoint sh \
  wpcli \
  -lc "
    set -eu

    mkdir -p \
      '$PRIVATE_DIR/masters' \
      '$PRIVATE_DIR/generated' \
      '$PRIVATE_DIR/temp'

    chown -R 33:33 \
      '$PRIVATE_DIR'

    chmod 0750 \
      '$PRIVATE_DIR' \
      '$PRIVATE_DIR/masters' \
      '$PRIVATE_DIR/generated' \
      '$PRIVATE_DIR/temp'
  "

wpcli config set \
  FACIL_DIGITAL_PRIVATE_DIR \
  "$PRIVATE_DIR" \
  >/dev/null

wpcli rewrite flush >/dev/null 2>&1 || true

wpcli eval '
use FacilDigital\Core\PDFs\PrivateStorage;
$storage = new PrivateStorage();
$storage->ensureReady();
echo $storage->root() . PHP_EOL;
'

echo "PASS  storage privado M2 preparado"
echo "PASS  raiz: $PRIVATE_DIR"
