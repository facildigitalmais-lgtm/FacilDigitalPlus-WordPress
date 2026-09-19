<?php

declare(strict_types=1);

namespace FacilDigital\Core\Courses;

use FacilDigital\Core\Core\Database;
use RuntimeException;
use WC_Product;

final class CourseBuilderService
{
    public function __construct(
        private readonly CourseRepository $courses =
            new CourseRepository(),
        private readonly CourseProductService $products =
            new CourseProductService(),
        private readonly CourseModuleRepository $modules =
            new CourseModuleRepository(),
        private readonly LessonRepository $lessons =
            new LessonRepository(),
        private readonly LessonResourceRepository $resources =
            new LessonResourceRepository()
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listCourses(): array
    {
        global $wpdb;

        $table = Database::table('courses');

        $rows = $wpdb->get_results(
            "SELECT * FROM {$table}
             ORDER BY updated_at DESC, id DESC",
            ARRAY_A
        );

        return is_array($rows)
            ? $rows
            : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function editorState(int $courseId): array
    {
        $course = $this->courses->findById(
            $courseId
        );

        if (!is_array($course)) {
            throw new RuntimeException(
                'course_builder_course_missing'
            );
        }

        return [
            'course' =>
                $course,
            'product' =>
                $this->products->productForCourse(
                    $courseId
                ),
            'curriculum' =>
                $this->curriculum(
                    $courseId
                ),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function saveCourse(
        int $courseId,
        array $payload
    ): int {
        $title = sanitize_text_field(
            (string) (
                $payload['title']
                ?? ''
            )
        );

        $slugInput = trim(
            (string) (
                $payload['slug']
                ?? ''
            )
        );

        $slug = sanitize_title(
            $slugInput !== ''
                ? $slugInput
                : $title
        );

        if ($title === '' || $slug === '') {
            throw new RuntimeException(
                'course_builder_identity_invalid'
            );
        }

        $status = $this->normalizeCourseStatus(
            (string) (
                $payload['status']
                ?? 'draft'
            )
        );

        $navigationMode =
            sanitize_key(
                (string) (
                    $payload['navigation_mode']
                    ?? 'free'
                )
            );

        if (
            !in_array(
                $navigationMode,
                ['free', 'sequential'],
                true
            )
        ) {
            throw new RuntimeException(
                'course_navigation_mode_invalid'
            );
        }

        $threshold = (float) (
            $payload['completion_threshold']
            ?? 95
        );

        if (
            $threshold <= 0
            || $threshold > 100
        ) {
            throw new RuntimeException(
                'course_completion_threshold_invalid'
            );
        }

        $introVideoId = $this->youtubeVideoId(
            (string) (
                $payload['intro_youtube_video_id']
                ?? ''
            ),
            'course_intro_youtube_invalid'
        );

        $imageId = max(
            0,
            (int) (
                $payload['image_id']
                ?? 0
            )
        );

        if (
            $courseId > 0
            && !array_key_exists('image_id', $payload)
        ) {
            $linkedProduct =
                $this->products->productForCourse(
                    $courseId
                );

            $imageId =
                $linkedProduct instanceof WC_Product
                    ? (int) $linkedProduct->get_image_id()
                    : 0;
        }

        if (
            $imageId > 0
            && !wp_attachment_is_image(
                $imageId
            )
        ) {
            throw new RuntimeException(
                'course_cover_invalid'
            );
        }

        $existingBySlug =
            $this->courses->findBySlug(
                $slug
            );

        if (
            is_array($existingBySlug)
            && (int) (
                $existingBySlug['id']
                ?? 0
            ) !== $courseId
        ) {
            throw new RuntimeException(
                'course_builder_slug_duplicate'
            );
        }

        $courseData = [
            'short_description' =>
                (string) (
                    $payload[
                        'short_description'
                    ]
                    ?? ''
                ),
            'description' =>
                (string) (
                    $payload['description']
                    ?? ''
                ),
            'intro_youtube_video_id' =>
                $introVideoId,
            'workload_minutes' =>
                max(
                    0,
                    (int) (
                        $payload[
                            'workload_minutes'
                        ]
                        ?? 0
                    )
                ),
            'completion_threshold' =>
                $threshold,
            'navigation_mode' =>
                $navigationMode,
            'certificate_enabled' =>
                !empty(
                    $payload[
                        'certificate_enabled'
                    ]
                ),
            'status' =>
                $status,
        ];

        $productData = [
            'image_id' =>
                $imageId,
            'status' =>
                $this->productStatusForCourse(
                    $status
                ),
            'catalog_visibility' =>
                (string) (
                    $payload[
                        'catalog_visibility'
                    ]
                    ?? 'visible'
                ),
            'description' =>
                (string) (
                    $payload[
                        'product_description'
                    ]
                    ?? ''
                ),
            'short_description' =>
                (string) (
                    $payload[
                        'product_short_description'
                    ]
                    ?? ''
                ),
        ];

        if ($courseId <= 0) {
            $courseData['created_by'] =
                get_current_user_id();

            $created =
                $this->products
                    ->createCourseProduct(
                        $title,
                        $slug,
                        $payload[
                            'regular_price'
                        ] ?? '0',
                        $courseData,
                        $productData
                    );

            return (int) (
                $created['course_id']
                ?? 0
            );
        }

        $current =
            $this->courses->findById(
                $courseId
            );

        if (!is_array($current)) {
            throw new RuntimeException(
                'course_builder_course_missing'
            );
        }

        $this->products
            ->updateProductForCourse(
                $courseId,
                [
                    'name' =>
                        $title,
                    'regular_price' =>
                        $payload[
                            'regular_price'
                        ] ?? '0',
                    'image_id' =>
                        $imageId,
                    'status' =>
                        $productData['status'],
                    'catalog_visibility' =>
                        $productData[
                            'catalog_visibility'
                        ],
                    'description' =>
                        $productData[
                            'description'
                        ],
                    'short_description' =>
                        $productData[
                            'short_description'
                        ],
                ]
            );

        $this->courses->update(
            $courseId,
            array_merge(
                $courseData,
                [
                    'title' =>
                        $title,
                    'slug' =>
                        $slug,
                ]
            )
        );

        return $courseId;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function saveModule(
        int $courseId,
        int $moduleId,
        array $payload
    ): int {
        $this->requireCourse(
            $courseId
        );

        $title = sanitize_text_field(
            (string) (
                $payload['title']
                ?? ''
            )
        );

        if ($title === '') {
            throw new RuntimeException(
                'course_module_title_invalid'
            );
        }

        if ($moduleId <= 0) {
            return $this->modules->create(
                $courseId,
                $title,
                (string) (
                    $payload['description']
                    ?? ''
                ),
                max(
                    0,
                    (int) (
                        $payload['sort_order']
                        ?? 0
                    )
                )
            );
        }

        $module =
            $this->modules->findById(
                $moduleId
            );

        if (
            !is_array($module)
            || (int) (
                $module['course_id']
                ?? 0
            ) !== $courseId
        ) {
            throw new RuntimeException(
                'course_module_course_invalid'
            );
        }

        $this->modules->update(
            $moduleId,
            [
                'title' =>
                    $title,
                'description' =>
                    (string) (
                        $payload['description']
                        ?? ''
                    ),
                'sort_order' =>
                    max(
                        0,
                        (int) (
                            $payload[
                                'sort_order'
                            ]
                            ?? 0
                        )
                    ),
                'status' =>
                    sanitize_key(
                        (string) (
                            $payload['status']
                            ?? 'active'
                        )
                    ),
            ]
        );

        return $moduleId;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function saveLesson(
        int $courseId,
        int $moduleId,
        int $lessonId,
        array $payload
    ): int {
        $this->requireCourse(
            $courseId
        );

        $module =
            $this->modules->findById(
                $moduleId
            );

        if (
            !is_array($module)
            || (int) (
                $module['course_id']
                ?? 0
            ) !== $courseId
        ) {
            throw new RuntimeException(
                'lesson_module_invalid'
            );
        }

        $title = sanitize_text_field(
            (string) (
                $payload['title']
                ?? ''
            )
        );

        $slug = sanitize_title(
            (string) (
                $payload['slug']
                ?? $title
            )
        );

        $type = sanitize_key(
            (string) (
                $payload['lesson_type']
                ?? 'text'
            )
        );

        if (
            !in_array(
                $type,
                ['text', 'video', 'mixed'],
                true
            )
        ) {
            throw new RuntimeException(
                'lesson_type_invalid'
            );
        }

        $videoId = $this->youtubeVideoId(
            (string) (
                $payload[
                    'youtube_video_id'
                ]
                ?? ''
            )
        );

        if (
            in_array(
                $type,
                ['video', 'mixed'],
                true
            )
            && $videoId === null
        ) {
            throw new RuntimeException(
                'course_lesson_video_required'
            );
        }

        if ($type === 'text') {
            $videoId = null;
        }

        $data = [
            'lesson_type' =>
                $type,
            'content' =>
                (string) (
                    $payload['content']
                    ?? ''
                ),
            'transcript' =>
                (string) (
                    $payload['transcript']
                    ?? ''
                ),
            'youtube_video_id' =>
                $videoId,
            'duration_seconds' =>
                max(
                    0,
                    (int) (
                        $payload[
                            'duration_seconds'
                        ]
                        ?? 0
                    )
                ),
            'is_required' =>
                !empty(
                    $payload['is_required']
                ),
            'sort_order' =>
                max(
                    0,
                    (int) (
                        $payload['sort_order']
                        ?? 0
                    )
                ),
            'status' =>
                sanitize_key(
                    (string) (
                        $payload['status']
                        ?? 'draft'
                    )
                ),
        ];

        if ($lessonId <= 0) {
            return $this->lessons->create(
                $courseId,
                $moduleId,
                $title,
                $slug,
                $data
            );
        }

        $current =
            $this->lessons->findById(
                $lessonId
            );

        if (
            !is_array($current)
            || (int) (
                $current['course_id']
                ?? 0
            ) !== $courseId
        ) {
            throw new RuntimeException(
                'lesson_missing'
            );
        }

        if (
            (int) (
                $current['module_id']
                ?? 0
            ) !== $moduleId
        ) {
            $this->lessons->moveToModule(
                $lessonId,
                $courseId,
                $moduleId
            );
        }

        $this->lessons->update(
            $lessonId,
            array_merge(
                $data,
                [
                    'title' =>
                        $title,
                    'slug' =>
                        $slug,
                ]
            )
        );

        return $lessonId;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function saveResource(
        int $courseId,
        int $lessonId,
        int $resourceId,
        array $payload
    ): int {
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
        ) {
            throw new RuntimeException(
                'lesson_resource_lesson_invalid'
            );
        }

        if ($resourceId <= 0) {
            return $this->resources->create(
                $courseId,
                $lessonId,
                (string) (
                    $payload['title']
                    ?? ''
                ),
                (string) (
                    $payload['storage_key']
                    ?? ''
                ),
                (string) (
                    $payload[
                        'original_filename'
                    ]
                    ?? ''
                ),
                (string) (
                    $payload['mime_type']
                    ?? ''
                ),
                [
                    'file_size' =>
                        isset(
                            $payload[
                                'file_size'
                            ]
                        )
                            ? (int) $payload[
                                'file_size'
                            ]
                            : null,
                    'sha256' =>
                        (string) (
                            $payload['sha256']
                            ?? ''
                        ),
                    'sort_order' =>
                        max(
                            0,
                            (int) (
                                $payload[
                                    'sort_order'
                                ]
                                ?? 0
                            )
                        ),
                    'status' =>
                        sanitize_key(
                            (string) (
                                $payload['status']
                                ?? 'active'
                            )
                        ),
                ]
            );
        }

        $resource =
            $this->resources->findById(
                $resourceId
            );

        if (
            !is_array($resource)
            || (int) (
                $resource['course_id']
                ?? 0
            ) !== $courseId
            || (int) (
                $resource['lesson_id']
                ?? 0
            ) !== $lessonId
        ) {
            throw new RuntimeException(
                'lesson_resource_missing'
            );
        }

        $this->resources->update(
            $resourceId,
            $payload
        );

        return $resourceId;
    }

    public function deleteModule(
        int $courseId,
        int $moduleId
    ): void {
        $module =
            $this->modules->findById(
                $moduleId
            );

        if (
            !is_array($module)
            || (int) (
                $module['course_id']
                ?? 0
            ) !== $courseId
        ) {
            throw new RuntimeException(
                'course_module_course_invalid'
            );
        }

        if (
            $this->lessons->forModule(
                $moduleId
            ) !== []
        ) {
            throw new RuntimeException(
                'course_module_not_empty'
            );
        }

        $this->modules->delete(
            $moduleId
        );
    }

    public function deleteLesson(
        int $courseId,
        int $lessonId
    ): void {
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
        ) {
            throw new RuntimeException(
                'lesson_missing'
            );
        }

        if (
            $this->resources->forLesson(
                $lessonId
            ) !== []
        ) {
            throw new RuntimeException(
                'course_lesson_has_resources'
            );
        }

        $this->lessons->delete(
            $lessonId
        );
    }

    public function deleteResource(
        int $courseId,
        int $resourceId
    ): void {
        $resource =
            $this->resources->findById(
                $resourceId
            );

        if (
            !is_array($resource)
            || (int) (
                $resource['course_id']
                ?? 0
            ) !== $courseId
        ) {
            throw new RuntimeException(
                'lesson_resource_missing'
            );
        }

        $this->resources->delete(
            $resourceId
        );
    }

    /**
     * @param list<int> $moduleOrder
     * @param array<int, list<int>> $lessonOrder
     */
    public function reorder(
        int $courseId,
        array $moduleOrder,
        array $lessonOrder
    ): void {
        $this->requireCourse(
            $courseId
        );

        foreach (
            array_values($moduleOrder)
            as $index => $moduleId
        ) {
            $module =
                $this->modules->findById(
                    (int) $moduleId
                );

            if (
                !is_array($module)
                || (int) (
                    $module['course_id']
                    ?? 0
                ) !== $courseId
            ) {
                throw new RuntimeException(
                    'course_reorder_module_invalid'
                );
            }

            $this->modules->update(
                (int) $moduleId,
                [
                    'sort_order' =>
                        ($index + 1) * 10,
                ]
            );
        }

        foreach (
            $lessonOrder
            as $moduleId => $lessonIds
        ) {
            $module =
                $this->modules->findById(
                    (int) $moduleId
                );

            if (
                !is_array($module)
                || (int) (
                    $module['course_id']
                    ?? 0
                ) !== $courseId
            ) {
                throw new RuntimeException(
                    'course_reorder_module_invalid'
                );
            }

            foreach (
                array_values($lessonIds)
                as $index => $lessonId
            ) {
                $lesson =
                    $this->lessons->findById(
                        (int) $lessonId
                    );

                if (
                    !is_array($lesson)
                    || (int) (
                        $lesson['course_id']
                        ?? 0
                    ) !== $courseId
                ) {
                    throw new RuntimeException(
                        'course_reorder_lesson_invalid'
                    );
                }

                if (
                    (int) (
                        $lesson['module_id']
                        ?? 0
                    ) !== (int) $moduleId
                ) {
                    $this->lessons->moveToModule(
                        (int) $lessonId,
                        $courseId,
                        (int) $moduleId
                    );
                }

                $this->lessons->update(
                    (int) $lessonId,
                    [
                        'sort_order' =>
                            ($index + 1) * 10,
                    ]
                );
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function curriculum(
        int $courseId
    ): array {
        $result = [];

        foreach (
            $this->modules->forCourse(
                $courseId
            )
            as $module
        ) {
            $moduleId =
                (int) (
                    $module['id']
                    ?? 0
                );

            $lessonRows = [];

            foreach (
                $this->lessons->forModule(
                    $moduleId
                )
                as $lesson
            ) {
                $lesson['resources'] =
                    $this->resources
                        ->forLesson(
                            (int) (
                                $lesson['id']
                                ?? 0
                            )
                        );

                $lessonRows[] =
                    $lesson;
            }

            $module['lessons'] =
                $lessonRows;

            $result[] = $module;
        }

        return $result;
    }

    private function requireCourse(
        int $courseId
    ): array {
        $course =
            $this->courses->findById(
                $courseId
            );

        if (!is_array($course)) {
            throw new RuntimeException(
                'course_builder_course_missing'
            );
        }

        return $course;
    }

    private function normalizeCourseStatus(
        string $status
    ): string {
        $status = sanitize_key($status);

        if (
            !in_array(
                $status,
                [
                    'draft',
                    'published',
                    'archived',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'course_status_invalid'
            );
        }

        return $status;
    }

    private function productStatusForCourse(
        string $status
    ): string {
        return match ($status) {
            'published' => 'publish',
            'archived' => 'private',
            default => 'draft',
        };
    }

    private function youtubeVideoId(
        string $value,
        string $invalidCode = 'course_lesson_youtube_invalid'
    ): ?string {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (
            preg_match(
                '/^[A-Za-z0-9_-]{11}$/',
                $value
            )
        ) {
            return $value;
        }

        $patterns = [
            '~youtu\.be/([A-Za-z0-9_-]{11})~',
            '~[?&]v=([A-Za-z0-9_-]{11})~',
            '~youtube\.com/embed/([A-Za-z0-9_-]{11})~',
            '~youtube\.com/shorts/([A-Za-z0-9_-]{11})~',
        ];

        foreach ($patterns as $pattern) {
            if (
                preg_match(
                    $pattern,
                    $value,
                    $matches
                )
            ) {
                return (string) $matches[1];
            }
        }

        throw new RuntimeException(
            $invalidCode
        );
    }
}