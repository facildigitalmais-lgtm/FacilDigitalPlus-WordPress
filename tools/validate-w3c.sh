#!/usr/bin/env bash
set -euo pipefail

[[ -f .env ]] || {
  echo "FAIL - .env nao existe"
  exit 1
}

set -a
# shellcheck disable=SC1091
source .env
set +a

wpcli() {
  docker compose run --rm wpcli wp "$@"
}

pass() {
  echo "PASS  $1"
}

fail() {
  echo "FAIL  $1"
  exit 1
}

echo "=================================================="
echo "W3C - REGISTRY / DIAGNOSTICS / OPERACAO"
echo "=================================================="
echo

echo "=== REGRESSAO W3B ==="

if ! ./tools/validate-w3b.sh 2>&1 | tee /tmp/fd-w3c-w3b.log
then
  fail "regressao W3B"
fi

pass "W3B intacta"

echo
echo "=== COMPOSER ==="

docker compose run --rm composer \
  validate \
  --no-check-publish \
  >/dev/null

docker compose run --rm composer \
  dump-autoload \
  --optimize \
  >/dev/null

pass "autoload atualizado"

echo
echo "=== CORE ==="

wpcli plugin is-active \
  facil-digital-core \
  >/dev/null \
  || fail "Core inativo"

CORE_VERSION="$(
  wpcli plugin get \
    facil-digital-core \
    --field=version
)"

version_ge() {
  printf '%s\n%s\n' "$2" "$1" \
    | sort -V -C
}

version_ge "$CORE_VERSION" "0.4.0" \
  || fail \
    "Core esperado >= 0.4.0; atual: $CORE_VERSION"

pass "Core >= 0.4.0"

echo
echo "=== MODULE REGISTRY ==="

wpcli eval '
use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Core\ModuleRegistry;

$modules = ModuleRegistry::defaults();

$required = [
    "FacilDigital\\Core\\Admin\\Menu",
    "FacilDigital\\Core\\API\\HealthController",
    "FacilDigital\\Core\\API\\StatusController",
    "FacilDigital\\Core\\CLI\\StatusCommand",
];

$actual = array_map(
    static fn ($module): string =>
        get_class($module),
    $modules
);

foreach ($required as $class) {
    if (!in_array($class, $actual, true)) {
        fwrite(
            STDERR,
            "Modulo base ausente: "
            . $class
            . PHP_EOL
        );
        exit(1);
    }
}

foreach ($modules as $module) {
    if (!$module instanceof ModuleInterface) {
        fwrite(
            STDERR,
            "Modulo fora do contrato: "
            . get_class($module)
            . PHP_EOL
        );
        exit(1);
    }
}

if (
    count($actual)
    !== count(array_unique($actual))
) {
    fwrite(
        STDERR,
        "Registry contem modulos duplicados."
        . PHP_EOL
    );
    exit(1);
}
'

pass "registry preserva modulos base e aceita expansao"

echo
echo "=== DIAGNOSTICS ==="

wpcli eval '
use FacilDigital\Core\Support\Diagnostics;

$status =
    (new Diagnostics())->snapshot();

$required = [
    "ready",
    "core_version",
    "schema_version",
    "schema_target",
    "database_ready",
    "missing_tables",
    "capabilities_version",
    "capabilities_target",
    "capabilities_ready",
    "woocommerce_active",
    "wordpress_version",
    "php_version",
    "environment",
    "requirements_errors",
];

foreach ($required as $key) {
    if (
        !array_key_exists(
            $key,
            $status
        )
    ) {
        fwrite(
            STDERR,
            "Diagnostico sem chave: "
            . $key
            . "."
            . PHP_EOL
        );
        exit(1);
    }
}

if ($status["ready"] !== true) {
    fwrite(
        STDERR,
        "Core nao esta ready "
        . "no diagnostico."
        . PHP_EOL
    );
    exit(1);
}

if (
    $status["database_ready"]
    !== true
) {
    fwrite(
        STDERR,
        "Banco nao esta ready."
        . PHP_EOL
    );
    exit(1);
}

if (
    $status["capabilities_ready"]
    !== true
) {
    fwrite(
        STDERR,
        "Capabilities nao estao ready."
        . PHP_EOL
    );
    exit(1);
}

if (
    $status["environment"]
    !== "development"
) {
    fwrite(
        STDERR,
        "Ambiente esperado development; atual: "
        . $status["environment"]
        . PHP_EOL
    );
    exit(1);
}

if (
    $status["woocommerce_active"]
    !== true
) {
    fwrite(
        STDERR,
        "WooCommerce nao esta ativo."
        . PHP_EOL
    );
    exit(1);
}

if (
    $status["missing_tables"]
    !== []
) {
    fwrite(
        STDERR,
        "Existem tabelas faltando."
        . PHP_EOL
    );
    exit(1);
}

if (
    $status["requirements_errors"]
    !== []
) {
    fwrite(
        STDERR,
        "Existem erros de requisitos."
        . PHP_EOL
    );
    exit(1);
}
'

pass "diagnostico central reporta Core pronto"

echo
echo "=== WP-CLI STATUS ==="

wpcli facil-digital status \
  --format=json \
  >/tmp/fd-w3c-status.json

python3 - <<'PY'
import json
from pathlib import Path

payload = json.loads(
    Path(
        "/tmp/fd-w3c-status.json"
    ).read_text()
)

assert payload["ready"] is True
assert payload["database_ready"] is True
assert payload["capabilities_ready"] is True
assert payload["woocommerce_active"] is True
assert payload["environment"] == "development"
assert payload["missing_tables"] == []
assert payload["requirements_errors"] == []
PY

pass "wp facil-digital status funcional"

echo
echo "=== REST STATUS PROTEGIDO ==="

wpcli eval '
use FacilDigital\Core\API\StatusController;
use FacilDigital\Core\Core\Capabilities;

$controller =
    new StatusController();

wp_set_current_user(0);

if ($controller->permissions()) {
    fwrite(
        STDERR,
        "REST status liberado "
        . "para anonimo."
        . PHP_EOL
    );
    exit(1);
}

$subscribers = get_users([
    "role" => "subscriber",
    "fields" => "ids",
    "number" => 1,
]);

if ($subscribers !== []) {
    wp_set_current_user(
        (int) $subscribers[0]
    );

    if ($controller->permissions()) {
        fwrite(
            STDERR,
            "REST status liberado "
            . "para subscriber."
            . PHP_EOL
        );
        exit(1);
    }
}

$administrators = get_users([
    "role" => "administrator",
    "fields" => "ids",
    "number" => 1,
]);

if ($administrators === []) {
    fwrite(
        STDERR,
        "Administrador ausente."
        . PHP_EOL
    );
    exit(1);
}

wp_set_current_user(
    (int) $administrators[0]
);

if (
    !current_user_can(
        Capabilities::ACCESS_ADMIN
    )
) {
    fwrite(
        STDERR,
        "Administrador sem ACCESS_ADMIN."
        . PHP_EOL
    );
    exit(1);
}

if (!$controller->permissions()) {
    fwrite(
        STDERR,
        "REST status bloqueado "
        . "para administrador."
        . PHP_EOL
    );
    exit(1);
}
'

pass "status REST exige capability administrativa"

echo
echo "=== REST ROUTE ==="

wpcli eval '
do_action("rest_api_init");

$routes =
    rest_get_server()
        ->get_routes();

$route =
    "/facil-digital/v1/status";

if (!isset($routes[$route])) {
    fwrite(
        STDERR,
        "Rota REST status "
        . "nao registrada."
        . PHP_EOL
    );
    exit(1);
}
'

pass "rota /facil-digital/v1/status registrada"

echo
echo "=== HEALTH PUBLICO ==="

HOST="$(
  printf '%s' "$WORDPRESS_URL" |
  sed -E \
    's#^https?://##; s#/$##'
)"

BASE="http://127.0.0.1:${WORDPRESS_PORT:-8080}"

HEALTH_STATUS="$(
  curl \
    -sS \
    -o /tmp/fd-w3c-health.json \
    -w '%{http_code}' \
    -H "Host: $HOST" \
    -H "X-Forwarded-Proto: https" \
    "${BASE}/wp-json/facil-digital/v1/health"
)"

[[ "$HEALTH_STATUS" == "200" ]] \
  || fail \
    "health endpoint HTTP $HEALTH_STATUS"

grep -q '"status":"ok"' \
  /tmp/fd-w3c-health.json \
  || fail \
    "health sem status ok"

pass "health publico preservado"

echo
echo "=== UNINSTALL ==="

if grep -Eiq \
  'DROP[[:space:]]+TABLE|TRUNCATE[[:space:]]+TABLE' \
  wp-content/plugins/facil-digital-core/uninstall.php
then
  fail \
    "uninstall contem operacao destrutiva"
fi

pass \
  "politica de uninstall continua nao destrutiva"

echo
echo "=== PHP / SHELL / GIT ==="

while IFS= read -r file
do
  docker compose exec -T \
    wordpress \
    php -l \
    "/workspace/$file" \
    >/dev/null
done < <(
  find \
    wp-content/plugins/facil-digital-core \
    -type f \
    -name '*.php' \
    -not -path '*/vendor/*' \
    | sort
)

bash -n tools/validate-w3a.sh
bash -n tools/validate-w3b.sh
bash -n tools/validate-w3c.sh

git diff --check

pass "sintaxe e git diff check"

echo
echo "=================================================="
echo "PASS - W3C VALIDADA"
echo "=================================================="
