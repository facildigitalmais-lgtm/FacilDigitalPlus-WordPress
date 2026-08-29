<?php

declare(strict_types=1);

namespace FacilDigital\Core\Import;

use FacilDigital\Core\Contests\ContestModule;
use FacilDigital\Core\Questions\QuestionRepository;
use FacilDigital\Core\Questions\QuestionService;
use InvalidArgumentException;
use RuntimeException;

final class QuestionCsvService
{
    public const MAX_BYTES = 10485760;
    public const MAX_ROWS = 10000;

    public function __construct(
        private readonly QuestionService $service = new QuestionService(),
        private readonly QuestionRepository $repository = new QuestionRepository()
    ) {
    }

    /** @return array<string,mixed> */
    public function import(string $path, int $userId, bool $dryRun = false): array
    {
        $this->assertFile($path);
        [$headers, $rows] = $this->read($path);
        $created = [];
        $errors = [];
        $valid = 0;

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            try {
                $payload = $this->payload($headers, $row);
                $this->validatePayload($payload);
                $valid++;
                if (!$dryRun) {
                    $created[] = $this->service->create($payload, $userId);
                }
            } catch (\Throwable $exception) {
                $errors[] = [
                    'line' => $line,
                    'code' => sanitize_key($exception->getMessage()),
                ];
            }
        }

        return [
            'status' => $errors === [] ? 'ok' : ($valid > 0 ? 'partial' : 'invalid'),
            'dry_run' => $dryRun,
            'rows' => count($rows),
            'valid' => $valid,
            'invalid' => count($errors),
            'created' => count($created),
            'created_ids' => $created,
            'errors' => array_slice($errors, 0, 100),
        ];
    }

    /** @param resource $handle */
    public function exportToStream($handle): int
    {
        if (!is_resource($handle)) {
            throw new InvalidArgumentException('csv_output_invalid');
        }

        $headers = [
            'id', 'tipo', 'enunciado', 'alternativa_a', 'alternativa_b',
            'alternativa_c', 'alternativa_d', 'alternativa_e', 'correta',
            'comentario', 'banca', 'concurso', 'cargo', 'disciplina', 'assunto',
            'dificuldade', 'ano', 'status',
        ];
        fputcsv($handle, $headers, ';');

        $offset = 0;
        $count = 0;
        do {
            $rows = $this->repository->list([], 500, $offset);
            foreach ($rows as $row) {
                $options = [];
                $correct = '';
                foreach ((array) ($row['options'] ?? []) as $option) {
                    if (!is_array($option)) {
                        continue;
                    }
                    $key = strtoupper((string) ($option['option_key'] ?? ''));
                    $options[$key] = (string) ($option['option_text'] ?? '');
                    if ((int) ($option['is_correct'] ?? 0) === 1) {
                        $correct = $key;
                    }
                }
                $contest = '';
                $termId = (int) ($row['contest_term_id'] ?? 0);
                if ($termId > 0) {
                    $term = get_term($termId, ContestModule::TAXONOMY);
                    if ($term instanceof \WP_Term) {
                        $contest = $term->slug;
                    }
                }
                fputcsv($handle, [
                    (int) $row['id'],
                    (string) $row['question_type'],
                    (string) $row['statement'],
                    $options['A'] ?? '', $options['B'] ?? '', $options['C'] ?? '',
                    $options['D'] ?? '', $options['E'] ?? '', $correct,
                    (string) ($row['explanation'] ?? ''),
                    (string) ($row['board'] ?? ''),
                    $contest,
                    (string) ($row['position_name'] ?? ''),
                    (string) ($row['subject'] ?? ''),
                    (string) ($row['topic'] ?? ''),
                    (string) ($row['difficulty'] ?? ''),
                    (string) ($row['exam_year'] ?? ''),
                    (string) ($row['status'] ?? ''),
                ], ';');
                $count++;
            }
            $offset += count($rows);
        } while (count($rows) === 500 && $offset < self::MAX_ROWS * 10);

        return $count;
    }

    public function export(string $path): int
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('csv_output_open_failed');
        }
        try {
            return $this->exportToStream($handle);
        } finally {
            fclose($handle);
        }
    }

    private function assertFile(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException('csv_file_unreadable');
        }
        $size = filesize($path);
        if ($size === false || $size <= 0 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('csv_file_size_invalid');
        }
    }

    /** @return array{0:list<string>,1:list<list<string>>} */
    private function read(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('csv_file_open_failed');
        }
        try {
            $first = fgets($handle);
            if (!is_string($first)) {
                throw new InvalidArgumentException('csv_header_missing');
            }
            $delimiter = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
            rewind($handle);
            $rawHeaders = fgetcsv($handle, 0, $delimiter);
            if (!is_array($rawHeaders)) {
                throw new InvalidArgumentException('csv_header_missing');
            }
            $headers = array_map([$this, 'normalizeHeader'], $rawHeaders);
            if (!in_array('enunciado', $headers, true) && !in_array('statement', $headers, true)) {
                throw new InvalidArgumentException('csv_header_statement_missing');
            }
            $rows = [];
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                if (count($rows) >= self::MAX_ROWS) {
                    throw new InvalidArgumentException('csv_row_limit_exceeded');
                }
                if ($row === [null] || $row === []) {
                    continue;
                }
                $rows[] = array_map(static fn($value): string => is_scalar($value) ? trim((string) $value) : '', $row);
            }
            return [$headers, $rows];
        } finally {
            fclose($handle);
        }
    }

    private function normalizeHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', trim($header)) ?? trim($header);
        $map = [
            'questao' => 'enunciado', 'questão' => 'enunciado',
            'comentário' => 'comentario', 'explicacao' => 'comentario', 'explicação' => 'comentario',
            'disciplina' => 'disciplina', 'assunto' => 'assunto', 'dificuldade' => 'dificuldade',
        ];
        $lower = function_exists('mb_strtolower') ? mb_strtolower($header, 'UTF-8') : strtolower($header);
        return $map[$lower] ?? sanitize_key($lower);
    }

    /** @param list<string> $headers @param list<string> $row @return array<string,mixed> */
    private function payload(array $headers, array $row): array
    {
        $data = [];
        foreach ($headers as $index => $header) {
            if ($header !== '') {
                $data[$header] = (string) ($row[$index] ?? '');
            }
        }

        $typeRaw = strtolower(trim($data['tipo'] ?? $data['question_type'] ?? 'multiple_choice'));
        $type = in_array($typeRaw, ['true_false', 'certo_errado', 'certo/errado', 'ce'], true)
            ? 'true_false'
            : 'multiple_choice';
        $correct = strtoupper(trim((string) ($data['correta'] ?? $data['correct'] ?? '')));
        if ($type === 'true_false') {
            if (in_array(strtolower($correct), ['certo', 'true'], true)) {
                $correct = 'C';
            } elseif (in_array(strtolower($correct), ['errado', 'false'], true)) {
                $correct = 'E';
            }
        }

        $options = [];
        foreach (['A','B','C','D','E'] as $index => $key) {
            $value = (string) ($data['alternativa_' . strtolower($key)] ?? $data['option_' . strtolower($key)] ?? '');
            if ($type === 'true_false' && !in_array($key, ['C','E'], true)) {
                continue;
            }
            if ($type === 'true_false') {
                $value = $key === 'C' ? 'Certo' : 'Errado';
            }
            if (trim($value) === '') {
                continue;
            }
            $options[] = [
                'option_key' => $key,
                'option_text' => $value,
                'is_correct' => $key === $correct,
                'sort_order' => $index,
            ];
        }

        $contestTermId = 0;
        $contest = trim((string) ($data['concurso'] ?? $data['contest'] ?? ''));
        if ($contest !== '') {
            if (ctype_digit($contest)) {
                $contestTermId = (int) $contest;
            } else {
                $term = get_term_by('slug', sanitize_title($contest), ContestModule::TAXONOMY);
                if (!$term instanceof \WP_Term) {
                    $term = get_term_by('name', $contest, ContestModule::TAXONOMY);
                }
                if ($term instanceof \WP_Term) {
                    $contestTermId = (int) $term->term_id;
                } else {
                    throw new InvalidArgumentException('csv_contest_not_found');
                }
            }
        }

        return [
            'question_type' => $type,
            'contest_term_id' => $contestTermId,
            'statement' => (string) ($data['enunciado'] ?? $data['statement'] ?? ''),
            'explanation' => (string) ($data['comentario'] ?? $data['explanation'] ?? ''),
            'board' => (string) ($data['banca'] ?? $data['board'] ?? ''),
            'position_name' => (string) ($data['cargo'] ?? $data['position_name'] ?? ''),
            'subject' => (string) ($data['disciplina'] ?? $data['subject'] ?? ''),
            'topic' => (string) ($data['assunto'] ?? $data['topic'] ?? ''),
            'difficulty' => (string) ($data['dificuldade'] ?? $data['difficulty'] ?? 'medium'),
            'exam_year' => absint($data['ano'] ?? $data['exam_year'] ?? 0),
            'status' => (string) ($data['status'] ?? 'active'),
            'options' => $options,
        ];
    }

    /** @param array<string,mixed> $payload */
    private function validatePayload(array $payload): void
    {
        if (strlen(trim((string) ($payload['statement'] ?? ''))) < 3) {
            throw new InvalidArgumentException('question_statement_required');
        }
        $year = (int) ($payload['exam_year'] ?? 0);
        if ($year !== 0 && ($year < 1900 || $year > 2100)) {
            throw new InvalidArgumentException('question_year_invalid');
        }
        $options = is_array($payload['options'] ?? null) ? $payload['options'] : [];
        if (count($options) < 2) {
            throw new InvalidArgumentException('question_options_insufficient');
        }
        $correct = 0;
        foreach ($options as $option) {
            if (is_array($option) && !empty($option['is_correct'])) {
                $correct++;
            }
        }
        if ($correct !== 1) {
            throw new InvalidArgumentException('question_correct_option_required');
        }
    }
}
