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

cleanup() {
  wpcli eval 'require "/workspace/tools/cleanup-m4.php";' >/dev/null 2>&1 || true
}
trap cleanup EXIT

echo "=================================================="
echo "M4 - W16/W17/W18/W19"
echo "ADMIN / IMPORTACAO / HARDENING / QA E CARGA"
echo "=================================================="
echo

echo "=== REGRESSAO M3 ==="
if ! ./tools/validate-m3.sh 2>&1 | tee /tmp/fd-m4-m3.log
then
  fail "regressao M3"
fi
pass "M3 intacto"

echo
echo "=== COMPOSER ==="
docker compose run --rm composer validate --no-check-publish >/dev/null
docker compose run --rm composer dump-autoload --optimize >/dev/null
pass "autoload atualizado"

echo
echo "=== CORE ==="
CORE_VERSION="$(wpcli plugin get facil-digital-core --field=version)"
[[ "$CORE_VERSION" == "0.8.0" ]] || fail "Core esperado 0.8.0; atual: $CORE_VERSION"
pass "Core 0.8.0"

echo
echo "=== ESTRUTURA M4 ==="
required=(
  wp-content/plugins/facil-digital-core/src/Admin/DashboardService.php
  wp-content/plugins/facil-digital-core/src/Admin/OperationsAdminModule.php
  wp-content/plugins/facil-digital-core/src/Import/QuestionCsvService.php
  wp-content/plugins/facil-digital-core/src/Import/ImportAdminModule.php
  wp-content/plugins/facil-digital-core/src/CLI/QuestionImportCommand.php
  wp-content/plugins/facil-digital-core/src/CLI/QaCommand.php
  wp-content/plugins/facil-digital-core/src/Security/SecurityAudit.php
  wp-content/plugins/facil-digital-core/src/Support/QaService.php
  tools/test-m4-import.php
  tools/test-m4-load.php
  tools/cleanup-m4.php
)
for file in "${required[@]}"; do
  [[ -f "$file" ]] || fail "arquivo M4 ausente: $file"
done
pass "estrutura M4 presente"

echo
echo "=== MODULOS ==="
wpcli eval '
use FacilDigital\Core\Core\ModuleRegistry;
$classes = array_map(static fn($m) => get_class($m), ModuleRegistry::defaults());
$required = [
  FacilDigital\Core\Admin\OperationsAdminModule::class,
  FacilDigital\Core\Import\ImportAdminModule::class,
  FacilDigital\Core\CLI\QuestionImportCommand::class,
  FacilDigital\Core\CLI\QaCommand::class,
];
foreach ($required as $class) {
  if (!in_array($class, $classes, true)) {
    fwrite(STDERR, "Modulo ausente: {$class}\n");
    exit(1);
  }
}
'
pass "admin, importacao e QA registrados no ModuleRegistry"

echo
echo "=== ADMIN / CAPABILITIES ==="
wpcli eval '
use FacilDigital\Core\Admin\OperationsAdminModule;
use FacilDigital\Core\Core\Capabilities;
use FacilDigital\Core\Import\ImportAdminModule;
$admin = get_users(["role" => "administrator", "number" => 1]);
if (!$admin) { exit(1); }
wp_set_current_user((int) $admin[0]->ID);
do_action("admin_menu");
global $submenu;
$items = $submenu["facil-digital"] ?? [];
$slugs = array_map(static fn($row) => (string) ($row[2] ?? ""), $items);
foreach ([
  OperationsAdminModule::RESULTS_SLUG,
  OperationsAdminModule::RANKINGS_SLUG,
  OperationsAdminModule::PDFS_SLUG,
  OperationsAdminModule::DOWNLOADS_SLUG,
  OperationsAdminModule::STUDENTS_SLUG,
  OperationsAdminModule::REPORTS_SLUG,
  OperationsAdminModule::SECURITY_SLUG,
  ImportAdminModule::SLUG,
] as $slug) {
  if (!in_array($slug, $slugs, true)) { fwrite(STDERR, "Menu ausente: {$slug}\n"); exit(1); }
}
$manager = get_role(Capabilities::ROLE_MANAGER);
$editor = get_role(Capabilities::ROLE_QUESTION_EDITOR);
if (!$manager instanceof WP_Role || !$editor instanceof WP_Role) { exit(1); }
if (!$manager->has_cap(Capabilities::VIEW_REPORTS) || !$manager->has_cap(Capabilities::MANAGE_STUDENTS)) { exit(1); }
if (!$editor->has_cap(Capabilities::MANAGE_QUESTIONS) || $editor->has_cap(Capabilities::VIEW_REPORTS)) { exit(1); }
'
pass "menu operacional e menor privilegio preservados"

echo
echo "=== FIXTURE OPERACIONAL ==="
cleanup
wpcli eval 'require "/workspace/tools/seed-m3.php";' >/tmp/fd-m4-seed.json
wpcli eval 'require "/workspace/tools/test-m3-engine.php";' >/tmp/fd-m4-engine.json
pass "fixture M3 reutilizada para metricas e QA"

echo
echo "=== DASHBOARD ADMIN ==="
wpcli eval '
use FacilDigital\Core\Admin\DashboardService;
$m = (new DashboardService())->snapshot();
$required = ["sales_today","orders_today","students","apostilas","questions","simulations","attempts","completed_attempts","average_percentage","ready_pdfs","downloads"];
foreach ($required as $key) {
  if (!array_key_exists($key, $m) || !is_numeric($m[$key])) { fwrite(STDERR, "Metrica invalida: {$key}\n"); exit(1); }
}
if ((int) $m["questions"] < 5 || (int) $m["simulations"] < 1 || (int) $m["completed_attempts"] < 1) { exit(1); }
'
pass "dashboard operacional calcula metricas reais"

echo
echo "=== IMPORTACAO CSV ==="
wpcli eval 'require "/workspace/tools/test-m4-import.php";' >/tmp/fd-m4-import.json
cat /tmp/fd-m4-import.json
python3 - <<'PY'
import json
from pathlib import Path
p = json.loads(Path('/tmp/fd-m4-import.json').read_text())
assert p['status'] == 'ok'
assert p['dry_valid'] == 2
assert p['dry_invalid'] == 1
assert p['created'] == 2
assert p['exported'] >= 2
PY
pass "dry-run, importacao, validacao e exportacao CSV funcionais"

echo
echo "=== WP-CLI M4 ==="
wpcli eval '
if (!class_exists("WP_CLI")) { exit(1); }
'
# O help confirma que os comandos foram registrados sem disparar importacao real.
docker compose run --rm -e PAGER=cat wpcli wp help facil-digital qa >/dev/null
docker compose run --rm -e PAGER=cat wpcli wp help facil-digital security-audit >/dev/null
docker compose run --rm -e PAGER=cat wpcli wp help facil-digital questions import >/dev/null
docker compose run --rm -e PAGER=cat wpcli wp help facil-digital questions export >/dev/null
pass "comandos QA, security-audit e import/export registrados"

echo
echo "=== HARDENING ==="
wpcli facil-digital security-audit --format=json >/tmp/fd-m4-security.json
python3 - <<'PY'
import json
from pathlib import Path
p = json.loads(Path('/tmp/fd-m4-security.json').read_text())
assert p['ready'] is True, p
ids = {c['id']: c['status'] for c in p['checks']}
for required in ['database','capabilities','woocommerce','mercado_pago_official','private_storage','least_privilege','wordpress_keys']:
    assert ids.get(required) == 'pass', (required, ids.get(required))
PY
pass "auditoria de seguranca aprovada"

echo
echo "=== QA INTEGRIDADE ==="
wpcli facil-digital qa --format=json >/tmp/fd-m4-qa.json
python3 - <<'PY'
import json
from pathlib import Path
p = json.loads(Path('/tmp/fd-m4-qa.json').read_text())
assert p['ready'] is True, p
assert p['security_ready'] is True
assert all(int(v) == 0 for v in p['integrity'].values()), p['integrity']
PY
pass "integridade relacional, seguranca e consultas criticas aprovadas"

echo
echo "=== CARGA / PAGINACAO ==="
wpcli eval 'require "/workspace/tools/test-m4-load.php";' >/tmp/fd-m4-load.json
cat /tmp/fd-m4-load.json
python3 - <<'PY'
import json
from pathlib import Path
p = json.loads(Path('/tmp/fd-m4-load.json').read_text())
assert p['status'] == 'ok'
assert p['questions'] == 120
assert float(p['query_ms']) < 20000
PY
pass "120 questoes temporarias, filtros e paginacao sob carga"

echo
echo "=== PRIVACIDADE ADMIN ==="
# O módulo pode mencionar nomes de campos privados em texto explicativo,
# mas nunca deve selecioná-los nem acessar índices de linha com esses nomes.
if grep -nE 'SELECT[^;]*(storage_key|ip_hash|user_agent_hash)' \
  wp-content/plugins/facil-digital-core/src/Admin/OperationsAdminModule.php \
  || grep -nE '\$row\[[^]]*(storage_key|ip_hash|user_agent_hash|billing_cpf)' \
  wp-content/plugins/facil-digital-core/src/Admin/OperationsAdminModule.php
then
  fail "painel operacional expoe campo privado"
fi
pass "painel nao seleciona storage key, CPF ou hashes de telemetria"

echo
echo "=== HARDENING ESTATICO ==="
if grep -R -nE 'chmod\([^;]*0?777|chmod[[:space:]]+-R[[:space:]]+777' \
  wp-content/plugins/facil-digital-core tools --include='*.php' --include='*.sh'
then
  fail "permissao 777 encontrada"
fi
if grep -R -nE '(ACCESS_TOKEN|MERCADO_PAGO_TOKEN|MP_ACCESS_TOKEN)[[:space:]]*=[[:space:]]*[^[:space:]]{20,}' \
  . --exclude-dir=.git --exclude-dir=vendor --exclude='*.lock' 2>/dev/null
then
  fail "possivel segredo Mercado Pago versionado"
fi
pass "sem chmod 777 ou token financeiro aparente"

echo
echo "=== PHP / SHELL / GIT ==="
while IFS= read -r -d '' file; do
  docker compose exec -T wordpress php -l "/workspace/${file}" >/dev/null
done < <(find wp-content/plugins/facil-digital-core/src -name '*.php' -print0)
for file in tools/validate-m1.sh tools/validate-m2.sh tools/validate-m3.sh tools/validate-m4.sh; do
  bash -n "$file"
done
git diff --check
if git ls-files '.runtime/*' | grep -q .; then
  fail "runtime privado versionado"
fi
pass "sintaxe, runtime privado e git diff check"

echo
echo "=================================================="
echo "PASS - M4 VALIDADO"
echo "=================================================="
