<?php

declare(strict_types=1);

namespace FacilDigital\Core\Courses;

use FacilDigital\Core\Contracts\ModuleInterface;
use RuntimeException;
use Throwable;

final class CourseAccountModule implements ModuleInterface
{
    public const COURSES_ENDPOINT = 'cursos';
    public const CLASSROOM_ENDPOINT = 'curso';
    public const CERTIFICATES_ENDPOINT = 'certificados';

    private const REWRITE_OPTION =
        'facil_digital_courses_account_rewrite_version';

    private const REWRITE_VERSION = '1.0.0';

    public function __construct(
        private readonly CourseLearningService $learning =
            new CourseLearningService()
    ) {
    }

    public function register(): void
    {
        add_filter(
            'woocommerce_get_query_vars',
            [$this, 'queryVars']
        );

        add_action(
            'init',
            [$this, 'registerEndpoints'],
            20
        );

        add_action(
            'wp_loaded',
            [$this, 'maybeFlushRewriteRules'],
            65
        );

        add_filter(
            'woocommerce_account_menu_items',
            [$this, 'menuItems'],
            35
        );

        add_action(
            'woocommerce_account_'
            . self::COURSES_ENDPOINT
            . '_endpoint',
            [$this, 'renderCourses']
        );

        add_action(
            'woocommerce_account_'
            . self::CLASSROOM_ENDPOINT
            . '_endpoint',
            [$this, 'renderClassroom']
        );

        add_action(
            'woocommerce_account_'
            . self::CERTIFICATES_ENDPOINT
            . '_endpoint',
            [$this, 'renderCertificates']
        );

        add_action(
            'wp_enqueue_scripts',
            [$this, 'enqueue']
        );

        add_action(
            'wp_ajax_fd_course_progress',
            [$this, 'ajaxProgress']
        );

        add_action(
            'wp_ajax_fd_course_complete_text',
            [$this, 'ajaxCompleteText']
        );
    }

    /**
     * @param array<string, string> $vars
     * @return array<string, string>
     */
    public function queryVars(array $vars): array
    {
        $vars[self::COURSES_ENDPOINT] =
            self::COURSES_ENDPOINT;

        $vars[self::CLASSROOM_ENDPOINT] =
            self::CLASSROOM_ENDPOINT;

        $vars[
            self::CERTIFICATES_ENDPOINT
        ] = self::CERTIFICATES_ENDPOINT;

        return $vars;
    }

    public function registerEndpoints(): void
    {
        foreach (
            [
                self::COURSES_ENDPOINT,
                self::CLASSROOM_ENDPOINT,
                self::CERTIFICATES_ENDPOINT,
            ]
            as $endpoint
        ) {
            add_rewrite_endpoint(
                $endpoint,
                EP_ROOT | EP_PAGES
            );
        }
    }

    public function maybeFlushRewriteRules(): void
    {
        if (
            get_option(
                self::REWRITE_OPTION,
                ''
            ) === self::REWRITE_VERSION
        ) {
            return;
        }

        flush_rewrite_rules(false);

        update_option(
            self::REWRITE_OPTION,
            self::REWRITE_VERSION,
            false
        );
    }

    /**
     * @param array<string, string> $items
     * @return array<string, string>
     */
    public function menuItems(array $items): array
    {
        $result = [];

        foreach ($items as $key => $label) {
            $result[$key] = $label;

            if ($key !== 'dashboard') {
                continue;
            }

            $result[
                self::COURSES_ENDPOINT
            ] = __(
                'Cursos',
                'facil-digital-core'
            );

            $result[
                self::CERTIFICATES_ENDPOINT
            ] = __(
                'Certificados',
                'facil-digital-core'
            );
        }

        if (
            !isset(
                $result[
                    self::COURSES_ENDPOINT
                ]
            )
        ) {
            $result[
                self::COURSES_ENDPOINT
            ] = __(
                'Cursos',
                'facil-digital-core'
            );
        }

        if (
            !isset(
                $result[
                    self::CERTIFICATES_ENDPOINT
                ]
            )
        ) {
            $result[
                self::CERTIFICATES_ENDPOINT
            ] = __(
                'Certificados',
                'facil-digital-core'
            );
        }

        return $result;
    }

    public function enqueue(): void
    {
        if (
            !function_exists(
                'is_account_page'
            )
            || !is_account_page()
            || !function_exists('WC')
            || !WC()
            || !WC()->query
        ) {
            return;
        }

        $endpoint =
            WC()->query->get_current_endpoint();

        if (
            !in_array(
                $endpoint,
                [
                    self::COURSES_ENDPOINT,
                    self::CLASSROOM_ENDPOINT,
                    self::CERTIFICATES_ENDPOINT,
                ],
                true
            )
        ) {
            return;
        }

        wp_enqueue_style(
            'fd-courses-frontend',
            plugins_url(
                'assets/frontend/courses.css',
                FACIL_DIGITAL_CORE_FILE
            ),
            [],
            FACIL_DIGITAL_CORE_VERSION
        );

        if (
            $endpoint
            !== self::CLASSROOM_ENDPOINT
        ) {
            return;
        }

        wp_enqueue_script(
            'fd-courses-learning',
            plugins_url(
                'assets/frontend/courses.js',
                FACIL_DIGITAL_CORE_FILE
            ),
            [],
            FACIL_DIGITAL_CORE_VERSION,
            true
        );

        wp_localize_script(
            'fd-courses-learning',
            'fdCoursesLearning',
            [
                'ajaxUrl' =>
                    admin_url(
                        'admin-ajax.php'
                    ),
                'nonce' =>
                    wp_create_nonce(
                        'fd_course_learning'
                    ),
                'heartbeatMs' =>
                    10000,
            ]
        );
    }

    public function renderCourses(): void
    {
        $userId = get_current_user_id();

        if ($userId <= 0) {
            return;
        }

        $rows =
            $this->learning->userCourses(
                $userId
            );

        echo '<section class="fd-course-library">';
        echo '<div class="fd-course-library__heading">';
        echo '<div>';
        echo '<h2>';
        echo esc_html__(
            'Meus cursos',
            'facil-digital-core'
        );
        echo '</h2>';
        echo '<p>';
        echo esc_html__(
            'Continue seus estudos de onde parou.',
            'facil-digital-core'
        );
        echo '</p>';
        echo '</div>';
        echo '</div>';

        if ($rows === []) {
            echo '<div class="woocommerce-info">';
            echo esc_html__(
                'Você ainda não possui cursos liberados.',
                'facil-digital-core'
            );
            echo '</div>';
            echo '</section>';
            return;
        }

        echo '<div class="fd-course-library__grid">';

        foreach ($rows as $row) {
            $course =
                (array) $row['course'];

            $enrollment =
                (array) $row['enrollment'];

            $summary =
                (array) $row['progress'];

            $enrollmentId =
                (int) $enrollment['id'];

            $lessonId =
                (int) (
                    $row[
                        'continue_lesson_id'
                    ]
                    ?? 0
                );

            $percent =
                (float) (
                    $summary['percent']
                    ?? 0
                );

            echo '<article class="fd-course-library-card">';

            echo '<div class="fd-course-library-card__body">';

            echo '<span class="fd-course-badge">';
            echo (
                (string) (
                    $enrollment['status']
                    ?? ''
                ) === 'completed'
            )
                ? esc_html__(
                    'Concluído',
                    'facil-digital-core'
                )
                : esc_html__(
                    'Em andamento',
                    'facil-digital-core'
                );
            echo '</span>';

            echo '<h3>';
            echo esc_html(
                (string) (
                    $course['title']
                    ?? ''
                )
            );
            echo '</h3>';

            echo '<p>';
            echo esc_html(
                (string) (
                    $course[
                        'short_description'
                    ]
                    ?? ''
                )
            );
            echo '</p>';

            echo '<div class="fd-course-progress">';
            echo '<div class="fd-course-progress__bar">';
            echo '<span style="width:';
            echo esc_attr(
                (string) $percent
            );
            echo '%"></span>';
            echo '</div>';
            echo '<div class="fd-course-progress__text">';
            echo esc_html(
                number_format(
                    $percent,
                    0,
                    ',',
                    '.'
                )
                . '% concluído'
            );
            echo '</div>';
            echo '</div>';

            if ($lessonId > 0) {
                echo '<a class="button alt fd-course-primary-action" href="';
                echo esc_url(
                    $this->classroomUrl(
                        $enrollmentId,
                        $lessonId
                    )
                );
                echo '">';
                echo (
                    (string) (
                        $enrollment['status']
                        ?? ''
                    ) === 'completed'
                )
                    ? esc_html__(
                        'Rever curso',
                        'facil-digital-core'
                    )
                    : esc_html__(
                        'Continuar curso',
                        'facil-digital-core'
                    );
                echo '</a>';
            } else {
                echo '<span class="fd-course-empty-action">';
                echo esc_html__(
                    'Nenhuma aula publicada.',
                    'facil-digital-core'
                );
                echo '</span>';
            }

            echo '</div>';
            echo '</article>';
        }

        echo '</div>';
        echo '</section>';
    }

    public function renderClassroom(
        string $value = ''
    ): void {
        $userId = get_current_user_id();

        if ($userId <= 0) {
            return;
        }

        $enrollmentId = absint($value);

        if ($enrollmentId <= 0) {
            $enrollmentId = absint(
                get_query_var(
                    self::CLASSROOM_ENDPOINT
                )
            );
        }

        $lessonId = absint(
            $_GET['aula']
            ?? 0
        );

        try {
            $state =
                $this->learning->classroom(
                    $userId,
                    $enrollmentId,
                    $lessonId
                );
        } catch (RuntimeException) {
            echo '<div class="woocommerce-error">';
            echo esc_html__(
                'Este curso ou aula não está disponível para sua conta.',
                'facil-digital-core'
            );
            echo '</div>';
            return;
        }

        $course =
            (array) $state['course'];

        $lesson =
            (array) $state['lesson'];

        $curriculum =
            (array) $state['curriculum'];

        $summary =
            (array) $state['progress'];

        $lessonId =
            (int) $lesson['id'];

        $lessonType =
            (string) (
                $lesson['lesson_type']
                ?? 'text'
            );

        $lessonCompleted =
            !empty(
                $lesson['completed']
            );

        echo '<div class="fd-course-classroom"';
        echo ' data-enrollment-id="';
        echo esc_attr(
            (string) $enrollmentId
        );
        echo '" data-lesson-id="';
        echo esc_attr(
            (string) $lessonId
        );
        echo '">';

        echo '<main class="fd-course-classroom__main">';

        echo '<div class="fd-course-classroom__top">';
        echo '<a href="';
        echo esc_url(
            wc_get_account_endpoint_url(
                self::COURSES_ENDPOINT
            )
        );
        echo '">';
        echo esc_html__(
            '← Meus cursos',
            'facil-digital-core'
        );
        echo '</a>';

        echo '<div class="fd-course-classroom__course-progress">';
        echo esc_html(
            number_format(
                (float) (
                    $summary['percent']
                    ?? 0
                ),
                0,
                ',',
                '.'
            )
            . '% do curso'
        );
        echo '</div>';
        echo '</div>';

        if (
            in_array(
                $lessonType,
                [
                    'video',
                    'mixed',
                ],
                true
            )
            && !empty(
                $lesson[
                    'youtube_video_id'
                ]
            )
        ) {
            echo '<div class="fd-course-video">';
            echo '<div id="fd-course-player"';
            echo ' data-video-id="';
            echo esc_attr(
                (string) $lesson[
                    'youtube_video_id'
                ]
            );
            echo '"></div>';
            echo '</div>';
        }

        echo '<article class="fd-course-lesson-content">';

        echo '<div class="fd-course-lesson-content__heading">';
        echo '<div>';
        echo '<span>';
        echo esc_html(
            (string) (
                $lesson['module_title']
                ?? ''
            )
        );
        echo '</span>';
        echo '<h1>';
        echo esc_html(
            (string) (
                $lesson['title']
                ?? ''
            )
        );
        echo '</h1>';
        echo '</div>';

        if ($lessonCompleted) {
            echo '<span class="fd-course-completed">';
            echo esc_html__(
                '✓ Aula concluída',
                'facil-digital-core'
            );
            echo '</span>';
        }

        echo '</div>';

        if (
            !empty(
                $lesson['content']
            )
        ) {
            echo '<div class="fd-course-lesson-content__body">';
            echo wp_kses_post(
                (string) $lesson['content']
            );
            echo '</div>';
        }

        if (
            $lessonType === 'text'
            && !$lessonCompleted
        ) {
            echo '<button type="button"';
            echo ' class="button alt fd-course-complete-text"';
            echo ' data-enrollment-id="';
            echo esc_attr(
                (string) $enrollmentId
            );
            echo '" data-lesson-id="';
            echo esc_attr(
                (string) $lessonId
            );
            echo '">';
            echo esc_html__(
                'Marcar aula como concluída',
                'facil-digital-core'
            );
            echo '</button>';
        }

        if (
            !empty(
                $lesson['transcript']
            )
        ) {
            echo '<section class="fd-course-transcript">';
            echo '<h2>';
            echo esc_html__(
                'Transcrição da aula',
                'facil-digital-core'
            );
            echo '</h2>';
            echo '<div>';
            echo wp_kses_post(
                (string) $lesson[
                    'transcript'
                ]
            );
            echo '</div>';
            echo '</section>';
        }

        $resources =
            (array) (
                $lesson['resources']
                ?? []
            );

        if ($resources !== []) {
            echo '<section class="fd-course-resources">';
            echo '<h2>';
            echo esc_html__(
                'Recursos desta aula',
                'facil-digital-core'
            );
            echo '</h2>';
            echo '<ul>';

            foreach ($resources as $resource) {
                echo '<li>';
                echo '<strong>';
                echo esc_html(
                    (string) (
                        $resource['title']
                        ?? ''
                    )
                );
                echo '</strong>';
                echo '<span>';
                echo esc_html(
                    (string) (
                        $resource[
                            'original_filename'
                        ]
                        ?? ''
                    )
                );
                echo '</span>';
                echo '</li>';
            }

            echo '</ul>';
            echo '</section>';
        }

        echo '</article>';
        echo '</main>';

        echo '<aside class="fd-course-classroom__sidebar">';
        echo '<div class="fd-course-sidebar__heading">';
        echo '<h2>';
        echo esc_html(
            (string) (
                $course['title']
                ?? ''
            )
        );
        echo '</h2>';
        echo '</div>';

        foreach ($curriculum as $module) {
            echo '<section class="fd-course-sidebar-module">';
            echo '<h3>';
            echo esc_html(
                (string) (
                    $module['title']
                    ?? ''
                )
            );
            echo '</h3>';

            echo '<div class="fd-course-sidebar-lessons">';

            foreach (
                (array) (
                    $module['lessons']
                    ?? []
                )
                as $item
            ) {
                $itemId =
                    (int) (
                        $item['id']
                        ?? 0
                    );

                $classes = [
                    'fd-course-sidebar-lesson',
                ];

                if (
                    $itemId === $lessonId
                ) {
                    $classes[] = 'is-current';
                }

                if (!empty($item['completed'])) {
                    $classes[] = 'is-completed';
                }

                if (!empty($item['locked'])) {
                    $classes[] = 'is-locked';
                }

                $class =
                    implode(' ', $classes);

                if (!empty($item['locked'])) {
                    echo '<div class="';
                    echo esc_attr($class);
                    echo '">';
                } else {
                    echo '<a class="';
                    echo esc_attr($class);
                    echo '" href="';
                    echo esc_url(
                        $this->classroomUrl(
                            $enrollmentId,
                            $itemId
                        )
                    );
                    echo '">';
                }

                echo '<span class="fd-course-sidebar-lesson__icon">';

                if (!empty($item['completed'])) {
                    echo '✓';
                } elseif (!empty($item['locked'])) {
                    echo '🔒';
                } elseif (
                    in_array(
                        (string) (
                            $item['lesson_type']
                            ?? ''
                        ),
                        [
                            'video',
                            'mixed',
                        ],
                        true
                    )
                ) {
                    echo '▶';
                } else {
                    echo '●';
                }

                echo '</span>';

                echo '<span class="fd-course-sidebar-lesson__title">';
                echo esc_html(
                    (string) (
                        $item['title']
                        ?? ''
                    )
                );
                echo '</span>';

                echo '<span class="fd-course-sidebar-lesson__progress"';
                echo ' data-lesson-progress="';
                echo esc_attr(
                    (string) $itemId
                );
                echo '">';

                $itemProgress =
                    (array) (
                        $item['progress']
                        ?? []
                    );

                echo esc_html(
                    number_format(
                        (float) (
                            $itemProgress[
                                'completion_percent'
                            ]
                            ?? 0
                        ),
                        0,
                        ',',
                        '.'
                    )
                    . '%'
                );

                echo '</span>';

                if (!empty($item['locked'])) {
                    echo '</div>';
                } else {
                    echo '</a>';
                }
            }

            echo '</div>';
            echo '</section>';
        }

        echo '</aside>';
        echo '</div>';
    }

    public function renderCertificates(): void
    {
        $userId = get_current_user_id();

        if ($userId <= 0) {
            return;
        }

        $rows =
            $this->learning
                ->certificatesForUser(
                    $userId
                );

        echo '<section class="fd-certificates">';
        echo '<h2>';
        echo esc_html__(
            'Meus certificados',
            'facil-digital-core'
        );
        echo '</h2>';

        if ($rows === []) {
            echo '<div class="woocommerce-info">';
            echo esc_html__(
                'Você ainda não possui certificados emitidos.',
                'facil-digital-core'
            );
            echo '</div>';
            echo '</section>';
            return;
        }

        echo '<div class="fd-certificates__grid">';

        foreach ($rows as $row) {
            $status =
                (string) (
                    $row['status']
                    ?? 'pending'
                );

            echo '<article class="fd-certificate-card">';

            echo '<span class="fd-course-badge">';
            echo esc_html(
                match ($status) {
                    'ready' =>
                        __(
                            'Disponível',
                            'facil-digital-core'
                        ),
                    'failed' =>
                        __(
                            'Em revisão',
                            'facil-digital-core'
                        ),
                    default =>
                        __(
                            'Em preparação',
                            'facil-digital-core'
                        ),
                }
            );
            echo '</span>';

            echo '<h3>';
            echo esc_html(
                (string) (
                    $row[
                        'course_title_snapshot'
                    ]
                    ?? ''
                )
            );
            echo '</h3>';

            echo '<p>';
            echo esc_html__(
                'Aluno:',
                'facil-digital-core'
            );
            echo ' ';
            echo esc_html(
                (string) (
                    $row[
                        'student_name_snapshot'
                    ]
                    ?? ''
                )
            );
            echo '</p>';

            echo '<p>';
            echo esc_html__(
                'Carga horária:',
                'facil-digital-core'
            );
            echo ' ';
            echo esc_html(
                number_format(
                    (
                        (int) (
                            $row[
                                'workload_minutes_snapshot'
                            ]
                            ?? 0
                        )
                    ) / 60,
                    1,
                    ',',
                    '.'
                )
                . ' h'
            );
            echo '</p>';

            echo '<p class="fd-certificate-code">';
            echo esc_html__(
                'Código de verificação:',
                'facil-digital-core'
            );
            echo ' ';
            echo '<strong>';
            echo esc_html(
                (string) (
                    $row[
                        'verification_code'
                    ]
                    ?? ''
                )
            );
            echo '</strong>';
            echo '</p>';

            echo '</article>';
        }

        echo '</div>';
        echo '</section>';
    }

    public function ajaxProgress(): void
    {
        check_ajax_referer(
            'fd_course_learning',
            'nonce'
        );

        $userId = get_current_user_id();

        if ($userId <= 0) {
            wp_send_json_error(
                [
                    'code' =>
                        'course_auth_required',
                ],
                401
            );
        }

        try {
            $result =
                $this->learning
                    ->recordVideoProgress(
                        $userId,
                        absint(
                            $_POST[
                                'enrollment_id'
                            ]
                            ?? 0
                        ),
                        absint(
                            $_POST[
                                'lesson_id'
                            ]
                            ?? 0
                        ),
                        max(
                            0,
                            (int) round(
                                (float) (
                                    $_POST[
                                        'position'
                                    ]
                                    ?? 0
                                )
                            )
                        ),
                        max(
                            0,
                            (int) round(
                                (float) (
                                    $_POST[
                                        'duration'
                                    ]
                                    ?? 0
                                )
                            )
                        ),
                        max(
                            0,
                            (int) round(
                                (float) (
                                    $_POST[
                                        'watched_delta'
                                    ]
                                    ?? 0
                                )
                            )
                        )
                    );

            wp_send_json_success($result);
        } catch (Throwable $exception) {
            wp_send_json_error(
                [
                    'code' =>
                        sanitize_key(
                            $exception
                                ->getMessage()
                        ),
                ],
                400
            );
        }
    }

    public function ajaxCompleteText(): void
    {
        check_ajax_referer(
            'fd_course_learning',
            'nonce'
        );

        $userId = get_current_user_id();

        if ($userId <= 0) {
            wp_send_json_error(
                [
                    'code' =>
                        'course_auth_required',
                ],
                401
            );
        }

        try {
            $result =
                $this->learning
                    ->completeTextLesson(
                        $userId,
                        absint(
                            $_POST[
                                'enrollment_id'
                            ]
                            ?? 0
                        ),
                        absint(
                            $_POST[
                                'lesson_id'
                            ]
                            ?? 0
                        )
                    );

            wp_send_json_success($result);
        } catch (Throwable $exception) {
            wp_send_json_error(
                [
                    'code' =>
                        sanitize_key(
                            $exception
                                ->getMessage()
                        ),
                ],
                400
            );
        }
    }

    private function classroomUrl(
        int $enrollmentId,
        int $lessonId
    ): string {
        $url = wc_get_endpoint_url(
            self::CLASSROOM_ENDPOINT,
            (string) $enrollmentId,
            wc_get_page_permalink(
                'myaccount'
            )
        );

        if ($lessonId > 0) {
            $url = add_query_arg(
                'aula',
                $lessonId,
                $url
            );
        }

        return $url;
    }
}