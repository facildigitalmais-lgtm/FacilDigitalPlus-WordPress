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
echo "FACIL DIGITAL+ COURSES LMS - RELEASE CANDIDATE"
echo "=================================================="

echo
echo "=== CUMULATIVE LMS ==="

./tools/validate-courses-lms.sh \
  || fail "validacao cumulativa LMS"

pass "suite cumulativa LMS"

echo
echo "=== RUNTIME READINESS ==="

if ! docker compose run --rm wpcli \
  wp eval-file \
  /workspace/tools/check-courses-runtime.php \
  --use-include \
  2>&1 \
  | tee /tmp/fd-courses-runtime.log
then
  fail "runtime readiness"
fi

grep -q '^COURSES_RUNTIME_READINESS=PASS$' \
  /tmp/fd-courses-runtime.log \
  || fail "marcador runtime readiness ausente"

pass "runtime LMS"

echo
echo "=== COMPOSER ==="

(
  cd wp-content/plugins/facil-digital-core

  XDEBUG_MODE=off \
    composer validate \
    --no-check-publish

  XDEBUG_MODE=off \
    composer check-platform-reqs
) || fail "Composer"

pass "Composer e plataforma"

echo
echo "=== JAVASCRIPT ==="

node --check \
  wp-content/plugins/facil-digital-core/assets/frontend/courses.js \
  || fail "sintaxe courses.js"

node --check \
  wp-content/plugins/facil-digital-core/assets/admin/courses.js \
  || fail "sintaxe admin courses.js"

pass "JavaScript"

echo
echo "=== WP-CRON HTTP SPAWN ==="

set +e

docker compose run --rm wpcli \
  wp cron test \
  >/tmp/fd-courses-cron.log \
  2>&1

CRON_EXIT="$?"

set -e

cat /tmp/fd-courses-cron.log

if [[ "$CRON_EXIT" -eq 0 ]]; then
  echo "COURSES_CRON_HTTP=PASS"
  pass "WP-Cron HTTP spawn"
else
  echo "COURSES_CRON_HTTP=WARNING"

  if [[ "${FD_REQUIRE_CRON_HTTP:-0}" == "1" ]]; then
    fail "WP-Cron HTTP spawn obrigatorio"
  fi

  echo "WARNING - wp cron test nao passou neste ambiente."
  echo "WARNING - validar cron novamente no ambiente de producao."
fi

echo
echo "=== SECURITY STATIC CHECKS ==="

if grep -Rni \
  'wp_ajax_nopriv_fd_course' \
  wp-content/plugins/facil-digital-core/src
then
  fail "AJAX anonimo de cursos encontrado"
fi

pass "sem AJAX anonimo LMS"

if grep -RniE \
  'wp_upload_dir|wp_get_attachment_url|uploads_url' \
  wp-content/plugins/facil-digital-core/src/Courses/CoursePrivateStorage.php \
  wp-content/plugins/facil-digital-core/src/Courses/CourseResourceService.php \
  wp-content/plugins/facil-digital-core/src/Courses/CertificateGenerationService.php
then
  fail "storage publico encontrado na entrega privada"
fi

pass "entrega privada sem URL publica"

echo
echo "=== GIT CHECK ==="

git diff --check \
  || fail "git diff check"

pass "git diff check"

echo
echo "=================================================="
echo "COURSES_RELEASE_CANDIDATE=PASS"
echo "=================================================="