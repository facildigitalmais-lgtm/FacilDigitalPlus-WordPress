#!/usr/bin/env bash

set -euo pipefail

ROOT="$(
  cd "$(dirname "${BASH_SOURCE[0]}")/.." &&
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
  docker compose run --rm wpcli wp "$@"
}

pass() {
  echo "PASS  $1"
}

fail() {
  echo "FAIL  $1"
  exit 1
}

cleanup() {
  wpcli eval \
    'require "/workspace/tools/cleanup-m2.php";' \
    >/dev/null 2>&1 \
    || true
}
trap cleanup EXIT

echo "=================================================="
echo "M2 - W8/W9/W10"
echo "PDF PRIVADO / WATERMARK / SENHA / DOWNLOAD / ALUNO"
echo "=================================================="
echo

echo "=== REGRESSAO M1 ==="
if ! ./tools/validate-m1.sh 2>&1 | tee /tmp/fd-m2-m1.log
then
  fail "regressao M1"
fi
pass "M1 intacto"

echo
echo "=== COMPOSER PDF ==="
docker compose run --rm composer validate --no-check-publish >/dev/null
docker compose run --rm composer install --no-interaction --prefer-dist --optimize-autoloader >/dev/null

wpcli eval '
if (!class_exists("setasign\\Fpdi\\Tcpdf\\Fpdi")) {
    fwrite(STDERR, "FPDI TCPDF ausente." . PHP_EOL);
    exit(1);
}
if (!class_exists("TCPDF")) {
    fwrite(STDERR, "TCPDF ausente." . PHP_EOL);
    exit(1);
}
'
pass "FPDI 2.6+ e TCPDF disponiveis"

echo
echo "=== CORE ==="
CORE_VERSION="$(wpcli plugin get facil-digital-core --field=version)"
[[ "$CORE_VERSION" == "0.6.0" ]] || fail "Core esperado 0.6.0; atual: $CORE_VERSION"
pass "Core 0.6.0"

echo
echo "=== STORAGE PRIVADO ==="
wpcli eval '
use FacilDigital\Core\PDFs\PrivateStorage;
$storage = new PrivateStorage();
$storage->ensureReady();
$root = wp_normalize_path($storage->root());
$public = rtrim(wp_normalize_path(ABSPATH), "/");
if ($root === $public || str_starts_with($root, $public . "/")) {
    fwrite(STDERR, "Storage dentro da raiz publica." . PHP_EOL);
    exit(1);
}
if (!is_writable($root)) {
    fwrite(STDERR, "Storage nao gravavel." . PHP_EOL);
    exit(1);
}
$blocked = false;
try {
    $storage->path("../wp-config.php");
} catch (Throwable) {
    $blocked = true;
}
if (!$blocked) {
    fwrite(STDERR, "Path traversal aceito." . PHP_EOL);
    exit(1);
}
echo $root . PHP_EOL;
'
pass "storage fora da webroot, gravavel e sem path traversal"

echo
echo "=== ACTION SCHEDULER ==="
wpcli eval '
if (!function_exists("as_enqueue_async_action")) {
    fwrite(STDERR, "Action Scheduler indisponivel." . PHP_EOL);
    exit(1);
}
'
pass "fila assincrona WooCommerce disponivel"

echo
echo "=== SEED / GERACAO PDF ==="
cleanup
wpcli eval \
  'require "/workspace/tools/seed-m2.php";' \
  >/tmp/fd-m2-seed.json

python3 - <<'PY2'
import json
from pathlib import Path
payload = json.loads(Path('/tmp/fd-m2-seed.json').read_text())
assert payload['status'] == 'seeded'
for key in ['product_id','user_id','other_user_id','order_id','entitlement_id','pdf_id']:
    assert int(payload[key]) > 0
PY2
pass "pedido, entitlement, master e PDF personalizado criados"

echo
echo "=== INTEGRIDADE / SENHA / WATERMARK ==="
wpcli eval '
use FacilDigital\Core\Core\Database;
use FacilDigital\Core\PDFs\PdfFileRepository;
use FacilDigital\Core\PDFs\PdfGenerationService;
use FacilDigital\Core\PDFs\PrivateStorage;
use FacilDigital\Core\WooCommerce\CheckoutModule;
$seed = get_option("fd_m2_seed", []);
$pdfId = (int) ($seed["pdf_id"] ?? 0);
$entitlementId = (int) ($seed["entitlement_id"] ?? 0);
$repo = new PdfFileRepository();
$row = $repo->findById($pdfId);
if (!is_array($row) || ($row["status"] ?? "") !== "ready") {
    fwrite(STDERR, "PDF nao ficou ready." . PHP_EOL);
    exit(1);
}
if ((int) ($row["password_enabled"] ?? 0) !== 1 || (int) ($row["watermark_enabled"] ?? 0) !== 1) {
    fwrite(STDERR, "Flags de protecao ausentes." . PHP_EOL);
    exit(1);
}
$key = (string) ($row["storage_key"] ?? "");
if ($key === "" || str_contains($key, "52998224725")) {
    fwrite(STDERR, "Storage key invalida ou contem CPF." . PHP_EOL);
    exit(1);
}
$storage = new PrivateStorage();
$path = $storage->path($key);
$storage->assertPdf($path);
$size = filesize($path);
$sha = hash_file("sha256", $path);
if ($size === false || (int) ($row["file_size"] ?? 0) !== (int) $size || !hash_equals((string) ($row["sha256"] ?? ""), (string) $sha)) {
    fwrite(STDERR, "Integridade SHA/size divergente." . PHP_EOL);
    exit(1);
}
$binary = file_get_contents($path);
if (!is_string($binary) || !str_contains($binary, "/Encrypt")) {
    fwrite(STDERR, "PDF nao contem dicionario de criptografia." . PHP_EOL);
    exit(1);
}
$before = $row["id"];
$again = (new PdfGenerationService())->generateForEntitlement($entitlementId);
if ((int) ($again["id"] ?? 0) !== (int) $before) {
    fwrite(STDERR, "Geracao nao idempotente." . PHP_EOL);
    exit(1);
}
$order = wc_get_order((int) ($seed["order_id"] ?? 0));
$user = get_userdata((int) ($seed["user_id"] ?? 0));
if (!$order instanceof WC_Order || !$user instanceof WP_User) {
    exit(1);
}
$data = (new PdfGenerationService())->watermarkData(
    $user,
    $order,
    (string) ($row["tracking_code"] ?? ""),
    CheckoutModule::getOrderCpf($order)
);
if ($data["cpf_masked"] !== "***.***.***-25") {
    fwrite(STDERR, "CPF watermark nao mascarado." . PHP_EOL);
    exit(1);
}
foreach ($data as $value) {
    if (str_contains((string) $value, "52998224725")) {
        fwrite(STDERR, "Watermark contem CPF completo." . PHP_EOL);
        exit(1);
    }
}
'
pass "PDF criptografado, watermark mascarado, SHA e idempotencia"

echo
echo "=== DOWNLOAD AUTORIZADO ==="
wpcli eval \
  'require "/workspace/tools/test-m2-downloads.php";' \
  >/tmp/fd-m2-downloads.json
python3 - <<'PY2'
import json
from pathlib import Path
p = json.loads(Path('/tmp/fd-m2-downloads.json').read_text())
assert all(p.values())
PY2
pass "ownership, limite, revogacao e hashes operacionais"

echo
echo "=== AREA DO ALUNO ==="
wpcli eval '
use FacilDigital\Core\Students\AccountModule;
$seed = get_option("fd_m2_seed", []);
$userId = (int) ($seed["user_id"] ?? 0);
$module = new AccountModule();
$module->registerEndpoint();
if (!array_key_exists(AccountModule::ENDPOINT, $module->menuItems(["dashboard" => "Dashboard", "orders" => "Pedidos"]))) {
    fwrite(STDERR, "Menu Minhas apostilas ausente." . PHP_EOL);
    exit(1);
}
$data = $module->dashboardData($userId);
if ((int) $data["apostilas"] < 1 || (int) $data["pdfs_ready"] < 1) {
    fwrite(STDERR, "Dashboard sem dados da apostila." . PHP_EOL);
    exit(1);
}
'
pass "dashboard e endpoint Minhas apostilas funcionais"

echo
echo "=== REST PDF PROTEGIDO ==="
wpcli eval '
use FacilDigital\Core\API\PdfController;
$seed = get_option("fd_m2_seed", []);
$controller = new PdfController();
wp_set_current_user(0);
if ($controller->permissions()) {
    fwrite(STDERR, "REST PDF liberado anonimo." . PHP_EOL);
    exit(1);
}
wp_set_current_user((int) ($seed["user_id"] ?? 0));
if (!$controller->permissions()) {
    fwrite(STDERR, "REST PDF bloqueou dono." . PHP_EOL);
    exit(1);
}
$response = $controller->index(new WP_REST_Request("GET", "/facil-digital/v1/me/pdfs"));
$json = wp_json_encode($response->get_data());
if (!is_string($json) || str_contains($json, "storage_key") || str_contains($json, "52998224725") || str_contains($json, "/workspace/.runtime")) {
    fwrite(STDERR, "REST PDF vazou dado privado." . PHP_EOL);
    exit(1);
}
'
pass "REST PDF autenticado sem storage key ou CPF"

echo
echo "=== MASTER PRIVADO ==="
wpcli eval '
use FacilDigital\Core\PDFs\PrivateStorage;
use FacilDigital\Core\Products\ProductMetadata;
$seed = get_option("fd_m2_seed", []);
$productId = (int) ($seed["product_id"] ?? 0);
$key = ProductMetadata::get($productId, ProductMetadata::MASTER_PDF_KEY);
if ($key === "" || !str_starts_with($key, "masters/product-")) {
    fwrite(STDERR, "Master key invalida." . PHP_EOL);
    exit(1);
}
$path = (new PrivateStorage())->path($key);
$public = rtrim(wp_normalize_path(ABSPATH), "/");
if (str_starts_with(wp_normalize_path($path), $public . "/")) {
    fwrite(STDERR, "Master dentro da webroot." . PHP_EOL);
    exit(1);
}
'
pass "master PDF permanece fora da webroot"

echo
echo "=== LGPD / SEGURANCA ==="

wpcli eval '
use FacilDigital\Core\PDFs\PrivateStorage;

$root = (new PrivateStorage())->root();

if (!is_dir($root)) {
    fwrite(
        STDERR,
        "Storage privado inexistente."
        . PHP_EOL
    );
    exit(1);
}

$iterator =
    new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $root,
            FilesystemIterator::SKIP_DOTS
        )
    );

$patterns = [
    "52998224725",
    "529.982.247-25",
];

foreach ($iterator as $file) {
    $path = wp_normalize_path(
        $file->getPathname()
    );

    foreach ($patterns as $pattern) {
        if (str_contains($path, $pattern)) {
            fwrite(
                STDERR,
                "CPF encontrado em path privado."
                . PHP_EOL
            );
            exit(1);
        }
    }
}
'

pass "storage sem CPF em paths"

echo
echo "=== PHP / JAVASCRIPT / SHELL / GIT ==="
while IFS= read -r file
do
  docker compose exec -T wordpress php -l "/workspace/$file" >/dev/null
done < <(
  find wp-content/plugins/facil-digital-core wp-content/themes/facil-digital \
    -type f -name '*.php' -not -path '*/vendor/*' | sort
)

bash -n tools/setup-m2-runtime.sh
bash -n tools/validate-m2.sh

git diff --check
pass "sintaxe e git diff check"

echo
echo "=================================================="
echo "PASS - M2 VALIDADO"
echo "=================================================="
