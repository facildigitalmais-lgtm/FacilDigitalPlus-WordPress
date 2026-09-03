<?php

declare(strict_types=1);

namespace FacilDigital\Core\Simulations;

use FacilDigital\Core\Products\ProductMetadata;
use FacilDigital\Core\Questions\QuestionRepository;
use InvalidArgumentException;
use WC_Product;

final class SimulationService
{
    public function __construct(
        private readonly SimulationRepository $repository = new SimulationRepository(),
        private readonly QuestionRepository $questions = new QuestionRepository()
    ) {
    }

    /** @param array<string,mixed> $input */
    public function create(array $input, int $userId): int
    {
        [$data, $questionIds, $productIds] = $this->normalize($input, $userId, true);
        return $this->repository->create($data, $questionIds, $productIds);
    }

    /** @param array<string,mixed> $input */
    public function update(int $id, array $input, int $userId): void
    {
        if ($this->repository->findById($id) === null) {
            throw new InvalidArgumentException('simulation_not_found');
        }
        [$data, $questionIds, $productIds] = $this->normalize($input, $userId, false);
        $this->repository->update($id, $data, $questionIds, $productIds);
    }

    public function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['draft', 'published', 'inactive'], true)) {
            throw new InvalidArgumentException('simulation_status_invalid');
        }
        $this->repository->setStatus($id, $status);
    }

    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }

    /** @param array<string,mixed> $input @return array{0:array<string,mixed>,1:list<int>,2:list<int>} */
    private function normalize(array $input, int $userId, bool $creating): array
    {
        $title = sanitize_text_field((string) ($input['title'] ?? ''));
        if (strlen(trim($title)) < 3) {
            throw new InvalidArgumentException('simulation_title_required');
        }

        $slug = sanitize_title((string) ($input['slug'] ?? $title));
        if ($slug === '') {
            throw new InvalidArgumentException('simulation_slug_required');
        }

        $duration = absint($input['duration_seconds'] ?? 0);
        if ($duration < 60 || $duration > DAY_IN_SECONDS) {
            throw new InvalidArgumentException('simulation_duration_invalid');
        }

        $attemptLimitRaw = $input['attempt_limit'] ?? null;
        $attemptLimit = ($attemptLimitRaw === '' || $attemptLimitRaw === null)
            ? null
            : max(1, min(1000, absint($attemptLimitRaw)));

        $minimumScore = (float) ($input['minimum_score'] ?? 0);
        $minimumScore = max(0.0, min(100.0, $minimumScore));

        $commentPolicy = sanitize_key((string) ($input['comment_policy'] ?? 'after_finish'));
        if (!in_array($commentPolicy, ['after_finish', 'never'], true)) {
            $commentPolicy = 'after_finish';
        }

        $selectionMode = sanitize_key((string) ($input['selection_mode'] ?? 'manual'));
        if (!in_array($selectionMode, ['manual', 'subject', 'topic', 'board', 'random'], true)) {
            $selectionMode = 'manual';
        }

        $status = sanitize_key((string) ($input['status'] ?? 'draft'));
        if (!in_array($status, ['draft', 'published', 'inactive'], true)) {
            $status = 'draft';
        }

        $questionIds = $this->resolveQuestions($input, $selectionMode);
        if ($questionIds === []) {
            throw new InvalidArgumentException('simulation_questions_required');
        }

        $productIds = $this->resolveProducts($input);

        $now = current_time('mysql', true);
        $data = [
            'title' => $title,
            'slug' => $slug,
            'description' => sanitize_textarea_field((string) ($input['description'] ?? '')),
            'contest_term_id' => absint($input['contest_term_id'] ?? 0) ?: null,
            'position_name' => sanitize_text_field((string) ($input['position_name'] ?? '')),
            'duration_seconds' => $duration,
            'question_count' => count($questionIds),
            'attempt_limit' => $attemptLimit,
            'minimum_score' => number_format($minimumScore, 2, '.', ''),
            'show_answer_key' => !empty($input['show_answer_key']) ? 1 : 0,
            'comment_policy' => $commentPolicy,
            'ranking_enabled' => !empty($input['ranking_enabled']) ? 1 : 0,
            'selection_mode' => $selectionMode,
            'status' => $status,
            'updated_at' => $now,
        ];
        if ($creating) {
            $data['created_by'] = $userId > 0 ? $userId : null;
            $data['created_at'] = $now;
        }

        return [$data, $questionIds, $productIds];
    }

    /** @param array<string,mixed> $input @return list<int> */
    private function resolveProducts(array $input): array
    {
        $raw = $input['product_ids'] ?? [];

        if (!is_array($raw)) {
            return [];
        }

        $ids = array_values(
            array_unique(
                array_filter(
                    array_map('absint', $raw)
                )
            )
        );

        $valid = [];

        foreach ($ids as $productId) {
            if ($productId <= 0 || !ProductMetadata::isApostila($productId)) {
                continue;
            }

            $product = wc_get_product($productId);

            if (!$product instanceof WC_Product) {
                continue;
            }

            $valid[] = $productId;
        }

        return $valid;
    }

    /** @param array<string,mixed> $input @return list<int> */
    private function resolveQuestions(array $input, string $mode): array
    {
        if ($mode === 'manual') {
            $raw = $input['question_ids'] ?? [];
            if (!is_array($raw)) {
                return [];
            }
            $ids = array_values(array_unique(array_filter(array_map('absint', $raw))));
            $valid = [];
            foreach ($ids as $id) {
                $question = $this->questions->find($id);
                if (is_array($question) && ($question['status'] ?? '') === 'active') {
                    $valid[] = $id;
                }
            }
            return $valid;
        }

        $limit = max(1, min(500, absint($input['question_count'] ?? 20)));
        $filters = [
            'contest_term_id' => absint($input['contest_term_id'] ?? 0) ?: null,
            'position_name' => sanitize_text_field((string) ($input['position_name'] ?? '')),
        ];
        if ($mode === 'subject') {
            $filters['subject'] = sanitize_text_field((string) ($input['selection_subject'] ?? ''));
        } elseif ($mode === 'topic') {
            $filters['topic'] = sanitize_text_field((string) ($input['selection_topic'] ?? ''));
        } elseif ($mode === 'board') {
            $filters['board'] = sanitize_text_field((string) ($input['selection_board'] ?? ''));
        }

        return $this->questions->selectActiveIds($filters, $limit, $mode === 'random');
    }
}
