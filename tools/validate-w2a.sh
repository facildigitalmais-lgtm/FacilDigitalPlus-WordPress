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

echo "=================================================="
echo "W2A - VALIDACAO"
echo "=================================================="

echo
echo "=== W1 ==="

./tools/validate-w1.sh \
  >/tmp/fd-w2a-w1.log

tail -5 \
  /tmp/fd-w2a-w1.log

pass "fundacao W1"

echo
echo "=== TEMA ==="

wpcli theme is-active \
  facil-digital \
  >/dev/null \
  || fail "tema inativo"

pass "tema ativo"

THEME_VERSION="$(
  wpcli theme get \
    facil-digital \
    --field=version
)"

MIN_VERSION="$(
  printf '%s\n' \
    "0.2.0" \
    "$THEME_VERSION" \
  | sort -V \
  | head -1
)"

[[ "$MIN_VERSION" == "0.2.0" ]] \
  || fail "tema anterior a 0.2.0"

echo "Tema: $THEME_VERSION"
pass "tema >= 0.2.0"

echo
echo "=== THEME SUPPORT ==="

CUSTOM_LOGO="$(
  wpcli eval '
    echo current_theme_supports(
      "custom-logo"
    )
      ? "yes"
      : "no";
  '
)"

[[ "$CUSTOM_LOGO" == "yes" ]] \
  || fail "custom-logo ausente"

pass "custom logo"

WOOCOMMERCE_SUPPORT="$(
  wpcli eval '
    echo current_theme_supports(
      "woocommerce"
    )
      ? "yes"
      : "no";
  '
)"

[[ "$WOOCOMMERCE_SUPPORT" == "yes" ]] \
  || fail "WooCommerce theme support ausente"

pass "WooCommerce theme support"

echo
echo "=== MENUS ==="

MENU_LOCATIONS="$(
  wpcli eval '
    echo wp_json_encode(
      get_registered_nav_menus()
    );
  '
)"

echo "$MENU_LOCATIONS"

for location in \
  primary \
  footer-company \
  footer-support \
  footer-legal
do
  printf '%s' \
    "$MENU_LOCATIONS" \
    | grep -q \
      "\"$location\"" \
    || fail \
      "menu ausente: $location"
done

pass "menu locations"

echo
echo "=== ARQUIVOS ==="

required_files=(
  "inc/setup.php"
  "inc/assets.php"
  "inc/template-functions.php"
  "inc/woocommerce.php"
  "searchform.php"
  "page.php"
  "assets/css/variables.css"
  "assets/css/reset.css"
  "assets/css/typography.css"
  "assets/css/layout.css"
  "assets/css/components.css"
  "assets/css/header.css"
  "assets/css/footer.css"
  "assets/css/responsive.css"
  "assets/js/navigation.js"
  "template-parts/header/site-branding.php"
  "template-parts/header/primary-navigation.php"
  "template-parts/header/header-actions.php"
  "template-parts/footer/footer-main.php"
  "template-parts/footer/footer-bottom.php"
)

for file in "${required_files[@]}"
do
  test -f \
    "wp-content/themes/facil-digital/$file" \
    || fail \
      "arquivo ausente: $file"
done

pass "estrutura W2A"

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

pass "sintaxe PHP"

echo
echo "=== JAVASCRIPT ==="

if command -v node \
  >/dev/null 2>&1
then
  node --check \
    wp-content/themes/facil-digital/assets/js/navigation.js

  pass "sintaxe JavaScript"
else
  echo "INFO  Node ausente; check JS ignorado"
fi

echo
echo "=== FRONTEND ==="

HOST="$(
  printf '%s' \
    "$WORDPRESS_URL" \
    | sed -E \
      's#^https?://##; s#/$##'
)"

HTTP_STATUS="$(
  curl \
    -sS \
    -o /tmp/fd-w2a-home.html \
    -w '%{http_code}' \
    -H "Host: $HOST" \
    -H "X-Forwarded-Proto: https" \
    http://127.0.0.1:8080/
)"

echo "HTTP $HTTP_STATUS"

[[ "$HTTP_STATUS" == "200" ]] \
  || fail "home nao retornou 200"

grep -q \
  'fd-site-header' \
  /tmp/fd-w2a-home.html \
  || fail "header ausente"

grep -q \
  'fd-primary-navigation' \
  /tmp/fd-w2a-home.html \
  || fail "navegacao ausente"

grep -q \
  'fd-header-actions' \
  /tmp/fd-w2a-home.html \
  || fail "acoes header ausentes"

grep -q \
  'fd-site-footer' \
  /tmp/fd-w2a-home.html \
  || fail "footer ausente"

grep -q \
  'fd-skip-link' \
  /tmp/fd-w2a-home.html \
  || fail "skip link ausente"

pass "HTML estrutural"

echo
echo "=== CORE REST ==="

REST_RESPONSE="$(
  curl \
    -sS \
    -H "Host: $HOST" \
    -H "X-Forwarded-Proto: https" \
    http://127.0.0.1:8080/wp-json/facil-digital/v1/health
)"

printf '%s' \
  "$REST_RESPONSE" \
  | grep -q \
    '"status":"ok"' \
  || fail "Core REST"

pass "Core REST intacto"

echo
echo "=== ELEMENTOR ==="

if wpcli plugin is-active \
  elementor \
  >/dev/null 2>&1
then
  fail "Elementor nao deve estar ativo"
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

echo
echo "=================================================="
echo "PASS - W2A VALIDADA"
echo "=================================================="