<?php

declare(strict_types=1);

namespace FacilDigital\Core\Questions;

use FacilDigital\Core\Core\Database;
use RuntimeException;

final class QuestionRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        global $wpdb;

        $table = Database::table('questions');
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d LIMIT 1",
                $id
            ),
            ARRAY_A
        );

        if (!is_array($row)) {
            return null;
        }

        $row['options'] = $this->optionsForQuestion($id);

        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function optionsForQuestion(int $questionId): array
    {
        global $wpdb;

        $table = Database::table('question_options');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE question_id = %d
                 ORDER BY sort_order ASC, id ASC",
                $questionId
            ),
            ARRAY_A
        );

        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function list(
        array $filters = [],
        int $limit = 50,
        int $offset = 0
    ): array {
        global $wpdb;

        $table = Database::table('questions');
        [$where, $params] = $this->where($filters);
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);

        $sql = "SELECT * FROM {$table} {$where}
                ORDER BY id DESC
                LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $rows = $wpdb->get_results(
            $wpdb->prepare($sql, ...$params),
            ARRAY_A
        );

        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function count(array $filters = []): int
    {
        global $wpdb;

        $table = Database::table('questions');
        [$where, $params] = $this->where($filters);
        $sql = "SELECT COUNT(*) FROM {$table} {$where}";

        if ($params !== []) {
            $sql = $wpdb->prepare($sql, ...$params);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<int>
     */
    public function selectActiveIds(
        array $filters,
        int $limit,
        bool $random = false
    ): array {
        global $wpdb;

        $filters['status'] = 'active';
        $table = Database::table('questions');
        [$where, $params] = $this->where($filters);
        $limit = max(1, min(1000, $limit));
        $order = $random ? 'RAND()' : 'id ASC';
        $sql = "SELECT id FROM {$table} {$where} ORDER BY {$order} LIMIT %d";
        $params[] = $limit;

        $ids = $wpdb->get_col(
            $wpdb->prepare($sql, ...$params)
        );

        return array_values(
            array_map('intval', is_array($ids) ? $ids : [])
        );
    }

    /**
     * @param array<string, mixed> $question
     * @param list<array<string, mixed>> $options
     */
    public function createWithOptions(
        array $question,
        array $options
    ): int {
        global $wpdb;

        $questions = Database::table('questions');
        $wpdb->query('START TRANSACTION');

        try {
            $inserted = $wpdb->insert(
                $questions,
                $question,
                $this->questionFormats($question)
            );

            if ($inserted === false) {
                throw new RuntimeException('question_insert_failed');
            }

            $id = (int) $wpdb->insert_id;
            $this->replaceOptions($id, $options);
            $wpdb->query('COMMIT');

            return $id;
        } catch (\Throwable $exception) {
            $wpdb->query('ROLLBACK');
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $question
     * @param list<array<string, mixed>> $options
     */
    public function updateWithOptions(
        int $id,
        array $question,
        array $options
    ): void {
        global $wpdb;

        $questions = Database::table('questions');
        $wpdb->query('START TRANSACTION');

        try {
            $updated = $wpdb->update(
                $questions,
                $question,
                ['id' => $id],
                $this->questionFormats($question),
                ['%d']
            );

            if ($updated === false) {
                throw new RuntimeException('question_update_failed');
            }

            $this->replaceOptions($id, $options);
            $wpdb->query('COMMIT');
        } catch (\Throwable $exception) {
            $wpdb->query('ROLLBACK');
            throw $exception;
        }
    }

    /**
     * @param list<array<string, mixed>> $options
     */
    private function replaceOptions(int $questionId, array $options): void
    {
        global $wpdb;

        $table = Database::table('question_options');
        $deleted = $wpdb->delete(
            $table,
            ['question_id' => $questionId],
            ['%d']
        );

        if ($deleted === false) {
            throw new RuntimeException('question_options_delete_failed');
        }

        foreach ($options as $option) {
            $inserted = $wpdb->insert(
                $table,
                [
                    'question_id' => $questionId,
                    'option_key' => (string) $option['option_key'],
                    'option_text' => (string) $option['option_text'],
                    'is_correct' => (int) $option['is_correct'],
                    'sort_order' => (int) $option['sort_order'],
                    'created_at' => (string) $option['created_at'],
                    'updated_at' => (string) $option['updated_at'],
                ],
                ['%d', '%s', '%s', '%d', '%d', '%s', '%s']
            );

            if ($inserted === false) {
                throw new RuntimeException('question_option_insert_failed');
            }
        }
    }

    public function setStatus(int $id, string $status): void
    {
        global $wpdb;

        $table = Database::table('questions');
        $updated = $wpdb->update(
            $table,
            [
                'status' => $status,
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            throw new RuntimeException('question_status_failed');
        }
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        $simulationQuestions = Database::table('simulation_questions');
        $attemptAnswers = Database::table('attempt_answers');

        $references = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT
                    (SELECT COUNT(*) FROM {$simulationQuestions} WHERE question_id = %d)
                    +
                    (SELECT COUNT(*) FROM {$attemptAnswers} WHERE question_id = %d)",
                $id,
                $id
            )
        );

        if ($references > 0) {
            $this->setStatus($id, 'inactive');
            return false;
        }

        $questions = Database::table('questions');
        $options = Database::table('question_options');
        $wpdb->query('START TRANSACTION');

        try {
            $wpdb->delete($options, ['question_id' => $id], ['%d']);
            $deleted = $wpdb->delete($questions, ['id' => $id], ['%d']);
            if ($deleted === false) {
                throw new RuntimeException('question_delete_failed');
            }
            $wpdb->query('COMMIT');
            return true;
        } catch (\Throwable $exception) {
            $wpdb->query('ROLLBACK');
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0:string,1:list<mixed>}
     */
    private function where(array $filters): array
    {
        global $wpdb;

        $parts = [];
        $params = [];

        $equals = [
            'status' => 'status',
            'question_type' => 'question_type',
            'contest_term_id' => 'contest_term_id',
            'board' => 'board',
            'position_name' => 'position_name',
            'subject' => 'subject',
            'topic' => 'topic',
            'difficulty' => 'difficulty',
            'exam_year' => 'exam_year',
        ];

        foreach ($equals as $key => $column) {
            if (!array_key_exists($key, $filters)) {
                continue;
            }
            $value = $filters[$key];
            if ($value === '' || $value === null) {
                continue;
            }
            $parts[] = "{$column} = " . (
                in_array($key, ['contest_term_id', 'exam_year'], true)
                    ? '%d'
                    : '%s'
            );
            $params[] = $value;
        }

        $search = isset($filters['search']) && is_scalar($filters['search'])
            ? trim((string) $filters['search'])
            : '';
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $parts[] = '(statement LIKE %s OR subject LIKE %s OR topic LIKE %s OR board LIKE %s)';
            array_push($params, $like, $like, $like, $like);
        }

        return [
            $parts === [] ? '' : 'WHERE ' . implode(' AND ', $parts),
            $params,
        ];
    }

    /**
     * @param array<string, mixed> $question
     * @return list<string>
     */
    private function questionFormats(array $question): array
    {
        $formats = [];
        foreach (array_keys($question) as $key) {
            $formats[] = in_array(
                $key,
                ['contest_term_id', 'exam_year', 'image_attachment_id', 'created_by'],
                true
            ) ? '%d' : '%s';
        }
        return $formats;
    }
}
