#!/usr/bin/env bash
set -euo pipefail

ROOT="$(pwd)"

[[ -f .env ]] || { echo "FAIL - .env nao existe"; exit 1; }
set -a
# shellcheck disable=SC1091
source .env
set +a

wpcli() { docker compose run --rm wpcli wp "$@"; }
pass() { echo "PASS  $1"; }
fail() { echo "FAIL  $1"; exit 1; }

echo "=================================================="
echo "W3A - DATABASE / INSTALLER / MIGRATIONS"
echo "=================================================="

echo
echo "=== REGRESSAO W2C ==="
if ! ./tools/validate-w2c.sh 2>&1 | tee /tmp/fd-w3a-w2c.log
then
  fail "regressao W2C"
fi

pass "W2C intacta"

echo
echo "=== COMPOSER ==="
docker compose run --rm composer validate --no-check-publish >/dev/null
docker compose run --rm composer dump-autoload --optimize >/dev/null
pass "autoload atualizado"

echo
echo "=== CORE ==="
wpcli plugin is-active facil-digital-core >/dev/null || fail "Core inativo"
CORE_VERSION="$(wpcli plugin get facil-digital-core --field=version)"
LOWEST_CORE_VERSION="$(printf '%s
%s
' '0.2.0' "$CORE_VERSION" | sort -V | head -n1)"
[[ "$LOWEST_CORE_VERSION" == "0.2.0" ]] || fail "Core esperado >= 0.2.0; atual: $CORE_VERSION"
pass "Core >= 0.2.0 ($CORE_VERSION)"

echo
echo "=== MIGRATIONS ==="
wpcli eval '\FacilDigital\Core\Core\Migrations::run();'
wpcli eval '\FacilDigital\Core\Core\Migrations::run();'
DECLARED_SCHEMA_VERSION="$(wpcli eval 'echo \FacilDigital\Core\Core\Database::SCHEMA_VERSION;')"
SCHEMA_VERSION="$(wpcli eval 'echo \FacilDigital\Core\Core\Database::installedVersion();')"
[[ "$SCHEMA_VERSION" == "$DECLARED_SCHEMA_VERSION" ]] || fail "schema instalado $SCHEMA_VERSION difere do declarado $DECLARED_SCHEMA_VERSION"
READY="$(wpcli eval 'echo \FacilDigital\Core\Core\Database::isReady() ? "yes" : "no";')"
[[ "$READY" == "yes" ]] || fail "Database::isReady() retornou false"
pass "schema declarado e instalado alinhados; migration idempotente"

echo
echo "=== TABELAS ==="
COUNT="$(wpcli eval 'echo count(\FacilDigital\Core\Core\Database::tables());')"
[[ "$COUNT" -ge "10" ]] || fail "esperadas ao menos 10 tabelas; atual: $COUNT"
wpcli eval '
  global $wpdb;
  $tables = \FacilDigital\Core\Core\Database::tables();
  $requiredLegacy = [
      "questions",
      "question_options",
      "simulations",
      "simulation_questions",
      "simulation_products",
      "attempts",
      "attempt_answers",
      "entitlements",
      "pdf_files",
      "downloads",
  ];
  foreach ($requiredLegacy as $key) {
      if (!isset($tables[$key])) {
          fwrite(STDERR, "Tabela legada ausente do mapa: {$key}" . PHP_EOL);
          exit(1);
      }
  }
  $missing = \FacilDigital\Core\Core\Database::missingTables();
  if ($missing !== []) {
      fwrite(STDERR, "Ausentes: " . implode(", ", $missing) . PHP_EOL);
      exit(1);
  }
  foreach ($tables as $key => $table) {
      if (strpos($table, $wpdb->prefix) !== 0) {
          fwrite(STDERR, "Prefixo incorreto: {$key}" . PHP_EOL);
          exit(1);
      }
      echo $table . PHP_EOL;
  }
'
pass "tabelas legadas preservadas e prefixo dinamico"

if grep -R --line-number --fixed-string 'wp_fd_' wp-content/plugins/facil-digital-core/src >/tmp/fd-w3a-prefix.log; then
  cat /tmp/fd-w3a-prefix.log
  fail "prefixo wp_fd_ hardcoded"
fi
pass "sem prefixo wp_ hardcoded"

echo
echo "=== COLUNAS CRITICAS ==="
wpcli eval '
  global $wpdb;
  $required = [
      "questions" => ["statement", "subject", "status"],
      "question_options" => ["question_id", "option_key", "is_correct"],
      "simulations" => ["slug", "duration_seconds", "attempt_limit"],
      "simulation_questions" => ["simulation_id", "question_id"],
      "simulation_products" => ["simulation_id", "product_id", "created_at"],
      "attempts" => ["simulation_id", "user_id", "expires_at", "percentage"],
      "attempt_answers" => ["attempt_id", "question_id", "selected_option_id"],
      "entitlements" => ["user_id", "product_id", "order_id", "status"],
      "pdf_files" => ["entitlement_id", "storage_key", "tracking_code", "status"],
      "downloads" => ["entitlement_id", "pdf_file_id", "ip_hash", "user_agent_hash"],
  ];
  foreach ($required as $tableKey => $columns) {
      $table = \FacilDigital\Core\Core\Database::table($tableKey);
      $rows = $wpdb->get_results("SHOW COLUMNS FROM `{$table}`", ARRAY_A);
      $actual = array_column($rows, "Field");
      foreach ($columns as $column) {
          if (!in_array($column, $actual, true)) {
              fwrite(STDERR, "Coluna ausente {$tableKey}.{$column}" . PHP_EOL);
              exit(1);
          }
      }
      foreach ($actual as $column) {
          if (stripos($column, "cpf") !== false) {
              fwrite(STDERR, "CPF indevido em {$tableKey}.{$column}" . PHP_EOL);
              exit(1);
          }
      }
  }
'
pass "schema critico e LGPD estrutural"

echo
echo "=== SIMULATION PRODUCT LINKS ==="

[[ -f tools/test-simulation-product-link.php ]] \
  || fail "teste simulation-product-link ausente"

if ! wpcli eval-file \
    /workspace/tools/test-simulation-product-link.php \
    --use-include \
    2>&1 | tee /tmp/fd-simulation-product-link.log
then
  fail "teste funcional simulation-product-link"
fi

grep -q '^SIMULATION_PRODUCT_LINK_FUNCTIONAL=PASS$' \
  /tmp/fd-simulation-product-link.log \
  || fail "marcador funcional simulation-product-link ausente"

grep -q '^FIXTURES_CLEANUP=OK$' \
  /tmp/fd-simulation-product-link.log \
  || fail "cleanup simulation-product-link nao confirmado"

pass "vinculo explicito de apostila e fallback legado"

echo
echo "=== APOSTILA PASSWORD GUIDANCE ==="

[[ -f tools/test-apostila-password-guidance.php ]] || fail "teste apostila-password-guidance ausente"

if ! wpcli eval-file /workspace/tools/test-apostila-password-guidance.php --use-include 2>&1 | tee /tmp/fd-apostila-password-guidance.log; then
  fail "teste funcional apostila-password-guidance"
fi

grep -q "^APOSTILA_PASSWORD_GUIDANCE_FUNCTIONAL=PASS$" /tmp/fd-apostila-password-guidance.log || fail "marcador funcional apostila-password-guidance ausente"

grep -q "^FIXTURES_CLEANUP=OK$" /tmp/fd-apostila-password-guidance.log || fail "cleanup apostila-password-guidance nao confirmado"

pass "orientacao de senha por CPF na area do aluno"

echo
echo "=== APOSTILA READY EMAIL ==="

[[ -f tools/test-apostila-ready-email.php ]] || fail "teste apostila-ready-email ausente"

if ! wpcli eval-file /workspace/tools/test-apostila-ready-email.php --use-include 2>&1 | tee /tmp/fd-apostila-ready-email.log
then
  fail "teste funcional apostila-ready-email"
fi

grep -q "^APOSTILA_READY_EMAIL_FUNCTIONAL=PASS$" /tmp/fd-apostila-ready-email.log || fail "marcador funcional apostila-ready-email ausente"

grep -q "^FIXTURES_CLEANUP=OK$" /tmp/fd-apostila-ready-email.log || fail "cleanup apostila-ready-email nao confirmado"

pass "email de apostila pronta, privacidade e idempotencia"

echo
echo "=== PHP / SHELL / GIT ==="
while IFS= read -r file; do
  docker compose exec -T wordpress php -l "/workspace/$file" >/dev/null
done < <(find wp-content/plugins/facil-digital-core -type f -name '*.php' -not -path '*/vendor/*' | sort)
bash -n tools/validate-w3a.sh
git diff --check
pass "sintaxe e git diff check"

echo
echo "=================================================="
echo "PASS - W3A VALIDADA"
echo "=================================================="
