#!/usr/bin/env bash

set -uo pipefail

ROOT="$(
  cd "$(dirname "${BASH_SOURCE[0]}")/.."
  pwd
)"

cd "$ROOT"

mkdir -p .runtime

REPORT="$ROOT/.runtime/p1-audit-report.txt"

: > "$REPORT"

exec > >(tee "$REPORT") 2>&1

PASS_COUNT=0
WARN_COUNT=0
FAIL_COUNT=0

pass() {
  PASS_COUNT=$((PASS_COUNT + 1))
  echo "PASS  $1"
}

warn() {
  WARN_COUNT=$((WARN_COUNT + 1))
  echo "WARN  $1"
}

fail() {
  FAIL_COUNT=$((FAIL_COUNT + 1))
  echo "FAIL  $1"
}

section() {
  echo
  echo "=================================================="
  echo "$1"
  echo "=================================================="
}

wpcli() {
  docker compose run --rm wpcli wp "$@"
}

version_ge() {
  printf '%s\n%s\n' "$2" "$1" |
    sort -V -C
}

section "P1 - AUDITORIA PRE-PRODUCAO"

echo "Repositorio: $ROOT"
echo "Data UTC: $(date -u '+%Y-%m-%dT%H:%M:%SZ')"

#
# GIT
#

section "1. GIT / WORKTREE"

BRANCH="$(git branch --show-current 2>/dev/null || true)"

echo "Branch: ${BRANCH:-desconhecida}"

if [[ "$BRANCH" == "feat/w3-core-foundation" ]]; then
  pass "branch de trabalho esperada"
else
  warn "branch atual diferente de feat/w3-core-foundation"
fi

DIRTY="$(
  git status --porcelain |
    grep -vE '^\?\? tools/audit-preproduction\.sh$' |
    grep -vE '^[ MARCUD?!]{2} \.runtime/' \
    || true
)"

if [[ -z "$DIRTY" ]]; then
  pass "working tree limpa, ignorando o auditor P1"
else
  echo "$DIRTY"
  warn "working tree possui alteracoes fora do auditor"
fi

if git diff --check; then
  pass "git diff check"
else
  fail "git diff encontrou problemas"
fi

#
# SEGREDOS
#

section "2. SEGREDOS / ARQUIVOS PRIVADOS"

if git ls-files --error-unmatch .env >/dev/null 2>&1; then
  fail ".env esta versionado"
else
  pass ".env nao versionado"
fi

if git check-ignore .env >/dev/null 2>&1; then
  pass ".env protegido pelo gitignore"
else
  fail ".env nao esta protegido pelo gitignore"
fi

SECRET_REPORT="/tmp/fd-p1-secrets.txt"
rm -f "$SECRET_REPORT"

if git grep \
  -nEI \
  'APP_USR-[A-Za-z0-9._-]{20,}' \
  -- \
  ':!*.lock' \
  >"$SECRET_REPORT" 2>/dev/null
then
  cat "$SECRET_REPORT"
  fail "possivel credencial Mercado Pago versionada"
else
  pass "nenhuma credencial Mercado Pago evidente no Git"
fi

#
# DOCKER
#

section "3. DOCKER / COMPOSE"

if docker compose config -q; then
  pass "docker compose config valido"
else
  fail "docker compose config invalido"
fi

docker compose ps || true

RUNNING_SERVICES="$(
  docker compose ps \
    --status running \
    --services \
    2>/dev/null \
    || true
)"

if grep -qx "db" <<<"$RUNNING_SERVICES"; then
  pass "MariaDB em execucao"
else
  fail "MariaDB nao esta em execucao"
fi

if grep -qx "wordpress" <<<"$RUNNING_SERVICES"; then
  pass "WordPress em execucao"
else
  fail "WordPress nao esta em execucao"
fi

#
# WORDPRESS
#

section "4. WORDPRESS"

if wpcli core is-installed >/dev/null 2>&1; then
  pass "WordPress instalado"
else
  fail "WordPress nao instalado"
fi

WP_VERSION="$(
  wpcli core version 2>/dev/null |
    tail -1 \
    || true
)"

echo "WordPress: $WP_VERSION"

ENVIRONMENT="$(
  wpcli eval \
    'echo wp_get_environment_type();' \
    2>/dev/null |
    tail -1 \
    || true
)"

echo "Environment: $ENVIRONMENT"

if [[ "$ENVIRONMENT" == "production" ]]; then
  warn "Codespaces esta marcado como production"
else
  pass "ambiente nao esta marcado como production"
fi

#
# URLs
#

section "5. URLS"

HOME_URL="$(
  wpcli option get home 2>/dev/null |
    tail -1 \
    || true
)"

SITE_URL="$(
  wpcli option get siteurl 2>/dev/null |
    tail -1 \
    || true
)"

echo "HOME:    $HOME_URL"
echo "SITEURL: $SITE_URL"

if [[ -n "$HOME_URL" && "$HOME_URL" == "$SITE_URL" ]]; then
  pass "home e siteurl consistentes"
else
  fail "home e siteurl divergentes ou vazios"
fi

if [[ "$HOME_URL" == *"app.github.dev"* ]]; then
  pass "URL adequada ao ambiente Codespaces"
elif [[ "$HOME_URL" == http://localhost* || "$HOME_URL" == https://localhost* ]]; then
  warn "WordPress esta configurado para localhost"
else
  warn "URL atual nao e Codespaces nem localhost"
fi

echo
echo "Buscando URLs de ambiente hardcoded no codigo..."

HARDCODED="/tmp/fd-p1-hardcoded-urls.txt"
rm -f "$HARDCODED"

if git grep \
  -nEI \
  'localhost|127\.0\.0\.1|app\.github\.dev' \
  -- \
  wp-content \
  ':!wp-content/plugins/facil-digital-core/src/Release/ReleaseReadinessService.php' \
  >"$HARDCODED" 2>/dev/null
then
  cat "$HARDCODED"
  warn "URLs de ambiente encontradas em wp-content"
else
  pass "nenhuma URL de ambiente hardcoded em wp-content; denylist de ReleaseReadiness excluida"
fi

#
# PLUGINS
#

section "6. PLUGINS / TEMA"

wpcli plugin list \
  --fields=name,status,version \
  --format=table \
  || true

if wpcli plugin is-active woocommerce >/dev/null 2>&1; then
  pass "WooCommerce ativo"
else
  fail "WooCommerce inativo"
fi

if wpcli plugin is-active facil-digital-core >/dev/null 2>&1; then
  pass "Facil Digital+ Core ativo"
else
  fail "Facil Digital+ Core inativo"
fi

CORE_VERSION="$(
  wpcli plugin get \
    facil-digital-core \
    --field=version \
    2>/dev/null |
    tail -1 \
    || true
)"

echo "Core: $CORE_VERSION"

if [[ -n "$CORE_VERSION" ]] &&
  version_ge "$CORE_VERSION" "0.9.0"
then
  pass "Core >= 0.9.0"
else
  fail "Core inferior a 0.9.0"
fi

if wpcli plugin is-active \
  woocommerce-mercadopago \
  >/dev/null 2>&1
then
  pass "plugin oficial Mercado Pago ativo"
else
  warn "plugin Mercado Pago nao identificado pelo slug esperado"
fi

ACTIVE_THEME="$(
  wpcli theme list \
    --status=active \
    --field=name \
    2>/dev/null |
    tail -1 \
    || true
)"

echo "Tema ativo: $ACTIVE_THEME"

if [[ "$ACTIVE_THEME" == "facil-digital" ]]; then
  pass "tema Facil Digital+ ativo"
else
  fail "tema Facil Digital+ nao e o tema ativo"
fi

#
# WOOCOMMERCE
#

section "7. WOOCOMMERCE"

COUNTRY="$(
  wpcli option get \
    woocommerce_default_country \
    2>/dev/null |
    tail -1 \
    || true
)"

CURRENCY="$(
  wpcli option get \
    woocommerce_currency \
    2>/dev/null |
    tail -1 \
    || true
)"

COMING_SOON="$(
  wpcli option get \
    woocommerce_coming_soon \
    2>/dev/null |
    tail -1 \
    || true
)"

echo "Pais:        $COUNTRY"
echo "Moeda:       $CURRENCY"
echo "Coming Soon: $COMING_SOON"

if [[ "${COUNTRY%%:*}" == "BR" ]]; then
  pass "pais WooCommerce BR"
else
  fail "pais WooCommerce diferente de BR"
fi

if [[ "$CURRENCY" == "BRL" ]]; then
  pass "moeda WooCommerce BRL"
else
  fail "moeda WooCommerce diferente de BRL"
fi

if [[ "$COMING_SOON" == "no" ]]; then
  pass "WooCommerce nao esta em Coming Soon"
else
  warn "WooCommerce esta em Coming Soon"
fi

#
# PAGINAS
#

section "8. PAGINAS / ROTAS"

wpcli eval '
$pages = [
    "shop" =>
        function_exists("wc_get_page_permalink")
            ? wc_get_page_permalink("shop")
            : "",

    "cart" =>
        function_exists("wc_get_cart_url")
            ? wc_get_cart_url()
            : "",

    "checkout" =>
        function_exists("wc_get_checkout_url")
            ? wc_get_checkout_url()
            : "",

    "myaccount" =>
        function_exists("wc_get_page_permalink")
            ? wc_get_page_permalink("myaccount")
            : "",

    "apostilas" =>
        home_url("/apostilas/"),

    "student_apostilas" =>
        function_exists("wc_get_account_endpoint_url")
            ? wc_get_account_endpoint_url("apostilas")
            : "",
];

foreach ($pages as $name => $url) {
    echo $name . "|" . $url . PHP_EOL;
}
' > /tmp/fd-p1-routes.txt 2>/dev/null || true

cat /tmp/fd-p1-routes.txt

echo
echo "=== HTTP ==="

while IFS='|' read -r name url
do
  [[ -n "$name" && -n "$url" ]] || continue

  HEADERS="/tmp/fd-p1-${name}.headers"
  rm -f "$HEADERS"

  HTTP="$(
    curl \
      -sS \
      -o /dev/null \
      -D "$HEADERS" \
      -w '%{http_code}' \
      "$url" \
      2>/dev/null \
      || true
  )"

  LOCATION="$(
    grep -i '^location:' "$HEADERS" 2>/dev/null |
      tail -1 |
      sed -E 's/^[Ll]ocation:[[:space:]]*//' |
      tr -d '\r' \
      || true
  )"

  echo "$name: HTTP ${HTTP:-000}"

  if [[ -n "$LOCATION" ]]; then
    echo "  Location: $LOCATION"
  fi

  if [[ "$LOCATION" == *"localhost"* &&
        "$HOME_URL" != *"localhost"* ]]
  then
    warn "$name redireciona para localhost no Codespaces"
    continue
  fi

  case "$HTTP" in
    2??)
      pass "$name respondeu HTTP $HTTP"
      ;;
    3??)
      pass "$name respondeu com redirect HTTP $HTTP"
      ;;
    *)
      fail "$name respondeu HTTP ${HTTP:-000}"
      ;;
  esac

done < /tmp/fd-p1-routes.txt

#
# CORE DATABASE
#

section "9. CORE / DATABASE"

CORE_DB="$(
  wpcli eval '
use FacilDigital\Core\Core\Database;

echo "READY="
    . (Database::isReady() ? "yes" : "no")
    . PHP_EOL;

echo "TABLES="
    . count(Database::tables())
    . PHP_EOL;

echo "MISSING="
    . implode(",", Database::missingTables())
    . PHP_EOL;
' 2>/dev/null \
  || true
)"

echo "$CORE_DB"

if grep -q '^READY=yes$' <<<"$CORE_DB"; then
  pass "Core database pronta"
else
  fail "Core database nao esta pronta"
fi

if grep -q '^TABLES=9$' <<<"$CORE_DB"; then
  pass "9 tabelas Core presentes"
else
  fail "quantidade inesperada de tabelas Core"
fi

if grep -q '^MISSING=$' <<<"$CORE_DB"; then
  pass "nenhuma tabela Core ausente"
else
  fail "ha tabelas Core ausentes"
fi

#
# MODULOS
#

section "10. MODULE REGISTRY"

wpcli eval '
use FacilDigital\Core\Core\ModuleRegistry;

$modules = ModuleRegistry::defaults();

foreach ($modules as $module) {
    echo get_class($module) . PHP_EOL;
}

echo "TOTAL=" . count($modules) . PHP_EOL;
' || fail "nao foi possivel consultar ModuleRegistry"

pass "ModuleRegistry consultado"

#
# TEMA / TEMPLATES
#

section "11. FRONTEND / TEMPLATES"

THEME="wp-content/themes/facil-digital"

EXPECTED_THEME_FILES=(
  "$THEME/functions.php"
  "$THEME/header.php"
  "$THEME/footer.php"
  "$THEME/front-page.php"
  "$THEME/woocommerce/archive-product.php"
  "$THEME/woocommerce/single-product.php"
  "$THEME/woocommerce/content-product.php"
)

for file in "${EXPECTED_THEME_FILES[@]}"
do
  if [[ -f "$file" ]]; then
    pass "presente: $file"
  else
    warn "template ausente: $file"
  fi
done

#
# STORAGE PRIVADO
#

section "12. STORAGE PRIVADO"

PRIVATE_STORAGE=".runtime/facil-digital-private"

if [[ -d "$PRIVATE_STORAGE" ]]; then
  pass "storage privado existe"

  COUNT_PRIVATE="$(
    find "$PRIVATE_STORAGE" \
      -type f \
      2>/dev/null |
      wc -l
  )"

  echo "Arquivos privados: $COUNT_PRIVATE"
else
  warn "storage privado nao encontrado neste runtime"
fi

if git ls-files '.runtime/*' |
  grep -q .
then
  fail ".runtime possui arquivos versionados"
else
  pass ".runtime fora do Git"
fi

#
# ACTION SCHEDULER
#

section "13. ACTION SCHEDULER"

wpcli eval '
global $wpdb;

$table =
    $wpdb->prefix
    . "actionscheduler_actions";

$exists = $wpdb->get_var(
    $wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $table
    )
);

if ($exists !== $table) {
    echo "AVAILABLE=no" . PHP_EOL;
    return;
}

echo "AVAILABLE=yes" . PHP_EOL;

foreach (
    [
        "pending",
        "in-progress",
        "failed",
        "complete",
    ] as $status
) {
    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$table}
             WHERE status = %s",
            $status
        )
    );

    echo strtoupper(
        str_replace("-", "_", $status)
    )
    . "="
    . $count
    . PHP_EOL;
}
' > /tmp/fd-p1-actions.txt 2>/dev/null || true

cat /tmp/fd-p1-actions.txt

if grep -q '^AVAILABLE=yes$' \
  /tmp/fd-p1-actions.txt
then
  pass "Action Scheduler disponivel"
else
  warn "Action Scheduler nao identificado"
fi

FAILED_ACTIONS="$(
  grep '^FAILED=' \
    /tmp/fd-p1-actions.txt |
    cut -d= -f2 \
    || echo 0
)"

if [[ "${FAILED_ACTIONS:-0}" =~ ^[0-9]+$ ]] &&
   (( FAILED_ACTIONS > 0 ))
then
  warn "Action Scheduler possui $FAILED_ACTIONS actions failed"
else
  pass "nenhuma action failed detectada"
fi

#
# DADOS DE TESTE
#

section "14. DADOS ARTIFICIAIS / TESTES"

wpcli eval '
global $wpdb;

$m1 = (int) $wpdb->get_var(
    "SELECT COUNT(*)
     FROM {$wpdb->posts}
     WHERE post_type = '\''product'\''
       AND post_name LIKE '\''fd-m1-%'\''"
);

echo "M1_PRODUCTS={$m1}" . PHP_EOL;

$product = function_exists("wc_get_product")
    ? wc_get_product(127)
    : false;

echo "W20_PRODUCT_127="
    . ($product ? "yes" : "no")
    . PHP_EOL;

$order = function_exists("wc_get_order")
    ? wc_get_order(128)
    : false;

echo "W20_ORDER_128="
    . ($order ? "yes" : "no")
    . PHP_EOL;

$user = get_user_by("id", 44);

echo "W20_USER_44="
    . (
        $user
            ? $user->user_login
            : "absent"
    )
    . PHP_EOL;
' > /tmp/fd-p1-testdata.txt 2>/dev/null || true

cat /tmp/fd-p1-testdata.txt

M1_PRODUCTS="$(
  grep '^M1_PRODUCTS=' \
    /tmp/fd-p1-testdata.txt |
    cut -d= -f2 \
    || echo 0
)"

if [[ "${M1_PRODUCTS:-0}" =~ ^[0-9]+$ ]] &&
   (( M1_PRODUCTS > 0 ))
then
  warn "$M1_PRODUCTS produto(s) de seed M1 ainda presentes"
else
  pass "nenhum produto fd-m1 identificado"
fi

if grep -q '^W20_PRODUCT_127=yes$' \
  /tmp/fd-p1-testdata.txt
then
  warn "produto de teste W20 #127 ainda presente"
fi

if grep -q '^W20_ORDER_128=yes$' \
  /tmp/fd-p1-testdata.txt
then
  warn "pedido de teste W20 #128 ainda presente"
fi

if grep -q '^W20_USER_44=cliente_w20$' \
  /tmp/fd-p1-testdata.txt
then
  warn "usuario cliente_w20 ainda presente"
fi

#
# HARDENING
#

section "15. HARDENING ATUAL"

wpcli eval '
echo "WP_DEBUG="
    . (
        defined("WP_DEBUG")
        && WP_DEBUG
            ? "true"
            : "false"
    )
    . PHP_EOL;

echo "DISALLOW_FILE_EDIT="
    . (
        defined("DISALLOW_FILE_EDIT")
        && DISALLOW_FILE_EDIT
            ? "true"
            : "false"
    )
    . PHP_EOL;

echo "FORCE_SSL_ADMIN="
    . (
        defined("FORCE_SSL_ADMIN")
        && FORCE_SSL_ADMIN
            ? "true"
            : "false"
    )
    . PHP_EOL;
' || true

if [[ "$ENVIRONMENT" != "production" ]]; then
  pass "hardening de producao sera gate posterior"
fi

#
# SINTAXE
#

section "16. SINTAXE PHP / SHELL"

PHP_ERROR=0

while IFS= read -r file
do
  if ! docker compose exec \
    -T wordpress \
    php -l "/workspace/$file" \
    >/dev/null 2>&1
  then
    echo "PHP INVALIDO: $file"
    PHP_ERROR=1
  fi

done < <(
  find \
    wp-content/plugins/facil-digital-core \
    wp-content/themes/facil-digital \
    -type f \
    -name '*.php' \
    -not -path '*/vendor/*' |
    sort
)

if (( PHP_ERROR == 0 )); then
  pass "PHP sem erros de sintaxe"
else
  fail "PHP possui erro de sintaxe"
fi

SHELL_ERROR=0

while IFS= read -r file
do
  if ! bash -n "$file"
  then
    echo "SHELL INVALIDO: $file"
    SHELL_ERROR=1
  fi

done < <(
  find tools \
    -maxdepth 1 \
    -type f \
    -name '*.sh' |
    sort
)

if (( SHELL_ERROR == 0 )); then
  pass "scripts shell sem erros de sintaxe"
else
  fail "scripts shell possuem erro de sintaxe"
fi

#
# REGRESSAO OPCIONAL
#

if [[ "${1:-}" == "--full" ]]; then

  section "17. REGRESSAO COMPLETA ATE M4"

  REGRESSION_LOG="$ROOT/.runtime/p1-regression-m4.log"

  echo "A regressao sera exibida ao vivo e salva em:"
  echo "$REGRESSION_LOG"
  echo

  if ./tools/validate-m4.sh 2>&1 | tee "$REGRESSION_LOG"
  then
    pass "regressao M1-M4 aprovada"
  else
    echo
    echo "=== ULTIMAS LINHAS DA FALHA ==="
    tail -80 "$REGRESSION_LOG"
    fail "regressao M1-M4 falhou"
  fi

else

  section "17. REGRESSAO COMPLETA"

  echo "Nao executada neste primeiro passe."
  echo
  echo "Depois da analise inicial:"
  echo "  ./tools/audit-preproduction.sh --full"

  warn "regressao M1-M4 ainda nao executada nesta auditoria"

fi

#
# RESULTADO
#

section "RESULTADO P1"

echo "PASS: $PASS_COUNT"
echo "WARN: $WARN_COUNT"
echo "FAIL: $FAIL_COUNT"

echo
echo "Relatorio:"
echo "$REPORT"

if (( FAIL_COUNT > 0 )); then
  echo
  echo "P1_STATUS=FAIL"
  exit 1
fi

echo
echo "P1_STATUS=PASS_WITH_WARNINGS"

exit 0
