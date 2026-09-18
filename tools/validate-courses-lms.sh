#!/usr/bin/env bash

set -euo pipefail

ROOT="$(
  cd "$(
    dirname "${BASH_SOURCE[0]}"
  )/.."
  pwd
)"

cd "$ROOT"

fail() {
  echo "FAIL - $1" >&2
  exit 1
}

pass() {
  echo "PASS  $1"
}

echo
echo "=================================================="
echo "FACIL DIGITAL+ COURSES LMS - VALIDACAO CUMULATIVA"
echo "=================================================="

echo
echo "=== BASELINE W3A ==="

[[ -f tools/validate-w3a.sh ]] \
  || fail "validate-w3a.sh ausente"

bash -n tools/validate-w3a.sh \
  || fail "sintaxe validate-w3a.sh"

./tools/validate-w3a.sh \
  || fail "baseline W3A"

pass "baseline W3A preservada"

echo
echo "=== COURSES SCHEMA FOUNDATION ==="

[[ -f tools/test-courses-schema.php ]] \
  || fail "teste courses-schema ausente"

if ! docker compose run --rm wpcli \
  wp eval-file \
  /workspace/tools/test-courses-schema.php \
  --use-include \
  2>&1 \
  | tee /tmp/fd-courses-schema.log
then
  fail "teste funcional courses-schema"
fi

grep -q '^COURSES_SCHEMA_IDEMPOTENT=PASS$' \
  /tmp/fd-courses-schema.log \
  || fail "schema LMS nao confirmou idempotencia"

grep -q '^COURSES_SCHEMA_FOUNDATION=PASS$' \
  /tmp/fd-courses-schema.log \
  || fail "schema LMS nao confirmou foundation"

grep -q '^SCHEMA_TEST_NO_FIXTURES=OK$' \
  /tmp/fd-courses-schema.log \
  || fail "estado do teste de schema nao confirmado"

pass "schema LMS 1.2.0 e tabelas legadas preservados"

echo
echo "=== PHP / SHELL / GIT ==="

while IFS= read -r file; do
  docker compose exec -T wordpress \
    php -l "/workspace/$file" \
    >/dev/null
done < <(
  find \
    wp-content/plugins/facil-digital-core \
    -type f \
    -name '*.php' \
    -not -path '*/vendor/*' \
    | sort
)

docker compose exec -T wordpress \
  php -l \
  /workspace/tools/test-courses-schema.php \
  >/dev/null

bash -n tools/validate-courses-lms.sh
git diff --check

pass "sintaxe PHP, shell e git diff check"

echo
echo "=================================================="
echo "PASS - COURSES LMS ETAPA ATUAL VALIDADA"
echo "=================================================="