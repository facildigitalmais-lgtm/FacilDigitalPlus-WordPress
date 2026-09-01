#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

ORDER_ID="${1:-}"
[[ "$ORDER_ID" =~ ^[0-9]+$ ]] || {
  echo "Uso: ./tools/m5-sandbox-gate.sh <order_id>"
  exit 2
}

wpcli() {
  docker compose run --rm wpcli wp "$@"
}

echo "=== PRONTIDAO AUTOMATICA SANDBOX ==="
wpcli facil-digital release check --stage=sandbox --format=json

echo
echo "=== PROVA E2E DO PAGAMENTO ==="
wpcli facil-digital release payment --order="$ORDER_ID" --require-pdf=1

echo
echo "=================================================="
echo "PASS - GATE TECNICO W20"
echo "=================================================="
echo "Confirme manualmente que a compra foi feita com comprador de teste distinto"
echo "e que o download foi realizado pela conta do aluno."
