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

        wp_enqueue_style(
            'fd-courses-admin',
            plugins_url(
                'assets/admin/courses.css',
                FACIL_DIGITAL_CORE_FILE
            ),
            [],
            FACIL_DIGITAL_CORE_VERSION
        );

        wp_enqueue_script(
            'fd-courses-admin',
            plugins_url(
                'assets/admin/courses.js',
                FACIL_DIGITAL_CORE_FILE
            ),
            [],
            FACIL_DIGITAL_CORE_VERSION,
            true
        );

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
        ?>
        <div class="wrap fd-courses-admin">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Cursos', 'facil-digital-core'); ?></h1>
            <a class="page-title-action" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'action' => 'new'], admin_url('admin.php'))); ?>"><?php echo esc_html__('Novo curso', 'facil-digital-core'); ?></a>
            <hr class="wp-header-end">

            <div class="fd-course-card">
                <table class="widefat striped">
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
                    <?php if ($rows === []) : ?>
                        <tr><td colspan="7">Nenhum curso cadastrado.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row) : ?>
                        <?php
                        $courseId = (int) $row['id'];
                        $product = wc_get_product((int) $row['product_id']);
                        ?>
                        <tr>
                            <td><?php echo esc_html((string) $courseId); ?></td>
                            <td><strong><?php echo esc_html((string) $row['title']); ?></strong></td>
                            <td><?php echo esc_html($product instanceof WC_Product ? '#' . $product->get_id() . ' — ' . $product->get_name() : 'Produto indisponível'); ?></td>
                            <td><?php echo esc_html(number_format(((int) $row['workload_minutes']) / 60, 1, ',', '.') . ' h'); ?></td>
                            <td><?php echo esc_html((string) $row['status']); ?></td>
                            <td><?php echo esc_html((string) $row['updated_at']); ?></td>
                            <td><a href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'action' => 'edit', 'id' => $courseId], admin_url('admin.php'))); ?>">Editar / currículo</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
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
        ?>
        <div class="wrap fd-courses-admin">
            <div class="fd-course-heading">
                <div>
                    <h1><?php echo esc_html($courseId > 0 ? 'Editar curso' : 'Novo curso'); ?></h1>
                    <?php if ($courseId > 0) : ?><p>Curso #<?php echo esc_html((string) $courseId); ?></p><?php endif; ?>
                </div>
                <a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG], admin_url('admin.php'))); ?>">Voltar aos cursos</a>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="fd_course_save">
                <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $courseId); ?>">
                <?php wp_nonce_field('fd_course_save'); ?>

                <div class="fd-course-grid">
                    <section class="fd-course-card">
                        <h2>Informações do curso</h2>

                        <p><label><strong>Título</strong><br>
                        <input class="large-text" name="title" required value="<?php echo esc_attr((string) $course['title']); ?>"></label></p>

                        <p><label><strong>Slug</strong><br>
                        <input class="regular-text" name="slug" value="<?php echo esc_attr((string) $course['slug']); ?>"></label></p>

                        <p><label><strong>Resumo acadêmico</strong><br>
                        <textarea class="large-text" rows="4" name="short_description"><?php echo esc_textarea((string) $course['short_description']); ?></textarea></label></p>

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

                    <aside class="fd-course-card">
                        <h2>Configurações</h2>

                        <p><label><strong>Carga horária</strong><br>
                        <input type="number" min="0" name="workload_minutes" value="<?php echo esc_attr((string) $course['workload_minutes']); ?>"> minutos</label></p>

                        <p><label><strong>Conclusão mínima de vídeo</strong><br>
                        <input type="number" min="1" max="100" step="0.01" name="completion_threshold" value="<?php echo esc_attr((string) $course['completion_threshold']); ?>">%</label></p>

                        <p><label><strong>Navegação</strong><br>
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

                    <section class="fd-course-card">
                        <h2>Venda pelo WooCommerce</h2>

                        <p><label><strong>Preço</strong><br>
                        <input type="number" min="0" step="0.01" name="regular_price" required value="<?php echo esc_attr((string) $price); ?>"></label></p>

                        <p><label><strong>Visibilidade no catálogo</strong><br>
                        <select name="catalog_visibility">
                            <?php foreach (['visible' => 'Visível', 'catalog' => 'Somente catálogo', 'search' => 'Somente busca', 'hidden' => 'Oculto'] as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($visibility, $key); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select></label></p>

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
                    <h2>Currículo</h2>
                    <p>Arraste módulos e aulas ou use os botões de mover. Depois salve a ordem.</p>
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
                                    <small><?php echo esc_html((string) $lesson['lesson_type'] . ' · ' . (string) $lesson['status']); ?></small>
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

            <section class="fd-course-card">
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
                <h1><?php echo esc_html($lessonId > 0 ? 'Editar aula' : 'Nova aula'); ?></h1>
                <a class="button" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'action' => 'edit', 'id' => $courseId], admin_url('admin.php'))); ?>">Voltar ao curso</a>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="fd_course_lesson_save">
                <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $courseId); ?>">
                <input type="hidden" name="lesson_id" value="<?php echo esc_attr((string) $lessonId); ?>">
                <?php wp_nonce_field('fd_course_lesson_save_' . $courseId); ?>

                <div class="fd-course-grid">
                    <section class="fd-course-card">
                        <p><label><strong>Título</strong><br><input class="large-text" name="title" required value="<?php echo esc_attr((string) $lesson['title']); ?>"></label></p>
                        <p><label><strong>Slug</strong><br><input class="regular-text" name="slug" value="<?php echo esc_attr((string) $lesson['slug']); ?>"></label></p>

                        <p><label><strong>Módulo</strong><br>
                        <select name="module_id">
                            <?php foreach ($allModules as $candidate) : ?>
                                <option value="<?php echo esc_attr((string) $candidate['id']); ?>" <?php selected((int) $candidate['id'], $moduleId); ?>><?php echo esc_html((string) $candidate['title']); ?></option>
                            <?php endforeach; ?>
                        </select></label></p>

                        <p><label><strong>Tipo</strong><br>
                        <select name="lesson_type">
                            <option value="text" <?php selected($lesson['lesson_type'], 'text'); ?>>Texto</option>
                            <option value="video" <?php selected($lesson['lesson_type'], 'video'); ?>>Vídeo</option>
                            <option value="mixed" <?php selected($lesson['lesson_type'], 'mixed'); ?>>Vídeo + conteúdo</option>
                        </select></label></p>

                        <p><label><strong>Vídeo do YouTube</strong><br>
                        <input class="large-text" name="youtube_video_id" placeholder="URL ou ID do vídeo" value="<?php echo esc_attr((string) ($lesson['youtube_video_id'] ?? '')); ?>"></label></p>

                        <p><label><strong>Duração</strong><br>
                        <input type="number" min="0" name="duration_seconds" value="<?php echo esc_attr((string) $lesson['duration_seconds']); ?>"> segundos</label></p>

                        <p><label><input type="checkbox" name="is_required" value="1" <?php checked((int) $lesson['is_required'], 1); ?>> Aula obrigatória</label></p>

                        <p><label><strong>Ordem</strong><br><input type="number" min="0" name="sort_order" value="<?php echo esc_attr((string) $lesson['sort_order']); ?>"></label></p>

                        <p><label><strong>Status</strong><br>
                        <select name="status"><option value="draft" <?php selected($lesson['status'], 'draft'); ?>>Rascunho</option><option value="published" <?php selected($lesson['status'], 'published'); ?>>Publicada</option><option value="archived" <?php selected($lesson['status'], 'archived'); ?>>Arquivada</option></select></label></p>
                    </section>

                    <section class="fd-course-card fd-course-card--wide">
                        <h2>Conteúdo da aula</h2>
                        <?php wp_editor((string) $lesson['content'], 'fd_lesson_content', ['textarea_name' => 'content', 'textarea_rows' => 12, 'media_buttons' => true]); ?>

                        <h2>Transcrição</h2>
                        <?php wp_editor((string) $lesson['transcript'], 'fd_lesson_transcript', ['textarea_name' => 'transcript', 'textarea_rows' => 10, 'media_buttons' => false]); ?>
                    </section>
                </div>

                <?php submit_button($lessonId > 0 ? 'Salvar aula' : 'Criar aula'); ?>
            </form>

            <?php if ($lessonId > 0) : ?>
                <section class="fd-course-card">
                    <h2>Recursos da aula</h2>
                    <p class="description">O arquivo será armazenado na área privada da Fácil Digital+ e somente alunos matriculados poderão baixá-lo. Limite por arquivo: 25 MB.</p>

                    <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="fd-course-resource-form">
                        <input type="hidden" name="action" value="fd_course_resource_save">
                        <input type="hidden" name="course_id" value="<?php echo esc_attr((string) $courseId); ?>">
                        <input type="hidden" name="lesson_id" value="<?php echo esc_attr((string) $lessonId); ?>">
                        <input type="hidden" name="resource_id" value="0">
                        <?php wp_nonce_field('fd_course_resource_save_' . $lessonId); ?>

                        <input name="title" required placeholder="Título do recurso">
                        <input type="file" name="resource_file" required accept=".pdf,.zip,.txt,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png">
                        <input type="number" min="0" name="sort_order" value="10" title="Ordem">
                        <select name="status"><option value="active">Ativo</option><option value="hidden">Oculto</option></select>
                        <button class="button button-primary">Enviar recurso</button>
                    </form>

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