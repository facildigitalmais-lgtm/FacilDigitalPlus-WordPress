<?php

declare(strict_types=1);

namespace FacilDigital\Core\Attempts;

use FacilDigital\Core\Core\Database;
use RuntimeException;

final class AttemptRepository
{
    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        global $wpdb;
        $table = Database::table('attempts');
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id),
            ARRAY_A
        );
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function activeForUserSimulation(int $userId, int $simulationId): ?array
    {
        global $wpdb;
        $table = Database::table('attempts');
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE user_id = %d AND simulation_id = %d AND status = 'in_progress'
                 ORDER BY id DESC LIMIT 1",
                $userId,
                $simulationId
            ),
            ARRAY_A
        );
        return is_array($row) ? $row : null;
    }

    public function countForUserSimulation(int $userId, int $simulationId): int
    {
        global $wpdb;
        $table = Database::table('attempts');
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND simulation_id = %d",
                $userId,
                $simulationId
            )
        );
    }

    /** @return array<string,mixed>|null */
    public function bestForUserSimulation(int $userId, int $simulationId): ?array
    {
        global $wpdb;
        $table = Database::table('attempts');
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE user_id = %d AND simulation_id = %d AND status = 'completed'
                 ORDER BY percentage DESC, elapsed_seconds ASC, id ASC
                 LIMIT 1",
                $userId,
                $simulationId
            ),
            ARRAY_A
        );
        return is_array($row) ? $row : null;
    }

    public function create(
        int $simulationId,
        int $userId,
        string $startedAt,
        ?string $expiresAt
    ): int {
        global $wpdb;
        $table = Database::table('attempts');
        $now = current_time('mysql', true);
        $inserted = $wpdb->insert(
            $table,
            [
                'simulation_id' => $simulationId,
                'user_id' => $userId,
                'status' => 'in_progress',
                'started_at' => $startedAt,
                'expires_at' => $expiresAt,
                'submitted_at' => null,
                'score' => '0.00',
                'percentage' => '0.00',
                'correct_count' => 0,
                'incorrect_count' => 0,
                'unanswered_count' => 0,
                'elapsed_seconds' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d','%d','%s','%s','%s','%s','%s','%s','%d','%d','%d','%d','%s','%s']
        );
        if ($inserted === false) {
            throw new RuntimeException('attempt_insert_failed');
        }
        return (int) $wpdb->insert_id;
    }

    public function upsertAnswer(
        int $attemptId,
        int $questionId,
        ?int $selectedOptionId
    ): void {
        global $wpdb;
        $table = Database::table('attempt_answers');
        $now = current_time('mysql', true);
        $sql = $wpdb->prepare(
            "INSERT INTO {$table}
                (attempt_id, question_id, selected_option_id, answer_value, is_correct, answered_at, updated_at)
             VALUES (%d, %d, NULLIF(%d, 0), NULL, NULL, %s, %s)
             ON DUPLICATE KEY UPDATE
                selected_option_id = VALUES(selected_option_id),
                answer_value = NULL,
                is_correct = NULL,
                answered_at = VALUES(answered_at),
                updated_at = VALUES(updated_at)",
            $attemptId,
            $questionId,
            $selectedOptionId ?? 0,
            $now,
            $now
        );
        $result = $wpdb->query($sql);
        if ($result === false) {
            throw new RuntimeException('attempt_answer_save_failed');
        }
    }

    public function markCorrectness(int $attemptId, int $questionId, ?bool $correct): void
    {
        global $wpdb;
        $table = Database::table('attempt_answers');
        $result = $wpdb->update(
            $table,
            [
                'is_correct' => $correct === null ? null : ($correct ? 1 : 0),
                'updated_at' => current_time('mysql', true),
            ],
            ['attempt_id' => $attemptId, 'question_id' => $questionId],
            ['%d', '%s'],
            ['%d','%d']
        );
        if ($result === false) {
            throw new RuntimeException('attempt_answer_score_failed');
        }
    }

    /** @return list<array<string,mixed>> */
    public function answers(int $attemptId): array
    {
        global $wpdb;
        $table = Database::table('attempt_answers');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE attempt_id = %d ORDER BY question_id ASC",
                $attemptId
            ),
            ARRAY_A
        );
        return is_array($rows) ? array_values($rows) : [];
    }

    /** @param array<string,mixed> $score */
    public function finalize(int $attemptId, array $score): void
    {
        global $wpdb;
        $table = Database::table('attempts');
        $result = $wpdb->update(
            $table,
            [
                'status' => 'completed',
                'submitted_at' => (string) $score['submitted_at'],
                'score' => (string) $score['score'],
                'percentage' => (string) $score['percentage'],
                'correct_count' => (int) $score['correct_count'],
                'incorrect_count' => (int) $score['incorrect_count'],
                'unanswered_count' => (int) $score['unanswered_count'],
                'elapsed_seconds' => (int) $score['elapsed_seconds'],
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $attemptId],
            ['%s','%s','%s','%s','%d','%d','%d','%d','%s'],
            ['%d']
        );
        if ($result === false) {
            throw new RuntimeException('attempt_finalize_failed');
        }
    }

    /** @return list<array<string,mixed>> */
    public function historyForUser(int $userId, int $limit = 100): array
    {
        global $wpdb;
        $attempts = Database::table('attempts');
        $simulations = Database::table('simulations');
        $limit = max(1, min(500, $limit));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, s.title AS simulation_title, s.slug AS simulation_slug,
                        s.minimum_score, s.contest_term_id
                 FROM {$attempts} a
                 INNER JOIN {$simulations} s ON s.id = a.simulation_id
                 WHERE a.user_id = %d AND a.status = 'completed'
                 ORDER BY a.submitted_at DESC, a.id DESC
                 LIMIT %d",
                $userId,
                $limit
            ),
            ARRAY_A
        );
        return is_array($rows) ? array_values($rows) : [];
    }
}
