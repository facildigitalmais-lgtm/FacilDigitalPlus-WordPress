#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

[[ -f .env ]] || { echo "FAIL - .env ausente"; exit 1; }
set -a
# shellcheck disable=SC1091
source .env
set +a

wpcli() {
  docker compose run --rm wpcli wp "$@"
}

pass() { echo "PASS  $1"; }
fail() { echo "FAIL  $1"; exit 1; }

echo "=================================================="
echo "M5 - W20/W21/W22/W23"
echo "SANDBOX / DOMINIO / PAGAMENTO REAL / PRODUCAO"
echo "=================================================="
echo

echo "=== REGRESSAO M4 ==="
if ! ./tools/validate-m4.sh 2>&1 | tee /tmp/fd-m5-m4.log; then
  fail "regressao M4"
fi
pass "M4 intacto"

echo
echo "=== COMPOSER ==="
docker compose run --rm composer validate --no-check-publish >/dev/null
docker compose run --rm composer dump-autoload --optimize >/dev/null
pass "autoload atualizado"

echo
echo "=== CORE ==="
CORE_VERSION="$(wpcli plugin get facil-digital-core --field=version)"
[[ "$CORE_VERSION" == "0.9.0" ]] || fail "Core esperado 0.9.0; atual: $CORE_VERSION"
pass "Core 0.9.0"

echo
echo "=== ESTRUTURA M5 ==="
required=(
  wp-content/plugins/facil-digital-core/src/Release/ReleaseReadinessService.php
  wp-content/plugins/facil-digital-core/src/Release/ReleaseCommand.php
  wp-content/plugins/facil-digital-core/src/Release/ReleaseAdminModule.php
  tools/test-m5-release.php
  tools/m5-domain-plan.sh
  tools/m5-sandbox-gate.sh
  tools/m5-production-readiness.sh
  docs/M5-GO-LIVE.md
)
for file in "${required[@]}"; do
  [[ -f "$file" ]] || fail "arquivo M5 ausente: $file"
done
pass "estrutura M5 presente"

echo
echo "=== MODULOS / CLI ==="
wpcli eval '
use FacilDigital\Core\Core\ModuleRegistry;
$classes = array_map(static fn($m) => get_class($m), ModuleRegistry::defaults());
foreach ([
  FacilDigital\Core\Release\ReleaseCommand::class,
  FacilDigital\Core\Release\ReleaseAdminModule::class,
] as $class) {
  if (!in_array($class, $classes, true)) {
    fwrite(STDERR, "Modulo ausente: {$class}\n");
    exit(1);
  }
}
'
docker compose run --rm -e PAGER=cat wpcli wp help facil-digital release check >/dev/null
docker compose run --rm -e PAGER=cat wpcli wp help facil-digital release payment >/dev/null
pass "release command e painel Go-live registrados"

echo
echo "=== READINESS SERVICE ==="
wpcli eval 'require "/workspace/tools/test-m5-release.php";' >/tmp/fd-m5-release.json
cat /tmp/fd-m5-release.json
python3 - <<'PY'
import json
from pathlib import Path
p = json.loads(Path('/tmp/fd-m5-release.json').read_text())
assert p['status'] == 'ok', p
assert p['sandbox_manual_gates'] >= 8, p
assert p['production_manual_gates'] >= 10, p
PY
pass "gates automaticos e manuais definidos; pagamento inexistente falha fechado"

echo
echo "=== SANDBOX AUTOMATICO ==="
set +e
wpcli facil-digital release check --stage=sandbox --format=json >/tmp/fd-m5-sandbox.json
SANDBOX_RC=$?
set -e
cat /tmp/fd-m5-sandbox.json
[[ $SANDBOX_RC -eq 0 ]] || fail "prontidao automatica sandbox bloqueada"
python3 - <<'PY'
import json
from pathlib import Path
p = json.loads(Path('/tmp/fd-m5-sandbox.json').read_text())
assert p['ready_automated'] is True, p
ids = {c['id']: c['status'] for c in p['checks']}
for k in ['core_version','woocommerce','mercado_pago_official','https_urls','action_scheduler','private_storage','rest_health']:
    assert ids.get(k) == 'pass', (k, ids.get(k))
PY
pass "sandbox tecnicamente pronto; transacao permanece gate manual"

echo
echo "=== PRODUCAO FAIL-CLOSED EM DEV ==="
ENVIRONMENT="$(wpcli eval 'echo wp_get_environment_type();')"
if [[ "$ENVIRONMENT" != "production" ]]; then
  set +e
  wpcli facil-digital release check --stage=production --format=json >/tmp/fd-m5-production.json
  PROD_RC=$?
  set -e
  cat /tmp/fd-m5-production.json
  [[ $PROD_RC -ne 0 ]] || fail "producao deveria bloquear fora de environment=production"
  python3 - <<'PY'
import json
from pathlib import Path
p = json.loads(Path('/tmp/fd-m5-production.json').read_text())
assert p['ready_automated'] is False, p
ids = {c['id']: c['status'] for c in p['checks']}
assert ids.get('environment') == 'fail', ids
PY
  pass "go-live de producao bloqueia corretamente no ambiente DEV"
else
  pass "ambiente ja production; fail-closed DEV nao aplicavel"
fi

echo
echo "=== PAGAMENTO PROOF FAIL-CLOSED ==="
set +e
wpcli facil-digital release payment --order=999999999 --require-pdf=1 >/tmp/fd-m5-payment-missing.json
PAY_RC=$?
set -e
[[ $PAY_RC -ne 0 ]] || fail "pedido inexistente aceito pelo release payment"
python3 - <<'PY'
import json
from pathlib import Path
p = json.loads(Path('/tmp/fd-m5-payment-missing.json').read_text())
assert p['ready'] is False
assert p['reason'] == 'order_not_found'
PY
pass "prova de pagamento falha fechada"

echo
echo "=== PLANO DE DOMINIO SEM MUTACAO ==="
CURRENT_HOME="$(wpcli option get home)"
BEFORE_HOME="$CURRENT_HOME"
BEFORE_SITEURL="$(wpcli option get siteurl)"
./tools/m5-domain-plan.sh "$CURRENT_HOME" "https://m5-target.example" >/tmp/fd-m5-domain-plan.log
AFTER_HOME="$(wpcli option get home)"
AFTER_SITEURL="$(wpcli option get siteurl)"
[[ "$BEFORE_HOME" == "$AFTER_HOME" && "$BEFORE_SITEURL" == "$AFTER_SITEURL" ]] || fail "domain plan alterou URLs"
grep -q "dry-run concluido" /tmp/fd-m5-domain-plan.log || fail "domain plan nao confirmou dry-run"
pass "migracao de dominio permanece dry-run ate gate manual W21"

echo
echo "=== SEGREDOS / HARDENING ==="
if git grep -nE '(TEST-|APP_USR-|PROD_ACCESS_TOKEN|MP_ACCESS_TOKEN|MERCADO_PAGO_ACCESS_TOKEN)[A-Za-z0-9._-]{12,}' -- ':!*.lock' ':!docs/*' 2>/dev/null; then
  fail "possivel credencial Mercado Pago versionada"
fi
if grep -R -nE 'search-replace.+--all-tables-with-prefix' tools/m5-domain-plan.sh | grep -v -- '--dry-run' >/dev/null 2>&1; then
  : # comando e --dry-run ficam em linhas distintas; verificacao funcional acima e a autoridade.
fi
pass "nenhum token financeiro aparente versionado"

echo
echo "=== PHP / SHELL / GIT ==="
while IFS= read -r -d '' file; do
  docker compose exec -T wordpress php -l "/workspace/${file}" >/dev/null
done < <(find wp-content/plugins/facil-digital-core/src/Release -name '*.php' -print0)
for file in tools/validate-m5.sh tools/m5-domain-plan.sh tools/m5-sandbox-gate.sh tools/m5-production-readiness.sh; do
  bash -n "$file"
done
git diff --check
if git ls-files '.runtime/*' | grep -q .; then
  fail "runtime privado versionado"
fi
pass "sintaxe, runtime privado e git diff check"

echo
echo "=================================================="
echo "PASS - M5 AUTOMACAO VALIDADA"
echo "=================================================="
echo
echo "STOP - W20 ainda exige compra real de TESTE no Mercado Pago."
echo "Depois da compra aprovada, execute:"
echo "  ./tools/m5-sandbox-gate.sh <ORDER_ID>"
echo
echo "Somente depois desse gate seguiremos para W21/W22/W23 em ambiente real."
