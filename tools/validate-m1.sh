#!/usr/bin/env bash

set -euo pipefail

ROOT="$(
  cd "$(
    dirname "${BASH_SOURCE[0]}"
  )/.." &&
  pwd
)"

cd "$ROOT"

[[ -f .env ]] || {
  echo "FAIL - .env ausente"
  exit 1
}

set -a
# shellcheck disable=SC1091
source .env
set +a

wpcli() {
  docker compose run \
    --rm \
    wpcli \
    wp \
    "$@"
}

pass() {
  echo "PASS  $1"
}

fail() {
  echo "FAIL  $1"
  exit 1
}

version_ge() {
  printf '%s\n%s\n' "$2" "$1" | sort -V -C
}

extract_set_cookie() {
  local headers="$1"
  local cookie_name="$2"

  awk \
    -v cookie_name="$cookie_name" \
    '
      {
        line = $0
        sub(/\r$/, "", line)

        if (index(tolower(line), "set-cookie:") != 1) {
          next
        }

        sub(/^[Ss][Ee][Tt]-[Cc][Oo][Oo][Kk][Ii][Ee]:[[:space:]]*/, "", line)

        if (index(tolower(line), tolower(cookie_name)) == 1) {
          sub(/;.*/, "", line)
          print line
          exit
        }
      }
    ' \
    "$headers"
}

cleanup() {
  wpcli eval \
    'require "/workspace/tools/cleanup-m1.php";' \
    >/dev/null 2>&1 \
    || true
}

trap cleanup EXIT

echo "=================================================="
echo "M1 - W4/W5/W6/W7"
echo "CONCURSOS / APOSTILAS / COMMERCE / ENTITLEMENTS"
echo "=================================================="
echo

echo "=== REGRESSAO W3C ==="

if ! ./tools/validate-w3c.sh 2>&1 | tee /tmp/fd-m1-w3c.log
then
  fail "regressao W3C"
fi

pass "W3C intacta"

echo
echo "=== COMPOSER ==="

docker compose run \
  --rm \
  composer \
  validate \
  --no-check-publish \
  >/dev/null

docker compose run \
  --rm \
  composer \
  dump-autoload \
  --optimize \
  >/dev/null

pass "autoload atualizado"

echo
echo "=== CORE ==="

CORE_VERSION="$(
  wpcli plugin get \
    facil-digital-core \
    --field=version
)"

version_ge "$CORE_VERSION" "0.5.0" \
  || fail \
    "Core esperado >= 0.5.0; atual: $CORE_VERSION"

pass "Core >= 0.5.0 ($CORE_VERSION)"

echo
echo "=== CAPABILITIES 1.1 ==="

wpcli eval '
use FacilDigital\Core\Core\Capabilities;

Capabilities::maybeRun();

if (
    Capabilities::installedVersion()
    !== "1.1.0"
) {
    fwrite(
        STDERR,
        "Capabilities != 1.1.0"
        . PHP_EOL
    );
    exit(1);
}

$manager =
    get_role(
        Capabilities::ROLE_MANAGER
    );

if (!$manager instanceof WP_Role) {
    fwrite(
        STDERR,
        "Gerente ausente."
        . PHP_EOL
    );
    exit(1);
}

foreach (
    Capabilities::managerProductCapabilities()
    as $capability
) {
    if (!$manager->has_cap($capability)) {
        fwrite(
            STDERR,
            "Gerente sem "
            . $capability
            . PHP_EOL
        );
        exit(1);
    }
}

foreach (
    [
        "manage_options",
        "manage_woocommerce",
        "edit_users",
        "activate_plugins",
    ]
    as $forbidden
) {
    if ($manager->has_cap($forbidden)) {
        fwrite(
            STDERR,
            "Gerente recebeu capability proibida: "
            . $forbidden
            . PHP_EOL
        );
        exit(1);
    }
}
'

pass "Gerente administra apostilas sem privilegio global"

echo
echo "=== MERCADO PAGO OFICIAL ==="

wpcli plugin is-active \
  woocommerce-mercadopago \
  >/dev/null \
  || fail \
    "Mercado Pago oficial inativo. Rode ./tools/setup-m1-runtime.sh"

wpcli eval '
use FacilDigital\Core\WooCommerce\MercadoPagoModule;

if (!MercadoPagoModule::isOfficialPluginActive()) {
    fwrite(
        STDERR,
        "Core nao detectou Mercado Pago oficial."
        . PHP_EOL
    );
    exit(1);
}
'

pass "Mercado Pago oficial ativo; Core sem gateway proprio"

echo
echo "=== TAXONOMIA CONCURSOS ==="

wpcli eval '
use FacilDigital\Core\Contests\ContestModule;

if (!taxonomy_exists(ContestModule::TAXONOMY)) {
    fwrite(
        STDERR,
        "fd_concurso ausente."
        . PHP_EOL
    );
    exit(1);
}

$taxonomy =
    get_taxonomy(
        ContestModule::TAXONOMY
    );

if (!$taxonomy instanceof WP_Taxonomy) {
    fwrite(
        STDERR,
        "Objeto da taxonomia invalido."
        . PHP_EOL
    );
    exit(1);
}

if (
    ($taxonomy->rewrite["slug"] ?? "")
    !== "concurso"
) {
    fwrite(
        STDERR,
        "Rewrite de concurso incorreto."
        . PHP_EOL
    );
    exit(1);
}

if (
    ($taxonomy->cap->manage_terms ?? "")
    !== "facil_digital_manage_contests"
) {
    fwrite(
        STDERR,
        "Capability de concurso incorreta."
        . PHP_EOL
    );
    exit(1);
}
'

pass "fd_concurso publico e protegido por capability"

echo
echo "=== SEED M1 ==="

cleanup

wpcli eval \
  'require "/workspace/tools/seed-m1.php";' \
  >/tmp/fd-m1-seed.json

python3 - <<'PY'
import json
from pathlib import Path

payload = json.loads(
    Path(
        "/tmp/fd-m1-seed.json"
    ).read_text()
)

assert payload["status"] == "seeded"
assert len(payload["products"]) == 3
assert len(payload["contests"]) == 2
PY

pass "3 apostilas e 2 concursos temporarios"

echo
echo "=== METADADOS APOSTILAS ==="

wpcli eval '
use FacilDigital\Core\Products\ProductMetadata;
use FacilDigital\Core\Products\ProductRepository;

$ids = get_posts([
    "post_type" => "product",
    "post_status" => "publish",
    "posts_per_page" => -1,
    "fields" => "ids",
    "meta_key" => "_fd_m1_seed",
    "meta_value" => "1",
]);

if (count($ids) !== 3) {
    fwrite(
        STDERR,
        "Quantidade M1 incorreta."
        . PHP_EOL
    );
    exit(1);
}

$repository =
    new ProductRepository();

foreach ($ids as $id) {
    $product =
        wc_get_product((int) $id);

    if (!$product instanceof WC_Product) {
        exit(1);
    }

    if (!$product->is_virtual()) {
        fwrite(
            STDERR,
            "Apostila nao virtual."
            . PHP_EOL
        );
        exit(1);
    }

    if ($product->is_downloadable()) {
        fwrite(
            STDERR,
            "Apostila marcada como downloadable Woo."
            . PHP_EOL
        );
        exit(1);
    }

    if (!ProductMetadata::isApostila((int) $id)) {
        fwrite(
            STDERR,
            "Flag apostila ausente."
            . PHP_EOL
        );
        exit(1);
    }

    $data =
        $repository->find((int) $id);

    if (
        !is_array($data)
        || $data["position_name"] === ""
        || $data["board"] === ""
        || $data["page_count"] === ""
        || $data["contests"] === []
    ) {
        fwrite(
            STDERR,
            "Metadados incompletos."
            . PHP_EOL
        );
        exit(1);
    }
}
'

pass "metadados comerciais e protecao estrutural"

echo
echo "=== CPF ==="

wpcli eval '
use FacilDigital\Core\Security\Cpf;

if (!Cpf::isValid("529.982.247-25")) {
    fwrite(
        STDERR,
        "CPF valido rejeitado."
        . PHP_EOL
    );
    exit(1);
}

foreach (
    [
        "",
        "111.111.111-11",
        "123.456.789-00",
        "52998224724",
    ]
    as $invalid
) {
    if (Cpf::isValid($invalid)) {
        fwrite(
            STDERR,
            "CPF invalido aceito."
            . PHP_EOL
        );
        exit(1);
    }
}

if (
    Cpf::mask("52998224725")
    !== "***.***.***-25"
) {
    fwrite(
        STDERR,
        "Mascara CPF incorreta."
        . PHP_EOL
    );
    exit(1);
}
'

pass "CPF normalizado, validado e mascarado"

echo
echo "=== CHECKOUT / CARRINHO ==="

PRODUCT_ID="$(
  wpcli post list \
    --post_type=product \
    --post_status=publish \
    --meta_key=_fd_m1_seed \
    --meta_value=1 \
    --field=ID \
    --format=ids \
  | awk '{print $1}'
)"

[[ -n "$PRODUCT_ID" ]] \
  || fail \
    "produto M1 ausente"

HOST="$(
  printf '%s' "$WORDPRESS_URL" |
  sed -E \
    's#^https?://##; s#/$##'
)"

BASE="http://127.0.0.1:${WORDPRESS_PORT:-8080}"

ADD_HEADERS="$(
  mktemp
)"

ADD_HTML="$(
  mktemp
)"

CHECKOUT_HTML="$(
  mktemp
)"

ADD_STATUS="$(
  curl \
    -sS \
    -D "$ADD_HEADERS" \
    -o "$ADD_HTML" \
    -w '%{http_code}' \
    -H "Host: $HOST" \
    -H "X-Forwarded-Proto: https" \
    "${BASE}/?add-to-cart=${PRODUCT_ID}"
)"

if \
  [[ "$ADD_STATUS" != "200" ]] \
  && [[ "$ADD_STATUS" != "302" ]]
then
  fail \
    "add-to-cart HTTP $ADD_STATUS"
fi

SESSION_COOKIE="$(
  extract_set_cookie \
    "$ADD_HEADERS" \
    "wp_woocommerce_session_"
)"

ITEMS_COOKIE="$(
  extract_set_cookie \
    "$ADD_HEADERS" \
    "woocommerce_items_in_cart="
)"

HASH_COOKIE="$(
  extract_set_cookie \
    "$ADD_HEADERS" \
    "woocommerce_cart_hash="
)"

[[ -n "$SESSION_COOKIE" ]] \
  || fail \
    "cookie de sessao WooCommerce ausente"

COOKIE_HEADER="$SESSION_COOKIE"

if [[ -n "$ITEMS_COOKIE" ]]; then
  COOKIE_HEADER="${COOKIE_HEADER}; ${ITEMS_COOKIE}"
fi

if [[ -n "$HASH_COOKIE" ]]; then
  COOKIE_HEADER="${COOKIE_HEADER}; ${HASH_COOKIE}"
fi

CHECKOUT_STATUS="$(
  curl \
    -sS \
    --cookie "$COOKIE_HEADER" \
    -o "$CHECKOUT_HTML" \
    -w '%{http_code}' \
    -H "Host: $HOST" \
    -H "X-Forwarded-Proto: https" \
    "${BASE}/checkout/"
)"

[[ "$CHECKOUT_STATUS" == "200" ]] \
  || fail \
    "checkout HTTP $CHECKOUT_STATUS"

wpcli eval '
use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;
use FacilDigital\Core\WooCommerce\CheckoutModule;

$checkoutFields = Package::container()->get(
    CheckoutFields::class
);

$fields =
    $checkoutFields->get_additional_fields();

$fieldId =
    CheckoutModule::BLOCK_CPF_FIELD;

if (!isset($fields[$fieldId])) {
    fwrite(
        STDERR,
        "Campo CPF do Checkout Block nao registrado."
        . PHP_EOL
    );

    exit(1);
}

$field = $fields[$fieldId];

if (
    ($field["location"] ?? null)
    !== "order"
) {
    fwrite(
        STDERR,
        "CPF registrado em location incorreto."
        . PHP_EOL
    );

    exit(1);
}

if (
    ($field["required"] ?? false)
    !== true
) {
    fwrite(
        STDERR,
        "CPF do Checkout Block nao obrigatorio."
        . PHP_EOL
    );

    exit(1);
}

$sanitize =
    $field["sanitize_callback"]
    ?? null;

if (!is_callable($sanitize)) {
    fwrite(
        STDERR,
        "CPF sem sanitize_callback."
        . PHP_EOL
    );

    exit(1);
}

$normalized =
    $sanitize(
        "529.982.247-25"
    );

if (
    $normalized
    !== "52998224725"
) {
    fwrite(
        STDERR,
        "Sanitizacao CPF incorreta."
        . PHP_EOL
    );

    exit(1);
}

$validate =
    $field["validate_callback"]
    ?? null;

if (!is_callable($validate)) {
    fwrite(
        STDERR,
        "CPF sem validate_callback."
        . PHP_EOL
    );

    exit(1);
}

$invalid =
    $validate(
        "11111111111"
    );

if (
    !$invalid
    instanceof WP_Error
) {
    fwrite(
        STDERR,
        "CPF invalido aceito pelo Checkout Block."
        . PHP_EOL
    );

    exit(1);
}

$valid =
    $validate(
        "52998224725"
    );

if ($valid instanceof WP_Error) {
    fwrite(
        STDERR,
        "CPF valido rejeitado pelo Checkout Block."
        . PHP_EOL
    );

    exit(1);
}
'

pass "CPF registrado e validado no Checkout Block"

rm -f \
  "$ADD_HEADERS" \
  "$ADD_HTML" \
  "$CHECKOUT_HTML"

pass "carrinho, checkout e CPF integrados"

wpcli rewrite flush \
  --hard \
  >/dev/null

echo
echo "=== CATALOGO POR CONCURSO ==="

FILTER_STATUS="$(
  curl \
    -sS \
    -o /tmp/fd-m1-filter.html \
    -w '%{http_code}' \
    -H "Host: $HOST" \
    -H "X-Forwarded-Proto: https" \
    "${BASE}/apostilas/?concurso=fd-m1-transpetro-2026"
)"

[[ "$FILTER_STATUS" == "200" ]] \
  || fail \
    "filtro concurso HTTP $FILTER_STATUS"

grep -q 'M1 Apostila Transpetro' \
  /tmp/fd-m1-filter.html \
  || fail \
    "filtro nao trouxe Transpetro"

if grep -q 'M1 Apostila Correios' \
    /tmp/fd-m1-filter.html
then
  fail \
    "filtro de concurso vazou produto Correios"
fi

pass "catalogo filtra por concurso"

echo
echo "=== ARCHIVE CONCURSO ==="

ARCHIVE_STATUS="$(
  curl \
    -sS \
    -o /tmp/fd-m1-contest.html \
    -w '%{http_code}' \
    -H "Host: $HOST" \
    -H "X-Forwarded-Proto: https" \
    "${BASE}/concurso/fd-m1-transpetro-2026/"
)"

[[ "$ARCHIVE_STATUS" == "200" ]] \
  || fail \
    "archive concurso HTTP $ARCHIVE_STATUS"

grep -q 'M1 Apostila Transpetro' \
  /tmp/fd-m1-contest.html \
  || fail \
    "archive sem produtos Transpetro"

pass "pagina publica /concurso/{slug}/ funcional"

echo
echo "=== ENTITLEMENT ==="

wpcli eval \
  'require "/workspace/tools/test-m1-entitlements.php";' \
  >/tmp/fd-m1-entitlements.json

python3 - <<'PY'
import json
from pathlib import Path

payload = json.loads(
    Path(
        "/tmp/fd-m1-entitlements.json"
    ).read_text()
)

assert payload["status"] == "ok"
assert payload["active_access"] is True
assert payload["order_1"] != payload["order_2"]
PY

pass "grant, idempotencia, refund e recompra"

echo
echo "=== REST ENTITLEMENTS ==="

wpcli eval '
use FacilDigital\Core\API\EntitlementController;

do_action("rest_api_init");

$routes =
    rest_get_server()
        ->get_routes();

$route =
    "/facil-digital/v1/me/entitlements";

if (!isset($routes[$route])) {
    fwrite(
        STDERR,
        "Rota entitlement ausente."
        . PHP_EOL
    );
    exit(1);
}

$controller =
    new EntitlementController();

wp_set_current_user(0);

if ($controller->permissions()) {
    fwrite(
        STDERR,
        "Entitlements liberados para anonimo."
        . PHP_EOL
    );
    exit(1);
}
'

pass "REST de entitlement exige usuario autenticado"

echo
echo "=== LGPD / SEGURANCA ==="

if grep -RniE \
  'error_log\s*\([^)]*cpf|wc_get_logger\(\).*cpf' \
  wp-content/plugins/facil-digital-core/src \
  >/tmp/fd-m1-pii.log
then
  cat /tmp/fd-m1-pii.log
  fail "possivel CPF em log"
fi

if grep -RniE \
  'cpf.*filename|filename.*cpf|cpf.*storage_key' \
  wp-content/plugins/facil-digital-core/src \
  >/tmp/fd-m1-file-pii.log
then
  cat /tmp/fd-m1-file-pii.log
  fail "possivel CPF em nome/storage"
fi

pass "sem CPF em logs, filenames ou storage keys"

echo
echo "=== PHP / JAVASCRIPT / SHELL / GIT ==="

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
    wp-content/themes/facil-digital \
    tools \
    -type f \
    -name '*.php' \
    -not -path '*/vendor/*' \
    | sort
)

node --check \
  wp-content/themes/facil-digital/assets/js/storefront.js

bash -n tools/setup-m1-runtime.sh
bash -n tools/validate-w3c.sh
bash -n tools/validate-m1.sh

git diff --check

pass "sintaxe e git diff check"

echo
echo "=================================================="
echo "PASS - M1 VALIDADO"
echo "=================================================="
