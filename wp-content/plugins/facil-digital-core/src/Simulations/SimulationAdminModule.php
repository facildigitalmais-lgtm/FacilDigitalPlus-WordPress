<?php

declare(strict_types=1);

namespace FacilDigital\Core\Simulations;

use FacilDigital\Core\Admin\Menu;
use FacilDigital\Core\Contests\ContestModule;
use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Core\Capabilities;
use FacilDigital\Core\Questions\QuestionRepository;

final class SimulationAdminModule implements ModuleInterface
{
    public const SLUG = 'facil-digital-simulations';

    public function __construct(
        private readonly SimulationRepository $repository = new SimulationRepository(),
        private readonly SimulationService $service = new SimulationService(),
        private readonly QuestionRepository $questions = new QuestionRepository()
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 21);
        add_action('admin_post_fd_simulation_save', [$this, 'save']);
        add_action('admin_post_fd_simulation_status', [$this, 'status']);
        add_action('admin_post_fd_simulation_delete', [$this, 'delete']);
    }

    public function menu(): void
    {
        add_submenu_page(
            Menu::PARENT_SLUG,
            __('Simulados', 'facil-digital-core'),
            __('Simulados', 'facil-digital-core'),
            Capabilities::MANAGE_SIMULATIONS,
            self::SLUG,
            [$this, 'render']
        );
    }

    public function render(): void
    {
        $this->guard();
        $action = sanitize_key((string) ($_GET['action'] ?? ''));
        $id = absint($_GET['id'] ?? 0);
        if ($action === 'new' || ($action === 'edit' && $id > 0)) {
            $this->form($id);
            return;
        }
        $this->listing();
    }

    private function listing(): void
    {
        $search = sanitize_text_field((string) ($_GET['s'] ?? ''));
        $status = sanitize_key((string) ($_GET['status'] ?? ''));
        $rows = $this->repository->list(['search' => $search, 'status' => $status], 100);
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Simulados', 'facil-digital-core'); ?></h1>
            <a class="page-title-action" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'action' => 'new'], admin_url('admin.php'))); ?>"><?php echo esc_html__('Adicionar simulado', 'facil-digital-core'); ?></a>
            <hr class="wp-header-end">
            <form method="get"><input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>"><p class="search-box"><input type="search" name="s" value="<?php echo esc_attr($search); ?>"><select name="status"><option value="">Todos</option><?php foreach (['published' => 'Publicado','draft' => 'Rascunho','inactive' => 'Inativo'] as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select><button class="button">Filtrar</button></p></form>
            <table class="widefat striped"><thead><tr><th>ID</th><th>Título</th><th>Questões</th><th>Duração</th><th>Tentativas</th><th>Status</th><th>Ações</th></tr></thead><tbody>
            <?php if ($rows === []) : ?><tr><td colspan="7">Nenhum simulado encontrado.</td></tr><?php endif; ?>
            <?php foreach ($rows as $row) : $id = (int) $row['id']; ?>
                <tr><td><?php echo esc_html((string) $id); ?></td><td><?php echo esc_html((string) $row['title']); ?></td><td><?php echo esc_html((string) $row['question_count']); ?></td><td><?php echo esc_html((string) ceil((int) $row['duration_seconds'] / 60) . ' min'); ?></td><td><?php echo esc_html($row['attempt_limit'] === null ? '∞' : (string) $row['attempt_limit']); ?></td><td><?php echo esc_html((string) $row['status']); ?></td><td>
                <a href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'action' => 'edit', 'id' => $id], admin_url('admin.php'))); ?>">Editar</a> |
                <a href="<?php echo esc_url(home_url('/simulado/' . rawurlencode((string) $row['slug']) . '/')); ?>" target="_blank" rel="noopener">Ver</a> |
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=fd_simulation_status&id=' . $id . '&status=' . (($row['status'] ?? '') === 'published' ? 'inactive' : 'published')), 'fd_simulation_status_' . $id)); ?>"><?php echo esc_html(($row['status'] ?? '') === 'published' ? 'Desativar' : 'Publicar'); ?></a> |
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=fd_simulation_delete&id=' . $id), 'fd_simulation_delete_' . $id)); ?>" onclick="return confirm('Excluir ou desativar este simulado?')">Excluir</a>
                </td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
        <?php
    }

    private function form(int $id): void
    {
        $row = $id > 0 ? $this->repository->findById($id) : null;
        if ($id > 0 && !is_array($row)) {
            wp_die(esc_html__('Simulado não encontrado.', 'facil-digital-core'));
        }
        $row = is_array($row) ? $row : [
            'title' => '', 'slug' => '', 'description' => '', 'contest_term_id' => 0,
            'position_name' => '', 'duration_seconds' => 7200, 'attempt_limit' => 3,
            'minimum_score' => '60.00', 'show_answer_key' => 1,
            'comment_policy' => 'after_finish', 'ranking_enabled' => 1,
            'selection_mode' => 'manual', 'status' => 'draft', 'question_ids' => [],
        ];
        $selected = array_map('intval', (array) ($row['question_ids'] ?? []));
        $questions = $this->questions->list(['status' => 'active'], 300);
        $terms = get_terms(['taxonomy' => ContestModule::TAXONOMY, 'hide_empty' => false]);
        ?>
        <div class="wrap"><h1><?php echo esc_html($id > 0 ? 'Editar simulado' : 'Adicionar simulado'); ?></h1>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="fd_simulation_save"><input type="hidden" name="id" value="<?php echo esc_attr((string) $id); ?>"><?php wp_nonce_field('fd_simulation_save'); ?>
            <table class="form-table"><tbody>
                <tr><th>Título</th><td><input class="regular-text" name="title" required value="<?php echo esc_attr((string) $row['title']); ?>"></td></tr>
                <tr><th>Slug</th><td><input class="regular-text" name="slug" value="<?php echo esc_attr((string) $row['slug']); ?>"></td></tr>
                <tr><th>Descrição</th><td><textarea class="large-text" rows="4" name="description"><?php echo esc_textarea((string) $row['description']); ?></textarea></td></tr>
                <tr><th>Concurso</th><td><select name="contest_term_id"><option value="0">—</option><?php if (!is_wp_error($terms)) : foreach ($terms as $term) : ?><option value="<?php echo esc_attr((string) $term->term_id); ?>" <?php selected((int) $row['contest_term_id'], (int) $term->term_id); ?>><?php echo esc_html($term->name); ?></option><?php endforeach; endif; ?></select></td></tr>
                <tr><th>Cargo</th><td><input class="regular-text" name="position_name" value="<?php echo esc_attr((string) $row['position_name']); ?>"></td></tr>
                <tr><th>Duração</th><td><input type="number" min="1" max="1440" name="duration_minutes" value="<?php echo esc_attr((string) max(1, (int) ceil((int) $row['duration_seconds'] / 60))); ?>"> minutos</td></tr>
                <tr><th>Tentativas permitidas</th><td><input type="number" min="1" max="1000" name="attempt_limit" value="<?php echo esc_attr($row['attempt_limit'] === null ? '' : (string) $row['attempt_limit']); ?>"><p class="description">Deixe vazio para ilimitado.</p></td></tr>
                <tr><th>Nota mínima</th><td><input type="number" min="0" max="100" step="0.01" name="minimum_score" value="<?php echo esc_attr((string) $row['minimum_score']); ?>">%</td></tr>
                <tr><th>Resultado</th><td><label><input type="checkbox" name="show_answer_key" value="1" <?php checked((int) $row['show_answer_key'], 1); ?>> Exibir gabarito após finalizar</label><br><label><input type="checkbox" name="ranking_enabled" value="1" <?php checked((int) $row['ranking_enabled'], 1); ?>> Participar do ranking</label></td></tr>
                <tr><th>Comentários</th><td><select name="comment_policy"><option value="after_finish" <?php selected($row['comment_policy'], 'after_finish'); ?>>Após finalizar</option><option value="never" <?php selected($row['comment_policy'], 'never'); ?>>Nunca</option></select></td></tr>
                <tr><th>Seleção</th><td><select name="selection_mode"><?php foreach (['manual' => 'Manual','subject' => 'Por disciplina','topic' => 'Por assunto','board' => 'Por banca','random' => 'Aleatória'] as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($row['selection_mode'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select><p><input name="selection_subject" placeholder="Disciplina"><input name="selection_topic" placeholder="Assunto"><input name="selection_board" placeholder="Banca"><input type="number" min="1" max="500" name="question_count" value="<?php echo esc_attr((string) max(1, (int) ($row['question_count'] ?? 20))); ?>" placeholder="Quantidade"></p></td></tr>
                <tr><th>Questões manuais</th><td><div style="max-height:360px;overflow:auto;border:1px solid #ccd0d4;padding:12px;background:#fff"><?php foreach ($questions as $question) : $qid = (int) $question['id']; ?><label style="display:block;margin-bottom:8px"><input type="checkbox" name="question_ids[]" value="<?php echo esc_attr((string) $qid); ?>" <?php checked(in_array($qid, $selected, true)); ?>> #<?php echo esc_html((string) $qid); ?> — <?php echo esc_html(wp_trim_words((string) $question['statement'], 16)); ?> <em><?php echo esc_html((string) ($question['subject'] ?? '')); ?></em></label><?php endforeach; ?></div></td></tr>
                <tr><th>Status</th><td><select name="status"><option value="draft" <?php selected($row['status'], 'draft'); ?>>Rascunho</option><option value="published" <?php selected($row['status'], 'published'); ?>>Publicado</option><option value="inactive" <?php selected($row['status'], 'inactive'); ?>>Inativo</option></select></td></tr>
            </tbody></table><?php submit_button($id > 0 ? 'Atualizar simulado' : 'Criar simulado'); ?>
        </form></div>
        <?php
    }

    public function save(): void
    {
        $this->guard();
        check_admin_referer('fd_simulation_save');
        $id = absint($_POST['id'] ?? 0);
        $attemptLimitRaw = isset($_POST['attempt_limit']) ? trim((string) wp_unslash($_POST['attempt_limit'])) : '';
        $payload = [
            'title' => wp_unslash((string) ($_POST['title'] ?? '')),
            'slug' => wp_unslash((string) ($_POST['slug'] ?? '')),
            'description' => wp_unslash((string) ($_POST['description'] ?? '')),
            'contest_term_id' => absint($_POST['contest_term_id'] ?? 0),
            'position_name' => wp_unslash((string) ($_POST['position_name'] ?? '')),
            'duration_seconds' => max(1, absint($_POST['duration_minutes'] ?? 120)) * 60,
            'attempt_limit' => $attemptLimitRaw === '' ? null : absint($attemptLimitRaw),
            'minimum_score' => (float) ($_POST['minimum_score'] ?? 0),
            'show_answer_key' => isset($_POST['show_answer_key']),
            'comment_policy' => sanitize_key((string) ($_POST['comment_policy'] ?? 'after_finish')),
            'ranking_enabled' => isset($_POST['ranking_enabled']),
            'selection_mode' => sanitize_key((string) ($_POST['selection_mode'] ?? 'manual')),
            'question_count' => absint($_POST['question_count'] ?? 20),
            'selection_subject' => wp_unslash((string) ($_POST['selection_subject'] ?? '')),
            'selection_topic' => wp_unslash((string) ($_POST['selection_topic'] ?? '')),
            'selection_board' => wp_unslash((string) ($_POST['selection_board'] ?? '')),
            'question_ids' => is_array($_POST['question_ids'] ?? null) ? array_map('absint', wp_unslash($_POST['question_ids'])) : [],
            'status' => sanitize_key((string) ($_POST['status'] ?? 'draft')),
        ];
        try {
            if ($id > 0) {
                $this->service->update($id, $payload, get_current_user_id());
            } else {
                $id = $this->service->create($payload, get_current_user_id());
            }
            $this->redirect(['updated' => 1, 'action' => 'edit', 'id' => $id]);
        } catch (\Throwable $exception) {
            $this->redirect(['fd_error' => sanitize_key($exception->getMessage()), 'action' => $id > 0 ? 'edit' : 'new', 'id' => $id]);
        }
    }

    public function status(): void
    {
        $this->guard();
        $id = absint($_GET['id'] ?? 0);
        check_admin_referer('fd_simulation_status_' . $id);
        $this->service->setStatus($id, sanitize_key((string) ($_GET['status'] ?? 'inactive')));
        $this->redirect(['updated' => 1]);
    }

    public function delete(): void
    {
        $this->guard();
        $id = absint($_GET['id'] ?? 0);
        check_admin_referer('fd_simulation_delete_' . $id);
        $deleted = $this->service->delete($id);
        $this->redirect([$deleted ? 'deleted' : 'deactivated' => 1]);
    }

    private function redirect(array $args): never
    {
        wp_safe_redirect(add_query_arg(array_merge(['page' => self::SLUG], $args), admin_url('admin.php')));
        exit;
    }

    private function guard(): void
    {
        if (!current_user_can(Capabilities::MANAGE_SIMULATIONS)) {
            wp_die(esc_html__('Acesso negado.', 'facil-digital-core'));
        }
    }
}
