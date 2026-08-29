#!/usr/bin/env bash

set -euo pipefail

ROOT="$(
  cd "$(
    dirname "${BASH_SOURCE[0]}"
  )/.." &&
  pwd
)"

cd "$ROOT"

if [[ ! -f .env ]]; then
  echo "FAIL - .env nao existe"
  exit 1
fi

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

HOST="$(
  printf '%s' \
    "$WORDPRESS_URL" \
  | sed -E \
      's#^https?://##; s#/$##'
)"

BASE="http://127.0.0.1:8080"

request() {
  local path="$1"
  local output="$2"

  curl \
    -sS \
    -o "$output" \
    -w '%{http_code}' \
    -H "Host: $HOST" \
    -H "X-Forwarded-Proto: https" \
    "${BASE}${path}"
}

seed_product_id() {
  local key="$1"

  wpcli eval "
    \$ids = get_posts(
      [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_key'       => '_fd_w2c_seed_key',
        'meta_value'     => '${key}',
        'no_found_rows'  => true,
      ]
    );

    echo \$ids
      ? (int) \$ids[0]
      : 0;
  "
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

        if (
          index(
            tolower(line),
            "set-cookie:"
          ) != 1
        ) {
          next
        }

        sub(
          /^[Ss][Ee][Tt]-[Cc][Oo][Oo][Kk][Ii][Ee]:[[:space:]]*/,
          "",
          line
        )

        if (index(tolower(line), tolower(cookie_name)) == 1) {
          sub(/;.*/, "", line)

          print line
          exit
        }
      }
    ' \
    "$headers"
}

echo "=================================================="
echo "W2C - VALIDACAO"
echo "=================================================="

echo
echo "=== W2B ==="

./tools/validate-w2b.sh \
  >/tmp/fd-w2c-w2b.log

tail -8 \
  /tmp/fd-w2c-w2b.log

pass "W2B intacta"

echo
echo "=== ARQUIVOS ==="

required_files=(
  "woocommerce/archive-product.php"
  "woocommerce/content-product.php"
  "woocommerce/single-product.php"
  "inc/catalog.php"
  "inc/product.php"
  "template-parts/catalog/header.php"
  "template-parts/catalog/toolbar.php"
  "template-parts/catalog/empty.php"
  "template-parts/product/summary.php"
  "template-parts/product/benefits.php"
  "template-parts/product/details.php"
  "template-parts/product/simulations.php"
  "template-parts/product/faq.php"
  "template-parts/product/related.php"
  "template-parts/search/header.php"
  "template-parts/search/result-card.php"
  "assets/css/storefront.css"
  "assets/css/product.css"
  "assets/css/search.css"
  "assets/js/storefront.js"
  "search.php"
  "404.php"
)

for file in "${required_files[@]}"
do
  [[ -f "wp-content/themes/facil-digital/$file" ]] \
    || fail \
      "arquivo ausente: $file"
done

[[ -f tools/seed-w2c.php ]] \
  || fail \
    "seed ausente"

[[ -f tools/cleanup-w2c.php ]] \
  || fail \
    "cleanup ausente"

pass "estrutura W2C"

echo
echo "=== SEED ==="

SEED_COUNT="$(
  wpcli eval '
    $query = new WP_Query(
      [
        "post_type"      => "product",
        "post_status"    => "publish",
        "posts_per_page" => -1,
        "fields"         => "ids",
        "meta_key"       => "_fd_w2c_seed",
        "meta_value"     => "1",
      ]
    );

    echo count(
      $query->posts
    );
  '
)"

echo "Produtos W2C: $SEED_COUNT"

[[ "$SEED_COUNT" == "3" ]] \
  || fail \
    "esperados 3 produtos seed"

pass "3 produtos temporarios"

PRODUCT_ID="$(
  seed_product_id \
    "transpetro-seguranca"
)"

[[ "$PRODUCT_ID" != "0" ]] \
  || fail \
    "produto principal seed ausente"

PRODUCT_NAME="$(
  wpcli post get \
    "$PRODUCT_ID" \
    --field=post_title
)"

PRODUCT_URL="$(
  wpcli post url \
    "$PRODUCT_ID"
)"

PRODUCT_PATH="$(
  printf '%s' \
    "$PRODUCT_URL" \
  | sed -E \
      "s#^https?://${HOST}##"
)"

[[ -n "$PRODUCT_PATH" ]] \
  || fail \
    "path do produto vazio"

echo "Produto: $PRODUCT_NAME"
echo "Path: $PRODUCT_PATH"

echo
echo "=== CATALOGO ==="

STATUS="$(
  request \
    "/apostilas/" \
    "/tmp/fd-w2c-catalog.html"
)"

echo "HTTP $STATUS"

[[ "$STATUS" == "200" ]] \
  || fail \
    "catalogo HTTP $STATUS"

grep -q \
  'fd-catalog-header' \
  /tmp/fd-w2c-catalog.html \
  || fail \
    "header catalogo ausente"

grep -q \
  'fd-catalog-toolbar' \
  /tmp/fd-w2c-catalog.html \
  || fail \
    "toolbar ausente"

grep -q \
  'Apostila Transpetro - Seguranca' \
  /tmp/fd-w2c-catalog.html \
  || fail \
    "produto seguranca ausente"

grep -q \
  'Apostila Transpetro - Dutos e Terminais' \
  /tmp/fd-w2c-catalog.html \
  || fail \
    "produto dutos ausente"

grep -q \
  'Apostila Transpetro - Contabilidade' \
  /tmp/fd-w2c-catalog.html \
  || fail \
    "produto contabilidade ausente"

pass "catalogo renderizado"

echo
echo "=== ORDENACAO ==="

STATUS="$(
  request \
    "/apostilas/?orderby=price" \
    "/tmp/fd-w2c-order.html"
)"

[[ "$STATUS" == "200" ]] \
  || fail \
    "ordenacao HTTP $STATUS"

ORDER_HTML="$(
  tr '\n' ' ' \
    < /tmp/fd-w2c-order.html
)"

printf '%s' "$ORDER_HTML" \
  | grep -Eq \
    "<option[^>]*value=['\"]price['\"][^>]*selected([[:space:]]*=[[:space:]]*['\"]selected['\"])?" \
  || fail \
    "orderby price nao selecionado"

pass "ordenacao por preco"

echo
echo "=== BUSCA DE PRODUTOS ==="

STATUS="$(
  request \
    "/?s=Transpetro&post_type=product" \
    "/tmp/fd-w2c-search.html"
)"

echo "HTTP $STATUS"

[[ "$STATUS" == "200" ]] \
  || fail \
    "busca HTTP $STATUS"

grep -q \
  'fd-search-page' \
  /tmp/fd-w2c-search.html \
  || fail \
    "template search.php nao carregou"

grep -q \
  'Apostila Transpetro - Seguranca' \
  /tmp/fd-w2c-search.html \
  || fail \
    "resultado de produto ausente"

pass "busca de produtos"

echo
echo "=== BUSCA SEM RESULTADO ==="

STATUS="$(
  request \
    "/?s=fd_w2c_resultado_impossivel_987654&post_type=product" \
    "/tmp/fd-w2c-no-results.html"
)"

[[ "$STATUS" == "200" ]] \
  || fail \
    "busca vazia HTTP $STATUS"

grep -q \
  'Nenhum resultado encontrado' \
  /tmp/fd-w2c-no-results.html \
  || fail \
    "estado vazio ausente"

pass "busca sem resultados"

echo
echo "=== PRODUTO INDIVIDUAL ==="

STATUS="$(
  request \
    "$PRODUCT_PATH" \
    "/tmp/fd-w2c-product.html"
)"

echo "HTTP $STATUS"

[[ "$STATUS" == "200" ]] \
  || fail \
    "produto HTTP $STATUS"

grep -q \
  'fd-product-primary' \
  /tmp/fd-w2c-product.html \
  || fail \
    "template produto ausente"

grep -q \
  "$PRODUCT_NAME" \
  /tmp/fd-w2c-product.html \
  || fail \
    "nome do produto ausente"

grep -q \
  '14,50' \
  /tmp/fd-w2c-product.html \
  || fail \
    "preco 14,50 ausente"

grep -q \
  'single_add_to_cart_button' \
  /tmp/fd-w2c-product.html \
  || fail \
    "CTA WooCommerce ausente"

pass "produto individual"

echo
echo "=== PRODUTO SEM IMAGEM ==="

IMAGE_ID="$(
  wpcli eval "
    \$product = wc_get_product(
      ${PRODUCT_ID}
    );

    echo \$product
      ? (int) \$product->get_image_id()
      : -1;
  "
)"

[[ "$IMAGE_ID" == "0" ]] \
  || fail \
    "seed deveria estar sem imagem"

grep -Eq \
  'woocommerce-placeholder|placeholder' \
  /tmp/fd-w2c-product.html \
  || fail \
    "placeholder de imagem ausente"

pass "fallback produto sem imagem"

echo
echo "=== ADD TO CART ==="

ADD_HEADERS="$(
  mktemp
)"

ADD_HTML="$(
  mktemp
)"

CART_HTML="$(
  mktemp
)"

cleanup_cart_test() {
  rm -f \
    "$ADD_HEADERS" \
    "$ADD_HTML" \
    "$CART_HTML"
}

trap cleanup_cart_test EXIT

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

echo "Add HTTP $ADD_STATUS"

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

pass "sessao WooCommerce criada"

COOKIE_HEADER="$SESSION_COOKIE"

if [[ -n "$ITEMS_COOKIE" ]]; then
  COOKIE_HEADER="${COOKIE_HEADER}; ${ITEMS_COOKIE}"
fi

if [[ -n "$HASH_COOKIE" ]]; then
  COOKIE_HEADER="${COOKIE_HEADER}; ${HASH_COOKIE}"
fi

CART_STATUS="$(
  curl \
    -sS \
    --cookie "$COOKIE_HEADER" \
    -o "$CART_HTML" \
    -w '%{http_code}' \
    -H "Host: $HOST" \
    -H "X-Forwarded-Proto: https" \
    "${BASE}/carrinho/"
)"

echo "Cart HTTP $CART_STATUS"

[[ "$CART_STATUS" == "200" ]] \
  || fail \
    "carrinho HTTP $CART_STATUS"

grep -q \
  "$PRODUCT_NAME" \
  "$CART_HTML" \
  || fail \
    "produto nao entrou no carrinho"

grep -q \
  'woocommerce-cart-form' \
  "$CART_HTML" \
  || fail \
    "formulario WooCommerce do carrinho ausente"

pass "add-to-cart WooCommerce"
pass "carrinho preserva sessao"

cleanup_cart_test

trap - EXIT

echo
echo "=== 404 ==="

NOT_FOUND_PATH="/fd-w2c-404-$RANDOM-$RANDOM/"

STATUS="$(
  request \
    "$NOT_FOUND_PATH" \
    "/tmp/fd-w2c-404.html"
)"

echo "HTTP $STATUS"

[[ "$STATUS" == "404" ]] \
  || fail \
    "404 retornou HTTP $STATUS"

grep -q \
  'fd-not-found' \
  /tmp/fd-w2c-404.html \
  || fail \
    "template 404 ausente"

pass "404 customizado"

echo
echo "=== WOOCOMMERCE ==="

wpcli plugin is-active \
  woocommerce \
  >/dev/null \
  || fail \
    "WooCommerce inativo"

pass "WooCommerce intacto"

echo
echo "=== CORE REST ==="

REST="$(
  curl \
    -sS \
    -H "Host: $HOST" \
    -H "X-Forwarded-Proto: https" \
    "${BASE}/wp-json/facil-digital/v1/health"
)"

printf '%s' \
  "$REST" \
  | grep -q \
    '"status":"ok"' \
  || fail \
    "Core REST"

pass "Core REST intacto"

echo
echo "=== PHP ==="

while IFS= read -r file
do
  docker compose exec \
    -T \
    wordpress \
    php -l \
    "/workspace/$file" \
    >/dev/null

done < <(
  find \
    wp-content/themes/facil-digital \
    -type f \
    -name '*.php' \
  | sort
)

docker compose exec \
  -T \
  wordpress \
  php -l \
  /workspace/tools/seed-w2c.php \
  >/dev/null

docker compose exec \
  -T \
  wordpress \
  php -l \
  /workspace/tools/cleanup-w2c.php \
  >/dev/null

pass "sintaxe PHP"

echo
echo "=== JAVASCRIPT ==="

if command -v node \
  >/dev/null 2>&1
then
  node --check \
    wp-content/themes/facil-digital/assets/js/navigation.js

  node --check \
    wp-content/themes/facil-digital/assets/js/auth.js

  node --check \
    wp-content/themes/facil-digital/assets/js/storefront.js

  pass "sintaxe JavaScript"
else
  echo "INFO  Node ausente; check JS ignorado"
fi

echo
echo "=== SHELL ==="

bash -n \
  tools/validate-w2c.sh

pass "sintaxe Shell"

echo
echo "=== ELEMENTOR ==="

if wpcli plugin is-active \
  elementor \
  >/dev/null 2>&1
then
  fail \
    "Elementor ativo"
fi

pass "sem Elementor"

echo
echo "=== GIT ==="

if git status --short \
  | grep -E \
    '(^|[[:space:]])\.env$|wp-config\.php|vendor/|private/|storage/|\.sql$|\.db$'
then
  fail \
    "runtime ou segredo no Git"
fi

git diff --check

pass "runtime privado"
pass "git diff check"

echo
echo "=================================================="
echo "PASS - W2C VALIDADA"
echo "=================================================="
