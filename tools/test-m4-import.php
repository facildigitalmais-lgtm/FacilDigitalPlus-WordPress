<?php

declare(strict_types=1);

use FacilDigital\Core\Import\QuestionCsvService;
use FacilDigital\Core\Questions\QuestionRepository;
use FacilDigital\Core\Questions\QuestionService;

$service = new QuestionCsvService();
$repo = new QuestionRepository();
$tmp = tempnam(sys_get_temp_dir(), 'fd-m4-csv-');
$export = tempnam(sys_get_temp_dir(), 'fd-m4-export-');
if ($tmp === false || $export === false) {
    throw new RuntimeException('m4_temp_failed');
}

$csv = implode("\n", [
    'tipo;enunciado;alternativa_a;alternativa_b;alternativa_c;alternativa_d;alternativa_e;correta;comentario;banca;cargo;disciplina;assunto;dificuldade;ano;status',
    'multiple_choice;Questão importada M4 número 1;Um;Dois;Três;Quatro;Cinco;C;Comentário M4;Banca M4;Cargo M4;Português;Interpretação;medium;2026;active',
    'true_false;Questão importada M4 número 2;;;;;;C;Comentário C/E;Banca M4;Cargo M4;Segurança;Normas;easy;2026;active',
    'multiple_choice;X;A;B;;;;Z;;;Cargo M4;Inválida;;;2026;active',
]) . "\n";
file_put_contents($tmp, $csv);

$dry = $service->import($tmp, 1, true);
if ((int) $dry['rows'] !== 3 || (int) $dry['valid'] !== 2 || (int) $dry['invalid'] !== 1 || (int) $dry['created'] !== 0) {
    throw new RuntimeException('m4_dry_run_failed');
}

$report = $service->import($tmp, 1, false);
$created = array_map('intval', (array) $report['created_ids']);
if (count($created) !== 2 || (int) $report['invalid'] !== 1) {
    throw new RuntimeException('m4_import_failed');
}

foreach ($created as $id) {
    $row = $repo->find($id);
    if (!is_array($row) || !str_contains((string) $row['statement'], 'M4')) {
        throw new RuntimeException('m4_import_persistence_failed');
    }
}

$exported = $service->export($export);
$contents = file_get_contents($export);
if ($exported < count($created) || !is_string($contents) || !str_contains($contents, 'enunciado') || !str_contains($contents, 'Questão importada M4')) {
    throw new RuntimeException('m4_export_failed');
}

foreach ($created as $id) {
    $repo->delete($id);
}
@unlink($tmp);
@unlink($export);

echo wp_json_encode([
    'status' => 'ok',
    'dry_valid' => (int) $dry['valid'],
    'dry_invalid' => (int) $dry['invalid'],
    'created' => count($created),
    'exported' => $exported,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
