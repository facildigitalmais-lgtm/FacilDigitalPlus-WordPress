<?php

declare(strict_types=1);

namespace FacilDigital\Core\Questions;

use InvalidArgumentException;

final class QuestionService
{
    public function __construct(
        private readonly QuestionRepository $repository = new QuestionRepository()
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(array $input, int $userId): int
    {
        [$question, $options] = $this->normalize($input, $userId, true);
        return $this->repository->createWithOptions($question, $options);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(int $id, array $input, int $userId): void
    {
        if ($this->repository->find($id) === null) {
            throw new InvalidArgumentException('question_not_found');
        }
        [$question, $options] = $this->normalize($input, $userId, false);
        $this->repository->updateWithOptions($id, $question, $options);
    }

    public function duplicate(int $id, int $userId): int
    {
        $source = $this->repository->find($id);
        if (!is_array($source)) {
            throw new InvalidArgumentException('question_not_found');
        }

        $input = $source;
        $input['statement'] = sprintf(
            'Cópia — %s',
            (string) ($source['statement'] ?? '')
        );
        $input['status'] = 'draft';

        return $this->create($input, $userId);
    }

    public function setStatus(int $id, string $status): void
    {
        $allowed = ['draft', 'active', 'inactive'];
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('question_status_invalid');
        }
        $this->repository->setStatus($id, $status);
    }

    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0:array<string,mixed>,1:list<array<string,mixed>>}
     */
    private function normalize(
        array $input,
        int $userId,
        bool $creating
    ): array {
        $type = sanitize_key((string) ($input['question_type'] ?? 'multiple_choice'));
        if (!in_array($type, ['multiple_choice', 'true_false'], true)) {
            throw new InvalidArgumentException('question_type_invalid');
        }

        $statement = sanitize_textarea_field(
            (string) ($input['statement'] ?? '')
        );
        if (strlen(trim($statement)) < 3) {
            throw new InvalidArgumentException('question_statement_required');
        }

        $difficulty = sanitize_key((string) ($input['difficulty'] ?? 'medium'));
        if (!in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
            $difficulty = 'medium';
        }

        $status = sanitize_key((string) ($input['status'] ?? 'draft'));
        if (!in_array($status, ['draft', 'active', 'inactive'], true)) {
            $status = 'draft';
        }

        $year = absint($input['exam_year'] ?? 0);
        if ($year !== 0 && ($year < 1900 || $year > 2100)) {
            throw new InvalidArgumentException('question_year_invalid');
        }

        $now = current_time('mysql', true);
        $question = [
            'contest_term_id' => absint($input['contest_term_id'] ?? 0) ?: null,
            'question_type' => $type,
            'statement' => $statement,
            'explanation' => sanitize_textarea_field((string) ($input['explanation'] ?? '')),
            'board' => sanitize_text_field((string) ($input['board'] ?? '')),
            'position_name' => sanitize_text_field((string) ($input['position_name'] ?? '')),
            'subject' => sanitize_text_field((string) ($input['subject'] ?? '')),
            'topic' => sanitize_text_field((string) ($input['topic'] ?? '')),
            'difficulty' => $difficulty,
            'exam_year' => $year ?: null,
            'status' => $status,
            'image_attachment_id' => absint($input['image_attachment_id'] ?? 0) ?: null,
            'updated_at' => $now,
        ];

        if ($creating) {
            $question['created_by'] = $userId > 0 ? $userId : null;
            $question['created_at'] = $now;
        }

        $options = $this->normalizeOptions($type, $input['options'] ?? []);

        return [$question, $options];
    }

    /**
     * @param mixed $rawOptions
     * @return list<array<string, mixed>>
     */
    private function normalizeOptions(string $type, mixed $rawOptions): array
    {
        $items = is_array($rawOptions) ? $rawOptions : [];
        $now = current_time('mysql', true);
        $normalized = [];

        if ($type === 'true_false') {
            $correctKey = '';
            foreach ($items as $item) {
                if (
                    is_array($item)
                    && !empty($item['is_correct'])
                ) {
                    $correctKey = strtoupper(
                        sanitize_key((string) ($item['option_key'] ?? ''))
                    );
                }
            }
            if (!in_array($correctKey, ['C', 'E'], true)) {
                throw new InvalidArgumentException('question_correct_option_required');
            }
            $items = [
                ['option_key' => 'C', 'option_text' => 'Certo', 'is_correct' => $correctKey === 'C'],
                ['option_key' => 'E', 'option_text' => 'Errado', 'is_correct' => $correctKey === 'E'],
            ];
        }

        $correct = 0;
        $seen = [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = strtoupper(
                sanitize_key((string) ($item['option_key'] ?? ''))
            );
            $text = sanitize_textarea_field((string) ($item['option_text'] ?? ''));
            if ($key === '' || trim($text) === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $isCorrect = !empty($item['is_correct']);
            if ($isCorrect) {
                $correct++;
            }
            $normalized[] = [
                'option_key' => substr($key, 0, 16),
                'option_text' => $text,
                'is_correct' => $isCorrect ? 1 : 0,
                'sort_order' => (int) ($item['sort_order'] ?? $index),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (count($normalized) < 2) {
            throw new InvalidArgumentException('question_options_insufficient');
        }
        if ($correct !== 1) {
            throw new InvalidArgumentException('question_correct_option_required');
        }

        return $normalized;
    }
}
