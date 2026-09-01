<?php

declare(strict_types=1);

namespace FacilDigital\Core\Questions;

use FacilDigital\Core\Admin\Menu;
use FacilDigital\Core\Contests\ContestModule;
use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Core\Capabilities;

final class QuestionAdminModule implements ModuleInterface
{
    public const SLUG = 'facil-digital-questions';

    public function __construct(
        private readonly QuestionRepository $repository = new QuestionRepository(),
        private readonly QuestionService $service = new QuestionService()
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 20);
        add_action('admin_post_fd_question_save', [$this, 'save']);
        add_action('admin_post_fd_question_duplicate', [$this, 'duplicate']);
        add_action('admin_post_fd_question_status', [$this, 'status']);
        add_action('admin_post_fd_question_delete', [$this, 'delete']);
    }

    public function menu(): void
    {
        add_submenu_page(
            Menu::PARENT_SLUG,
            __('Banco de Questões', 'facil-digital-core'),
            __('Banco de Questões', 'facil-digital-core'),
            Capabilities::MANAGE_QUESTIONS,
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
        $subject = sanitize_text_field((string) ($_GET['subject'] ?? ''));
        $rows = $this->repository->list([
            'search' => $search,
            'status' => $status,
            'subject' => $subject,
        ], 100, 0);
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Banco de Questões', 'facil-digital-core'); ?></h1>
            <a class="page-title-action" href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'action' => 'new'], admin_url('admin.php'))); ?>"><?php echo esc_html__('Adicionar questão', 'facil-digital-core'); ?></a>
            <hr class="wp-header-end">
            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>">
                <p class="search-box">
                    <label class="screen-reader-text" for="fd-question-search"><?php echo esc_html__('Buscar questões', 'facil-digital-core'); ?></label>
                    <input id="fd-question-search" type="search" name="s" value="<?php echo esc_attr($search); ?>">
                    <select name="status"><option value=""><?php echo esc_html__('Todos os status', 'facil-digital-core'); ?></option><?php foreach (['active' => 'Ativa', 'draft' => 'Rascunho', 'inactive' => 'Inativa'] as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select>
                    <input type="text" name="subject" placeholder="Disciplina" value="<?php echo esc_attr($subject); ?>">
                    <button class="button"><?php echo esc_html__('Filtrar', 'facil-digital-core'); ?></button>
                </p>
            </form>
            <table class="widefat striped"><thead><tr><th>ID</th><th><?php echo esc_html__('Questão', 'facil-digital-core'); ?></th><th><?php echo esc_html__('Disciplina', 'facil-digital-core'); ?></th><th><?php echo esc_html__('Banca', 'facil-digital-core'); ?></th><th><?php echo esc_html__('Status', 'facil-digital-core'); ?></th><th><?php echo esc_html__('Ações', 'facil-digital-core'); ?></th></tr></thead><tbody>
            <?php if ($rows === []) : ?><tr><td colspan="6"><?php echo esc_html__('Nenhuma questão encontrada.', 'facil-digital-core'); ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $row) : $qid = (int) $row['id']; ?>
                <tr>
                    <td><?php echo esc_html((string) $qid); ?></td>
                    <td><?php echo esc_html(wp_trim_words((string) $row['statement'], 18)); ?></td>
                    <td><?php echo esc_html((string) ($row['subject'] ?? '')); ?></td>
                    <td><?php echo esc_html((string) ($row['board'] ?? '')); ?></td>
                    <td><?php echo esc_html((string) $row['status']); ?></td>
                    <td>
                        <a href="<?php echo esc_url(add_query_arg(['page' => self::SLUG, 'action' => 'edit', 'id' => $qid], admin_url('admin.php'))); ?>"><?php echo esc_html__('Editar', 'facil-digital-core'); ?></a> |
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=fd_question_duplicate&id=' . $qid), 'fd_question_duplicate_' . $qid)); ?>"><?php echo esc_html__('Duplicar', 'facil-digital-core'); ?></a> |
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=fd_question_status&id=' . $qid . '&status=' . (($row['status'] ?? '') === 'active' ? 'inactive' : 'active')), 'fd_question_status_' . $qid)); ?>"><?php echo esc_html(($row['status'] ?? '') === 'active' ? __('Desativar', 'facil-digital-core') : __('Ativar', 'facil-digital-core')); ?></a> |
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=fd_question_delete&id=' . $qid), 'fd_question_delete_' . $qid)); ?>" onclick="return confirm('Excluir ou desativar esta questão?')"><?php echo esc_html__('Excluir', 'facil-digital-core'); ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
        <?php
    }

    private function form(int $id): void
    {
        $row = $id > 0 ? $this->repository->find($id) : null;
        if ($id > 0 && !is_array($row)) {
            wp_die(esc_html__('Questão não encontrada.', 'facil-digital-core'));
        }
        $row = is_array($row) ? $row : [
            'question_type' => 'multiple_choice', 'statement' => '', 'explanation' => '',
            'board' => '', 'position_name' => '', 'subject' => '', 'topic' => '',
            'difficulty' => 'medium', 'exam_year' => '', 'status' => 'active',
            'contest_term_id' => 0, 'image_attachment_id' => 0, 'options' => [],
        ];
        $optionMap = [];
        foreach ((array) ($row['options'] ?? []) as $option) {
            if (is_array($option)) {
                $optionMap[(string) $option['option_key']] = $option;
            }
        }
        $terms = get_terms(['taxonomy' => ContestModule::TAXONOMY, 'hide_empty' => false]);
        ?>
        <div class="wrap"><h1><?php echo esc_html($id > 0 ? __('Editar questão', 'facil-digital-core') : __('Adicionar questão', 'facil-digital-core')); ?></h1>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="fd_question_save"><input type="hidden" name="id" value="<?php echo esc_attr((string) $id); ?>">
            <?php wp_nonce_field('fd_question_save'); ?>
            <table class="form-table"><tbody>
                <tr><th><label for="question_type">Tipo</label></th><td><select id="question_type" name="question_type"><option value="multiple_choice" <?php selected($row['question_type'], 'multiple_choice'); ?>>A/B/C/D/E</option><option value="true_false" <?php selected($row['question_type'], 'true_false'); ?>>Certo/Errado</option></select><p class="description">Para Certo/Errado, use C ou E como resposta correta.</p></td></tr>
                <tr><th><label for="contest_term_id">Concurso</label></th><td><select id="contest_term_id" name="contest_term_id"><option value="0">—</option><?php if (!is_wp_error($terms)) : foreach ($terms as $term) : ?><option value="<?php echo esc_attr((string) $term->term_id); ?>" <?php selected((int) $row['contest_term_id'], (int) $term->term_id); ?>><?php echo esc_html($term->name); ?></option><?php endforeach; endif; ?></select></td></tr>
                <?php foreach (['position_name' => 'Cargo', 'subject' => 'Disciplina', 'topic' => 'Assunto', 'board' => 'Banca', 'exam_year' => 'Ano', 'image_attachment_id' => 'ID da imagem'] as $key => $label) : ?><tr><th><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th><td><input class="regular-text" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr((string) ($row[$key] ?? '')); ?>"></td></tr><?php endforeach; ?>
                <tr><th><label for="difficulty">Dificuldade</label></th><td><select id="difficulty" name="difficulty"><?php foreach (['easy' => 'Fácil', 'medium' => 'Média', 'hard' => 'Difícil'] as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($row['difficulty'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></td></tr>
                <tr><th><label for="statement">Enunciado</label></th><td><textarea class="large-text" rows="6" id="statement" name="statement" required><?php echo esc_textarea((string) $row['statement']); ?></textarea></td></tr>
                <tr><th><label for="explanation">Comentário</label></th><td><textarea class="large-text" rows="5" id="explanation" name="explanation"><?php echo esc_textarea((string) $row['explanation']); ?></textarea></td></tr>
                <tr><th>Alternativas</th><td><?php foreach (['A','B','C','D','E'] as $index => $key) : $option = $optionMap[$key] ?? []; ?><p><label><input type="radio" name="correct_key" value="<?php echo esc_attr($key); ?>" <?php checked((int) ($option['is_correct'] ?? 0), 1); ?>> <strong><?php echo esc_html($key); ?></strong></label> <textarea name="option_<?php echo esc_attr(strtolower($key)); ?>" rows="2" style="width:80%;vertical-align:middle"><?php echo esc_textarea((string) ($option['option_text'] ?? '')); ?></textarea></p><?php endforeach; ?></td></tr>
                <tr><th><label for="status">Status</label></th><td><select id="status" name="status"><?php foreach (['active' => 'Ativa', 'draft' => 'Rascunho', 'inactive' => 'Inativa'] as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($row['status'], $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></td></tr>
            </tbody></table>
            <?php submit_button($id > 0 ? __('Atualizar questão', 'facil-digital-core') : __('Criar questão', 'facil-digital-core')); ?>
        </form></div>
        <?php
    }

    public function save(): void
    {
        $this->guard();
        check_admin_referer('fd_question_save');
        $id = absint($_POST['id'] ?? 0);
        $type = sanitize_key((string) ($_POST['question_type'] ?? 'multiple_choice'));
        $correctKey = strtoupper(sanitize_key((string) ($_POST['correct_key'] ?? '')));
        $options = [];
        foreach (['A','B','C','D','E'] as $index => $key) {
            $field = 'option_' . strtolower($key);
            $text = sanitize_textarea_field((string) wp_unslash($_POST[$field] ?? ''));
            if ($type === 'true_false' && !in_array($key, ['C','E'], true)) {
                continue;
            }
            if ($type === 'true_false') {
                $text = $key === 'C' ? 'Certo' : 'Errado';
            }
            if ($text === '') {
                continue;
            }
            $options[] = ['option_key' => $key, 'option_text' => $text, 'is_correct' => $key === $correctKey, 'sort_order' => $index];
        }
        $payload = [
            'question_type' => $type,
            'contest_term_id' => absint($_POST['contest_term_id'] ?? 0),
            'position_name' => wp_unslash((string) ($_POST['position_name'] ?? '')),
            'subject' => wp_unslash((string) ($_POST['subject'] ?? '')),
            'topic' => wp_unslash((string) ($_POST['topic'] ?? '')),
            'board' => wp_unslash((string) ($_POST['board'] ?? '')),
            'exam_year' => absint($_POST['exam_year'] ?? 0),
            'image_attachment_id' => absint($_POST['image_attachment_id'] ?? 0),
            'difficulty' => sanitize_key((string) ($_POST['difficulty'] ?? 'medium')),
            'statement' => wp_unslash((string) ($_POST['statement'] ?? '')),
            'explanation' => wp_unslash((string) ($_POST['explanation'] ?? '')),
            'status' => sanitize_key((string) ($_POST['status'] ?? 'active')),
            'options' => $options,
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

    public function duplicate(): void
    {
        $this->guard();
        $id = absint($_GET['id'] ?? 0);
        check_admin_referer('fd_question_duplicate_' . $id);
        $newId = $this->service->duplicate($id, get_current_user_id());
        $this->redirect(['action' => 'edit', 'id' => $newId, 'duplicated' => 1]);
    }

    public function status(): void
    {
        $this->guard();
        $id = absint($_GET['id'] ?? 0);
        check_admin_referer('fd_question_status_' . $id);
        $this->service->setStatus($id, sanitize_key((string) ($_GET['status'] ?? 'inactive')));
        $this->redirect(['updated' => 1]);
    }

    public function delete(): void
    {
        $this->guard();
        $id = absint($_GET['id'] ?? 0);
        check_admin_referer('fd_question_delete_' . $id);
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
        if (!current_user_can(Capabilities::MANAGE_QUESTIONS)) {
            wp_die(esc_html__('Acesso negado.', 'facil-digital-core'));
        }
    }
}
