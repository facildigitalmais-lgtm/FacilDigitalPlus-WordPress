#!/usr/bin/env bash

set -euo pipefail

ROOT="$(
  cd "$(
    dirname "${BASH_SOURCE[0]}"
  )/.." &&
  pwd
)"

cd "$ROOT"

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

PROBE="$(
  date -u +%Y%m%dT%H%M%SZ
)-$RANDOM"

echo "=================================================="
echo "W1 - TESTE DE PERSISTENCIA"
echo "=================================================="

echo
echo "Criando prova:"
echo "$PROBE"

EXISTING_ID="$(
  wpcli post list \
    --post_type=page \
    --name=w1-persistence-probe \
    --field=ID \
    --format=ids
)"

if [[ -z "$EXISTING_ID" ]]; then
  PAGE_ID="$(
    wpcli post create \
      --post_type=page \
      --post_status=private \
      --post_title="W1 Persistence Probe" \
      --post_name="w1-persistence-probe" \
      --post_content="$PROBE" \
      --porcelain
  )"
else
  PAGE_ID="$EXISTING_ID"

  wpcli post update \
    "$PAGE_ID" \
    --post_content="$PROBE" \
    >/dev/null
fi

wpcli option update \
  fd_w1_persistence_probe \
  "$PROBE" \
  >/dev/null

echo
echo "Reiniciando MariaDB e WordPress..."

docker compose restart \
  db \
  wordpress

echo
echo "Aguardando retorno..."

for attempt in $(
  seq 1 60
); do
  if wpcli core is-installed \
      >/dev/null 2>&1; then
    break
  fi

  if [[ "$attempt" -eq 60 ]]; then
    echo "FAIL - WordPress nao retornou"
    exit 1
  fi

  sleep 2
done

OPTION_VALUE="$(
  wpcli option get \
    fd_w1_persistence_probe
)"

PAGE_VALUE="$(
  wpcli post get \
    "$PAGE_ID" \
    --field=post_content
)"

if [[ "$OPTION_VALUE" != "$PROBE" ]]; then
  echo "FAIL - option nao persistiu"
  exit 1
fi

if [[ "$PAGE_VALUE" != "$PROBE" ]]; then
  echo "FAIL - pagina nao persistiu"
  exit 1
fi

echo
echo "PASS - banco persistiu apos restart"
echo "PASS - pagina persistiu apos restart"

wpcli post delete \
  "$PAGE_ID" \
  --force \
  >/dev/null

wpcli option delete \
  fd_w1_persistence_probe \
  >/dev/null

echo
echo "Prova temporaria removida."

echo
echo "=================================================="
echo "PASS - PERSISTENCIA W1"
echo "=================================================="