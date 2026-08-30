#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

[[ -f .env ]] || { echo "FAIL - .env ausente"; exit 1; }

OLD_URL="${1:-}"
NEW_URL="${2:-}"

[[ -n "$OLD_URL" && -n "$NEW_URL" ]] || {
  echo "Uso: ./tools/m5-domain-plan.sh https://origem.example https://destino.example"
  exit 2
}

[[ "$OLD_URL" =~ ^https:// ]] || { echo "FAIL - URL de origem deve usar HTTPS"; exit 1; }
[[ "$NEW_URL" =~ ^https:// ]] || { echo "FAIL - URL de destino deve usar HTTPS"; exit 1; }
[[ "$OLD_URL" != "$NEW_URL" ]] || { echo "FAIL - URLs devem ser diferentes"; exit 1; }

wpcli() {
  docker compose run --rm wpcli wp "$@"
}

CURRENT_HOME="$(wpcli option get home)"
CURRENT_SITEURL="$(wpcli option get siteurl)"

echo "=================================================="
echo "M5 - PLANO DE MIGRACAO DE DOMINIO (DRY-RUN)"
echo "=================================================="
echo "Home atual:    $CURRENT_HOME"
echo "Siteurl atual: $CURRENT_SITEURL"
echo "Origem:        $OLD_URL"
echo "Destino:       $NEW_URL"
echo

echo "=== SEARCH-REPLACE DRY-RUN ==="
wpcli search-replace \
  "$OLD_URL" \
  "$NEW_URL" \
  --all-tables-with-prefix \
  --skip-columns=guid \
  --precise \
  --dry-run

echo
echo "PASS - dry-run concluido; nenhuma alteracao foi persistida."
echo "STOP - troca real de dominio exige gate manual W21."
