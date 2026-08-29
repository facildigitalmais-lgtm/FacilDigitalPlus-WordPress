<?php

declare(strict_types=1);

namespace FacilDigital\Core\Students;

use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Entitlements\EntitlementService;
use FacilDigital\Core\PDFs\DownloadModule;
use FacilDigital\Core\PDFs\DownloadRepository;
use FacilDigital\Core\PDFs\PdfFileRepository;
use FacilDigital\Core\Products\ProductMetadata;

final class AccountModule implements ModuleInterface
{
    public const ENDPOINT = 'apostilas';
    private const REWRITE_VERSION = '1.0.0';
    private const REWRITE_OPTION = 'facil_digital_account_rewrite_version';

    public function __construct(
        private readonly EntitlementService $entitlements = new EntitlementService(),
        private readonly PdfFileRepository $files = new PdfFileRepository(),
        private readonly DownloadRepository $downloads = new DownloadRepository()
    ) {
    }

    public function register(): void
    {
        add_action(
            'init',
            [$this, 'registerEndpoint']
        );

        add_action(
            'wp_loaded',
            [$this, 'maybeFlushRewriteRules'],
            50
        );

        add_filter(
            'woocommerce_account_menu_items',
            [$this, 'menuItems']
        );

        add_action(
            'woocommerce_account_' . self::ENDPOINT . '_endpoint',
            [$this, 'renderApostilas']
        );

        add_action(
            'woocommerce_account_dashboard',
            [$this, 'renderDashboard'],
            5
        );
    }

    public function registerEndpoint(): void
    {
        add_rewrite_endpoint(
            self::ENDPOINT,
            EP_ROOT | EP_PAGES
        );
    }

    public function maybeFlushRewriteRules(): void
    {
        if (
            get_option(self::REWRITE_OPTION, '')
            === self::REWRITE_VERSION
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

            if ($key === 'dashboard') {
                $result[self::ENDPOINT] = __(
                    'Minhas apostilas',
                    'facil-digital-core'
                );
            }
        }

        if (!isset($result[self::ENDPOINT])) {
            $result[self::ENDPOINT] = __(
                'Minhas apostilas',
                'facil-digital-core'
            );
        }

        return $result;
    }

    /**
     * @return array{apostilas:int,pdfs_ready:int,downloads:int}
     */
    public function dashboardData(int $userId): array
    {
        return [
            'apostilas' => count(
                $this->entitlements->activeForUser($userId)
            ),
            'pdfs_ready' => count(
                $this->files->readyForUser($userId)
            ),
            'downloads' => $this->downloads->countForUser($userId),
        ];
    }

    public function renderDashboard(): void
    {
        $userId = get_current_user_id();
        if ($userId <= 0) {
            return;
        }

        $data = $this->dashboardData($userId);

        echo '<section class="fd-student-overview" aria-label="';
        echo esc_attr__('Resumo da área do aluno', 'facil-digital-core');
        echo '">';

        $cards = [
            __('Apostilas', 'facil-digital-core') => $data['apostilas'],
            __('PDFs prontos', 'facil-digital-core') => $data['pdfs_ready'],
            __('Downloads realizados', 'facil-digital-core') => $data['downloads'],
        ];

        foreach ($cards as $label => $value) {
            echo '<article class="fd-student-stat">';
            echo '<strong>' . esc_html((string) $value) . '</strong>';
            echo '<span>' . esc_html($label) . '</span>';
            echo '</article>';
        }

        echo '</section>';
    }

    public function renderApostilas(): void
    {
        $userId = get_current_user_id();
        if ($userId <= 0) {
            return;
        }

        $rows = $this->entitlements->activeForUser($userId);

        echo '<section class="fd-student-library">';
        echo '<header class="fd-student-library__header">';
        echo '<h2>' . esc_html__('Minhas apostilas', 'facil-digital-core') . '</h2>';
        echo '<p>' . esc_html__('Seus materiais ficam disponíveis aqui após a confirmação do pagamento e a geração segura do PDF.', 'facil-digital-core') . '</p>';
        echo '</header>';

        if ($rows === []) {
            echo '<div class="woocommerce-info">';
            echo esc_html__('Você ainda não possui apostilas liberadas.', 'facil-digital-core');
            echo '</div></section>';
            return;
        }

        echo '<div class="fd-student-library__grid">';

        foreach ($rows as $entitlement) {
            $productId = (int) ($entitlement['product_id'] ?? 0);
            $product = wc_get_product($productId);
            $version = ProductMetadata::get(
                $productId,
                ProductMetadata::MATERIAL_VERSION,
                '1'
            );
            $pdf = $this->files->findForEntitlementVersion(
                (int) ($entitlement['id'] ?? 0),
                $version
            );

            echo '<article class="fd-student-book">';
            echo '<div class="fd-student-book__content">';
            echo '<h3>' . esc_html(
                $product ? $product->get_name() : __('Apostila', 'facil-digital-core')
            ) . '</h3>';
            echo '<p>';
            echo esc_html__('Pedido', 'facil-digital-core') . ' #';
            echo esc_html((string) ((int) ($entitlement['order_id'] ?? 0)));
            echo '</p>';

            if (
                is_array($pdf)
                && ($pdf['status'] ?? '') === 'ready'
            ) {
                $limit = max(
                    1,
                    (int) ProductMetadata::get(
                        $productId,
                        ProductMetadata::DOWNLOAD_LIMIT,
                        '5'
                    )
                );
                $used = $this->downloads->countForEntitlement(
                    (int) ($entitlement['id'] ?? 0)
                );

                echo '<p class="fd-student-book__status is-ready">';
                echo esc_html__('PDF personalizado pronto', 'facil-digital-core');
                echo '</p>';
                echo '<p>' . esc_html(
                    sprintf(
                        __('Downloads: %1$d de %2$d', 'facil-digital-core'),
                        $used,
                        $limit
                    )
                ) . '</p>';

                if ($used < $limit) {
                    echo '<a class="button alt" href="';
                    echo esc_url(
                        DownloadModule::url(
                            (int) ($pdf['id'] ?? 0)
                        )
                    );
                    echo '">';
                    echo esc_html__('Baixar apostila', 'facil-digital-core');
                    echo '</a>';
                } else {
                    echo '<span class="fd-student-book__status is-limit">';
                    echo esc_html__('Limite de downloads atingido', 'facil-digital-core');
                    echo '</span>';
                }
            } else {
                echo '<p class="fd-student-book__status is-processing">';
                echo esc_html__('PDF em preparação', 'facil-digital-core');
                echo '</p>';
            }

            echo '</div></article>';
        }

        echo '</div></section>';
    }
}
