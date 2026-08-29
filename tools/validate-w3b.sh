#!/usr/bin/env bash
set -euo pipefail

[[ -f .env ]] || { echo "FAIL - .env nao existe"; exit 1; }

set -a
# shellcheck disable=SC1091
source .env
set +a

wpcli() { docker compose run --rm wpcli wp "$@"; }
pass() { echo "PASS  $1"; }
fail() { echo "FAIL  $1"; exit 1; }

echo "=================================================="
echo "W3B - ROLES / CAPABILITIES / MODULOS"
echo "=================================================="
echo

echo "=== REGRESSAO W3A ==="
./tools/validate-w3a.sh >/tmp/fd-w3b-w3a.log
tail -8 /tmp/fd-w3b-w3a.log
pass "W3A intacta"

echo
echo "=== COMPOSER ==="
docker compose run --rm composer validate --no-check-publish >/dev/null
docker compose run --rm composer dump-autoload --optimize >/dev/null
pass "autoload atualizado"

echo
echo "=== CORE ==="
wpcli plugin is-active facil-digital-core >/dev/null || fail "Core inativo"
CORE_VERSION="$(wpcli plugin get facil-digital-core --field=version)"
[[ "$CORE_VERSION" == "0.3.0" ]] || fail "Core esperado 0.3.0; atual: $CORE_VERSION"
pass "Core 0.3.0"

echo
echo "=== CAPABILITIES ==="
wpcli eval '\FacilDigital\Core\Core\Capabilities::install();'
wpcli eval '\FacilDigital\Core\Core\Capabilities::install();'

CAP_VERSION="$(wpcli eval 'echo \FacilDigital\Core\Core\Capabilities::installedVersion();')"
[[ "$CAP_VERSION" == "1.0.0" ]] || fail "capabilities esperada 1.0.0; atual: $CAP_VERSION"

READY="$(wpcli eval 'echo \FacilDigital\Core\Core\Capabilities::isReady() ? "yes" : "no";')"
[[ "$READY" == "yes" ]] || fail "Capabilities::isReady() retornou false"
pass "capabilities 1.0.0 e instalacao idempotente"

echo
echo "=== ROLES ==="
wpcli eval '
use FacilDigital\Core\Core\Capabilities;

$administrator = get_role("administrator");
$manager = get_role(Capabilities::ROLE_MANAGER);
$editor = get_role(Capabilities::ROLE_QUESTION_EDITOR);

if (!$administrator || !$manager || !$editor) {
    fwrite(STDERR, "Role obrigatoria ausente." . PHP_EOL);
    exit(1);
}

foreach (Capabilities::all() as $capability) {
    if (!$administrator->has_cap($capability)) {
        fwrite(STDERR, "Administrador sem {$capability}." . PHP_EOL);
        exit(1);
    }
}

foreach (Capabilities::managerCapabilities() as $capability) {
    if (!$manager->has_cap($capability)) {
        fwrite(STDERR, "Gerente sem {$capability}." . PHP_EOL);
        exit(1);
    }
}

if ($manager->has_cap(Capabilities::MANAGE_SETTINGS)) {
    fwrite(STDERR, "Gerente recebeu configuracoes indevidamente." . PHP_EOL);
    exit(1);
}

foreach (Capabilities::all() as $capability) {
    $expected = in_array(
        $capability,
        Capabilities::questionEditorCapabilities(),
        true
    );

    if ($editor->has_cap($capability) !== $expected) {
        fwrite(STDERR, "Editor com capability incorreta: {$capability}." . PHP_EOL);
        exit(1);
    }
}

foreach (["manage_options", "manage_woocommerce", "install_plugins", "edit_users"] as $dangerous) {
    if ($manager->has_cap($dangerous) || $editor->has_cap($dangerous)) {
        fwrite(STDERR, "Role customizada recebeu capability ampla: {$dangerous}." . PHP_EOL);
        exit(1);
    }
}

if (!$manager->has_cap("read") || !$editor->has_cap("read")) {
    fwrite(STDERR, "Role customizada sem read." . PHP_EOL);
    exit(1);
}
'
pass "Administrador, Gerente e Editor de Questoes com menor privilegio"

echo
echo "=== USUARIO COMUM ==="
wpcli eval '
use FacilDigital\Core\Core\Capabilities;

$subscriber = get_role("subscriber");
if ($subscriber && $subscriber->has_cap(Capabilities::ACCESS_ADMIN)) {
    fwrite(STDERR, "Subscriber recebeu acesso ao Core." . PHP_EOL);
    exit(1);
}
'
pass "usuario comum sem acesso administrativo ao Core"

echo
echo "=== MODULOS ==="
wpcli eval '
$required = [
    "FacilDigital\\Core\\Contracts\\ModuleInterface",
    "FacilDigital\\Core\\Admin\\Menu",
    "FacilDigital\\Core\\API\\HealthController",
];

foreach ($required as $class) {
    $exists = str_ends_with($class, "ModuleInterface")
        ? interface_exists($class)
        : class_exists($class);

    if (!$exists) {
        fwrite(STDERR, "Modulo/autoload ausente: {$class}." . PHP_EOL);
        exit(1);
    }
}
'
pass "contrato modular, Admin e API carregados"

echo
echo "=== MENU ADMIN ==="
wpcli eval '
use FacilDigital\Core\Admin\Menu;
use FacilDigital\Core\Core\Capabilities;

$fdMenu = new Menu();
$fdMenu->registerMenu();

global $menu, $submenu;
$rootFound = false;

foreach ((array) $menu as $item) {
    if (($item[2] ?? null) !== Menu::PARENT_SLUG) {
        continue;
    }

    if (($item[1] ?? null) !== Capabilities::ACCESS_ADMIN) {
        fwrite(STDERR, "Capability incorreta no menu raiz." . PHP_EOL);
        exit(1);
    }

    $rootFound = true;
    break;
}

if (!$rootFound) {
    fwrite(STDERR, "Menu raiz Facil Digital+ ausente." . PHP_EOL);
    exit(1);
}

$settingsFound = false;
foreach (($submenu[Menu::PARENT_SLUG] ?? []) as $item) {
    if (($item[2] ?? null) !== "facil-digital-settings") {
        continue;
    }

    if (($item[1] ?? null) !== Capabilities::MANAGE_SETTINGS) {
        fwrite(STDERR, "Settings sem capability administrativa." . PHP_EOL);
        exit(1);
    }

    $settingsFound = true;
}

if (!$settingsFound) {
    fwrite(STDERR, "Submenu de configuracoes ausente." . PHP_EOL);
    exit(1);
}
'
pass "menu modular protegido por capabilities proprias"

echo
echo "=== CORE REST ==="
REST_STATUS="$(curl -sS -o /tmp/fd-w3b-health.json -w '%{http_code}' "${WORDPRESS_URL%/}/wp-json/facil-digital/v1/health")"
[[ "$REST_STATUS" == "200" ]] || fail "health endpoint HTTP $REST_STATUS"
grep -q '"status":"ok"' /tmp/fd-w3b-health.json || fail "health endpoint sem status ok"
grep -q '"service":"facil-digital-core"' /tmp/fd-w3b-health.json || fail "health endpoint sem service"
pass "REST health preservado no modulo API"

echo
echo "=== PHP / SHELL / GIT ==="
while IFS= read -r file; do
  docker compose exec -T wordpress php -l "/workspace/$file" >/dev/null
done < <(find wp-content/plugins/facil-digital-core -type f -name '*.php' -not -path '*/vendor/*' | sort)

bash -n tools/validate-w3a.sh
bash -n tools/validate-w3b.sh
git diff --check
pass "sintaxe e git diff check"

echo
echo "=================================================="
echo "PASS - W3B VALIDADA"
echo "=================================================="
