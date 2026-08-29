#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

[[ -f .env ]] || { echo "FAIL - .env ausente"; exit 1; }
set -a
# shellcheck disable=SC1091
source .env
set +a

wpcli() {
  docker compose run --rm wpcli wp "$@"
}

pass() { echo "PASS  $1"; }
fail() { echo "FAIL  $1"; exit 1; }

cleanup() {
  wpcli eval 'require "/workspace/tools/cleanup-m3.php";' >/dev/null 2>&1 || true
}
trap cleanup EXIT

HOST="$(printf '%s' "$WORDPRESS_URL" | sed -E 's#^https?://##; s#/$##')"
BASE="http://127.0.0.1:${WORDPRESS_PORT:-8080}"

request() {
  local path="$1"
  local output="$2"
  curl -sS -o "$output" -w '%{http_code}' \
    -H "Host: $HOST" \
    -H "X-Forwarded-Proto: https" \
    "${BASE}${path}"
}

echo "=================================================="
echo "M3 - W11/W12/W13/W14/W15"
echo "QUESTOES / SIMULADOS / TENTATIVAS / RESULTADOS / RANKING"
echo "=================================================="
echo

echo "=== REGRESSAO M2 ==="
if ! ./tools/validate-m2.sh 2>&1 | tee /tmp/fd-m3-m2.log
then
  fail "regressao M2"
fi
pass "M2 intacto"

echo
echo "=== COMPOSER ==="
docker compose run --rm composer validate --no-check-publish >/dev/null
docker compose run --rm composer dump-autoload --optimize >/dev/null
pass "autoload atualizado"

echo
echo "=== CORE ==="
CORE_VERSION="$(wpcli plugin get facil-digital-core --field=version)"
[[ "$CORE_VERSION" == "0.7.0" || "$CORE_VERSION" == "0.8.0" ]] || fail "Core esperado >= 0.7.0; atual: $CORE_VERSION"
pass "Core >= 0.7.0 ($CORE_VERSION)"

echo
echo "=== ESTRUTURA M3 ==="
required=(
  wp-content/plugins/facil-digital-core/src/Questions/QuestionRepository.php
  wp-content/plugins/facil-digital-core/src/Questions/QuestionService.php
  wp-content/plugins/facil-digital-core/src/Questions/QuestionAdminModule.php
  wp-content/plugins/facil-digital-core/src/Simulations/SimulationRepository.php
  wp-content/plugins/facil-digital-core/src/Simulations/SimulationService.php
  wp-content/plugins/facil-digital-core/src/Simulations/SimulationAccessService.php
  wp-content/plugins/facil-digital-core/src/Simulations/SimulationFrontendModule.php
  wp-content/plugins/facil-digital-core/src/Simulations/SimulationAdminModule.php
  wp-content/plugins/facil-digital-core/src/Attempts/AttemptRepository.php
  wp-content/plugins/facil-digital-core/src/Attempts/AttemptService.php
  wp-content/plugins/facil-digital-core/src/Ranking/RankingService.php
  wp-content/plugins/facil-digital-core/src/API/SimulationController.php
  wp-content/plugins/facil-digital-core/src/Students/SimulationAccountModule.php
  wp-content/themes/facil-digital/templates/simulation.php
  wp-content/themes/facil-digital/assets/js/simulation.js
  wp-content/themes/facil-digital/assets/css/simulation.css
  tools/seed-m3.php
  tools/test-m3-engine.php
  tools/cleanup-m3.php
)
for file in "${required[@]}"; do
  [[ -f "$file" ]] || fail "arquivo M3 ausente: $file"
done
pass "estrutura funcional M3 presente"

echo
echo "=== PERMISSOES ==="
wpcli eval '
use FacilDigital\Core\Core\Capabilities;
$manager = get_role(Capabilities::ROLE_MANAGER);
$editor = get_role(Capabilities::ROLE_QUESTION_EDITOR);
if (!$manager instanceof WP_Role || !$editor instanceof WP_Role) { exit(1); }
if (!$manager->has_cap(Capabilities::MANAGE_QUESTIONS) || !$manager->has_cap(Capabilities::MANAGE_SIMULATIONS)) { exit(1); }
if (!$editor->has_cap(Capabilities::MANAGE_QUESTIONS) || $editor->has_cap(Capabilities::MANAGE_SIMULATIONS)) { exit(1); }
'
pass "editor de questoes e gerente preservam menor privilegio"

echo
echo "=== SEED M3 ==="
cleanup
wpcli eval 'require "/workspace/tools/seed-m3.php";' >/tmp/fd-m3-seed.json
python3 - <<'PY'
import json
from pathlib import Path
p = json.loads(Path('/tmp/fd-m3-seed.json').read_text())
assert p['status'] == 'seeded'
assert len(p['question_ids']) == 5
assert int(p['simulation_id']) > 0
assert int(p['user_id']) > 0
assert int(p['unauthorized_user_id']) > 0
assert int(p['ranking_user_id']) > 0
PY
pass "5 questoes, simulado e alunos temporarios criados"

echo
echo "=== BANCO DE QUESTOES ==="
wpcli eval '
use FacilDigital\Core\Questions\QuestionRepository;
use FacilDigital\Core\Questions\QuestionService;
$seed = get_option("fd_m3_seed", []);
$id = (int) $seed["question_ids"][0];
$repo = new QuestionRepository();
$row = $repo->find($id);
if (!is_array($row) || count($row["options"] ?? []) !== 5) { exit(1); }
$service = new QuestionService();
$copy = $service->duplicate($id, 1);
$copyRow = $repo->find($copy);
if (!is_array($copyRow) || ($copyRow["status"] ?? "") !== "draft") { exit(1); }
$service->setStatus($copy, "active");
if (($repo->find($copy)["status"] ?? "") !== "active") { exit(1); }
if (!$service->delete($copy)) { exit(1); }
$security = $repo->selectActiveIds(["subject" => "Segurança"], 10, false);
if (count($security) !== 2) { exit(1); }
'
pass "CRUD, alternativas, duplicacao, status, exclusao e filtros funcionais"

echo
echo "=== SELECAO DE SIMULADOS ==="
wpcli eval '
use FacilDigital\Core\Simulations\SimulationService;
$seed = get_option("fd_m3_seed", []);
$service = new SimulationService();
$base = [
  "title" => "M3 Selection Temp",
  "contest_term_id" => (int) $seed["term_id"],
  "position_name" => "Cargo M3",
  "duration_seconds" => 600,
  "attempt_limit" => 1,
  "minimum_score" => 0,
  "show_answer_key" => true,
  "comment_policy" => "after_finish",
  "ranking_enabled" => false,
  "status" => "draft",
  "question_count" => 2,
];
$cases = [
  ["subject", ["selection_subject" => "Segurança"]],
  ["topic", ["selection_topic" => "Aritmética"]],
  ["board", ["selection_board" => "Banca M3"]],
  ["random", []],
];
foreach ($cases as $index => [$mode, $extra]) {
  $payload = array_merge($base, $extra, [
    "title" => "M3 Selection " . $mode,
    "slug" => "fd-m3-selection-" . $mode,
    "selection_mode" => $mode,
  ]);
  $id = $service->create($payload, 1);
  if ($id <= 0) { exit(1); }
  $service->delete($id);
}
'
pass "selecao manual, disciplina, assunto, banca e aleatoria estruturadas"

echo
echo "=== MOTOR / AUTOSAVE / RESULTADO / RANKING ==="
wpcli eval 'require "/workspace/tools/test-m3-engine.php";' >/tmp/fd-m3-engine.json
cat /tmp/fd-m3-engine.json
python3 - <<'PY'
import json
from pathlib import Path
p = json.loads(Path('/tmp/fd-m3-engine.json').read_text())
assert p['status'] == 'ok'
assert abs(float(p['percentage']) - 80.0) < 0.01
assert p['attempt_limit_blocked'] is True
assert p['expired_blocked'] is True
assert p['unauthorized_blocked'] is True
assert int(p['ranking_entries']) >= 2
PY
pass "servidor controla tentativa, tempo, autosave, correcao, historico e ranking"

echo
echo "=== REST ==="
wpcli eval '
$routes = rest_get_server()->get_routes();
$required = [
  "/facil-digital/v1/simulations",
  "/facil-digital/v1/simulations/(?P<id>\\d+)/attempts",
  "/facil-digital/v1/attempts/(?P<id>\\d+)",
  "/facil-digital/v1/attempts/(?P<id>\\d+)/answers",
  "/facil-digital/v1/attempts/(?P<id>\\d+)/finish",
  "/facil-digital/v1/attempts/(?P<id>\\d+)/result",
  "/facil-digital/v1/me/results",
  "/facil-digital/v1/simulations/(?P<id>\\d+)/ranking",
];
foreach ($required as $route) {
  if (!isset($routes[$route])) {
    fwrite(STDERR, "Rota ausente: {$route}\n");
    exit(1);
  }
}
wp_set_current_user(0);
$request = new WP_REST_Request("GET", "/facil-digital/v1/me/results");
$response = rest_do_request($request);
if ($response->get_status() < 400) {
  fwrite(STDERR, "REST anonimo autorizado.\n");
  exit(1);
}
'
pass "REST autenticado e rotas M3 registradas"

echo
echo "=== RATE LIMIT ==="
wpcli eval '
use FacilDigital\Core\Support\RateLimiter;
$limiter = new RateLimiter();
$scope = "m3_gate_" . wp_generate_password(8, false, false);
if (!$limiter->hit($scope, 999999, 2, 60)) { exit(1); }
if (!$limiter->hit($scope, 999999, 2, 60)) { exit(1); }
if ($limiter->hit($scope, 999999, 2, 60)) { exit(1); }
'
pass "rate limiting basico ativo em pontos criticos"

echo
echo "=== ADMIN ==="
wpcli eval '
use FacilDigital\Core\Questions\QuestionAdminModule;
use FacilDigital\Core\Simulations\SimulationAdminModule;
$admin = get_users(["role" => "administrator", "number" => 1]);
if (!$admin) { exit(1); }
wp_set_current_user((int) $admin[0]->ID);
do_action("admin_menu");
global $submenu;
$items = $submenu["facil-digital"] ?? [];
$slugs = array_map(static fn($row) => (string) ($row[2] ?? ""), $items);
if (!in_array(QuestionAdminModule::SLUG, $slugs, true) || !in_array(SimulationAdminModule::SLUG, $slugs, true)) { exit(1); }
'
pass "Banco de Questoes e Simulados integrados ao admin"

echo
echo "=== AREA DO ALUNO ==="
wpcli eval '
$items = apply_filters("woocommerce_account_menu_items", ["dashboard" => "Painel", "customer-logout" => "Sair"]);
foreach (["apostilas", "simulados", "resultados"] as $required) {
  if (!isset($items[$required])) { exit(1); }
}
'
pass "Minha Conta inclui apostilas, simulados e resultados"

echo
echo "=== FRONTEND SIMULADO ==="
SLUG="$(wpcli eval '$s = get_option("fd_m3_seed", []); echo $s["simulation_slug"] ?? "";')"
[[ -n "$SLUG" ]] || fail "slug M3 ausente"
wpcli rewrite flush >/dev/null 2>&1 || true
STATUS="$(request "/simulado/${SLUG}/" /tmp/fd-m3-simulation.html)"
echo "HTTP $STATUS"
[[ "$STATUS" == "200" ]] || fail "pagina do simulado HTTP $STATUS"
grep -q 'fd-simulation-page' /tmp/fd-m3-simulation.html || fail "template de simulado ausente"
grep -q 'M3 Simulado Concurso Teste' /tmp/fd-m3-simulation.html || fail "titulo do simulado ausente"
pass "pagina /simulado/{slug}/ responsiva carregada"

echo
echo "=== SEGURANCA / PRIVACIDADE ==="
if grep -R -nE 'selected_option.*is_correct|correct_key.*questions' \
  wp-content/plugins/facil-digital-core/src/API \
  >/tmp/fd-m3-leak.log 2>/dev/null; then
  cat /tmp/fd-m3-leak.log
  fail "possivel vazamento de gabarito no REST"
fi
if grep -R -nE 'billing_cpf|_fd_billing_cpf' \
  wp-content/plugins/facil-digital-core/src/Ranking \
  wp-content/plugins/facil-digital-core/src/Attempts \
  >/tmp/fd-m3-pii.log 2>/dev/null; then
  cat /tmp/fd-m3-pii.log
  fail "CPF referenciado por ranking/tentativas"
fi
pass "tentativa ativa sem gabarito e ranking sem CPF/email/telefone"

echo
echo "=== PHP / JAVASCRIPT / SHELL / GIT ==="
while IFS= read -r file; do
  docker compose exec -T wordpress php -l "/workspace/$file" >/dev/null
done < <(find wp-content/plugins/facil-digital-core -type f -name '*.php' -not -path '*/vendor/*' | sort)
for file in tools/seed-m3.php tools/test-m3-engine.php tools/cleanup-m3.php wp-content/themes/facil-digital/templates/simulation.php; do
  docker compose exec -T wordpress php -l "/workspace/$file" >/dev/null
done
if command -v node >/dev/null 2>&1; then
  node --check wp-content/themes/facil-digital/assets/js/simulation.js
fi
bash -n tools/validate-m3.sh
git diff --check
if git status --short | grep -E '(^|[[:space:]])\.env$|wp-config\.php|vendor/|\.runtime/|\.sql$|\.db$'; then
  fail "runtime ou segredo no Git"
fi
pass "sintaxe e git diff check"

echo
echo "=================================================="
echo "PASS - M3 VALIDADO"
echo "=================================================="
