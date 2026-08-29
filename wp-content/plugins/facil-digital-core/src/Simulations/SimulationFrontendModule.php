<?php

declare(strict_types=1);

namespace FacilDigital\Core\Simulations;

use FacilDigital\Core\Attempts\AttemptRepository;
use FacilDigital\Core\Contracts\ModuleInterface;

final class SimulationFrontendModule implements ModuleInterface
{
    private const REWRITE_VERSION = '1.0.0';
    private const REWRITE_OPTION = 'facil_digital_simulation_rewrite_version';

    public function __construct(
        private readonly SimulationRepository $simulations = new SimulationRepository(),
        private readonly SimulationAccessService $access = new SimulationAccessService(),
        private readonly AttemptRepository $attempts = new AttemptRepository()
    ) {
    }

    public function register(): void
    {
        add_action('init', [$this, 'rewrite']);
        add_filter('query_vars', [$this, 'queryVars']);
        add_action('wp_loaded', [$this, 'maybeFlush'], 50);
        add_filter('template_include', [$this, 'template'], 50);
        add_action('wp_enqueue_scripts', [$this, 'localize'], 20);
    }

    public function rewrite(): void
    {
        add_rewrite_rule(
            '^simulado/([^/]+)/?$',
            'index.php?fd_simulation=$matches[1]',
            'top'
        );
    }

    /** @param list<string> $vars @return list<string> */
    public function queryVars(array $vars): array
    {
        $vars[] = 'fd_simulation';
        return array_values(array_unique($vars));
    }

    public function maybeFlush(): void
    {
        if (get_option(self::REWRITE_OPTION, '') === self::REWRITE_VERSION) {
            return;
        }
        flush_rewrite_rules(false);
        update_option(self::REWRITE_OPTION, self::REWRITE_VERSION, false);
    }

    public function template(string $template): string
    {
        $slug = sanitize_title((string) get_query_var('fd_simulation'));
        if ($slug === '') {
            return $template;
        }
        $simulation = $this->simulations->findBySlug($slug);
        if (!is_array($simulation) || ($simulation['status'] ?? '') !== 'published') {
            global $wp_query;
            if ($wp_query instanceof \WP_Query) {
                $wp_query->set_404();
            }
            status_header(404);
            return get_404_template() ?: $template;
        }

        $userId = get_current_user_id();
        $best = $userId > 0
            ? $this->attempts->bestForUserSimulation($userId, (int) $simulation['id'])
            : null;
        $GLOBALS['fd_simulation_page_data'] = [
            'id' => (int) $simulation['id'],
            'title' => (string) $simulation['title'],
            'description' => (string) ($simulation['description'] ?? ''),
            'question_count' => (int) $simulation['question_count'],
            'duration_seconds' => (int) $simulation['duration_seconds'],
            'attempt_limit' => $simulation['attempt_limit'] === null ? null : (int) $simulation['attempt_limit'],
            'attempts_used' => $userId > 0 ? $this->attempts->countForUserSimulation($userId, (int) $simulation['id']) : 0,
            'best_percentage' => is_array($best) ? (float) $best['percentage'] : null,
            'can_access' => $userId > 0 && $this->access->canAccess($userId, (int) $simulation['id']),
            'logged_in' => $userId > 0,
            'login_url' => wc_get_page_permalink('myaccount'),
        ];

        $themeTemplate = locate_template('templates/simulation.php');
        return $themeTemplate !== '' ? $themeTemplate : $template;
    }

    public function localize(): void
    {
        $slug = sanitize_title((string) get_query_var('fd_simulation'));
        if ($slug === '' || !wp_script_is('fd-simulation', 'enqueued')) {
            return;
        }
        $simulation = $this->simulations->findBySlug($slug);
        if (!is_array($simulation)) {
            return;
        }
        wp_localize_script('fd-simulation', 'fdSimulationConfig', [
            'simulationId' => (int) $simulation['id'],
            'restRoot' => esc_url_raw(rest_url('facil-digital/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'messages' => [
                'saving' => __('Salvando...', 'facil-digital-core'),
                'saved' => __('Resposta salva.', 'facil-digital-core'),
                'finishConfirm' => __('Deseja finalizar o simulado agora?', 'facil-digital-core'),
                'genericError' => __('Não foi possível processar a solicitação.', 'facil-digital-core'),
            ],
        ]);
    }
}
