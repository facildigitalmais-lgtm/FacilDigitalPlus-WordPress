<?php

declare(strict_types=1);

use FacilDigital\Core\Attempts\AttemptException;
use FacilDigital\Core\Attempts\AttemptRepository;
use FacilDigital\Core\Attempts\AttemptService;
use FacilDigital\Core\Core\Database;
use FacilDigital\Core\Questions\QuestionRepository;
use FacilDigital\Core\Ranking\RankingService;
use FacilDigital\Core\Simulations\SimulationAccessService;

$seed = get_option('fd_m3_seed', []);
if (!is_array($seed) || empty($seed['simulation_id'])) {
    throw new RuntimeException('m3_seed_missing');
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$userId = (int) $seed['user_id'];
$unauthorizedUserId = (int) $seed['unauthorized_user_id'];
$rankingUserId = (int) $seed['ranking_user_id'];
$simulationId = (int) $seed['simulation_id'];
$questionIds = array_map('intval', (array) $seed['question_ids']);

$access = new SimulationAccessService();
$assert($access->canAccess($userId, $simulationId), 'm3_entitled_access_failed');
$assert(!$access->canAccess($unauthorizedUserId, $simulationId), 'm3_unauthorized_access_allowed');

$questions = new QuestionRepository();
$attempts = new AttemptService();
$attemptRepo = new AttemptRepository();

$choiceFor = static function (int $questionId, bool $correct) use ($questions): int {
    foreach ($questions->optionsForQuestion($questionId) as $option) {
        if (((int) $option['is_correct'] === 1) === $correct) {
            return (int) $option['id'];
        }
    }
    throw new RuntimeException('m3_option_missing');
};

$state = $attempts->start($userId, $simulationId);
$assert(($state['status'] ?? '') === 'in_progress', 'm3_attempt_not_started');
$assert(count((array) ($state['questions'] ?? [])) === 5, 'm3_question_count_bad');
$encodedState = wp_json_encode($state);
$assert(is_string($encodedState), 'm3_state_json_failed');
foreach (['is_correct', 'correct_key', 'explanation'] as $forbidden) {
    $assert(!str_contains($encodedState, '"' . $forbidden . '"'), 'm3_active_attempt_leak_' . $forbidden);
}

$attemptId = (int) $state['attempt_id'];
foreach ($questionIds as $index => $questionId) {
    $optionId = $choiceFor($questionId, $index < 4);
    $saved = $attempts->saveAnswer($userId, $attemptId, $questionId, $optionId);
    $assert(!empty($saved['saved']), 'm3_autosave_failed');
    if ($index === 0) {
        $attempts->saveAnswer($userId, $attemptId, $questionId, $optionId);
    }
}
$assert(count($attemptRepo->answers($attemptId)) === 5, 'm3_autosave_not_idempotent');

$result = $attempts->finish($userId, $attemptId);
$assert(abs((float) $result['percentage'] - 80.0) < 0.01, 'm3_server_score_bad');
$assert((int) $result['correct_count'] === 4, 'm3_correct_count_bad');
$assert((int) $result['incorrect_count'] === 1, 'm3_incorrect_count_bad');
$assert((int) $result['unanswered_count'] === 0, 'm3_unanswered_count_bad');
$assert(!empty($result['breakdown']), 'm3_breakdown_missing');
$assert(isset($result['review'][0]['correct_key']), 'm3_answer_key_missing');
$assert(isset($result['review'][0]['explanation']), 'm3_comment_missing');

$history = $attempts->history($userId, 10);
$assert(count($history) === 1, 'm3_history_missing');

$unauthorizedBlocked = false;
try {
    $attempts->start($unauthorizedUserId, $simulationId);
} catch (AttemptException $exception) {
    $unauthorizedBlocked = $exception->errorCode === 'simulation_access_denied';
}
$assert($unauthorizedBlocked, 'm3_unauthorized_start_not_blocked');

$rankingState = $attempts->start($rankingUserId, $simulationId);
$rankingAttemptId = (int) $rankingState['attempt_id'];
foreach ($questionIds as $index => $questionId) {
    $attempts->saveAnswer(
        $rankingUserId,
        $rankingAttemptId,
        $questionId,
        $choiceFor($questionId, $index < 3)
    );
}
$rankingResult = $attempts->finish($rankingUserId, $rankingAttemptId);
$assert(abs((float) $rankingResult['percentage'] - 60.0) < 0.01, 'm3_ranking_score_bad');

$expiredState = $attempts->start($rankingUserId, $simulationId);
$expiredAttemptId = (int) $expiredState['attempt_id'];
global $wpdb;
$wpdb->update(
    Database::table('attempts'),
    ['expires_at' => gmdate('Y-m-d H:i:s', time() - 10)],
    ['id' => $expiredAttemptId],
    ['%s'],
    ['%d']
);
$expiredBlocked = false;
try {
    $attempts->saveAnswer(
        $rankingUserId,
        $expiredAttemptId,
        $questionIds[0],
        $choiceFor($questionIds[0], true)
    );
} catch (AttemptException $exception) {
    $expiredBlocked = $exception->errorCode === 'attempt_expired';
}
$assert($expiredBlocked, 'm3_server_timer_not_enforced');
$expiredRow = $attemptRepo->find($expiredAttemptId);
$assert(is_array($expiredRow) && ($expiredRow['status'] ?? '') === 'completed', 'm3_expired_not_finalized');

$secondMain = $attempts->start($userId, $simulationId);
$attempts->finish($userId, (int) $secondMain['attempt_id']);
$limitBlocked = false;
try {
    $attempts->start($userId, $simulationId);
} catch (AttemptException $exception) {
    $limitBlocked = $exception->errorCode === 'attempt_limit_reached';
}
$assert($limitBlocked, 'm3_attempt_limit_not_enforced');

$ranking = new RankingService();
$entries = $ranking->forSimulation($simulationId, 100);
$assert(count($entries) >= 2, 'm3_ranking_entries_missing');
$assert((float) $entries[0]['score'] >= (float) $entries[1]['score'], 'm3_ranking_sort_bad');
$rankJson = wp_json_encode($entries);
$assert(is_string($rankJson), 'm3_ranking_json_failed');
foreach (['@', '52998224725', 'cpf', 'email'] as $forbidden) {
    $assert(!str_contains(strtolower($rankJson), strtolower($forbidden)), 'm3_ranking_pii_leak');
}
$assert($ranking->generalPositionForUser($userId) === 1, 'm3_general_position_bad');
$assert($ranking->monthly(gmdate('Y-m'), 100) !== [], 'm3_monthly_ranking_missing');

$payload = [
    'status' => 'ok',
    'attempt_id' => $attemptId,
    'percentage' => (float) $result['percentage'],
    'history_count' => count($history),
    'ranking_entries' => count($entries),
    'attempt_limit_blocked' => $limitBlocked,
    'expired_blocked' => $expiredBlocked,
    'unauthorized_blocked' => $unauthorizedBlocked,
];

echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
