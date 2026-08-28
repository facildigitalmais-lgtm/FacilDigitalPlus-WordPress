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

request() {
  local path="$1"
  local output="$2"

  curl \
    -sS \
    -o "$output" \
    -w '%{http_code}' \
    -H "Host: $HOST" \
    -H "X-Forwarded-Proto: https" \
    "http://127.0.0.1:8080${path}"
}

page_id() {
  local slug="$1"

  wpcli eval "
    \$page = get_page_by_path(
      '${slug}',
      OBJECT,
      'page'
    );

    echo \$page
      ? (int) \$page->ID
      : 0;
  "
}

check_template() {
  local slug="$1"
  local expected="$2"

  local id
  local actual

  id="$(
    page_id "$slug"
  )"

  [[ "$id" != "0" ]] \
    || fail \
      "pagina ausente: $slug"

  actual="$(
    wpcli post meta get \
      "$id" \
      _wp_page_template \
      2>/dev/null \
      || true
  )"

  [[ "$actual" == "$expected" ]] \
    || fail \
      "template incorreto em $slug: $actual"

  pass "$slug -> $expected"
}

echo "=================================================="
echo "W2B - VALIDACAO"
echo "=================================================="

echo
echo "=== W2A ==="

./tools/validate-w2a.sh \
  >/tmp/fd-w2b-w2a.log

tail -6 \
  /tmp/fd-w2b-w2a.log

pass "W2A intacta"

echo
echo "=== TEMA ==="

THEME_VERSION="$(
  wpcli theme get \
    facil-digital \
    --field=version
)"

echo "Tema: $THEME_VERSION"

[[ "$THEME_VERSION" == "0.3.0" ]] \
  || fail "tema esperado 0.3.0"

pass "tema 0.3.0"

echo
echo "=== ARQUIVOS W2B ==="

required_files=(
  "inc/authentication.php"
  "templates/page-about.php"
  "templates/page-contact.php"
  "templates/page-faq.php"
  "templates/page-login.php"
  "templates/page-register.php"
  "templates/page-lost-password.php"
  "templates/page-privacy.php"
  "templates/page-terms.php"
  "template-parts/components/section-heading.php"
  "template-parts/components/product-card.php"
  "template-parts/components/empty-state.php"
  "template-parts/components/auth-notices.php"
  "template-parts/home/hero.php"
  "template-parts/home/featured-products.php"
  "template-parts/home/benefits.php"
  "template-parts/home/steps.php"
  "template-parts/home/simulations.php"
  "template-parts/home/faq.php"
  "template-parts/home/final-cta.php"
  "assets/css/home.css"
  "assets/css/pages.css"
  "assets/css/auth.css"
  "assets/js/auth.js"
)

for file in "${required_files[@]}"
do
  [[ -f "wp-content/themes/facil-digital/$file" ]] \
    || fail "arquivo ausente: $file"
done

pass "estrutura W2B"

echo
echo "=== TEMPLATES DAS PAGINAS ==="

check_template \
  "entrar" \
  "templates/page-login.php"

check_template \
  "cadastro" \
  "templates/page-register.php"

check_template \
  "recuperar-senha" \
  "templates/page-lost-password.php"

check_template \
  "sobre" \
  "templates/page-about.php"

check_template \
  "contato" \
  "templates/page-contact.php"

check_template \
  "faq" \
  "templates/page-faq.php"

check_template \
  "privacidade" \
  "templates/page-privacy.php"

check_template \
  "termos" \
  "templates/page-terms.php"

echo
echo "=== WOOCOMMERCE ACCOUNT ==="

REGISTRATION="$(
  wpcli option get \
    woocommerce_enable_myaccount_registration
)"

[[ "$REGISTRATION" == "yes" ]] \
  || fail "cadastro Woo desabilitado"

pass "cadastro Woo habilitado"

CUSTOMER_FUNCTION="$(
  wpcli eval '
    echo function_exists(
      "wc_create_new_customer"
    )
      ? "yes"
      : "no";
  '
)"

[[ "$CUSTOMER_FUNCTION" == "yes" ]] \
  || fail "wc_create_new_customer indisponivel"

pass "API de clientes WooCommerce"

echo
echo "=== HOME ==="

STATUS="$(
  request \
    "/" \
    "/tmp/fd-w2b-home.html"
)"

echo "HTTP $STATUS"

[[ "$STATUS" == "200" ]] \
  || fail "home HTTP $STATUS"

grep -q \
  'fd-home-hero' \
  /tmp/fd-w2b-home.html \
  || fail "hero comercial ausente"

grep -q \
  'fd-home-featured' \
  /tmp/fd-w2b-home.html \
  || fail "produtos destaque ausentes"

grep -q \
  'fd-home-benefits' \
  /tmp/fd-w2b-home.html \
  || fail "beneficios ausentes"

grep -q \
  'fd-home-faq' \
  /tmp/fd-w2b-home.html \
  || fail "FAQ home ausente"

pass "home comercial"

echo
echo "=== PAGINAS PUBLICAS ==="

for slug in \
  sobre \
  contato \
  faq \
  privacidade \
  termos
do
  STATUS="$(
    request \
      "/${slug}/" \
      "/tmp/fd-w2b-${slug}.html"
  )"

  echo "$slug -> HTTP $STATUS"

  [[ "$STATUS" == "200" ]] \
    || fail "$slug HTTP $STATUS"
done

pass "institucionais HTTP 200"

echo
echo "=== LOGIN ==="

STATUS="$(
  request \
    "/entrar/" \
    "/tmp/fd-w2b-login.html"
)"

[[ "$STATUS" == "200" ]] \
  || fail "login HTTP $STATUS"

grep -q \
  'name="fd_auth_action"' \
  /tmp/fd-w2b-login.html \
  || fail "action login ausente"

grep -q \
  'value="login"' \
  /tmp/fd-w2b-login.html \
  || fail "login action invalida"

grep -q \
  'name="fd_nonce"' \
  /tmp/fd-w2b-login.html \
  || fail "nonce login ausente"

grep -q \
  'name="user_password"' \
  /tmp/fd-w2b-login.html \
  || fail "senha login ausente"

pass "login server-side estruturado"

echo
echo "=== CADASTRO ==="

STATUS="$(
  request \
    "/cadastro/" \
    "/tmp/fd-w2b-register.html"
)"

[[ "$STATUS" == "200" ]] \
  || fail "cadastro HTTP $STATUS"

grep -q \
  'value="register"' \
  /tmp/fd-w2b-register.html \
  || fail "register action ausente"

grep -q \
  'name="fd_nonce"' \
  /tmp/fd-w2b-register.html \
  || fail "nonce cadastro ausente"

grep -q \
  'name="accept_terms"' \
  /tmp/fd-w2b-register.html \
  || fail "aceite legal ausente"

pass "cadastro estruturado"

echo
echo "=== RECUPERACAO ==="

STATUS="$(
  request \
    "/recuperar-senha/" \
    "/tmp/fd-w2b-lost.html"
)"

[[ "$STATUS" == "200" ]] \
  || fail "recuperacao HTTP $STATUS"

grep -q \
  'value="lost_password"' \
  /tmp/fd-w2b-lost.html \
  || fail "lost password action ausente"

grep -q \
  'name="fd_nonce"' \
  /tmp/fd-w2b-lost.html \
  || fail "nonce recuperacao ausente"

pass "recuperacao estruturada"

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
  /workspace/tools/wp-configure.php \
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

  pass "sintaxe JavaScript"
else
  echo "INFO  Node ausente; check JS ignorado"
fi

echo
echo "=== CORE REST ==="

REST="$(
  curl \
    -sS \
    -H "Host: $HOST" \
    -H "X-Forwarded-Proto: https" \
    http://127.0.0.1:8080/wp-json/facil-digital/v1/health
)"

printf '%s' \
  "$REST" \
  | grep -q \
    '"status":"ok"' \
  || fail "Core REST"

pass "Core REST intacto"

echo
echo "=== WOOCOMMERCE ==="

wpcli plugin is-active \
  woocommerce \
  >/dev/null \
  || fail "WooCommerce inativo"

pass "WooCommerce intacto"

echo
echo "=== ELEMENTOR ==="

if wpcli plugin is-active \
  elementor \
  >/dev/null 2>&1
then
  fail "Elementor ativo"
fi

pass "sem Elementor"

echo
echo "=== GIT ==="

if git status --short \
  | grep -E \
    '(^|[[:space:]])\.env$|wp-config\.php|vendor/|private/|storage/'
then
  fail "runtime ou segredo no Git"
fi

pass "runtime privado"

git diff --check

pass "git diff check"

echo
echo "=================================================="
echo "PASS - W2B VALIDADA"
echo "=================================================="