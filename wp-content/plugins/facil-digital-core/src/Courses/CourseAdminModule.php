<?php

declare(strict_types=1);

namespace FacilDigital\Core\Courses;

use FacilDigital\Core\Admin\Menu;
use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Core\Capabilities;
use Throwable;
use WC_Product;

final class CourseAdminModule implements ModuleInterface
{
    public const SLUG = 'facil-digital-courses';

    public function __construct(
        private readonly CourseBuilderService $builder =
            new CourseBuilderService(),
        private readonly CourseModuleRepository $modules =
            new CourseModuleRepository(),
        private readonly LessonRepository $lessons =
            new LessonRepository(),
        private readonly LessonResourceRepository $resources =
            new LessonResourceRepository(),
        private readonly CourseResourceService $resourceFiles =
            new CourseResourceService()
    ) {
    }

    public function register(): void
    {
        add_action(
            'admin_menu',
            [$this, 'menu'],
            22
        );

        add_action(
            'admin_enqueue_scripts',
            [$this, 'enqueue']
        );

        add_action(
            'admin_post_fd_course_save',
            [$this, 'saveCourse']
        );

        add_action(
            'admin_post_fd_course_module_save',
            [$this, 'saveModule']
        );

        add_action(
            'admin_post_fd_course_module_delete',
            [$this, 'deleteModule']
        );

        add_action(
            'admin_post_fd_course_lesson_save',
            [$this, 'saveLesson']
        );

        add_action(
            'admin_post_fd_course_lesson_delete',
            [$this, 'deleteLesson']
        );

        add_action(
            'admin_post_fd_course_resource_save',
            [$this, 'saveResource']
        );

        add_action(
            'admin_post_fd_course_resource_delete',
            [$this, 'deleteResource']
        );

        add_action(
            'admin_post_fd_course_reorder',
            [$this, 'reorder']
        );
    }

    public function menu(): void
    {
        add_submenu_page(
            Menu::PARENT_SLUG,
            __('Cursos', 'facil-digital-core'),
            __('Cursos', 'facil-digital-core'),
            Capabilities::ACCESS_ADMIN,
            self::SLUG,
            [$this, 'render']
        );
    }

    public function enqueue(string $hook): void
    {
        unset($hook);

        if (
            sanitize_key(
                (string) (
                    $_GET['page']
                    ?? ''
                )
            ) !== self::SLUG
        ) {
            return;
        }

        $stylePath =
            FACIL_DIGITAL_CORE_DIR
            . 'assets/admin/courses.css';

        $scriptPath =
            FACIL_DIGITAL_CORE_DIR
            . 'assets/admin/courses.js';

        $styleMtime = is_file($stylePath)
            ? filemtime($stylePath)
            : false;

        $scriptMtime = is_file($scriptPath)
            ? filemtime($scriptPath)
            : false;

        $styleVersion = $styleMtime !== false
            ? (string) $styleMtime
            : FACIL_DIGITAL_CORE_VERSION;

        $scriptVersion = $scriptMtime !== false
            ? (string) $scriptMtime
            : FACIL_DIGITAL_CORE_VERSION;

        wp_enqueue_style(
            'fd-courses-admin',
            plugins_url(
                'assets/admin/courses.css',
                FACIL_DIGITAL_CORE_FILE
            ),
            [],
            $styleVersion
        );

        wp_enqueue_script(
            'fd-courses-admin',
            plugins_url(
                'assets/admin/courses.js',
                FACIL_DIGITAL_CORE_FILE
            ),
            [],
            $scriptVersion,
            true
        );

        wp_enqueue_media();

        wp_enqueue_editor();
    }

    public function render(): void
    {
        $this->guard();
        $this->notice();

        $action = sanitize_key(
            (string) (
                $_GET['action']
                ?? ''
            )
        );

        if ($action === 'new') {
            $this->courseForm(0);
            return;
        }

        if ($action === 'edit') {
            $this->courseForm(
                absint(
                    $_GET['id']
                    ?? 0
                )
            );
            return;
        }

        if ($action === 'lesson') {
            $this->lessonForm(
                absint(
                    $_GET['course_id']
                    ?? 0
                ),
                absint(
                    $_GET['module_id']
                    ?? 0
                ),
                absint(
                    $_GET['lesson_id']
                    ?? 0
                )
            );
            return;
        }

        $this->listing();
    }

    public function saveCourse(): void
    {
        $this->guard();
        check_admin_referer(
            'fd_course_save'
        );

        $courseId = absint(
            $_POST['course_id']
            ?? 0
        );

        try {
            $courseId =
                $this->builder->saveCourse(
                    $courseId,
                    (array) wp_unslash(
                        $_POST
                    )
                );

            $this->redirect(
                $courseId,
                'course_saved'
            );
        } catch (Throwable $exception) {
            $this->redirectError(
                $courseId,
                $exception
            );
        }
    }

    public function saveModule(): void
    {
        $this->guard();

        $courseId = absint(
            $_POST['course_id']
            ?? 0
        );

        check_admin_referer(
            'fd_course_module_save_'
            . $courseId
        );

        try {
            $this->builder->saveModule(
                $courseId,
                absint(
                    $_POST['module_id']
                    ?? 0
                ),
                (array) wp_unslash(
                    $_POST
                )
            );

            $this->redirect(
                $courseId,
                'module_saved'
            );
        } catch (Throwable $exception) {
            $this->redirectError(
                $courseId,
                $exception
            );
        }
    }

    public function deleteModule(): void
    {
        $this->guard();

        $courseId = absint(
            $_GET['course_id']
            ?? 0
        );

        $moduleId = absint(
            $_GET['module_id']
            ?? 0
        );

        check_admin_referer(
            'fd_course_module_delete_'
            . $moduleId
        );

        try {
            $this->builder->deleteModule(
                $courseId,
                $moduleId
            );

            $this->redirect(
                $courseId,
                'module_deleted'
            );
        } catch (Throwable $exception) {
            $this->redirectError(
                $courseId,
                $exception
            );
        }
    }

    public function saveLesson(): void
    {
        $this->guard();

        $courseId = absint(
            $_POST['course_id']
            ?? 0
        );

        check_admin_referer(
            'fd_course_lesson_save_'
            . $courseId
        );

        try {
            $lessonId =
                $this->builder->saveLesson(
                    $courseId,
                    absint(
                        $_POST['module_id']
                        ?? 0
                    ),
                    absint(
                        $_POST['lesson_id']
                        ?? 0
                    ),
                    (array) wp_unslash(
                        $_POST
                    )
                );

            $lesson =
                $this->lessons->findById(
                    $lessonId
                );

            $this->redirect(
                $courseId,
                'lesson_saved',
                [
                    'action' =>
                        'lesson',
                    'module_id' =>
                        (int) (
                            $lesson[
                                'module_id'
                            ]
                            ?? 0
                        ),
                    'lesson_id' =>
                        $lessonId,
                ]
            );
        } catch (Throwable $exception) {
            $this->redirectError(
                $courseId,
                $exception
            );
        }
    }

    public function deleteLesson(): void
    {
        $this->guard();

        $courseId = absint(
            $_GET['course_id']
            ?? 0
        );

        $lessonId = absint(
            $_GET['lesson_id']
            ?? 0
        );

        check_admin_referer(
            'fd_course_lesson_delete_'
            . $lessonId
        );

        try {
            $this->builder->deleteLesson(
                $courseId,
                $lessonId
            );

            $this->redirect(
                $courseId,
                'lesson_deleted'
            );
        } catch (Throwable $exception) {
            $this->redirectError(
                $courseId,
                $exception
            );
        }
    }

    public function saveResource(): void
    {
        $this->guard();

        $courseId = absint(
            $_POST['course_id']
            ?? 0
        );

        $lessonId = absint(
            $_POST['lesson_id']
            ?? 0
        );

        check_admin_referer(
            'fd_course_resource_save_'
            . $lessonId
        );

        try {
            $file = isset(
                $_FILES['resource_file']
            )
            && is_array(
                $_FILES['resource_file']
            )
                ? $_FILES['resource_file']
                : [];

            $this->resourceFiles->upload(
                $courseId,
                $lessonId,
                (string) wp_unslash(
                    $_POST['title']
                    ?? ''
                ),
                $file,
                max(
                    0,
                    absint(
                        $_POST['sort_order']
                        ?? 0
                    )
                ),
                sanitize_key(
                    (string) (
                        $_POST['status']
                        ?? 'active'
                    )
                )
            );

            $lesson =
                $this->lessons->findById(
                    $lessonId
                );

            $this->redirect(
                $courseId,
                'resource_saved',
                [
                    'action' =>
                        'lesson',
                    'module_id' =>
                        (int) (
                            $lesson[
                                'module_id'
                            ]
                            ?? 0
                        ),
                    'lesson_id' =>
                        $lessonId,
                ]
            );
        } catch (Throwable $exception) {
            $this->redirectError(
                $courseId,
                $exception
            );
        }
    }

    public function deleteResource(): void
    {
        $this->guard();

        $courseId = absint(
            $_GET['course_id']
            ?? 0
        );

        $lessonId = absint(
            $_GET['lesson_id']
            ?? 0
        );

        $resourceId = absint(
            $_GET['resource_id']
            ?? 0
        );

        check_admin_referer(
            'fd_course_resource_delete_'
            . $resourceId
        );

        try {
            $this->resourceFiles->delete(
                $courseId,
                $resourceId
            );

            $lesson =
                $this->lessons->findById(
                    $lessonId
                );

            $this->redirect(
                $courseId,
                'resource_deleted',
                [
                    'action' =>
                        'lesson',
                    'module_id' =>
                        (int) (
                            $lesson[
                                'module_id'
                            ]
                            ?? 0
                        ),
                    'lesson_id' =>
                        $lessonId,
                ]
            );
        } catch (Throwable $exception) {
            $this->redirectError(
                $courseId,
                $exception
            );
        }
    }

    public function reorder(): void
    {
        $this->guard();

        $courseId = absint(
            $_POST['course_id']
            ?? 0
        );

        check_admin_referer(
            'fd_course_reorder_'
            . $courseId
        );

        $moduleOrder =
            array_values(
                array_filter(
                    array_map(
                        'absint',
                        explode(
                            ',',
                            (string) (
                                $_POST[
                                    'module_order'
                                ]
                                ?? ''
                            )
                        )
                    )
                )
            );

        $decoded = json_decode(
            wp_unslash(
                (string) (
                    $_POST[
                        'lesson_order'
                    ]
                    ?? '{}'
                )
            ),
            true
        );

        $lessonOrder = [];

        if (is_array($decoded)) {
            foreach (
                $decoded
                as $moduleId => $ids
            ) {
                if (!is_array($ids)) {
                    continue;
                }

                $lessonOrder[
                    absint($moduleId)
                ] = array_values(
                    array_filter(
                        array_map(
                            'absint',
                            $ids
                        )
                    )
                );
            }
        }

        try {
            $this->builder->reorder(
                $courseId,
                $moduleOrder,
                $lessonOrder
            );

            $this->redirect(
                $courseId,
                'order_saved'
            );
        } catch (Throwable $exception) {
            $this->redirectError(
                $courseId,
                $exception
            );
        }
    }

    private function listing(): void
    {
        $rows =
            $this->builder->listCourses();

        $totalCourses = count($rows);
        $publishedCourses = 0;
        $draftCourses = 0;
        $archivedCourses = 0;

        foreach ($rows as $summaryRow) {
            switch ((string) ($summaryRow['status'] ?? 'draft')) {
                case 'published':
                    $publishedCourses++;
                    break;

                case 'archived':
                    $archivedCourses++;
                    break;

                default:
                    $draftCourses++;
                    break;
            }
        }
        ?>
        <div class="wrap fd-courses-admin fd-courses-listing">
            <div class="fd-courses-listing__header">
                <div>
                    <p class="fd-course-heading__eyebrow">
                        Fácil Digital+ LMS
                    </p>

                    <h1>
                        <?php echo esc_html__('Cursos', 'facil-digital-core'); ?>
                    </h1>

                    <p class="fd-courses-listing__description">
                        Gerencie os cursos online, produtos vinculados e estado de publicação.
                    </p>
                </div>

                <a
                    class="button button-primary fd-courses-listing__new"
                    href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'action' => 'new'], admin_url('admin.php'))); ?>"
                >
                    <span
                        class="dashicons dashicons-plus-alt2"
                        aria-hidden="true"
                    ></span>
                    <?php echo esc_html__('Novo curso', 'facil-digital-core'); ?>
                </a>
            </div>

            <hr class="wp-header-end">

            <div
                class="fd-course-stats"
                aria-label="<?php echo esc_attr__('Resumo dos cursos', 'facil-digital-core'); ?>"
            >
                <div class="fd-course-stat">
                    <span class="fd-course-stat__label">Total de cursos</span>
                    <strong class="fd-course-stat__value">
                        <?php echo esc_html((string) $totalCourses); ?>
                    </strong>
                </div>

                <div class="fd-course-stat fd-course-stat--published">
                    <span class="fd-course-stat__label">Publicados</span>
                    <strong class="fd-course-stat__value">
                        <?php echo esc_html((string) $publishedCourses); ?>
                    </strong>
                </div>

                <div class="fd-course-stat fd-course-stat--draft">
                    <span class="fd-course-stat__label">Rascunhos</span>
                    <strong class="fd-course-stat__value">
                        <?php echo esc_html((string) $draftCourses); ?>
                    </strong>
                </div>

                <div class="fd-course-stat fd-course-stat--archived">
                    <span class="fd-course-stat__label">Arquivados</span>
                    <strong class="fd-course-stat__value">
                        <?php echo esc_html((string) $archivedCourses); ?>
                    </strong>
                </div>
            </div>

            <div class="fd-course-card fd-course-list-card">
                <div class="fd-course-list-card__header">
                    <div>
                        <h2>Cursos cadastrados</h2>
                        <p>
                            Consulte o produto associado, carga horária e status de cada curso.
                        </p>
                    </div>

                    <span class="fd-course-list-card__count">
                        <?php
                        echo esc_html(
                            sprintf(
                                _n(
                                    '%d curso',
                                    '%d cursos',
                                    $totalCourses,
                                    'facil-digital-core'
                                ),
                                $totalCourses
                            )
                        );
                        ?>
                    </span>
                </div>

                <?php if ($rows === []) : ?>
                    <div class="fd-course-empty">
                        <span
                            class="dashicons dashicons-welcome-learn-more"
                            aria-hidden="true"
                        ></span>

                        <h2>Nenhum curso cadastrado</h2>

                        <p>
                            Crie o primeiro curso para começar a montar módulos, aulas e materiais.
                        </p>

                        <a
                            class="button button-primary"
                            href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'action' => 'new'], admin_url('admin.php'))); ?>"
                        >
                            Criar primeiro curso
                        </a>
                    </div>
                <?php else : ?>
                    <div class="fd-course-table-wrap">
                        <table class="widefat striped fd-course-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Curso</th>
                                    <th>Produto</th>
                                    <th>Carga horária</th>
                                    <th>Status</th>
                                    <th>Atualizado</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>

                            <tbody>
                            <?php foreach ($rows as $row) : ?>
                                <?php
                                $courseId = (int) $row['id'];
                                $product = wc_get_product((int) $row['product_id']);

                                $courseStatus =
                                    (string) $row['status'];

                                $courseStatusLabel = match ($courseStatus) {
                                    'published' => 'Publicado',
                                    'archived' => 'Arquivado',
                                    default => 'Rascunho',
                                };
                                ?>

                                <tr>
                                    <td class="fd-course-table__id">
                                        #<?php echo esc_html((string) $courseId); ?>
                                    </td>

                                    <td class="fd-course-table__course">
                                        <strong>
                                            <?php echo esc_html((string) $row['title']); ?>
                                        </strong>

                                        <span class="fd-course-table__slug">
                                            <?php echo esc_html((string) $row['slug']); ?>
                                        </span>
                                    </td>

                                    <td class="fd-course-table__product">
                                        <?php
                                        echo esc_html(
                                            $product instanceof WC_Product
                                                ? '#' . $product->get_id() . ' — ' . $product->get_name()
                                                : 'Produto indisponível'
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <?php
                                        echo esc_html(
                                            number_format(
                                                ((int) $row['workload_minutes']) / 60,
                                                1,
                                                ',',
                                                '.'
                                            ) . ' h'
                                        );
                                        ?>
                                    </td>

                                    <td>
                                        <span class="fd-course-status fd-course-status--<?php echo esc_attr($courseStatus); ?>">
                                            <?php echo esc_html($courseStatusLabel); ?>
                                        </span>
                                    </td>

                                    <td class="fd-course-table__updated">
                                        <?php echo esc_html((string) $row['updated_at']); ?>
                                    </td>

                                    <td class="fd-course-table__actions">
                                        <a
                                            class="button button-secondary"
                                            href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'action' => 'edit', 'id' => $courseId], admin_url('admin.php'))); ?>"
                                            aria-label="<?php echo esc_attr('Editar curso ' . (string) $row['title']); ?>"
                                        >
                                            <span
                                                class="dashicons dashicons-edit"
                                                aria-hidden="true"
                                            ></span>
                                            Editar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    private function courseForm(int $courseId): void
    {
        $course = [
            'title' => '',
            'slug' => '',
            'short_description' => '',
            'description' => '',
            'intro_youtube_video_id' => '',
            'workload_minutes' => 0,
            'completion_threshold' => '95.00',
            'navigation_mode' => 'free',
            'certificate_enabled' => 1,
            'status' => 'draft',
        ];

        $product = null;
        $curriculum = [];

        if ($courseId > 0) {
            $state =
                $this->builder->editorState(
                    $courseId
                );

            $course = (array) $state['course'];
            $product = $state['product'];
            $curriculum =
                (array) $state['curriculum'];
        }

        $price =
            $product instanceof WC_Product
                ? $product->get_regular_price()
                : '';

        $visibility =
            $product instanceof WC_Product
                ? $product->get_catalog_visibility()
                : 'visible';

        $productDescription =
            $product instanceof WC_Product
                ? $product->get_description()
                : '';

        $productShortDescription =
            $product instanceof WC_Product
                ? $product->get_short_description()
                : '';

        $imageId =
            $product instanceof WC_Product
                ? (int) $product->get_image_id()
                : 0;
        ?>
        <div class="wrap fd-courses-admin">
            <div class="fd-course-heading">
                <div>
                    <p class="fd-course-heading__eyebrow">Cursos online</p>
                    <h1><?php echo esc_html($courseId > 0 ? (string) $course['title'] : 'Novo curso'); ?></h1>
                    <?php if ($courseId > 0) : ?>
                        <div class="fd-course-heading__meta">
                            <span>Curso #<?php echo esc_html((string) $courseId); ?></span>
                            <span class="fd-course-status fd-course-status--<?php echo esc_attr((string) $course['status']); ?>">
                                <?php
                                echo esc_html(
                                    match ((string) $course['status']) {
                                        'published' => 'Publicado',
                                        'archived' => 'Arquivado',
                                        default => 'Rascunho',
                                    }
                                );
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                <a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG], admin_url('admin.php'))); ?>">← Voltar aos cursos</a>
            </div>

            <form class="fd-course-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="fd_course_save">
                <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $courseId); ?>">
                <?php wp_nonce_field('fd_course_save'); ?>

                <div class="fd-course-grid">
                    <section class="fd-course-card fd-course-card--primary">
                        <h2>Informações do curso</h2>
                        <p class="fd-course-card__intro">Defina como o curso será apresentado ao aluno.</p>

                        <p class="fd-course-field"><label><strong>Título do curso</strong><br>
                        <input class="large-text" name="title" required value="<?php echo esc_attr((string) $course['title']); ?>"></label></p>

                        <p class="fd-course-field"><label><strong>Endereço amigável</strong><br>
                        <input class="regular-text" name="slug" value="<?php echo esc_attr((string) $course['slug']); ?>"></label>
                        <span class="fd-course-field__help">Usado internamente na URL. Pode deixar o sistema gerar a partir do título.</span></p>

                        <p class="fd-course-field"><label><strong>Resumo do curso</strong><br>
                        <textarea class="large-text" rows="4" name="short_description"><?php echo esc_textarea((string) $course['short_description']); ?></textarea></label>
                        <span class="fd-course-field__help">Uma descrição curta para apresentar rapidamente o conteúdo ao aluno.</span></p>

                        <p><strong>Descrição / apresentação</strong></p>
                        <?php
                        wp_editor(
                            (string) $course['description'],
                            'fd_course_description',
                            [
                                'textarea_name' => 'description',
                                'textarea_rows' => 10,
                                'media_buttons' => true,
                            ]
                        );
                        ?>
                    </section>

                    <section class="fd-course-card fd-course-card--presentation">
                        <div class="fd-course-section-heading">
                            <div>
                                <h2>Capa e apresentação</h2>
                                <p>Configure a identidade visual e o vídeo de boas-vindas que serão exibidos na apresentação do curso.</p>
                            </div>
                        </div>

                        <div class="fd-course-presentation-grid">
                            <div class="fd-course-cover-field">
                                <h3>Capa do curso</h3>

                                <div class="fd-course-cover__preview" data-course-cover-preview>
                                    <?php if ($imageId > 0) : ?>
                                        <?php echo wp_get_attachment_image(
                                            $imageId,
                                            'medium_large',
                                            false,
                                            [
                                                'class' => 'fd-course-cover__image',
                                            ]
                                        ); ?>
                                    <?php else : ?>
                                        <div class="fd-course-cover__placeholder" data-course-cover-placeholder>
                                            <span class="dashicons dashicons-format-image" aria-hidden="true"></span>
                                            <strong>Nenhuma capa selecionada</strong>
                                            <span>Escolha uma imagem da Biblioteca de Mídia.</span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <input
                                    type="hidden"
                                    name="image_id"
                                    value="<?php echo esc_attr((string) $imageId); ?>"
                                    data-course-cover-input
                                >

                                <div class="fd-course-cover__actions">
                                    <button type="button" class="button button-secondary" data-course-cover-select>
                                        <?php echo esc_html($imageId > 0 ? 'Alterar capa' : 'Selecionar capa'); ?>
                                    </button>

                                    <button
                                        type="button"
                                        class="button-link-delete"
                                        data-course-cover-remove
                                        <?php echo $imageId > 0 ? '' : 'hidden'; ?>
                                    >
                                        Remover capa
                                    </button>
                                </div>

                                <span class="fd-course-field__help">
                                    Esta imagem será usada como capa do curso e como imagem principal do produto vinculado no WooCommerce.
                                </span>
                            </div>

                            <div class="fd-course-intro-video-field">
                                <h3>Vídeo introdutório</h3>

                                <p class="fd-course-field">
                                    <label>
                                        <strong>Link ou ID do YouTube</strong><br>
                                        <input
                                            class="large-text"
                                            name="intro_youtube_video_id"
                                            placeholder="Ex.: https://youtu.be/M7lc1UVf-VE"
                                            value="<?php echo esc_attr((string) ($course['intro_youtube_video_id'] ?? '')); ?>"
                                        >
                                    </label>

                                    <span class="fd-course-field__help">
                                        Opcional. Este vídeo será exibido na apresentação do curso e não contará como aula nem como progresso.
                                    </span>
                                </p>
                            </div>
                        </div>
                    </section>

                    <aside class="fd-course-card fd-course-card--settings">
                        <h2>Configurações de aprendizagem</h2>
                        <p class="fd-course-card__intro">Controle progresso, navegação e emissão do certificado.</p>

                        <p class="fd-course-field"><label><strong>Carga horária</strong><br>
                        <input type="number" min="0" name="workload_minutes" value="<?php echo esc_attr((string) $course['workload_minutes']); ?>"> minutos</label>
                        <span class="fd-course-field__help">Carga horária total que aparecerá no curso e no certificado.</span></p>

                        <p class="fd-course-field"><label><strong>Percentual mínimo do vídeo</strong><br>
                        <input type="number" min="1" max="100" step="0.01" name="completion_threshold" value="<?php echo esc_attr((string) $course['completion_threshold']); ?>">%</label>
                        <span class="fd-course-field__help">Percentual assistido necessário para considerar uma videoaula concluída.</span></p>

                        <p class="fd-course-field"><label><strong>Navegação entre aulas</strong><br>
                        <select name="navigation_mode">
                            <option value="free" <?php selected($course['navigation_mode'], 'free'); ?>>Livre</option>
                            <option value="sequential" <?php selected($course['navigation_mode'], 'sequential'); ?>>Sequencial</option>
                        </select></label></p>

                        <p><label><input type="checkbox" name="certificate_enabled" value="1" <?php checked((int) $course['certificate_enabled'], 1); ?>> Emitir certificado</label></p>

                        <p><label><strong>Status</strong><br>
                        <select name="status">
                            <option value="draft" <?php selected($course['status'], 'draft'); ?>>Rascunho</option>
                            <option value="published" <?php selected($course['status'], 'published'); ?>>Publicado</option>
                            <option value="archived" <?php selected($course['status'], 'archived'); ?>>Arquivado</option>
                        </select></label></p>
                    </aside>

                    <section class="fd-course-card fd-course-card--commerce">
                        <h2>Venda e acesso</h2>
                        <p class="fd-course-card__intro">O curso é vinculado automaticamente a um produto do WooCommerce.</p>

                        <p class="fd-course-field"><label><strong>Preço</strong><br>
                        <input type="number" min="0" step="0.01" name="regular_price" required value="<?php echo esc_attr((string) $price); ?>"></label></p>

                        <p class="fd-course-field"><label><strong>Onde o produto aparece</strong><br>
                        <select name="catalog_visibility">
                            <?php foreach (['visible' => 'Catálogo e busca', 'catalog' => 'Somente catálogo', 'search' => 'Somente busca', 'hidden' => 'Oculto do catálogo'] as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($visibility, $key); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select></label>
                        <span class="fd-course-field__help">Use “Oculto do catálogo” enquanto estiver preparando ou testando o curso.</span></p>

                        <p><label><strong>Resumo comercial</strong><br>
                        <textarea class="large-text" rows="4" name="product_short_description"><?php echo esc_textarea((string) $productShortDescription); ?></textarea></label></p>

                        <p><label><strong>Descrição comercial</strong><br>
                        <textarea class="large-text" rows="7" name="product_description"><?php echo esc_textarea((string) $productDescription); ?></textarea></label></p>
                    </section>
                </div>

                <?php submit_button($courseId > 0 ? 'Salvar curso' : 'Criar curso'); ?>
            </form>

            <?php if ($courseId > 0) : ?>
                <?php $this->curriculum($courseId, $curriculum); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * @param list<array<string, mixed>> $curriculum
     */
    private function curriculum(
        int $courseId,
        array $curriculum
    ): void {
        ?>
        <hr>
        <div class="fd-course-builder">
            <div class="fd-course-heading">
                <div>
                    <p class="fd-course-heading__eyebrow">Estrutura de aprendizagem</p>
                    <h2>Currículo</h2>
                    <p>Organize os módulos e aulas na ordem em que o aluno irá estudá-los.</p>
                </div>
            </div>

            <div class="fd-course-modules">
            <?php foreach ($curriculum as $module) : ?>
                <?php
                $moduleId = (int) $module['id'];
                $lessons = (array) ($module['lessons'] ?? []);
                ?>
                <section class="fd-course-module" data-module-id="<?php echo esc_attr((string) $moduleId); ?>" draggable="true">
                    <div class="fd-course-module__header">
                        <button type="button" class="fd-course-drag-handle" title="Arrastar módulo">☰</button>
                        <strong><?php echo esc_html((string) $module['title']); ?></strong>
                        <span><?php echo esc_html(count($lessons) . ' aula(s)'); ?></span>
                        <button type="button" class="button fd-course-move-up">↑</button>
                        <button type="button" class="button fd-course-move-down">↓</button>
                    </div>

                    <form class="fd-course-inline-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="fd_course_module_save">
                        <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $courseId); ?>">
                        <input type="hidden" name="module_id" value="<?php echo esc_attr((string) $moduleId); ?>">
                        <?php wp_nonce_field('fd_course_module_save_' . $courseId); ?>

                        <input name="title" value="<?php echo esc_attr((string) $module['title']); ?>" required>
                        <input name="description" value="<?php echo esc_attr((string) ($module['description'] ?? '')); ?>" placeholder="Descrição">
                        <input type="number" min="0" name="sort_order" value="<?php echo esc_attr((string) $module['sort_order']); ?>">
                        <select name="status"><option value="active" <?php selected($module['status'], 'active'); ?>>Ativo</option><option value="hidden" <?php selected($module['status'], 'hidden'); ?>>Oculto</option></select>
                        <button class="button">Salvar módulo</button>
                    </form>

                    <div class="fd-course-module__actions">
                        <a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'action' => 'lesson', 'course_id' => $courseId, 'module_id' => $moduleId], admin_url('admin.php'))); ?>">Adicionar aula</a>
                        <a class="button-link-delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=fd_course_module_delete&course_id=' . $courseId . '&module_id=' . $moduleId), 'fd_course_module_delete_' . $moduleId)); ?>" onclick="return confirm('Excluir este módulo vazio?')">Excluir módulo</a>
                    </div>

                    <div class="fd-course-lessons" data-module-id="<?php echo esc_attr((string) $moduleId); ?>">
                        <?php foreach ($lessons as $lesson) : ?>
                            <?php $lessonId = (int) $lesson['id']; ?>
                            <div class="fd-course-lesson" data-lesson-id="<?php echo esc_attr((string) $lessonId); ?>" draggable="true">
                                <button type="button" class="fd-course-drag-handle" title="Arrastar aula">☰</button>
                                <div class="fd-course-lesson__name">
                                    <strong><?php echo esc_html((string) $lesson['title']); ?></strong>
                                    <div class="fd-course-lesson__meta">
                                        <span class="fd-course-type">
                                            <?php
                                            echo esc_html(
                                                match ((string) $lesson['lesson_type']) {
                                                    'video' => 'Vídeo',
                                                    'mixed' => 'Vídeo + conteúdo',
                                                    default => 'Texto',
                                                }
                                            );
                                            ?>
                                        </span>
                                        <span class="fd-course-status fd-course-status--<?php echo esc_attr((string) $lesson['status']); ?>">
                                            <?php
                                            echo esc_html(
                                                match ((string) $lesson['status']) {
                                                    'published' => 'Publicada',
                                                    'archived' => 'Arquivada',
                                                    default => 'Rascunho',
                                                }
                                            );
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                <button type="button" class="button fd-course-move-up">↑</button>
                                <button type="button" class="button fd-course-move-down">↓</button>
                                <a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'action' => 'lesson', 'course_id' => $courseId, 'module_id' => $moduleId, 'lesson_id' => $lessonId], admin_url('admin.php'))); ?>">Editar</a>
                                <a class="button-link-delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=fd_course_lesson_delete&course_id=' . $courseId . '&lesson_id=' . $lessonId), 'fd_course_lesson_delete_' . $lessonId)); ?>" onclick="return confirm('Excluir esta aula?')">Excluir</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
            </div>

            <section class="fd-course-card fd-course-new-module">
                <h3>Novo módulo</h3>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="fd_course_module_save">
                    <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $courseId); ?>">
                    <input type="hidden" name="module_id" value="0">
                    <?php wp_nonce_field('fd_course_module_save_' . $courseId); ?>
                    <input class="regular-text" name="title" required placeholder="Nome do módulo">
                    <input class="regular-text" name="description" placeholder="Descrição">
                    <input type="hidden" name="sort_order" value="<?php echo esc_attr((string) ((count($curriculum) + 1) * 10)); ?>">
                    <input type="hidden" name="status" value="active">
                    <button class="button button-primary">Adicionar módulo</button>
                </form>
            </section>

            <form class="fd-course-reorder-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="fd_course_reorder">
                <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $courseId); ?>">
                <input type="hidden" name="module_order" value="">
                <input type="hidden" name="lesson_order" value="{}">
                <?php wp_nonce_field('fd_course_reorder_' . $courseId); ?>
                <button class="button button-primary">Salvar ordem do currículo</button>
            </form>
        </div>
        <?php
    }

    private function lessonForm(
        int $courseId,
        int $moduleId,
        int $lessonId
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
            wp_die('Módulo inválido.');
        }

        $lesson = [
            'title' => '',
            'slug' => '',
            'lesson_type' => 'text',
            'content' => '',
            'transcript' => '',
            'youtube_video_id' => '',
            'duration_seconds' => 0,
            'is_required' => 1,
            'sort_order' => 10,
            'status' => 'draft',
        ];

        if ($lessonId > 0) {
            $stored =
                $this->lessons->findById(
                    $lessonId
                );

            if (
                !is_array($stored)
                || (int) (
                    $stored['course_id']
                    ?? 0
                ) !== $courseId
            ) {
                wp_die('Aula inválida.');
            }

            $lesson = $stored;
            $moduleId =
                (int) $stored['module_id'];
        }

        $allModules =
            $this->modules->forCourse(
                $courseId
            );

        $resources =
            $lessonId > 0
                ? $this->resources
                    ->forLesson($lessonId)
                : [];
        ?>
        <div class="wrap fd-courses-admin">
            <div class="fd-course-heading">
                <div>
                    <p class="fd-course-heading__eyebrow">Editor de aula</p>
                    <h1><?php echo esc_html($lessonId > 0 ? (string) $lesson['title'] : 'Nova aula'); ?></h1>
                    <p class="fd-course-heading__description">
                        <?php echo esc_html('Módulo: ' . (string) $module['title']); ?>
                    </p>
                </div>
                <a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'action' => 'edit', 'id' => $courseId], admin_url('admin.php'))); ?>">← Voltar ao curso</a>
            </div>

            <form class="fd-course-lesson-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="fd_course_lesson_save">
                <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $courseId); ?>">
                <input type="hidden" name="lesson_id" value="<?php echo esc_attr((string) $lessonId); ?>">
                <?php wp_nonce_field('fd_course_lesson_save_' . $courseId); ?>

                <div class="fd-course-grid">
                    <section class="fd-course-card fd-course-lesson-settings">
                        <div class="fd-course-section-heading">
                            <div>
                                <h2>Configurações da aula</h2>
                                <p>Defina a identificação, formato, publicação e comportamento desta aula.</p>
                            </div>
                        </div>

                        <p class="fd-course-field fd-course-lesson-field--title">
                            <label>
                                <strong>Título da aula</strong><br>
                                <input class="large-text" name="title" required value="<?php echo esc_attr((string) $lesson['title']); ?>">
                            </label>
                        </p>

                        <p class="fd-course-field fd-course-lesson-field--slug">
                            <label>
                                <strong>Endereço amigável</strong><br>
                                <input class="regular-text" name="slug" value="<?php echo esc_attr((string) $lesson['slug']); ?>">
                            </label>
                            <span class="fd-course-field__help">
                                Identificador amigável usado internamente para esta aula.
                            </span>
                        </p>

                        <p class="fd-course-field fd-course-lesson-field--module">
                            <label>
                                <strong>Módulo</strong><br>
                                <select name="module_id">
                                    <?php foreach ($allModules as $candidate) : ?>
                                        <option value="<?php echo esc_attr((string) $candidate['id']); ?>" <?php selected((int) $candidate['id'], $moduleId); ?>><?php echo esc_html((string) $candidate['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <span class="fd-course-field__help">
                                Você pode mover a aula para outro módulo.
                            </span>
                        </p>

                        <p class="fd-course-field fd-course-lesson-field--type">
                            <label>
                                <strong>Tipo de aula</strong><br>
                                <select name="lesson_type">
                                    <option value="text" <?php selected($lesson['lesson_type'], 'text'); ?>>Aula em texto</option>
                                    <option value="video" <?php selected($lesson['lesson_type'], 'video'); ?>>Videoaula</option>
                                    <option value="mixed" <?php selected($lesson['lesson_type'], 'mixed'); ?>>Vídeo + conteúdo complementar</option>
                                </select>
                            </label>
                            <span class="fd-course-field__help">
                                Escolha o formato principal desta aula.
                            </span>
                        </p>

                        <div class="fd-course-video-settings" data-lesson-types="video,mixed">
                            <div class="fd-course-video-settings__header">
                                <span class="dashicons dashicons-video-alt3" aria-hidden="true"></span>
                                <div>
                                    <strong>Vídeo do YouTube</strong>
                                    <span>Configure o vídeo que será reproduzido nesta aula.</span>
                                </div>
                            </div>

                            <p class="fd-course-field fd-course-youtube-field">
                                <label>
                                    <strong>Link ou ID do vídeo</strong><br>
                                    <input class="large-text" name="youtube_video_id" placeholder="Ex.: https://youtu.be/M7lc1UVf-VE" value="<?php echo esc_attr((string) ($lesson['youtube_video_id'] ?? '')); ?>">
                                </label>
                                <span class="fd-course-field__help">
                                    Você pode colar o link completo do YouTube, um link youtu.be, Shorts, embed ou apenas o ID do vídeo.
                                </span>
                            </p>

                            <p class="fd-course-field">
                                <label>
                                    <strong>Duração da videoaula</strong><br>
                                    <input type="number" min="0" name="duration_seconds" value="<?php echo esc_attr((string) $lesson['duration_seconds']); ?>"> segundos
                                </label>
                                <span class="fd-course-field__help">
                                    Esse valor auxilia no acompanhamento do progresso do aluno.
                                </span>
                            </p>
                        </div>

                        <p class="fd-course-field fd-course-lesson-field--status">
                            <label>
                                <strong>Status</strong><br>
                                <select name="status">
                                    <option value="draft" <?php selected($lesson['status'], 'draft'); ?>>Rascunho</option>
                                    <option value="published" <?php selected($lesson['status'], 'published'); ?>>Publicada</option>
                                    <option value="archived" <?php selected($lesson['status'], 'archived'); ?>>Arquivada</option>
                                </select>
                            </label>
                            <span class="fd-course-field__help">
                                Somente aulas publicadas devem ficar disponíveis aos alunos.
                            </span>
                        </p>

                        <p class="fd-course-field fd-course-lesson-field--order">
                            <label>
                                <strong>Ordem</strong><br>
                                <input type="number" min="0" name="sort_order" value="<?php echo esc_attr((string) $lesson['sort_order']); ?>">
                            </label>
                            <span class="fd-course-field__help">
                                Define a posição da aula dentro do módulo.
                            </span>
                        </p>

                        <p class="fd-course-field fd-course-lesson-field--required">
                            <label>
                                <input type="checkbox" name="is_required" value="1" <?php checked((int) $lesson['is_required'], 1); ?>>
                                <span>
                                    <strong>Aula obrigatória</strong>
                                    <small>Exigir esta aula para o progresso e conclusão do curso.</small>
                                </span>
                            </label>
                        </p>
                    </section>

                    <section class="fd-course-card fd-course-card--wide fd-course-lesson-content-card">
                        <div class="fd-course-section-heading">
                            <div>
                                <h2>Conteúdo da aula</h2>
                                <p>Adicione textos, orientações, materiais complementares ou explicações para o aluno.</p>
                            </div>
                        </div>

                        <?php wp_editor((string) $lesson['content'], 'fd_lesson_content', ['textarea_name' => 'content', 'textarea_rows' => 12, 'media_buttons' => true]); ?>
                    </section>

                    <section class="fd-course-card fd-course-card--wide fd-course-lesson-transcript-card">
                        <div class="fd-course-section-heading">
                            <div>
                                <h2>Transcrição</h2>
                                <p>Opcional. Útil para acessibilidade e para alunos que preferem acompanhar o conteúdo por texto.</p>
                            </div>
                        </div>

                        <?php wp_editor((string) $lesson['transcript'], 'fd_lesson_transcript', ['textarea_name' => 'transcript', 'textarea_rows' => 10, 'media_buttons' => false]); ?>
                    </section>
                </div>

                <?php submit_button($lessonId > 0 ? 'Salvar aula' : 'Criar aula'); ?>
            </form>

            <?php if ($lessonId > 0) : ?>
                <section class="fd-course-card fd-course-lesson-resources">
                    <div class="fd-course-section-heading">
                        <div>
                            <h2>Recursos da aula</h2>
                            <p>Arquivos complementares disponíveis somente para alunos autorizados. Limite por arquivo: 25 MB.</p>
                        </div>
                    </div>

                    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="fd-course-resource-form fd-course-resource-form--enhanced">
                        <input type="hidden" name="action" value="fd_course_resource_save">
                        <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $courseId); ?>">
                        <input type="hidden" name="lesson_id" value="<?php echo esc_attr((string) $lessonId); ?>">
                        <input type="hidden" name="resource_id" value="0">
                        <?php wp_nonce_field('fd_course_resource_save_' . $lessonId); ?>

                        <label>
                            <span>Título do recurso</span>
                            <input name="title" required placeholder="Ex.: Material de apoio">
                        </label>

                        <label>
                            <span>Arquivo</span>
                            <input type="file" name="resource_file" required accept=".pdf,.zip,.txt,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png">
                        </label>

                        <label>
                            <span>Ordem</span>
                            <input type="number" min="0" name="sort_order" value="10">
                        </label>

                        <label>
                            <span>Status</span>
                            <select name="status">
                                <option value="active">Ativo</option>
                                <option value="hidden">Oculto</option>
                            </select>
                        </label>

                        <button class="button button-primary">Enviar recurso</button>
                    </form>

                    <div class="fd-course-resource-table-wrap">
                    <table class="widefat striped">
                        <thead><tr><th>Recurso</th><th>Arquivo</th><th>MIME</th><th>Status</th><th>Ação</th></tr></thead>
                        <tbody>
                        <?php if ($resources === []) : ?><tr><td colspan="5">Nenhum recurso cadastrado.</td></tr><?php endif; ?>
                        <?php foreach ($resources as $resource) : ?>
                            <?php $resourceId = (int) $resource['id']; ?>
                            <tr>
                                <td><?php echo esc_html((string) $resource['title']); ?></td>
                                <td><?php echo esc_html((string) $resource['original_filename']); ?></td>
                                <td><?php echo esc_html((string) $resource['mime_type']); ?></td>
                                <td><?php echo esc_html((string) $resource['status']); ?></td>
                                <td><a class="button-link-delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=fd_course_resource_delete&course_id=' . $courseId . '&lesson_id=' . $lessonId . '&resource_id=' . $resourceId), 'fd_course_resource_delete_' . $resourceId)); ?>" onclick="return confirm('Excluir este recurso?')">Excluir</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </section>
            <?php endif; ?>
        </div>
        <?php
    }

    private function notice(): void
    {
        $notice = sanitize_key(
            (string) (
                $_GET['fd_notice']
                ?? ''
            )
        );

        $error = sanitize_key(
            (string) (
                $_GET['fd_error']
                ?? ''
            )
        );

        if ($notice !== '') {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html(
                match ($notice) {
                    'course_saved' => 'Curso salvo.',
                    'module_saved' => 'Módulo salvo.',
                    'module_deleted' => 'Módulo excluído.',
                    'lesson_saved' => 'Aula salva.',
                    'lesson_deleted' => 'Aula excluída.',
                    'resource_saved' => 'Recurso salvo.',
                    'resource_deleted' => 'Recurso excluído.',
                    'order_saved' => 'Ordem do currículo salva.',
                    default => 'Alteração salva.',
                }
            );
            echo '</p></div>';
        }

        if ($error !== '') {
            echo '<div class="notice notice-error"><p>';
            echo esc_html(
                'Não foi possível concluir a operação: '
                . $error
            );
            echo '</p></div>';
        }
    }

    /**
     * @param array<string, int|string> $extra
     */
    private function redirect(
        int $courseId,
        string $notice,
        array $extra = []
    ): never {
        $args = array_merge(
            [
                'page' => self::SLUG,
                'fd_notice' => $notice,
            ],
            $extra
        );

        if (
            $courseId > 0
            && !isset($args['action'])
        ) {
            $args['action'] = 'edit';
            $args['id'] = $courseId;
        }

        wp_safe_redirect(
            add_query_arg(
                $args,
                admin_url('admin.php')
            )
        );

        exit;
    }

    /**
     * @param array<string, int|string> $extra
     */
    private function redirectError(
        int $courseId,
        Throwable $exception,
        array $extra = []
    ): never {
        $args = array_merge(
            [
                'page' => self::SLUG,
                'fd_error' =>
                    sanitize_key(
                        $exception->getMessage()
                    ),
            ],
            $extra
        );

        if ($courseId > 0) {
            $args['action'] =
                $args['action']
                ?? 'edit';

            $args['id'] =
                $args['id']
                ?? $courseId;
        }

        wp_safe_redirect(
            add_query_arg(
                $args,
                admin_url('admin.php')
            )
        );

        exit;
    }

    private function guard(): void
    {
        if (
            !current_user_can(
                Capabilities::ACCESS_ADMIN
            )
        ) {
            wp_die(
                esc_html__(
                    'Acesso negado.',
                    'facil-digital-core'
                )
            );
        }
    }
}