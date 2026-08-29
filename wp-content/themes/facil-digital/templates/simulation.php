<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$data = $GLOBALS['fd_simulation_page_data'] ?? null;
if (!is_array($data)) {
    status_header(404);
    get_header();
    echo '<main class="fd-shell"><p>Simulado não encontrado.</p></main>';
    get_footer();
    return;
}

get_header();
?>
<main class="fd-simulation-page">
    <div class="fd-shell">
        <section class="fd-simulation-hero">
            <div>
                <span class="fd-simulation-eyebrow">Fácil Digital+ · Simulado</span>
                <h1><?php echo esc_html((string) $data['title']); ?></h1>
                <?php if ((string) $data['description'] !== '') : ?>
                    <p><?php echo esc_html((string) $data['description']); ?></p>
                <?php endif; ?>
            </div>
            <div class="fd-simulation-meta" aria-label="Informações do simulado">
                <div><strong><?php echo esc_html((string) $data['question_count']); ?></strong><span>questões</span></div>
                <div><strong><?php echo esc_html((string) ceil((int) $data['duration_seconds'] / 60)); ?></strong><span>minutos</span></div>
                <div><strong><?php echo esc_html((string) $data['attempts_used']); ?></strong><span>tentativas</span></div>
                <div><strong><?php echo esc_html($data['best_percentage'] === null ? '—' : number_format_i18n((float) $data['best_percentage'], 1) . '%'); ?></strong><span>melhor resultado</span></div>
            </div>
        </section>

        <?php if (!$data['logged_in']) : ?>
            <section class="fd-simulation-access"><h2>Entre para realizar o simulado</h2><p>Seu acesso é validado pela compra das apostilas vinculadas.</p><a class="button alt" href="<?php echo esc_url((string) $data['login_url']); ?>">Entrar na minha conta</a></section>
        <?php elseif (!$data['can_access']) : ?>
            <section class="fd-simulation-access is-denied"><h2>Simulado não liberado</h2><p>Este simulado exige uma apostila com entitlement ativo para o mesmo concurso e cargo.</p></section>
        <?php else : ?>
            <section
                id="fd-simulation-app"
                class="fd-simulation-app"
                data-simulation-id="<?php echo esc_attr((string) $data['id']); ?>"
            >
                <div id="fd-simulation-start" class="fd-simulation-start">
                    <h2>Pronto para começar?</h2>
                    <p>O tempo é controlado pelo servidor. Suas respostas são salvas automaticamente.</p>
                    <button id="fd-simulation-start-button" class="button alt" type="button">Iniciar ou continuar simulado</button>
                </div>
                <div id="fd-simulation-workspace" class="fd-simulation-workspace" hidden>
                    <header class="fd-simulation-toolbar">
                        <div><strong id="fd-simulation-progress">Questão 1</strong><span id="fd-simulation-save-status" aria-live="polite"></span></div>
                        <time id="fd-simulation-timer" class="fd-simulation-timer">00:00:00</time>
                    </header>
                    <article class="fd-simulation-question">
                        <p id="fd-simulation-subject" class="fd-simulation-subject"></p>
                        <h2 id="fd-simulation-statement"></h2>
                        <div id="fd-simulation-options" class="fd-simulation-options"></div>
                    </article>
                    <nav class="fd-simulation-navigation" aria-label="Navegação entre questões">
                        <button id="fd-simulation-prev" type="button" class="button">Anterior</button>
                        <div id="fd-simulation-numbers" class="fd-simulation-numbers"></div>
                        <button id="fd-simulation-next" type="button" class="button">Próxima</button>
                    </nav>
                    <div class="fd-simulation-actions"><button id="fd-simulation-finish" type="button" class="button alt">Finalizar simulado</button></div>
                </div>
                <section id="fd-simulation-result" class="fd-simulation-result" hidden aria-live="polite"></section>
                <p id="fd-simulation-error" class="fd-simulation-error" hidden aria-live="assertive"></p>
            </section>
        <?php endif; ?>
    </div>
</main>
<?php
get_footer();
