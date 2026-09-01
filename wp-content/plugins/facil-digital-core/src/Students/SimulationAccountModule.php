<?php

declare(strict_types=1);

namespace FacilDigital\Core\Students;

use FacilDigital\Core\Attempts\AttemptRepository;
use FacilDigital\Core\Attempts\AttemptService;
use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Ranking\RankingService;
use FacilDigital\Core\Simulations\SimulationAccessService;
use FacilDigital\Core\Simulations\SimulationRepository;

final class SimulationAccountModule implements ModuleInterface
{
    public const SIMULATIONS_ENDPOINT = 'simulados';
    public const RESULTS_ENDPOINT = 'resultados';
    private const REWRITE_VERSION = '1.0.0';
    private const REWRITE_OPTION = 'facil_digital_sim_account_rewrite_version';

    public function __construct(
        private readonly SimulationRepository $simulations = new SimulationRepository(),
        private readonly SimulationAccessService $access = new SimulationAccessService(),
        private readonly AttemptRepository $attemptRepository = new AttemptRepository(),
        private readonly AttemptService $attempts = new AttemptService(),
        private readonly RankingService $ranking = new RankingService()
    ) {
    }

    public function register(): void
    {
        add_action('init', [$this, 'registerEndpoints']);
        add_action('wp_loaded', [$this, 'maybeFlush'], 50);
        add_filter('woocommerce_account_menu_items', [$this, 'menuItems'], 20);
        add_action('woocommerce_account_' . self::SIMULATIONS_ENDPOINT . '_endpoint', [$this, 'renderSimulations']);
        add_action('woocommerce_account_' . self::RESULTS_ENDPOINT . '_endpoint', [$this, 'renderResults']);
        add_action('woocommerce_account_dashboard', [$this, 'renderDashboard'], 15);
    }

    public function registerEndpoints(): void
    {
        add_rewrite_endpoint(self::SIMULATIONS_ENDPOINT, EP_ROOT | EP_PAGES);
        add_rewrite_endpoint(self::RESULTS_ENDPOINT, EP_ROOT | EP_PAGES);
    }

    public function maybeFlush(): void
    {
        if (get_option(self::REWRITE_OPTION, '') === self::REWRITE_VERSION) {
            return;
        }
        flush_rewrite_rules(false);
        update_option(self::REWRITE_OPTION, self::REWRITE_VERSION, false);
    }

    /** @param array<string,string> $items @return array<string,string> */
    public function menuItems(array $items): array
    {
        $result = [];
        $inserted = false;
        foreach ($items as $key => $label) {
            $result[$key] = $label;
            if ($key === AccountModule::ENDPOINT || (!$inserted && $key === 'dashboard' && !isset($items[AccountModule::ENDPOINT]))) {
                $result[self::SIMULATIONS_ENDPOINT] = __('Simulados', 'facil-digital-core');
                $result[self::RESULTS_ENDPOINT] = __('Resultados', 'facil-digital-core');
                $inserted = true;
            }
        }
        if (!$inserted) {
            $result[self::SIMULATIONS_ENDPOINT] = __('Simulados', 'facil-digital-core');
            $result[self::RESULTS_ENDPOINT] = __('Resultados', 'facil-digital-core');
        }
        return $result;
    }

    /** @return array{simulations:int,average:float,ranking:?int} */
    public function dashboardData(int $userId): array
    {
        $available = count($this->availableForUser($userId));
        $history = $this->attempts->history($userId, 500);
        $average = 0.0;
        if ($history !== []) {
            $average = round(
                array_sum(array_map(static fn (array $row): float => (float) $row['percentage'], $history)) / count($history),
                1
            );
        }
        return [
            'simulations' => $available,
            'average' => $average,
            'ranking' => $this->ranking->generalPositionForUser($userId),
        ];
    }

    public function renderDashboard(): void
    {
        $userId = get_current_user_id();
        if ($userId <= 0) {
            return;
        }
        $data = $this->dashboardData($userId);
        echo '<section class="fd-student-overview fd-student-overview--learning" aria-label="';
        echo esc_attr__('Desempenho em simulados', 'facil-digital-core');
        echo '">';
        $cards = [
            __('Simulados disponíveis', 'facil-digital-core') => (string) $data['simulations'],
            __('Média', 'facil-digital-core') => number_format_i18n($data['average'], 1) . '%',
            __('Ranking geral', 'facil-digital-core') => $data['ranking'] === null ? '—' : $data['ranking'] . 'º',
        ];
        foreach ($cards as $label => $value) {
            echo '<article class="fd-student-stat"><strong>' . esc_html($value) . '</strong><span>' . esc_html($label) . '</span></article>';
        }
        echo '</section>';
    }

    public function renderSimulations(): void
    {
        $userId = get_current_user_id();
        if ($userId <= 0) {
            return;
        }
        $items = $this->availableForUser($userId);
        echo '<section class="fd-student-library"><header class="fd-student-library__header">';
        echo '<h2>' . esc_html__('Simulados', 'facil-digital-core') . '</h2>';
        echo '<p>' . esc_html__('Treine com cronômetro, autosave e correção pelo servidor.', 'facil-digital-core') . '</p></header>';
        if ($items === []) {
            echo '<div class="woocommerce-info fd-student-empty-state" role="status">' . esc_html__('Nenhum simulado está liberado para suas apostilas.', 'facil-digital-core') . '</div></section>';
            return;
        }
        echo '<div class="fd-student-library__grid">';
        foreach ($items as $simulation) {
            $id = (int) $simulation['id'];
            $best = $this->attemptRepository->bestForUserSimulation($userId, $id);
            echo '<article class="fd-student-book"><div class="fd-student-book__content">';
            echo '<h3>' . esc_html((string) $simulation['title']) . '</h3>';
            echo '<p>' . esc_html(sprintf('%d questões · %d min', (int) $simulation['question_count'], (int) ceil((int) $simulation['duration_seconds'] / 60))) . '</p>';
            if (is_array($best)) {
                echo '<p>' . esc_html(sprintf(__('Melhor resultado: %s%%', 'facil-digital-core'), number_format_i18n((float) $best['percentage'], 1))) . '</p>';
            }
            echo '<a class="button alt" href="' . esc_url(home_url('/simulado/' . rawurlencode((string) $simulation['slug']) . '/')) . '">';
            echo esc_html__('Acessar simulado', 'facil-digital-core') . '</a>';
            echo '</div></article>';
        }
        echo '</div></section>';
    }

    public function renderResults(): void
    {
        $userId =
            get_current_user_id();

        if ($userId <= 0) {
            return;
        }

        $history =
            $this->attempts->history(
                $userId,
                200
            );

        echo '<section class="fd-student-results">';

        echo '<header class="fd-student-library__header">';
        echo '<span class="fd-student-eyebrow">';
        echo esc_html__(
            'Seu desempenho',
            'facil-digital-core'
        );
        echo '</span>';

        echo '<h2>';
        echo esc_html__(
            'Resultados',
            'facil-digital-core'
        );
        echo '</h2>';

        echo '<p>';
        echo esc_html__(
            'Consulte o histórico dos simulados que você já finalizou.',
            'facil-digital-core'
        );
        echo '</p>';
        echo '</header>';

        if ($history === []) {
            echo '<div class="woocommerce-info fd-student-empty-state" role="status">';
            echo esc_html__(
                'Você ainda não finalizou simulados.',
                'facil-digital-core'
            );
            echo '</div>';

            echo '</section>';
            return;
        }

        echo '<div class="fd-results-table-wrap">';

        echo '<table class="shop_table shop_table_responsive fd-results-table" aria-label="';
        echo esc_attr__(
            'Histórico de resultados',
            'facil-digital-core'
        );
        echo '">';

        echo '<thead><tr>';

        echo '<th scope="col">';
        echo esc_html__(
            'Simulado',
            'facil-digital-core'
        );
        echo '</th>';

        echo '<th scope="col">';
        echo esc_html__(
            'Resultado',
            'facil-digital-core'
        );
        echo '</th>';

        echo '<th scope="col">';
        echo esc_html__(
            'Tempo',
            'facil-digital-core'
        );
        echo '</th>';

        echo '<th scope="col">';
        echo esc_html__(
            'Status',
            'facil-digital-core'
        );
        echo '</th>';

        echo '</tr></thead><tbody>';

        foreach ($history as $row) {
            $url =
                home_url(
                    '/simulado/'
                    . rawurlencode(
                        (string) $row['slug']
                    )
                    . '/'
                );

            $result =
                number_format_i18n(
                    (float) $row['percentage'],
                    1
                )
                . '%';

            $status =
                !empty($row['passed'])
                    ? __(
                        'Aprovado',
                        'facil-digital-core'
                    )
                    : __(
                        'Abaixo da nota mínima',
                        'facil-digital-core'
                    );

            echo '<tr>';

            echo '<th scope="row" data-title="';
            echo esc_attr__(
                'Simulado',
                'facil-digital-core'
            );
            echo '">';

            echo '<a href="';
            echo esc_url($url);
            echo '">';
            echo esc_html(
                (string) $row['title']
            );
            echo '</a>';

            echo '</th>';

            echo '<td data-title="';
            echo esc_attr__(
                'Resultado',
                'facil-digital-core'
            );
            echo '">';
            echo esc_html($result);
            echo '</td>';

            echo '<td data-title="';
            echo esc_attr__(
                'Tempo',
                'facil-digital-core'
            );
            echo '">';
            echo esc_html(
                $this->time(
                    (int) $row[
                        'elapsed_seconds'
                    ]
                )
            );
            echo '</td>';

            echo '<td data-title="';
            echo esc_attr__(
                'Status',
                'facil-digital-core'
            );
            echo '">';
            echo esc_html($status);
            echo '</td>';

            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
        echo '</section>';
    }

    /** @return list<array<string,mixed>> */
    private function availableForUser(int $userId): array
    {
        $items = [];
        foreach ($this->simulations->list(['status' => 'published'], 500) as $simulation) {
            if ($this->access->canAccess($userId, (int) $simulation['id'])) {
                $items[] = $simulation;
            }
        }
        return $items;
    }

    private function time(int $seconds): string
    {
        $seconds = max(0, $seconds);
        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
}
