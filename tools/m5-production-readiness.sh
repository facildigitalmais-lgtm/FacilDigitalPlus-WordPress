#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

wpcli() {
  docker compose run --rm wpcli wp "$@"
}

echo "=================================================="
echo "M5 - PRODUCTION READINESS"
echo "=================================================="

wpcli facil-digital release check --stage=production --format=json

echo
echo "PASS - checks automaticos de producao aprovados."
echo "STOP - ainda sao obrigatorios pagamento real controlado e autorizacao humana W22/W23."
