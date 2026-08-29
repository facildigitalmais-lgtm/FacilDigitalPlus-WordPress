<?php

declare(strict_types=1);

namespace FacilDigital\Core\Ranking;

use FacilDigital\Core\Core\Database;

final class RankingService
{
    /** @return list<array<string,mixed>> */
    public function forSimulation(int $simulationId, int $limit = 100): array
    {
        if ($simulationId <= 0) {
            return [];
        }
        return $this->build(['simulation_id' => $simulationId], $limit, false);
    }

    /** @return list<array<string,mixed>> */
    public function forContest(int $contestTermId, int $limit = 100): array
    {
        if ($contestTermId <= 0) {
            return [];
        }
        return $this->build(['contest_term_id' => $contestTermId], $limit, false);
    }

    /** @return list<array<string,mixed>> */
    public function monthly(string $yearMonth, int $limit = 100): array
    {
        if (preg_match('/^\d{4}-\d{2}$/', $yearMonth) !== 1) {
            return [];
        }
        $start = $yearMonth . '-01 00:00:00';
        $timestamp = strtotime($start . ' UTC');
        if ($timestamp === false) {
            return [];
        }
        $end = gmdate('Y-m-d H:i:s', strtotime('+1 month', $timestamp));
        return $this->build([
            'submitted_from' => $start,
            'submitted_to' => $end,
        ], $limit, false);
    }

    /** @return list<array<string,mixed>> */
    public function general(int $limit = 100): array
    {
        return $this->build([], $limit, false);
    }

    public function generalPositionForUser(int $userId): ?int
    {
        if ($userId <= 0) {
            return null;
        }
        foreach ($this->build([], 5000, true) as $entry) {
            if ((int) ($entry['user_id'] ?? 0) === $userId) {
                return (int) $entry['rank'];
            }
        }
        return null;
    }

    /** @param array<string,mixed> $filters @return list<array<string,mixed>> */
    private function build(array $filters, int $limit, bool $includeUserId): array
    {
        global $wpdb;

        $attempts = Database::table('attempts');
        $simulations = Database::table('simulations');
        $parts = ["a.status = 'completed'", 's.ranking_enabled = 1'];
        $params = [];

        if (!empty($filters['simulation_id'])) {
            $parts[] = 'a.simulation_id = %d';
            $params[] = (int) $filters['simulation_id'];
        }
        if (!empty($filters['contest_term_id'])) {
            $parts[] = 's.contest_term_id = %d';
            $params[] = (int) $filters['contest_term_id'];
        }
        if (!empty($filters['submitted_from'])) {
            $parts[] = 'a.submitted_at >= %s';
            $params[] = (string) $filters['submitted_from'];
        }
        if (!empty($filters['submitted_to'])) {
            $parts[] = 'a.submitted_at < %s';
            $params[] = (string) $filters['submitted_to'];
        }

        $sql = "SELECT a.id, a.user_id, a.simulation_id, a.percentage,
                       a.elapsed_seconds, a.submitted_at
                FROM {$attempts} a
                INNER JOIN {$simulations} s ON s.id = a.simulation_id
                WHERE " . implode(' AND ', $parts) . "
                ORDER BY a.user_id ASC, a.simulation_id ASC,
                         a.percentage DESC, a.elapsed_seconds ASC,
                         a.submitted_at ASC, a.id ASC
                LIMIT 20000";
        if ($params !== []) {
            $sql = $wpdb->prepare($sql, ...$params);
        }
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $best = [];
        foreach ($rows as $row) {
            $key = (int) $row['user_id'] . ':' . (int) $row['simulation_id'];
            if (!isset($best[$key])) {
                $best[$key] = $row;
            }
        }

        $users = [];
        foreach ($best as $row) {
            $userId = (int) $row['user_id'];
            if (!isset($users[$userId])) {
                $users[$userId] = [
                    'sum' => 0.0,
                    'elapsed' => 0,
                    'simulations' => 0,
                ];
            }
            $users[$userId]['sum'] += (float) $row['percentage'];
            $users[$userId]['elapsed'] += (int) $row['elapsed_seconds'];
            $users[$userId]['simulations']++;
        }

        $entries = [];
        foreach ($users as $userId => $values) {
            $count = max(1, (int) $values['simulations']);
            $entries[] = [
                'user_id' => (int) $userId,
                'name' => $this->anonymousName((int) $userId),
                'score' => round((float) $values['sum'] / $count, 2),
                'elapsed_seconds' => (int) $values['elapsed'],
                'simulation_count' => $count,
            ];
        }

        usort(
            $entries,
            static function (array $a, array $b): int {
                $score = (float) $b['score'] <=> (float) $a['score'];
                if ($score !== 0) {
                    return $score;
                }
                $elapsed = (int) $a['elapsed_seconds'] <=> (int) $b['elapsed_seconds'];
                if ($elapsed !== 0) {
                    return $elapsed;
                }
                return (int) $a['user_id'] <=> (int) $b['user_id'];
            }
        );

        $limit = max(1, min(5000, $limit));
        $entries = array_slice($entries, 0, $limit);
        foreach ($entries as $index => &$entry) {
            $entry['rank'] = $index + 1;
            if (!$includeUserId) {
                unset($entry['user_id']);
            }
        }
        unset($entry);

        return $entries;
    }

    private function anonymousName(int $userId): string
    {
        $user = get_userdata($userId);
        if (!$user instanceof \WP_User) {
            return 'Aluno';
        }
        $display = trim(sanitize_text_field($user->display_name));
        if ($display === '') {
            return 'Aluno';
        }
        $parts = preg_split('/\s+/', $display) ?: [];
        $first = (string) ($parts[0] ?? 'Aluno');
        if (count($parts) < 2) {
            return $first;
        }
        $last = (string) end($parts);
        $initial = function_exists('mb_substr')
            ? mb_substr($last, 0, 1)
            : substr($last, 0, 1);
        return $first . ' ' . strtoupper($initial) . '.';
    }
}
