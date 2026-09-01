<?php

declare(strict_types=1);

namespace FacilDigital\Core\Attempts;

use FacilDigital\Core\Questions\QuestionRepository;
use FacilDigital\Core\Simulations\SimulationAccessService;
use FacilDigital\Core\Simulations\SimulationRepository;

final class AttemptService
{
    public function __construct(
        private readonly AttemptRepository $attempts = new AttemptRepository(),
        private readonly SimulationRepository $simulations = new SimulationRepository(),
        private readonly QuestionRepository $questions = new QuestionRepository(),
        private readonly SimulationAccessService $access = new SimulationAccessService()
    ) {
    }

    /** @return array<string,mixed> */
    public function start(int $userId, int $simulationId): array
    {
        $simulation = $this->simulationForAccess($userId, $simulationId);
        $active = $this->attempts->activeForUserSimulation($userId, $simulationId);
        if (is_array($active)) {
            if ($this->isExpired($active)) {
                $this->finalizeAttempt($active, $simulation);
            } else {
                return $this->state($userId, (int) $active['id']);
            }
        }

        $limit = isset($simulation['attempt_limit']) && $simulation['attempt_limit'] !== null
            ? (int) $simulation['attempt_limit'] : 0;
        if (
            $limit > 0
            && $this->attempts->countForUserSimulation($userId, $simulationId) >= $limit
        ) {
            throw new AttemptException(
                'attempt_limit_reached',
                'Limite de tentativas atingido.',
                409
            );
        }

        $now = current_time('timestamp', true);
        $startedAt = gmdate('Y-m-d H:i:s', $now);
        $duration = max(60, (int) ($simulation['duration_seconds'] ?? 0));
        $expiresAt = gmdate('Y-m-d H:i:s', $now + $duration);
        $attemptId = $this->attempts->create(
            $simulationId,
            $userId,
            $startedAt,
            $expiresAt
        );

        return $this->state($userId, $attemptId);
    }

    /** @return array<string,mixed> */
    public function state(int $userId, int $attemptId): array
    {
        $attempt = $this->ownedAttempt($userId, $attemptId);
        $simulation = $this->simulationForAccess($userId, (int) $attempt['simulation_id']);
        if (($attempt['status'] ?? '') === 'in_progress' && $this->isExpired($attempt)) {
            $this->finalizeAttempt($attempt, $simulation);
            return [
                'status' => 'completed',
                'attempt_id' => $attemptId,
                'result' => $this->result($userId, $attemptId),
            ];
        }
        if (($attempt['status'] ?? '') !== 'in_progress') {
            return [
                'status' => 'completed',
                'attempt_id' => $attemptId,
                'result' => $this->result($userId, $attemptId),
            ];
        }

        $answers = [];
        foreach ($this->attempts->answers($attemptId) as $answer) {
            $answers[(int) $answer['question_id']] = isset($answer['selected_option_id'])
                ? (int) $answer['selected_option_id'] : 0;
        }

        $questions = [];
        foreach ($this->simulations->questionRows((int) $simulation['id']) as $row) {
            $questionId = (int) $row['id'];
            $options = [];
            foreach ($this->questions->optionsForQuestion($questionId) as $option) {
                $options[] = [
                    'id' => (int) $option['id'],
                    'key' => (string) $option['option_key'],
                    'text' => (string) $option['option_text'],
                ];
            }
            $questions[] = [
                'id' => $questionId,
                'type' => (string) $row['question_type'],
                'statement' => (string) $row['statement'],
                'subject' => (string) ($row['subject'] ?? ''),
                'topic' => (string) ($row['topic'] ?? ''),
                'image_attachment_id' => (int) ($row['image_attachment_id'] ?? 0),
                'options' => $options,
                'selected_option_id' => $answers[$questionId] ?? 0,
            ];
        }

        return [
            'status' => 'in_progress',
            'attempt_id' => $attemptId,
            'simulation_id' => (int) $simulation['id'],
            'title' => (string) $simulation['title'],
            'question_count' => count($questions),
            'started_at' => $this->iso((string) $attempt['started_at']),
            'expires_at' => $this->iso((string) ($attempt['expires_at'] ?? '')),
            'server_now' => gmdate(DATE_ATOM),
            'remaining_seconds' => $this->remainingSeconds($attempt),
            'questions' => $questions,
        ];
    }

    /** @return array<string,mixed> */
    public function saveAnswer(
        int $userId,
        int $attemptId,
        int $questionId,
        ?int $optionId
    ): array {
        $attempt = $this->ownedAttempt($userId, $attemptId);
        $simulation = $this->simulationForAccess($userId, (int) $attempt['simulation_id']);
        if (($attempt['status'] ?? '') !== 'in_progress') {
            throw new AttemptException('attempt_closed', 'Tentativa já finalizada.', 409);
        }
        if ($this->isExpired($attempt)) {
            $this->finalizeAttempt($attempt, $simulation);
            throw new AttemptException('attempt_expired', 'O tempo do simulado terminou.', 409);
        }
        if (!$this->simulations->questionBelongs((int) $simulation['id'], $questionId)) {
            throw new AttemptException('question_not_in_simulation', 'Questão inválida.', 422);
        }
        if ($optionId !== null && $optionId > 0) {
            $valid = false;
            foreach ($this->questions->optionsForQuestion($questionId) as $option) {
                if ((int) $option['id'] === $optionId) {
                    $valid = true;
                    break;
                }
            }
            if (!$valid) {
                throw new AttemptException('option_invalid', 'Alternativa inválida.', 422);
            }
        } else {
            $optionId = null;
        }

        $this->attempts->upsertAnswer($attemptId, $questionId, $optionId);

        return [
            'saved' => true,
            'attempt_id' => $attemptId,
            'question_id' => $questionId,
            'selected_option_id' => $optionId ?? 0,
            'remaining_seconds' => $this->remainingSeconds($attempt),
        ];
    }

    /** @return array<string,mixed> */
    public function finish(int $userId, int $attemptId): array
    {
        $attempt = $this->ownedAttempt($userId, $attemptId);
        $simulation = $this->simulationForAccess($userId, (int) $attempt['simulation_id']);
        if (($attempt['status'] ?? '') === 'in_progress') {
            $this->finalizeAttempt($attempt, $simulation);
        }
        return $this->result($userId, $attemptId);
    }

    /** @return array<string,mixed> */
    public function result(int $userId, int $attemptId): array
    {
        $attempt = $this->ownedAttempt($userId, $attemptId);
        if (($attempt['status'] ?? '') !== 'completed') {
            throw new AttemptException('attempt_not_finished', 'Tentativa ainda não finalizada.', 409);
        }
        $simulation = $this->simulations->findById((int) $attempt['simulation_id']);
        if (!is_array($simulation)) {
            throw new AttemptException('simulation_not_found', 'Simulado não encontrado.', 404);
        }

        $answerMap = [];
        foreach ($this->attempts->answers($attemptId) as $answer) {
            $answerMap[(int) $answer['question_id']] = $answer;
        }
        $showKey = (int) ($simulation['show_answer_key'] ?? 0) === 1;
        $showComment = $showKey && ($simulation['comment_policy'] ?? '') === 'after_finish';
        $review = [];
        $breakdown = [];

        foreach ($this->simulations->questionRows((int) $simulation['id']) as $row) {
            $questionId = (int) $row['id'];
            $answer = $answerMap[$questionId] ?? null;
            $selectedId = is_array($answer) ? (int) ($answer['selected_option_id'] ?? 0) : 0;
            $selectedKey = '';
            $correctKey = '';
            foreach ($this->questions->optionsForQuestion($questionId) as $option) {
                if ((int) $option['id'] === $selectedId) {
                    $selectedKey = (string) $option['option_key'];
                }
                if ((int) $option['is_correct'] === 1) {
                    $correctKey = (string) $option['option_key'];
                }
            }
            $subject = trim((string) ($row['subject'] ?? '')) ?: 'Geral';
            if (!isset($breakdown[$subject])) {
                $breakdown[$subject] = ['correct' => 0, 'total' => 0];
            }
            $breakdown[$subject]['total']++;
            if (is_array($answer) && (int) ($answer['is_correct'] ?? 0) === 1) {
                $breakdown[$subject]['correct']++;
            }

            $item = [
                'question_id' => $questionId,
                'statement' => (string) $row['statement'],
                'subject' => $subject,
                'selected_key' => $selectedKey,
            ];
            if ($showKey) {
                $item['correct_key'] = $correctKey;
                $item['is_correct'] = is_array($answer)
                    ? ((int) ($answer['is_correct'] ?? 0) === 1)
                    : false;
            }
            if ($showComment) {
                $item['explanation'] = (string) ($row['explanation'] ?? '');
            }
            $review[] = $item;
        }

        $breakdownRows = [];
        foreach ($breakdown as $subject => $values) {
            $total = max(1, (int) $values['total']);
            $breakdownRows[] = [
                'subject' => $subject,
                'correct' => (int) $values['correct'],
                'total' => (int) $values['total'],
                'percentage' => round(((int) $values['correct'] / $total) * 100, 2),
            ];
        }

        return [
            'attempt_id' => $attemptId,
            'simulation_id' => (int) $simulation['id'],
            'title' => (string) $simulation['title'],
            'score' => (float) $attempt['score'],
            'percentage' => (float) $attempt['percentage'],
            'correct_count' => (int) $attempt['correct_count'],
            'incorrect_count' => (int) $attempt['incorrect_count'],
            'unanswered_count' => (int) $attempt['unanswered_count'],
            'elapsed_seconds' => (int) $attempt['elapsed_seconds'],
            'minimum_score' => (float) $simulation['minimum_score'],
            'passed' => (float) $attempt['percentage'] >= (float) $simulation['minimum_score'],
            'submitted_at' => $this->iso((string) ($attempt['submitted_at'] ?? '')),
            'breakdown' => $breakdownRows,
            'review' => $review,
        ];
    }

    /** @return list<array<string,mixed>> */
    public function history(int $userId, int $limit = 100): array
    {
        if ($userId <= 0) {
            return [];
        }
        return array_map(
            static function (array $row): array {
                return [
                    'attempt_id' => (int) $row['id'],
                    'simulation_id' => (int) $row['simulation_id'],
                    'title' => (string) $row['simulation_title'],
                    'slug' => (string) $row['simulation_slug'],
                    'percentage' => (float) $row['percentage'],
                    'score' => (float) $row['score'],
                    'elapsed_seconds' => (int) $row['elapsed_seconds'],
                    'submitted_at' => (string) ($row['submitted_at'] ?? ''),
                    'passed' => (float) $row['percentage'] >= (float) $row['minimum_score'],
                ];
            },
            $this->attempts->historyForUser($userId, $limit)
        );
    }

    /** @param array<string,mixed> $attempt @param array<string,mixed> $simulation */
    private function finalizeAttempt(array $attempt, array $simulation): void
    {
        $attemptId = (int) $attempt['id'];
        $answerMap = [];
        foreach ($this->attempts->answers($attemptId) as $answer) {
            $answerMap[(int) $answer['question_id']] = $answer;
        }

        $totalPoints = 0.0;
        $earned = 0.0;
        $correct = 0;
        $incorrect = 0;
        $unanswered = 0;

        foreach ($this->simulations->questionRows((int) $simulation['id']) as $row) {
            $questionId = (int) $row['id'];
            $points = max(0.0, (float) ($row['points'] ?? 1));
            $totalPoints += $points;
            $answer = $answerMap[$questionId] ?? null;
            $selectedId = is_array($answer) ? (int) ($answer['selected_option_id'] ?? 0) : 0;
            if ($selectedId <= 0) {
                $unanswered++;
                continue;
            }
            $isCorrect = false;
            foreach ($this->questions->optionsForQuestion($questionId) as $option) {
                if ((int) $option['id'] === $selectedId) {
                    $isCorrect = (int) $option['is_correct'] === 1;
                    break;
                }
            }
            $this->attempts->markCorrectness($attemptId, $questionId, $isCorrect);
            if ($isCorrect) {
                $correct++;
                $earned += $points;
            } else {
                $incorrect++;
            }
        }

        $percentage = $totalPoints > 0 ? round(($earned / $totalPoints) * 100, 2) : 0.0;
        $now = current_time('timestamp', true);
        $started = strtotime((string) $attempt['started_at'] . ' UTC') ?: $now;
        $duration = max(0, (int) ($simulation['duration_seconds'] ?? 0));
        $elapsed = max(0, $now - $started);
        if ($duration > 0) {
            $elapsed = min($elapsed, $duration);
        }
        $this->attempts->finalize($attemptId, [
            'submitted_at' => gmdate('Y-m-d H:i:s', $now),
            'score' => number_format($earned, 2, '.', ''),
            'percentage' => number_format($percentage, 2, '.', ''),
            'correct_count' => $correct,
            'incorrect_count' => $incorrect,
            'unanswered_count' => $unanswered,
            'elapsed_seconds' => $elapsed,
        ]);
    }

    /** @return array<string,mixed> */
    private function ownedAttempt(int $userId, int $attemptId): array
    {
        $attempt = $this->attempts->find($attemptId);
        if (!is_array($attempt)) {
            throw new AttemptException('attempt_not_found', 'Tentativa não encontrada.', 404);
        }
        if ((int) $attempt['user_id'] !== $userId) {
            throw new AttemptException('attempt_forbidden', 'Tentativa não pertence ao usuário.', 403);
        }
        return $attempt;
    }

    /** @return array<string,mixed> */
    private function simulationForAccess(int $userId, int $simulationId): array
    {
        $simulation = $this->simulations->findById($simulationId);
        if (!is_array($simulation) || ($simulation['status'] ?? '') !== 'published') {
            throw new AttemptException('simulation_not_found', 'Simulado não encontrado.', 404);
        }
        if (!$this->access->canAccess($userId, $simulationId)) {
            throw new AttemptException('simulation_access_denied', 'Seu acesso a este simulado não está liberado.', 403);
        }
        return $simulation;
    }

    /** @param array<string,mixed> $attempt */
    private function isExpired(array $attempt): bool
    {
        $expires = (string) ($attempt['expires_at'] ?? '');
        if ($expires === '') {
            return false;
        }
        $timestamp = strtotime($expires . ' UTC');
        return $timestamp !== false && current_time('timestamp', true) >= $timestamp;
    }

    /** @param array<string,mixed> $attempt */
    private function remainingSeconds(array $attempt): int
    {
        $expires = (string) ($attempt['expires_at'] ?? '');
        if ($expires === '') {
            return 0;
        }
        $timestamp = strtotime($expires . ' UTC');
        if ($timestamp === false) {
            return 0;
        }
        return max(0, $timestamp - current_time('timestamp', true));
    }

    private function iso(string $mysqlUtc): string
    {
        if ($mysqlUtc === '') {
            return '';
        }
        $timestamp = strtotime($mysqlUtc . ' UTC');
        return $timestamp === false ? '' : gmdate(DATE_ATOM, $timestamp);
    }
}
