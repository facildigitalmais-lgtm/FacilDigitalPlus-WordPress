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
  echo "❌ .env nao existe."
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
echo "W1 - VALIDACAO"
echo "=================================================="

echo
echo "=== CONTAINERS ==="

docker compose ps

docker compose exec \
  -T \
  db \
  healthcheck.sh \
  --connect \
  --innodb_initialized \
  >/dev/null \
  || fail "MariaDB indisponivel"

pass "MariaDB disponivel"

echo
echo "=== PHP ==="

PHP_VERSION="$(
  docker compose exec \
    -T \
    wordpress \
    php -r 'echo PHP_VERSION;'
)"

echo "PHP: $PHP_VERSION"

PHP_MAJOR_MINOR="$(
  echo "$PHP_VERSION" \
    | cut -d. -f1-2
)"

[[ "$PHP_MAJOR_MINOR" == "8.3" ]] \
  || fail "PHP esperado 8.3"

pass "PHP 8.3"

echo
echo "=== WORDPRESS ==="

WP_VERSION="$(
  wpcli core version
)"

echo "WordPress: $WP_VERSION"

[[ "$WP_VERSION" == "7.1" ]] \
  || fail "WordPress esperado 7.1"

pass "WordPress 7.1"

echo
echo "=== PREFIXO ==="

DB_PREFIX="$(
  wpcli eval \
    'global $wpdb; echo $wpdb->prefix;'
)"

echo "Prefixo: $DB_PREFIX"

[[ "$DB_PREFIX" == "$WORDPRESS_TABLE_PREFIX" ]] \
  || fail "Prefixo WordPress incorreto"

pass "prefixo customizado"

echo
echo "=== WOOCOMMERCE ==="

WOO_VERSION="$(
  wpcli plugin get \
    woocommerce \
    --field=version
)"

echo "WooCommerce: $WOO_VERSION"

[[ "$WOO_VERSION" == "11.0.1" ]] \
  || fail "WooCommerce esperado 11.0.1"

wpcli plugin is-active \
  woocommerce \
  >/dev/null \
  || fail "WooCommerce inativo"

pass "WooCommerce 11.0.1 ativo"

echo
echo "=== TEMA ==="

wpcli theme is-active \
  facil-digital \
  >/dev/null \
  || fail "Tema Facil Digital+ inativo"

pass "tema Facil Digital+ ativo"

echo
echo "=== CORE ==="

wpcli plugin is-active \
  facil-digital-core \
  >/dev/null \
  || fail "Facil Digital+ Core inativo"

CORE_BOOTED="$(
  wpcli eval \
    'echo defined("FACIL_DIGITAL_CORE_BOOTED") ? "yes" : "no";'
)"

[[ "$CORE_BOOTED" == "yes" ]] \
  || fail "Core nao concluiu bootstrap"

pass "Facil Digital+ Core ativo"

echo
echo "=== HPOS ==="

HPOS_DECLARED="$(
  wpcli eval '
    if (
      class_exists(
        "\Automattic\WooCommerce\Utilities\FeaturesUtil"
      )
    ) {
      echo "available";
    } else {
      echo "missing";
    }
  '
)"

[[ "$HPOS_DECLARED" == "available" ]] \
  || fail "FeaturesUtil WooCommerce indisponivel"

pass "infraestrutura HPOS disponivel"

echo
echo "=== CONFIGURACAO COMERCIAL ==="

CURRENCY="$(
  wpcli option get \
    woocommerce_currency
)"

[[ "$CURRENCY" == "BRL" ]] \
  || fail "Moeda deve ser BRL"

pass "moeda BRL"

COUNTRY="$(
  wpcli option get \
    woocommerce_default_country
)"

[[ "${COUNTRY%%:*}" == "BR" ]] \
  || fail "Pais deve ser BR; atual: $COUNTRY"

pass "pais BR"

COMING_SOON="$(
  wpcli option get \
    woocommerce_coming_soon
)"

[[ "$COMING_SOON" == "no" ]] \
  || fail "loja WooCommerce esta em Coming Soon"

pass "loja publica para testes E2E"

GUEST="$(
  wpcli option get \
    woocommerce_enable_guest_checkout
)"

[[ "$GUEST" == "no" ]] \
  || fail "Guest checkout deve estar desativado"

pass "guest checkout desativado"

REGISTRATION="$(
  wpcli option get \
    users_can_register
)"

[[ "$REGISTRATION" == "1" ]] \
  || fail "Cadastro WordPress deve estar ativo"

pass "cadastro de usuarios ativo"

echo
echo "=== PAGINAS ==="

for slug in \
  inicio \
  apostilas \
  carrinho \
  checkout \
  minha-conta \
  entrar \
  cadastro \
  recuperar-senha \
  sobre \
  contato \
  faq \
  privacidade \
  termos
do
  PAGE_ID="$(
    wpcli post list \
      --post_type=page \
      --name="$slug" \
      --field=ID \
      --format=ids
  )"

  [[ -n "$PAGE_ID" ]] \
    || fail "Pagina ausente: $slug"
done

pass "paginas base criadas"

echo
echo "=== PERMALINKS ==="

PERMALINK="$(
  wpcli option get \
    permalink_structure
)"

[[ "$PERMALINK" == "/%postname%/" ]] \
  || fail "Permalink incorreto"

pass "permalinks amigaveis"

echo
echo "=== SEGURANCA GIT ==="

if git ls-files \
    | grep -Eq \
      '(^|/)\.env$|wp-config\.php$|(^|/)private/|(^|/)storage/'
then
  fail "arquivo privado versionado"
fi

pass "nenhum arquivo privado versionado"

if git ls-files \
    | grep -Eq \
      'wp-content/plugins/facil-digital-core/vendor/'
then
  fail "vendor do Core versionado"
fi

pass "vendor nao versionado"

echo
echo "=================================================="
echo "PASS - W1 FUNDACAO VALIDADA"
echo "=================================================="