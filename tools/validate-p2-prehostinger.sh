#!/usr/bin/env bash

set -uo pipefail

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

section "P2 - FRONTEND PRE-HOSTINGER"

BRANCH="$(
    git branch --show-current \
        2>/dev/null \
        || true
)"

echo "Branch: $BRANCH"

if [[ "$BRANCH" == "feat/w3-core-foundation" ]]; then
    pass "branch esperada"
else
    fail "branch inesperada"
fi

if git diff --check; then
    pass "git diff --check"
else
    fail "git diff --check"
fi

if git ls-files --error-unmatch .env \
    >/dev/null 2>&1
then
    fail ".env versionado"
else
    pass ".env fora do Git"
fi


section "WORDPRESS / PLUGINS"

ENVIRONMENT="$(
    wpcli eval \
        'echo wp_get_environment_type();' \
        2>/dev/null \
        | tail -1 \
        || true
)"

echo "Environment: $ENVIRONMENT"

if [[ "$ENVIRONMENT" == "production" ]]; then
    fail "Codespaces marcado como production"
else
    pass "ambiente nao-producao"
fi

for plugin in \
    facil-digital-core \
    woocommerce \
    woocommerce-mercadopago
do
    if wpcli plugin is-active "$plugin" \
        >/dev/null 2>&1
    then
        pass "plugin ativo: $plugin"
    else
        fail "plugin inativo: $plugin"
    fi
done

THEME="$(
    wpcli theme list \
        --status=active \
        --field=name \
        2>/dev/null \
        | tail -1 \
        || true
)"

if [[ "$THEME" == "facil-digital" ]]; then
    pass "tema Facil Digital+ ativo"
else
    fail "tema inesperado: $THEME"
fi


section "CORE DATABASE"

CORE_DB="$(
    wpcli eval '
use FacilDigital\Core\Core\Database;

echo "READY="
    . (
        Database::isReady()
            ? "yes"
            : "no"
    )
    . PHP_EOL;

echo "MISSING="
    . implode(
        ",",
        Database::missingTables()
    )
    . PHP_EOL;
' 2>/dev/null \
    || true
)"

echo "$CORE_DB"

if grep -q '^READY=yes$' \
    <<<"$CORE_DB"
then
    pass "Core database pronta"
else
    fail "Core database nao pronta"
fi

if grep -q '^MISSING=$' \
    <<<"$CORE_DB"
then
    pass "nenhuma tabela Core ausente"
else
    fail "tabelas Core ausentes"
fi


section "WOOCOMMERCE TEMPLATES"

LOCATED="$(
    wpcli eval '
foreach (
    [
        "archive-product.php",
        "single-product.php",
        "content-product.php",
        "myaccount/dashboard.php",
        "myaccount/orders.php",
        "myaccount/form-edit-account.php",
    ] as $template
) {
    echo $template
        . "="
        . wc_locate_template(
            $template
        )
        . PHP_EOL;
}
' 2>/dev/null \
    || true
)"

echo "$LOCATED"

for expected in \
    "facil-digital/woocommerce/archive-product.php" \
    "facil-digital/woocommerce/single-product.php" \
    "facil-digital/woocommerce/content-product.php" \
    "facil-digital/woocommerce/myaccount/dashboard.php" \
    "facil-digital/woocommerce/myaccount/orders.php" \
    "facil-digital/woocommerce/myaccount/form-edit-account.php"
do
    if grep -q "$expected" \
        <<<"$LOCATED"
    then
        pass "template: $expected"
    else
        fail "template nao localizado: $expected"
    fi
done


section "BUSCA"

SEARCH_FORM="$(
    wpcli eval '
echo get_search_form(
    [
        "echo" => false,
    ]
);
' 2>/dev/null \
    || true
)"

if grep -q 'name="busca"' \
    <<<"$SEARCH_FORM"
then
    pass "busca usa parametro multicampo"
else
    fail "searchform nao usa busca"
fi

if grep -q '/apostilas/' \
    <<<"$SEARCH_FORM"
then
    pass "busca aponta para catalogo"
else
    fail "busca nao aponta para catalogo"
fi

if grep -q 'name="post_type"' \
    <<<"$SEARCH_FORM"
then
    fail "searchform ainda usa post_type"
else
    pass "searchform sem post_type legado"
fi


section "PAGINAS"

for id in 7 8 10 2
do
    STATUS="$(
        wpcli post get "$id" \
            --field=post_status \
            2>/dev/null \
            | tail -1 \
            || true
    )"

    if [[ "$STATUS" == "draft" ]]; then
        pass "pagina antiga #$id em draft"
    else
        fail "pagina antiga #$id status=$STATUS"
    fi
done

SHOP_ID="$(
    wpcli eval \
        'echo wc_get_page_id("shop");' \
        2>/dev/null \
        | tail -1
)"

CART_ID="$(
    wpcli eval \
        'echo wc_get_page_id("cart");' \
        2>/dev/null \
        | tail -1
)"

CHECKOUT_ID="$(
    wpcli eval \
        'echo wc_get_page_id("checkout");' \
        2>/dev/null \
        | tail -1
)"

ACCOUNT_ID="$(
    wpcli eval \
        'echo wc_get_page_id("myaccount");' \
        2>/dev/null \
        | tail -1
)"

[[ "$SHOP_ID" == "13" ]] \
    && pass "shop #13" \
    || fail "shop inesperado: $SHOP_ID"

[[ "$CART_ID" == "14" ]] \
    && pass "cart #14" \
    || fail "cart inesperado: $CART_ID"

[[ "$CHECKOUT_ID" == "9" ]] \
    && pass "checkout #9" \
    || fail "checkout inesperado: $CHECKOUT_ID"

[[ "$ACCOUNT_ID" == "15" ]] \
    && pass "account #15" \
    || fail "account inesperado: $ACCOUNT_ID"


section "AREA DO ALUNO"

ACCOUNT="$(
    wpcli eval '
$items =
    apply_filters(
        "woocommerce_account_menu_items",
        wc_get_account_menu_items()
    );

foreach ($items as $endpoint => $label) {
    echo $endpoint
        . "|"
        . wp_strip_all_tags($label)
        . PHP_EOL;
}
' 2>/dev/null \
    || true
)"

echo "$ACCOUNT"

for expected in \
    "dashboard|Visão geral" \
    "apostilas|Minhas apostilas" \
    "simulados|Simulados" \
    "resultados|Resultados" \
    "orders|Pedidos" \
    "downloads|Downloads" \
    "edit-account|Meus dados" \
    "seguranca|Segurança" \
    "customer-logout|Sair"
do
    if grep -Fqx "$expected" \
        <<<"$ACCOUNT"
    then
        pass "menu: $expected"
    else
        fail "menu ausente: $expected"
    fi
done


section "APOSTILA / DOWNLOAD"

PRODUCT="$(
    wpcli eval '
$product =
    wc_get_product(127);

echo "APOSTILA="
    . (
        \FacilDigital\Core\Products\ProductMetadata::isApostila(
            127
        )
            ? "yes"
            : "no"
    )
    . PHP_EOL;

echo "SOLD_INDIVIDUALLY="
    . (
        $product
        && $product->is_sold_individually()
            ? "yes"
            : "no"
    )
    . PHP_EOL;
' 2>/dev/null \
    || true
)"

echo "$PRODUCT"

grep -q '^APOSTILA=yes$' \
    <<<"$PRODUCT" \
    && pass "produto W20 continua apostila" \
    || fail "produto W20 nao e apostila"

grep -q '^SOLD_INDIVIDUALLY=yes$' \
    <<<"$PRODUCT" \
    && pass "apostila vendida individualmente" \
    || fail "regra sold individually"


DOWNLOAD="$(
    wpcli eval '
wp_set_current_user(44);

$account =
    new \FacilDigital\Core\Students\AccountModule();

$before =
    $account->dashboardData(44);

ob_start();

$account->renderDownloads();

$html =
    ob_get_clean();

$after =
    $account->dashboardData(44);

echo "BUTTON="
    . (
        strpos(
            $html,
            "Baixar apostila"
        ) !== false
            ? "yes"
            : "no"
    )
    . PHP_EOL;

echo "BEFORE="
    . $before["downloads"]
    . PHP_EOL;

echo "AFTER="
    . $after["downloads"]
    . PHP_EOL;

echo "EXECUTED="
    . (
        $after["downloads"]
        > $before["downloads"]
            ? "yes"
            : "no"
    )
    . PHP_EOL;
' 2>/dev/null \
    || true
)"

echo "$DOWNLOAD"

grep -q '^BUTTON=yes$' \
    <<<"$DOWNLOAD" \
    && pass "link protegido renderizado" \
    || fail "link protegido ausente"

grep -q '^EXECUTED=no$' \
    <<<"$DOWNLOAD" \
    && pass "nenhum download executado" \
    || fail "render registrou download"


section "COPY VISIVEL"

OLD_COPY="$(
    grep -RniE \
        'Pagina nao|endereco nao|Paginacao|Proxima|catalogo completo|preparacao|Politica de Privacidade|Informacoes gerais|Ir para o conteudo|Conteudo encontrado' \
        wp-content/themes/facil-digital/header.php \
        wp-content/themes/facil-digital/404.php \
        wp-content/themes/facil-digital/search.php \
        wp-content/themes/facil-digital/templates \
        wp-content/themes/facil-digital/template-parts \
        2>/dev/null \
        | grep -v 'Template Name:' \
        || true
)"

if [[ -z "$OLD_COPY" ]]; then
    pass "copy antiga visivel nao encontrada"
else
    echo "$OLD_COPY"
    fail "copy antiga visivel encontrada"
fi


section "PHP LINT"

PHP_COUNT="$(
    docker compose exec -T wordpress \
        sh -lc '
find \
  /var/www/html/wp-content/themes/facil-digital \
  /var/www/html/wp-content/plugins/facil-digital-core/src \
  -type f \
  -name "*.php" \
  | wc -l
' </dev/null \
        | tr -d '\r'
)"

PHP_OUTPUT="$(
    docker compose exec -T wordpress \
        sh -lc '
find \
  /var/www/html/wp-content/themes/facil-digital \
  /var/www/html/wp-content/plugins/facil-digital-core/src \
  -type f \
  -name "*.php" \
  -print0 |
xargs -0 -n1 php -l
' </dev/null 2>&1
)"

if grep -qE \
    'Parse error|Errors parsing|Fatal error' \
    <<<"$PHP_OUTPUT"
then
    echo "$PHP_OUTPUT"
    fail "PHP lint"
else
    echo "PHP_FILES=$PHP_COUNT"
    pass "PHP lint completo"
fi


section "JAVASCRIPT"

JS_FAIL=0
JS_COUNT=0

while IFS= read -r file
do
    JS_COUNT=$((JS_COUNT + 1))

    if ! node --check "$file"
    then
        JS_FAIL=$((JS_FAIL + 1))
    fi
done < <(
    find \
      wp-content/themes/facil-digital/assets/js \
      -type f \
      -name '*.js' \
      -print \
      | sort
)

echo "JS_FILES=$JS_COUNT"

if (( JS_FAIL == 0 )); then
    pass "JavaScript syntax"
else
    fail "$JS_FAIL JavaScript(s) invalidos"
fi


section "RESUMO"

echo "PASS=$PASS_COUNT"
echo "WARN=$WARN_COUNT"
echo "FAIL=$FAIL_COUNT"

if (( FAIL_COUNT == 0 )); then
    echo "P2GH_STATUS=PASS"
else
    echo "P2GH_STATUS=FAIL"
fi

echo
echo "IMPORTANTE:"
echo "Este gate NAO executa pagamento Mercado Pago."
echo "Este gate NAO acessa fd_download."
echo "Este gate NAO fecha W20-W23."
