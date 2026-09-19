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
echo "=== COURSES DOMAIN REPOSITORIES ==="

[[ -f tools/test-courses-domain.php ]] \
  || fail "teste courses-domain ausente"

if ! docker compose run --rm wpcli \
  wp eval-file \
  /workspace/tools/test-courses-domain.php \
  --use-include \
  2>&1 \
  | tee /tmp/fd-courses-domain.log
then
  fail "teste funcional courses-domain"
fi

grep -q '^COURSES_RELATION_INTEGRITY=PASS$' \
  /tmp/fd-courses-domain.log \
  || fail "integridade relacional do dominio nao confirmada"

grep -q '^COURSES_REPOSITORIES_CRUD=PASS$' \
  /tmp/fd-courses-domain.log \
  || fail "CRUD dos repositories nao confirmado"

grep -q '^FIXTURES_CLEANUP=OK$' \
  /tmp/fd-courses-domain.log \
  || fail "cleanup courses-domain nao confirmado"

pass "CRUD de cursos, modulos e aulas"

echo
echo "=== LESSON RESOURCES / ENROLLMENTS ==="

[[ -f tools/test-courses-resources-enrollments.php ]] \
  || fail "teste resources-enrollments ausente"

if ! docker compose run --rm wpcli \
  wp eval-file \
  /workspace/tools/test-courses-resources-enrollments.php \
  --use-include \
  2>&1 \
  | tee /tmp/fd-courses-resources-enrollments.log
then
  fail "teste funcional resources-enrollments"
fi

grep -q '^COURSES_RESOURCE_INTEGRITY=PASS$' \
  /tmp/fd-courses-resources-enrollments.log \
  || fail "integridade dos recursos nao confirmada"

grep -q '^COURSES_ENROLLMENT_IDEMPOTENCY=PASS$' \
  /tmp/fd-courses-resources-enrollments.log \
  || fail "idempotencia de matricula nao confirmada"

grep -q '^COURSES_RESOURCES_ENROLLMENTS=PASS$' \
  /tmp/fd-courses-resources-enrollments.log \
  || fail "resources-enrollments nao confirmado"

grep -q '^FIXTURES_CLEANUP=OK$' \
  /tmp/fd-courses-resources-enrollments.log \
  || fail "cleanup resources-enrollments nao confirmado"

pass "recursos privados e persistencia de matriculas"

echo
echo "=== PROGRESS / CERTIFICATES ==="

[[ -f tools/test-courses-progress-certificates.php ]] \
  || fail "teste progress-certificates ausente"

if ! docker compose run --rm wpcli \
  wp eval-file \
  /workspace/tools/test-courses-progress-certificates.php \
  --use-include \
  2>&1 \
  | tee /tmp/fd-courses-progress-certificates.log
then
  fail "teste funcional progress-certificates"
fi

grep -q '^COURSES_PROGRESS_IDEMPOTENCY=PASS$' \
  /tmp/fd-courses-progress-certificates.log \
  || fail "idempotencia do progresso nao confirmada"

grep -q '^COURSES_CERTIFICATE_IDEMPOTENCY=PASS$' \
  /tmp/fd-courses-progress-certificates.log \
  || fail "idempotencia do certificado nao confirmada"

grep -q '^COURSES_PROGRESS_CERTIFICATES=PASS$' \
  /tmp/fd-courses-progress-certificates.log \
  || fail "progress-certificates nao confirmado"

grep -q '^FIXTURES_CLEANUP=OK$' \
  /tmp/fd-courses-progress-certificates.log \
  || fail "cleanup progress-certificates nao confirmado"

pass "persistencia de progresso e certificados"

echo
echo "=== COURSE / WOOCOMMERCE PRODUCTS ==="

[[ -f tools/test-courses-products.php ]] \
  || fail "teste courses-products ausente"

if ! docker compose run --rm wpcli \
  wp eval-file \
  /workspace/tools/test-courses-products.php \
  --use-include \
  2>&1 \
  | tee /tmp/fd-courses-products.log
then
  fail "teste funcional courses-products"
fi

grep -q '^COURSES_PRODUCT_LINKAGE=PASS$' \
  /tmp/fd-courses-products.log \
  || fail "vinculo curso-produto nao confirmado"

grep -q '^COURSES_PRODUCT_COEXISTENCE=PASS$' \
  /tmp/fd-courses-products.log \
  || fail "coexistencia de produtos nao confirmada"

grep -q '^COURSES_WOOCOMMERCE_INTEGRATION=PASS$' \
  /tmp/fd-courses-products.log \
  || fail "integracao WooCommerce nao confirmada"

grep -q '^FIXTURES_CLEANUP=OK$' \
  /tmp/fd-courses-products.log \
  || fail "cleanup courses-products nao confirmado"

pass "curso vinculado a produto WooCommerce"

echo
echo "=== COURSE BUILDER ADMIN ==="

[[ -f tools/test-courses-admin-builder.php ]] \
  || fail "teste courses-admin-builder ausente"

if ! docker compose run --rm wpcli \
  wp eval-file \
  /workspace/tools/test-courses-admin-builder.php \
  --use-include \
  2>&1 \
  | tee /tmp/fd-courses-admin-builder.log
then
  fail "teste funcional courses-admin-builder"
fi

grep -q '^COURSES_ADMIN_SECURITY=PASS$' \
  /tmp/fd-courses-admin-builder.log \
  || fail "seguranca do Course Builder nao confirmada"

grep -q '^COURSES_CURRICULUM_BUILDER=PASS$' \
  /tmp/fd-courses-admin-builder.log \
  || fail "curriculo do Course Builder nao confirmado"

grep -q '^COURSES_ADMIN_BUILDER=PASS$' \
  /tmp/fd-courses-admin-builder.log \
  || fail "Course Builder administrativo nao confirmado"

grep -q '^FIXTURES_CLEANUP=OK$' \
  /tmp/fd-courses-admin-builder.log \
  || fail "cleanup Course Builder nao confirmado"

pass "Course Builder administrativo"

echo
echo "=== STUDENT COURSES / LEARNING ==="

[[ -f tools/test-courses-learning.php ]] \
  || fail "teste courses-learning ausente"

if ! docker compose run --rm wpcli \
  wp eval-file \
  /workspace/tools/test-courses-learning.php \
  --use-include \
  2>&1 \
  | tee /tmp/fd-courses-learning.log
then
  fail "teste funcional courses-learning"
fi

grep -q '^COURSES_COMMERCE_ENROLLMENT=PASS$' \
  /tmp/fd-courses-learning.log \
  || fail "matricula comercial nao confirmada"

grep -q '^COURSES_LEARNING_PROGRESS=PASS$' \
  /tmp/fd-courses-learning.log \
  || fail "progresso de aprendizagem nao confirmado"

grep -q '^COURSES_CERTIFICATE_TRIGGER=PASS$' \
  /tmp/fd-courses-learning.log \
  || fail "gatilho de certificado nao confirmado"

grep -q '^COURSES_ACCOUNT_ENDPOINTS=PASS$' \
  /tmp/fd-courses-learning.log \
  || fail "endpoints da area do aluno nao confirmados"

grep -q '^FIXTURES_CLEANUP=OK$' \
  /tmp/fd-courses-learning.log \
  || fail "cleanup courses-learning nao confirmado"

pass "matricula, area do aluno, aprendizagem e certificados"

echo
echo "=== PRIVATE RESOURCES / CERTIFICATE DELIVERY ==="

[[ -f tools/test-courses-delivery.php ]] \
  || fail "teste courses-delivery ausente"

if ! docker compose run --rm wpcli \
  wp eval-file \
  /workspace/tools/test-courses-delivery.php \
  --use-include \
  2>&1 \
  | tee /tmp/fd-courses-delivery.log
then
  fail "teste funcional courses-delivery"
fi

grep -q '^COURSES_RESOURCE_PRIVATE_DELIVERY=PASS$' \
  /tmp/fd-courses-delivery.log \
  || fail "recursos privados nao confirmados"

grep -q '^COURSES_CERTIFICATE_GENERATION=PASS$' \
  /tmp/fd-courses-delivery.log \
  || fail "geracao real de certificado nao confirmada"

grep -q '^COURSES_CERTIFICATE_VERIFICATION=PASS$' \
  /tmp/fd-courses-delivery.log \
  || fail "verificacao de certificado nao confirmada"

grep -q '^COURSES_DELIVERY_SECURITY=PASS$' \
  /tmp/fd-courses-delivery.log \
  || fail "seguranca de entrega LMS nao confirmada"

grep -q '^FIXTURES_CLEANUP=OK$' \
  /tmp/fd-courses-delivery.log \
  || fail "cleanup courses-delivery nao confirmado"

pass "recursos privados, certificado PDF e verificacao publica"

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

docker compose exec -T wordpress \
  php -l \
  /workspace/tools/test-courses-domain.php \
  >/dev/null

docker compose exec -T wordpress \
  php -l \
  /workspace/tools/test-courses-resources-enrollments.php \
  >/dev/null

docker compose exec -T wordpress \
  php -l \
  /workspace/tools/test-courses-progress-certificates.php \
  >/dev/null

docker compose exec -T wordpress \
  php -l \
  /workspace/tools/test-courses-products.php \
  >/dev/null

docker compose exec -T wordpress \
  php -l \
  /workspace/tools/test-courses-admin-builder.php \
  >/dev/null

docker compose exec -T wordpress \
  php -l \
  /workspace/tools/test-courses-learning.php \
  >/dev/null

docker compose exec -T wordpress \
  php -l \
  /workspace/tools/test-courses-delivery.php \
  >/dev/null

bash -n tools/validate-courses-lms.sh
git diff --check

pass "sintaxe PHP, shell e git diff check"

echo
echo "=================================================="
echo "PASS - COURSES LMS ETAPA ATUAL VALIDADA"
echo "=================================================="