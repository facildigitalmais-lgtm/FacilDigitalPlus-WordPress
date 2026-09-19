<?php

declare(strict_types=1);

namespace FacilDigital\Core\Courses;

use RuntimeException;
use WP_User;

final class CourseLearningService
{
    private const MAX_HEARTBEAT_DELTA = 20;
    private const MAX_POSITION_JUMP = 30;

    public function __construct(
        private readonly CourseRepository $courses =
            new CourseRepository(),
        private readonly CourseModuleRepository $modules =
            new CourseModuleRepository(),
        private readonly LessonRepository $lessons =
            new LessonRepository(),
        private readonly LessonResourceRepository $resources =
            new LessonResourceRepository(),
        private readonly EnrollmentRepository $enrollments =
            new EnrollmentRepository(),
        private readonly ProgressRepository $progress =
            new ProgressRepository(),
        private readonly CertificateRepository $certificates =
            new CertificateRepository()
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function userCourses(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $result = [];

        foreach (
            $this->enrollments->forUser(
                $userId
            )
            as $enrollment
        ) {
            $status =
                (string) (
                    $enrollment['status']
                    ?? ''
                );

            if (
                !in_array(
                    $status,
                    [
                        'active',
                        'completed',
                    ],
                    true
                )
            ) {
                continue;
            }

            $course =
                $this->courses->findById(
                    (int) (
                        $enrollment['course_id']
                        ?? 0
                    )
                );

            if (!is_array($course)) {
                continue;
            }

            $curriculum =
                $this->studentCurriculum(
                    $enrollment,
                    $course
                );

            $result[] = [
                'enrollment' =>
                    $enrollment,
                'course' =>
                    $course,
                'progress' =>
                    $this->progressSummary(
                        $enrollment,
                        $course
                    ),
                'stats' =>
                    $this->curriculumStats(
                        $curriculum
                    ),
                'continue_lesson_id' =>
                    $this->firstAccessibleLessonId(
                        $curriculum
                    ),
            ];
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function certificatesForUser(
        int $userId
    ): array {
        if ($userId <= 0) {
            return [];
        }

        return $this->certificates->forUser(
            $userId
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function classroom(
        int $userId,
        int $enrollmentId,
        int $lessonId = 0
    ): array {
        $enrollment =
            $this->requireEnrollment(
                $userId,
                $enrollmentId
            );

        $course =
            $this->requireCourse(
                (int) $enrollment[
                    'course_id'
                ]
            );

        $curriculum =
            $this->studentCurriculum(
                $enrollment,
                $course
            );

        if ($curriculum === []) {
            throw new RuntimeException(
                'course_has_no_lessons'
            );
        }

        if ($lessonId <= 0) {
            $lessonId =
                $this->firstAccessibleLessonId(
                    $curriculum
                );
        }

        $current =
            $this->findCurriculumLesson(
                $curriculum,
                $lessonId
            );

        if (!is_array($current)) {
            throw new RuntimeException(
                'course_lesson_missing'
            );
        }

        if (
            !empty(
                $current['locked']
            )
        ) {
            throw new RuntimeException(
                'course_lesson_locked'
            );
        }

        return [
            'enrollment' =>
                $enrollment,
            'course' =>
                $course,
            'curriculum' =>
                $curriculum,
            'lesson' =>
                $current,
            'progress' =>
                $this->progressSummary(
                    $enrollment,
                    $course
                ),
            'navigation' =>
                $this->lessonNavigation(
                    $curriculum,
                    $lessonId
                ),
            'certificate' =>
                $this->certificates
                    ->findByEnrollmentId(
                        $enrollmentId
                    ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordVideoProgress(
        int $userId,
        int $enrollmentId,
        int $lessonId,
        int $positionSeconds,
        int $clientDurationSeconds,
        int $watchedDelta
    ): array {
        $enrollment =
            $this->requireEnrollment(
                $userId,
                $enrollmentId
            );

        $course =
            $this->requireCourse(
                (int) $enrollment[
                    'course_id'
                ]
            );

        $lesson =
            $this->requirePublishedLesson(
                (int) $course['id'],
                $lessonId
            );

        if (
            !in_array(
                (string) (
                    $lesson['lesson_type']
                    ?? ''
                ),
                [
                    'video',
                    'mixed',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'course_video_progress_invalid'
            );
        }

        $this->assertLessonAccessible(
            $enrollment,
            $course,
            $lessonId
        );

        $progressId =
            $this->progress->getOrCreate(
                $enrollmentId,
                $lessonId
            );

        $current =
            $this->progress->findById(
                $progressId
            );

        if (!is_array($current)) {
            throw new RuntimeException(
                'lesson_progress_missing'
            );
        }

        if (
            (string) (
                $current['status']
                ?? ''
            ) === 'completed'
        ) {
            return $this->progressResponse(
                $current,
                true,
                $this->maybeCompleteCourse(
                    $userId,
                    $enrollment,
                    $course
                )
            );
        }

        $this->progress->start(
            $progressId
        );

        $duration =
            max(
                0,
                (int) (
                    $lesson[
                        'duration_seconds'
                    ]
                    ?? 0
                )
            );

        if ($duration <= 0) {
            $duration = min(
                86400,
                max(
                    0,
                    $clientDurationSeconds
                )
            );
        }

        if ($duration <= 0) {
            throw new RuntimeException(
                'course_video_duration_invalid'
            );
        }

        $positionSeconds = min(
            $duration,
            max(
                0,
                $positionSeconds
            )
        );

        $watchedDelta = min(
            self::MAX_HEARTBEAT_DELTA,
            max(
                0,
                $watchedDelta
            )
        );

        $lastPosition =
            max(
                0,
                (int) (
                    $current[
                        'last_position_seconds'
                    ]
                    ?? 0
                )
            );

        $currentWatched =
            max(
                0,
                (int) (
                    $current[
                        'watched_seconds'
                    ]
                    ?? 0
                )
            );

        $advance =
            $positionSeconds
            - $lastPosition;

        if (
            $currentWatched > 0
            || $lastPosition > 0
        ) {
            if (
                $advance <= 0
                || $advance
                    > self::MAX_POSITION_JUMP
            ) {
                $watchedDelta = 0;
            } else {
                $watchedDelta = min(
                    $watchedDelta,
                    $advance + 2
                );
            }
        } else {
            $watchedDelta = min(
                $watchedDelta,
                $positionSeconds + 2
            );
        }

        $watchedSeconds = min(
            $duration,
            $currentWatched
            + $watchedDelta
        );

        $percent = round(
            min(
                100,
                (
                    $watchedSeconds
                    / $duration
                ) * 100
            ),
            2
        );

        $this->progress->recordMetrics(
            $progressId,
            $watchedSeconds,
            $positionSeconds,
            $duration,
            $percent
        );

        $threshold =
            (float) (
                $course[
                    'completion_threshold'
                ]
                ?? 95
            );

        $lessonCompleted = false;

        if ($percent >= $threshold) {
            $this->progress->markCompleted(
                $progressId
            );

            $lessonCompleted = true;
        }

        $courseCompleted = false;

        if ($lessonCompleted) {
            $courseCompleted =
                $this->maybeCompleteCourse(
                    $userId,
                    $enrollment,
                    $course
                );
        }

        $after =
            $this->progress->findById(
                $progressId
            );

        if (!is_array($after)) {
            throw new RuntimeException(
                'lesson_progress_missing'
            );
        }

        return $this->progressResponse(
            $after,
            $lessonCompleted,
            $courseCompleted
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function completeTextLesson(
        int $userId,
        int $enrollmentId,
        int $lessonId
    ): array {
        $enrollment =
            $this->requireEnrollment(
                $userId,
                $enrollmentId
            );

        $course =
            $this->requireCourse(
                (int) $enrollment[
                    'course_id'
                ]
            );

        $lesson =
            $this->requirePublishedLesson(
                (int) $course['id'],
                $lessonId
            );

        if (
            (string) (
                $lesson['lesson_type']
                ?? ''
            ) !== 'text'
        ) {
            throw new RuntimeException(
                'course_text_completion_invalid'
            );
        }

        $this->assertLessonAccessible(
            $enrollment,
            $course,
            $lessonId
        );

        $progressId =
            $this->progress->getOrCreate(
                $enrollmentId,
                $lessonId
            );

        $this->progress->markCompleted(
            $progressId
        );

        $courseCompleted =
            $this->maybeCompleteCourse(
                $userId,
                $enrollment,
                $course
            );

        $row =
            $this->progress->findById(
                $progressId
            );

        if (!is_array($row)) {
            throw new RuntimeException(
                'lesson_progress_missing'
            );
        }

        return $this->progressResponse(
            $row,
            true,
            $courseCompleted
        );
    }

    /**
     * @param list<array<string, mixed>> $curriculum
     * @return array<string, int>
     */
    private function curriculumStats(
        array $curriculum
    ): array {
        $moduleCount = 0;
        $lessonCount = 0;
        $requiredCount = 0;
        $completedCount = 0;
        $videoCount = 0;
        $resourceCount = 0;

        foreach ($curriculum as $module) {
            $moduleLessons =
                (array) (
                    $module['lessons']
                    ?? []
                );

            if ($moduleLessons === []) {
                continue;
            }

            $moduleCount++;

            foreach ($moduleLessons as $lesson) {
                $lessonCount++;

                if (
                    (int) (
                        $lesson['is_required']
                        ?? 0
                    ) === 1
                ) {
                    $requiredCount++;
                }

                if (!empty($lesson['completed'])) {
                    $completedCount++;
                }

                if (
                    in_array(
                        (string) (
                            $lesson['lesson_type']
                            ?? ''
                        ),
                        [
                            'video',
                            'mixed',
                        ],
                        true
                    )
                ) {
                    $videoCount++;
                }

                $resourceCount += count(
                    (array) (
                        $lesson['resources']
                        ?? []
                    )
                );
            }
        }

        return [
            'module_count' =>
                $moduleCount,
            'lesson_count' =>
                $lessonCount,
            'required_count' =>
                $requiredCount,
            'completed_count' =>
                $completedCount,
            'video_count' =>
                $videoCount,
            'resource_count' =>
                $resourceCount,
        ];
    }

    /**
     * @param list<array<string, mixed>> $curriculum
     * @return array<string, array<string, mixed>|null>
     */
    private function lessonNavigation(
        array $curriculum,
        int $currentLessonId
    ): array {
        $flat = [];

        foreach ($curriculum as $module) {
            foreach (
                (array) (
                    $module['lessons']
                    ?? []
                )
                as $lesson
            ) {
                $lessonId =
                    (int) (
                        $lesson['id']
                        ?? 0
                    );

                if ($lessonId <= 0) {
                    continue;
                }

                $flat[] = [
                    'id' =>
                        $lessonId,
                    'title' =>
                        (string) (
                            $lesson['title']
                            ?? ''
                        ),
                    'locked' =>
                        !empty(
                            $lesson['locked']
                        ),
                    'completed' =>
                        !empty(
                            $lesson['completed']
                        ),
                ];
            }
        }

        $currentIndex = null;

        foreach ($flat as $index => $item) {
            if (
                (int) $item['id']
                === $currentLessonId
            ) {
                $currentIndex = $index;
                break;
            }
        }

        if ($currentIndex === null) {
            return [
                'previous' => null,
                'next' => null,
            ];
        }

        return [
            'previous' =>
                $currentIndex > 0
                    ? $flat[
                        $currentIndex - 1
                    ]
                    : null,
            'next' =>
                isset(
                    $flat[
                        $currentIndex + 1
                    ]
                )
                    ? $flat[
                        $currentIndex + 1
                    ]
                    : null,
        ];
    }

    /**
     * @param array<string, mixed> $enrollment
     * @param array<string, mixed> $course
     * @return array<string, mixed>
     */
    private function progressSummary(
        array $enrollment,
        array $course
    ): array {
        $lessonIds =
            $this->completionLessonIds(
                (int) $course['id']
            );

        $progressMap =
            $this->progressMap(
                (int) $enrollment['id']
            );

        $completed = 0;

        foreach ($lessonIds as $lessonId) {
            if (
                isset($progressMap[$lessonId])
                && (string) (
                    $progressMap[
                        $lessonId
                    ]['status']
                    ?? ''
                ) === 'completed'
            ) {
                $completed++;
            }
        }

        $total = count($lessonIds);

        return [
            'total' =>
                $total,
            'completed' =>
                $completed,
            'percent' =>
                $total > 0
                    ? round(
                        (
                            $completed
                            / $total
                        ) * 100,
                        2
                    )
                    : 0.0,
        ];
    }

    /**
     * @param array<string, mixed> $enrollment
     * @param array<string, mixed> $course
     * @return list<array<string, mixed>>
     */
    private function studentCurriculum(
        array $enrollment,
        array $course
    ): array {
        $courseId =
            (int) $course['id'];

        $progressMap =
            $this->progressMap(
                (int) $enrollment['id']
            );

        $sequential =
            (string) (
                $course[
                    'navigation_mode'
                ]
                ?? 'free'
            ) === 'sequential'
            && (string) (
                $enrollment['status']
                ?? ''
            ) !== 'completed';

        $blocked = false;
        $result = [];

        foreach (
            $this->modules->forCourse(
                $courseId
            )
            as $module
        ) {
            if (
                (string) (
                    $module['status']
                    ?? ''
                ) !== 'active'
            ) {
                continue;
            }

            $moduleLessons = [];

            foreach (
                $this->lessons->forModule(
                    (int) $module['id']
                )
                as $lesson
            ) {
                if (
                    (string) (
                        $lesson['status']
                        ?? ''
                    ) !== 'published'
                ) {
                    continue;
                }

                $lessonId =
                    (int) $lesson['id'];

                $lessonProgress =
                    $progressMap[
                        $lessonId
                    ]
                    ?? null;

                $completed =
                    is_array($lessonProgress)
                    && (string) (
                        $lessonProgress[
                            'status'
                        ]
                        ?? ''
                    ) === 'completed';

                $locked =
                    $sequential
                    && $blocked;

                $activeResources = [];

                foreach (
                    $this->resources->forLesson(
                        $lessonId
                    )
                    as $resource
                ) {
                    if (
                        (string) (
                            $resource['status']
                            ?? ''
                        ) === 'active'
                    ) {
                        $activeResources[] =
                            $resource;
                    }
                }

                $lesson['progress'] =
                    $lessonProgress;

                $lesson['completed'] =
                    $completed;

                $lesson['locked'] =
                    $locked;

                $lesson['resources'] =
                    $activeResources;

                $moduleLessons[] =
                    $lesson;

                if (
                    $sequential
                    && (int) (
                        $lesson['is_required']
                        ?? 0
                    ) === 1
                    && !$completed
                ) {
                    $blocked = true;
                }
            }

            if ($moduleLessons === []) {
                continue;
            }

            $module['lessons'] =
                $moduleLessons;

            $result[] = $module;
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $curriculum
     */
    private function firstAccessibleLessonId(
        array $curriculum
    ): int {
        $fallback = 0;

        foreach ($curriculum as $module) {
            foreach (
                (array) (
                    $module['lessons']
                    ?? []
                )
                as $lesson
            ) {
                if (!empty($lesson['locked'])) {
                    continue;
                }

                $lessonId =
                    (int) (
                        $lesson['id']
                        ?? 0
                    );

                if (
                    $fallback <= 0
                    && $lessonId > 0
                ) {
                    $fallback = $lessonId;
                }

                if (
                    $lessonId > 0
                    && empty(
                        $lesson['completed']
                    )
                ) {
                    return $lessonId;
                }
            }
        }

        return $fallback;
    }

    /**
     * @param list<array<string, mixed>> $curriculum
     * @return array<string, mixed>|null
     */
    private function findCurriculumLesson(
        array $curriculum,
        int $lessonId
    ): ?array {
        foreach ($curriculum as $module) {
            foreach (
                (array) (
                    $module['lessons']
                    ?? []
                )
                as $lesson
            ) {
                if (
                    (int) (
                        $lesson['id']
                        ?? 0
                    ) !== $lessonId
                ) {
                    continue;
                }

                $lesson['module_title'] =
                    (string) (
                        $module['title']
                        ?? ''
                    );

                return $lesson;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $enrollment
     * @param array<string, mixed> $course
     */
    private function assertLessonAccessible(
        array $enrollment,
        array $course,
        int $lessonId
    ): void {
        if (
            (string) (
                $course['navigation_mode']
                ?? 'free'
            ) !== 'sequential'
        ) {
            return;
        }

        $lesson =
            $this->findCurriculumLesson(
                $this->studentCurriculum(
                    $enrollment,
                    $course
                ),
                $lessonId
            );

        if (!is_array($lesson)) {
            throw new RuntimeException(
                'course_lesson_missing'
            );
        }

        if (!empty($lesson['locked'])) {
            throw new RuntimeException(
                'course_lesson_locked'
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function progressMap(
        int $enrollmentId
    ): array {
        $result = [];

        foreach (
            $this->progress->forEnrollment(
                $enrollmentId
            )
            as $row
        ) {
            $result[
                (int) $row['lesson_id']
            ] = $row;
        }

        return $result;
    }

    /**
     * @return list<int>
     */
    private function completionLessonIds(
        int $courseId
    ): array {
        $required = [];
        $all = [];

        foreach (
            $this->modules->forCourse(
                $courseId
            )
            as $module
        ) {
            if (
                (string) (
                    $module['status']
                    ?? ''
                ) !== 'active'
            ) {
                continue;
            }

            foreach (
                $this->lessons->forModule(
                    (int) $module['id']
                )
                as $lesson
            ) {
                if (
                    (string) (
                        $lesson['status']
                        ?? ''
                    ) !== 'published'
                ) {
                    continue;
                }

                $lessonId =
                    (int) $lesson['id'];

                $all[] = $lessonId;

                if (
                    (int) (
                        $lesson['is_required']
                        ?? 0
                    ) === 1
                ) {
                    $required[] =
                        $lessonId;
                }
            }
        }

        return $required !== []
            ? $required
            : $all;
    }

    /**
     * @param array<string, mixed> $enrollment
     * @param array<string, mixed> $course
     */
    private function maybeCompleteCourse(
        int $userId,
        array $enrollment,
        array $course
    ): bool {
        $lessonIds =
            $this->completionLessonIds(
                (int) $course['id']
            );

        if ($lessonIds === []) {
            return false;
        }

        $progressMap =
            $this->progressMap(
                (int) $enrollment['id']
            );

        foreach ($lessonIds as $lessonId) {
            if (
                !isset(
                    $progressMap[$lessonId]
                )
                || (string) (
                    $progressMap[
                        $lessonId
                    ]['status']
                    ?? ''
                ) !== 'completed'
            ) {
                return false;
            }
        }

        if (
            (string) (
                $enrollment['status']
                ?? ''
            ) !== 'completed'
        ) {
            $this->enrollments
                ->markCompleted(
                    (int) $enrollment['id']
                );
        }

        if (
            (int) (
                $course[
                    'certificate_enabled'
                ]
                ?? 0
            ) === 1
        ) {
            $user = get_userdata($userId);

            $studentName =
                $user instanceof WP_User
                    ? $user->display_name
                    : 'Aluno';

            $certificateId =
                $this->certificates
                    ->createPending(
                        (int) $enrollment['id'],
                        $studentName,
                        (string) $course['title'],
                        max(
                            0,
                            (int) (
                                $course[
                                    'workload_minutes'
                                ]
                                ?? 0
                            )
                        )
                    );

            do_action(
                'facil_digital_certificate_pending',
                $certificateId
            );
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function requireEnrollment(
        int $userId,
        int $enrollmentId
    ): array {
        if (
            $userId <= 0
            || $enrollmentId <= 0
        ) {
            throw new RuntimeException(
                'course_access_denied'
            );
        }

        $enrollment =
            $this->enrollments->findById(
                $enrollmentId
            );

        if (
            !is_array($enrollment)
            || (int) (
                $enrollment['user_id']
                ?? 0
            ) !== $userId
            || !in_array(
                (string) (
                    $enrollment['status']
                    ?? ''
                ),
                [
                    'active',
                    'completed',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'course_access_denied'
            );
        }

        $expiresAt =
            (string) (
                $enrollment['expires_at']
                ?? ''
            );

        if (
            $expiresAt !== ''
            && strtotime(
                $expiresAt . ' UTC'
            ) < time()
        ) {
            throw new RuntimeException(
                'course_access_expired'
            );
        }

        return $enrollment;
    }

    /**
     * @return array<string, mixed>
     */
    private function requireCourse(
        int $courseId
    ): array {
        $course =
            $this->courses->findById(
                $courseId
            );

        if (!is_array($course)) {
            throw new RuntimeException(
                'course_missing'
            );
        }

        return $course;
    }

    /**
     * @return array<string, mixed>
     */
    private function requirePublishedLesson(
        int $courseId,
        int $lessonId
    ): array {
        $lesson =
            $this->lessons->findById(
                $lessonId
            );

        if (
            !is_array($lesson)
            || (int) (
                $lesson['course_id']
                ?? 0
            ) !== $courseId
            || (string) (
                $lesson['status']
                ?? ''
            ) !== 'published'
        ) {
            throw new RuntimeException(
                'course_lesson_missing'
            );
        }

        return $lesson;
    }

    /**
     * @param array<string, mixed> $progress
     * @return array<string, mixed>
     */
    private function progressResponse(
        array $progress,
        bool $lessonCompleted,
        bool $courseCompleted
    ): array {
        return [
            'progress_id' =>
                (int) (
                    $progress['id']
                    ?? 0
                ),
            'percent' =>
                (float) (
                    $progress[
                        'completion_percent'
                    ]
                    ?? 0
                ),
            'watched_seconds' =>
                (int) (
                    $progress[
                        'watched_seconds'
                    ]
                    ?? 0
                ),
            'lesson_completed' =>
                $lessonCompleted
                || (string) (
                    $progress['status']
                    ?? ''
                ) === 'completed',
            'course_completed' =>
                $courseCompleted,
        ];
    }
}