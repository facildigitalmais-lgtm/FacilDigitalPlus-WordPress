<?php

declare(strict_types=1);

namespace FacilDigital\Core\API;

use FacilDigital\Core\Attempts\AttemptException;
use FacilDigital\Core\Attempts\AttemptRepository;
use FacilDigital\Core\Attempts\AttemptService;
use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Ranking\RankingService;
use FacilDigital\Core\Simulations\SimulationAccessService;
use FacilDigital\Core\Simulations\SimulationRepository;
use FacilDigital\Core\Support\RateLimiter;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class SimulationController implements ModuleInterface
{
    public function __construct(
        private readonly AttemptService $attempts = new AttemptService(),
        private readonly AttemptRepository $attemptRepository = new AttemptRepository(),
        private readonly SimulationRepository $simulations = new SimulationRepository(),
        private readonly SimulationAccessService $access = new SimulationAccessService(),
        private readonly RankingService $ranking = new RankingService(),
        private readonly RateLimiter $limiter = new RateLimiter()
    ) {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('facil-digital/v1', '/simulations', [
            'methods' => 'GET',
            'callback' => [$this, 'listSimulations'],
            'permission_callback' => [$this, 'loggedIn'],
        ]);
        register_rest_route('facil-digital/v1', '/simulations/(?P<id>\d+)/attempts', [
            'methods' => 'POST',
            'callback' => [$this, 'startAttempt'],
            'permission_callback' => [$this, 'loggedIn'],
        ]);
        register_rest_route('facil-digital/v1', '/attempts/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'attemptState'],
            'permission_callback' => [$this, 'loggedIn'],
        ]);
        register_rest_route('facil-digital/v1', '/attempts/(?P<id>\d+)/answers', [
            'methods' => 'POST',
            'callback' => [$this, 'saveAnswer'],
            'permission_callback' => [$this, 'loggedIn'],
        ]);
        register_rest_route('facil-digital/v1', '/attempts/(?P<id>\d+)/finish', [
            'methods' => 'POST',
            'callback' => [$this, 'finishAttempt'],
            'permission_callback' => [$this, 'loggedIn'],
        ]);
        register_rest_route('facil-digital/v1', '/attempts/(?P<id>\d+)/result', [
            'methods' => 'GET',
            'callback' => [$this, 'attemptResult'],
            'permission_callback' => [$this, 'loggedIn'],
        ]);
        register_rest_route('facil-digital/v1', '/me/results', [
            'methods' => 'GET',
            'callback' => [$this, 'myResults'],
            'permission_callback' => [$this, 'loggedIn'],
        ]);
        register_rest_route('facil-digital/v1', '/simulations/(?P<id>\d+)/ranking', [
            'methods' => 'GET',
            'callback' => [$this, 'simulationRanking'],
            'permission_callback' => [$this, 'loggedIn'],
        ]);
    }

    public function loggedIn(): bool|WP_Error
    {
        if (is_user_logged_in()) {
            return true;
        }
        return new WP_Error(
            'rest_not_logged_in',
            __('Faça login para acessar os simulados.', 'facil-digital-core'),
            ['status' => 401]
        );
    }

    public function listSimulations(): WP_REST_Response
    {
        $userId = get_current_user_id();
        $items = [];
        foreach ($this->simulations->list(['status' => 'published'], 500) as $simulation) {
            $id = (int) $simulation['id'];
            if (!$this->access->canAccess($userId, $id)) {
                continue;
            }
            $best = $this->attemptRepository->bestForUserSimulation($userId, $id);
            $items[] = [
                'id' => $id,
                'title' => (string) $simulation['title'],
                'slug' => (string) $simulation['slug'],
                'description' => (string) ($simulation['description'] ?? ''),
                'duration_seconds' => (int) $simulation['duration_seconds'],
                'question_count' => (int) $simulation['question_count'],
                'attempt_limit' => $simulation['attempt_limit'] === null ? null : (int) $simulation['attempt_limit'],
                'attempts_used' => $this->attemptRepository->countForUserSimulation($userId, $id),
                'best_percentage' => is_array($best) ? (float) $best['percentage'] : null,
                'url' => home_url('/simulado/' . rawurlencode((string) $simulation['slug']) . '/'),
            ];
        }
        return new WP_REST_Response(['items' => $items], 200);
    }

    public function startAttempt(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();
        if (!$this->limiter->hit('sim_start', $userId, 20, 300)) {
            return $this->rateError();
        }
        return $this->attemptCall(
            fn (): array => $this->attempts->start($userId, (int) $request['id'])
        );
    }

    public function attemptState(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();
        return $this->attemptCall(
            fn (): array => $this->attempts->state($userId, (int) $request['id'])
        );
    }

    public function saveAnswer(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();
        if (!$this->limiter->hit('sim_answer', $userId, 180, 60)) {
            return $this->rateError();
        }
        $questionId = absint($request->get_param('question_id'));
        $optionRaw = $request->get_param('selected_option_id');
        $optionId = $optionRaw === null || $optionRaw === '' ? null : absint($optionRaw);
        return $this->attemptCall(
            fn (): array => $this->attempts->saveAnswer(
                $userId,
                (int) $request['id'],
                $questionId,
                $optionId
            )
        );
    }

    public function finishAttempt(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();
        if (!$this->limiter->hit('sim_finish', $userId, 30, 300)) {
            return $this->rateError();
        }
        return $this->attemptCall(
            fn (): array => $this->attempts->finish($userId, (int) $request['id'])
        );
    }

    public function attemptResult(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $userId = get_current_user_id();
        return $this->attemptCall(
            fn (): array => $this->attempts->result($userId, (int) $request['id'])
        );
    }

    public function myResults(): WP_REST_Response
    {
        return new WP_REST_Response([
            'items' => $this->attempts->history(get_current_user_id(), 200),
        ], 200);
    }

    public function simulationRanking(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $simulationId = (int) $request['id'];
        $simulation = $this->simulations->findById($simulationId);
        if (!is_array($simulation) || (int) ($simulation['ranking_enabled'] ?? 0) !== 1) {
            return new WP_Error('ranking_disabled', 'Ranking indisponível.', ['status' => 404]);
        }
        if (!$this->access->canAccess(get_current_user_id(), $simulationId)) {
            return new WP_Error('simulation_access_denied', 'Acesso negado.', ['status' => 403]);
        }
        return new WP_REST_Response([
            'items' => $this->ranking->forSimulation($simulationId, 100),
        ], 200);
    }

    /** @param callable():array<string,mixed> $callback */
    private function attemptCall(callable $callback): WP_REST_Response|WP_Error
    {
        try {
            return new WP_REST_Response($callback(), 200);
        } catch (AttemptException $exception) {
            return new WP_Error(
                $exception->errorCode,
                $exception->getMessage(),
                ['status' => $exception->httpStatus]
            );
        } catch (\Throwable $exception) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FD_SIMULATION_ERROR code=' . sanitize_key($exception->getMessage()));
            }
            return new WP_Error(
                'simulation_internal_error',
                __('Não foi possível processar o simulado.', 'facil-digital-core'),
                ['status' => 500]
            );
        }
    }

    private function rateError(): WP_Error
    {
        return new WP_Error(
            'rate_limit_exceeded',
            __('Muitas solicitações. Aguarde um momento.', 'facil-digital-core'),
            ['status' => 429]
        );
    }
}
