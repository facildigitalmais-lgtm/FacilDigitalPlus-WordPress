<?php

declare(strict_types=1);

namespace FacilDigital\Core\Simulations;

use FacilDigital\Core\Core\Database;
use RuntimeException;

final class SimulationRepository
{
    /** @return array<string,mixed>|null */
    public function findById(int $id): ?array
    {
        global $wpdb;
        $table = Database::table('simulations');
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id),
            ARRAY_A
        );
        if (!is_array($row)) {
            return null;
        }
        $row['question_ids'] = $this->questionIds($id);
        $row['product_ids'] = $this->productIds($id);
        return $row;
    }

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        global $wpdb;
        $table = Database::table('simulations');
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s LIMIT 1", sanitize_title($slug)),
            ARRAY_A
        );
        if (!is_array($row)) {
            return null;
        }
        $row['question_ids'] = $this->questionIds((int) $row['id']);
        $row['product_ids'] = $this->productIds((int) $row['id']);
        return $row;
    }

    /** @return list<array<string,mixed>> */
    public function list(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        global $wpdb;
        $table = Database::table('simulations');
        $parts = [];
        $params = [];
        foreach ([
            'status' => 'status',
            'contest_term_id' => 'contest_term_id',
            'position_name' => 'position_name',
        ] as $key => $column) {
            $value = $filters[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $parts[] = $column . (str_ends_with($key, '_id') ? ' = %d' : ' = %s');
            $params[] = $value;
        }
        $search = isset($filters['search']) && is_scalar($filters['search'])
            ? trim((string) $filters['search']) : '';
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $parts[] = '(title LIKE %s OR description LIKE %s OR position_name LIKE %s)';
            array_push($params, $like, $like, $like);
        }
        $where = $parts === [] ? '' : 'WHERE ' . implode(' AND ', $parts);
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        $params[] = $limit;
        $params[] = $offset;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d",
                ...$params
            ),
            ARRAY_A
        );
        return is_array($rows) ? array_values($rows) : [];
    }

    /** @return list<int> */
    public function questionIds(int $simulationId): array
    {
        global $wpdb;
        $table = Database::table('simulation_questions');
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT question_id FROM {$table}
                 WHERE simulation_id = %d
                 ORDER BY sort_order ASC, id ASC",
                $simulationId
            )
        );
        return array_values(array_map('intval', is_array($ids) ? $ids : []));
    }

    /** @return list<int> */
    public function productIds(int $simulationId): array
    {
        global $wpdb;
        $table = Database::table('simulation_products');
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT product_id FROM {$table}
                 WHERE simulation_id = %d
                 ORDER BY id ASC",
                $simulationId
            )
        );

        if ($wpdb->last_error !== '') {
            throw new RuntimeException('simulation_products_read_failed');
        }

        return array_values(
            array_map('intval', is_array($ids) ? $ids : [])
        );
    }

    /** @return list<array<string,mixed>> */
    public function questionRows(int $simulationId): array
    {
        global $wpdb;
        $map = Database::table('simulation_questions');
        $questions = Database::table('questions');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT q.*, sq.points, sq.sort_order AS simulation_order
                 FROM {$map} sq
                 INNER JOIN {$questions} q ON q.id = sq.question_id
                 WHERE sq.simulation_id = %d
                 ORDER BY sq.sort_order ASC, sq.id ASC",
                $simulationId
            ),
            ARRAY_A
        );
        return is_array($rows) ? array_values($rows) : [];
    }

    public function questionBelongs(int $simulationId, int $questionId): bool
    {
        global $wpdb;
        $table = Database::table('simulation_questions');
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE simulation_id = %d AND question_id = %d",
                $simulationId,
                $questionId
            )
        ) > 0;
    }

    /** @param array<string,mixed> $data @param list<int> $questionIds @param list<int> $productIds */
    public function create(array $data, array $questionIds, array $productIds): int
    {
        global $wpdb;
        $table = Database::table('simulations');
        $wpdb->query('START TRANSACTION');
        try {
            $inserted = $wpdb->insert($table, $data, $this->formats($data));
            if ($inserted === false) {
                throw new RuntimeException('simulation_insert_failed');
            }
            $id = (int) $wpdb->insert_id;
            $this->replaceQuestions($id, $questionIds);
            $this->replaceProducts($id, $productIds);
            $wpdb->query('COMMIT');
            return $id;
        } catch (\Throwable $exception) {
            $wpdb->query('ROLLBACK');
            throw $exception;
        }
    }

    /** @param array<string,mixed> $data @param list<int> $questionIds @param list<int> $productIds */
    public function update(int $id, array $data, array $questionIds, array $productIds): void
    {
        global $wpdb;
        $table = Database::table('simulations');
        $wpdb->query('START TRANSACTION');
        try {
            $updated = $wpdb->update($table, $data, ['id' => $id], $this->formats($data), ['%d']);
            if ($updated === false) {
                throw new RuntimeException('simulation_update_failed');
            }
            $this->replaceQuestions($id, $questionIds);
            $this->replaceProducts($id, $productIds);
            $wpdb->query('COMMIT');
        } catch (\Throwable $exception) {
            $wpdb->query('ROLLBACK');
            throw $exception;
        }
    }

    /** @param list<int> $questionIds */
    private function replaceQuestions(int $simulationId, array $questionIds): void
    {
        global $wpdb;
        $table = Database::table('simulation_questions');
        $deleted = $wpdb->delete($table, ['simulation_id' => $simulationId], ['%d']);
        if ($deleted === false) {
            throw new RuntimeException('simulation_questions_delete_failed');
        }
        $now = current_time('mysql', true);
        foreach (array_values(array_unique(array_map('intval', $questionIds))) as $index => $questionId) {
            if ($questionId <= 0) {
                continue;
            }
            $inserted = $wpdb->insert(
                $table,
                [
                    'simulation_id' => $simulationId,
                    'question_id' => $questionId,
                    'sort_order' => $index + 1,
                    'points' => '1.00',
                    'created_at' => $now,
                ],
                ['%d', '%d', '%d', '%s', '%s']
            );
            if ($inserted === false) {
                throw new RuntimeException('simulation_question_insert_failed');
            }
        }
    }

    /** @param list<int> $productIds */
    private function replaceProducts(int $simulationId, array $productIds): void
    {
        global $wpdb;
        $table = Database::table('simulation_products');

        $deleted = $wpdb->delete(
            $table,
            ['simulation_id' => $simulationId],
            ['%d']
        );

        if ($deleted === false) {
            throw new RuntimeException('simulation_products_delete_failed');
        }

        $now = current_time('mysql', true);

        foreach (
            array_values(
                array_unique(
                    array_filter(
                        array_map('intval', $productIds)
                    )
                )
            ) as $productId
        ) {
            if ($productId <= 0) {
                continue;
            }

            $inserted = $wpdb->insert(
                $table,
                [
                    'simulation_id' => $simulationId,
                    'product_id' => $productId,
                    'created_at' => $now,
                ],
                ['%d', '%d', '%s']
            );

            if ($inserted === false) {
                throw new RuntimeException('simulation_product_insert_failed');
            }
        }
    }

    public function setStatus(int $id, string $status): void
    {
        global $wpdb;
        $table = Database::table('simulations');
        $result = $wpdb->update(
            $table,
            ['status' => $status, 'updated_at' => current_time('mysql', true)],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );
        if ($result === false) {
            throw new RuntimeException('simulation_status_failed');
        }
    }

    public function delete(int $id): bool
    {
        global $wpdb;
        $attempts = Database::table('attempts');
        $count = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$attempts} WHERE simulation_id = %d", $id)
        );
        if ($count > 0) {
            $this->setStatus($id, 'inactive');
            return false;
        }
        $table = Database::table('simulations');
        $map = Database::table('simulation_questions');
        $products = Database::table('simulation_products');
        $wpdb->query('START TRANSACTION');
        try {
            $questionsDeleted = $wpdb->delete(
                $map,
                ['simulation_id' => $id],
                ['%d']
            );

            if ($questionsDeleted === false) {
                throw new RuntimeException(
                    'simulation_questions_delete_failed'
                );
            }

            $productsDeleted = $wpdb->delete(
                $products,
                ['simulation_id' => $id],
                ['%d']
            );

            if ($productsDeleted === false) {
                throw new RuntimeException(
                    'simulation_products_delete_failed'
                );
            }

            $deleted = $wpdb->delete($table, ['id' => $id], ['%d']);
            if ($deleted === false) {
                throw new RuntimeException('simulation_delete_failed');
            }
            $wpdb->query('COMMIT');
            return true;
        } catch (\Throwable $exception) {
            $wpdb->query('ROLLBACK');
            throw $exception;
        }
    }

    /** @param array<string,mixed> $data @return list<string> */
    private function formats(array $data): array
    {
        $intFields = [
            'contest_term_id', 'duration_seconds', 'question_count', 'attempt_limit',
            'show_answer_key', 'ranking_enabled', 'created_by',
        ];
        $formats = [];
        foreach (array_keys($data) as $key) {
            $formats[] = in_array($key, $intFields, true) ? '%d' : '%s';
        }
        return $formats;
    }
}
