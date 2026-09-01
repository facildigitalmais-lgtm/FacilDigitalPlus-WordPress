<?php

declare(strict_types=1);

namespace FacilDigital\Core\Admin;

use FacilDigital\Core\PDFs\PrivateStorage;
use FacilDigital\Core\Products\ProductMetadata;
use Throwable;
use WC_Product;

/**
 * ADMIN-C - operacao relatorios e readiness.
 *
 * Camada administrativa predominantemente de leitura.
 *
 * Responsabilidades:
 * - consolidar resultados/tentativas sem substituir o Core;
 * - consolidar PDFs, downloads e entitlements;
 * - mostrar readiness de apostilas, simulados e ambiente;
 * - oferecer atalhos para as telas tecnicas ja existentes.
 *
 * Nao altera:
 * - pedidos/status/pagamentos;
 * - resultados/ranking;
 * - PDFs/downloads;
 * - entitlements;
 * - simulados/questoes;
 * - configuracoes do ambiente.
 */
final class AdminOperationsModule
{
    private const PARENT_PAGE = 'facil-digital';

    private const PAGE = 'facil-digital-operacao';

    private const CAPABILITY = 'manage_options';

    public function register(): void
    {
        add_action(
            'admin_menu',
            [$this, 'registerMenu'],
            125
        );

        add_action(
            'admin_menu',
            [$this, 'organizeMenu'],
            160
        );

        add_action(
            'admin_enqueue_scripts',
            [$this, 'enqueueAssets'],
            45
        );
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            self::PARENT_PAGE,
            __(
                'Operação e Relatórios',
                'facil-digital-core'
            ),
            __(
                'Operação',
                'facil-digital-core'
            ),
            self::CAPABILITY,
            self::PAGE,
            [$this, 'render']
        );
    }

    public function organizeMenu(): void
    {
        global $submenu;

        if (
            !isset($submenu[self::PARENT_PAGE])
            || !is_array($submenu[self::PARENT_PAGE])
        ) {
            return;
        }

        $priority = [];
        $remaining = [];

        foreach ($submenu[self::PARENT_PAGE] as $item) {
            $slug =
                isset($item[2])
                ? (string) $item[2]
                : '';

            $title =
                isset($item[0])
                ? $this->normalize(
                    wp_strip_all_tags(
                        (string) $item[0]
                    )
                )
                : '';

            $slot = null;

            if ($slug === 'facil-digital-admin') {
                $slot = 10;
            } elseif ($slug === 'facil-digital-vendas') {
                $slot = 20;
            } elseif ($slug === 'facil-digital-apostilas') {
                $slot = 30;
            } elseif (
                str_contains(
                    $title,
                    'banco de quest'
                )
            ) {
                $slot = 40;
            } elseif ($title === 'simulados') {
                $slot = 50;
            } elseif (
                str_contains(
                    $title,
                    'import'
                )
            ) {
                $slot = 60;
            } elseif ($slug === self::PAGE) {
                $slot = 70;
            }

            if ($slot !== null) {
                while (isset($priority[$slot])) {
                    $slot++;
                }

                $priority[$slot] = $item;

                continue;
            }

            $remaining[] = $item;
        }

        ksort($priority);

        $submenu[self::PARENT_PAGE] =
            array_values(
                array_merge(
                    $priority,
                    $remaining
                )
            );
    }

    public function enqueueAssets(
        string $hookSuffix
    ): void {
        unset($hookSuffix);

        if ($this->currentPage() !== self::PAGE) {
            return;
        }

        wp_enqueue_style(
            'facil-digital-admin-a',
            plugins_url(
                'assets/admin/admin-a.css',
                FACIL_DIGITAL_CORE_FILE
            ),
            [],
            defined('FACIL_DIGITAL_CORE_VERSION')
                ? FACIL_DIGITAL_CORE_VERSION
                : null
        );

        wp_enqueue_style(
            'facil-digital-admin-c',
            plugins_url(
                'assets/admin/admin-c.css',
                FACIL_DIGITAL_CORE_FILE
            ),
            [
                'facil-digital-admin-a',
            ],
            defined('FACIL_DIGITAL_CORE_VERSION')
                ? FACIL_DIGITAL_CORE_VERSION
                : null
        );
    }

    public function render(): void
    {
        $this->guard();

        $tab =
            isset($_GET['fd_tab'])
            ? sanitize_key(
                wp_unslash(
                    (string) $_GET['fd_tab']
                )
            )
            : 'overview';

        if (
            !in_array(
                $tab,
                [
                    'overview',
                    'results',
                    'delivery',
                    'readiness',
                ],
                true
            )
        ) {
            $tab = 'overview';
        }

        ?>
        <div class="wrap fd-admin-a fd-admin-c">
            <?php
            $this->renderHero();
            $this->renderTabs($tab);

            match ($tab) {
                'results' =>
                    $this->renderResults(),
                'delivery' =>
                    $this->renderDelivery(),
                'readiness' =>
                    $this->renderReadiness(),
                default =>
                    $this->renderOverview(),
            };
            ?>
        </div>
        <?php
    }

    private function renderHero(): void
    {
        ?>
        <header class="fd-admin-a__hero fd-admin-c__hero">
            <div>
                <span class="fd-admin-a__eyebrow">
                    <?php
                    echo esc_html__(
                        'Fácil Digital+ · Operação',
                        'facil-digital-core'
                    );
                    ?>
                </span>

                <h1>
                    <?php
                    echo esc_html__(
                        'Operação e Relatórios',
                        'facil-digital-core'
                    );
                    ?>
                </h1>

                <p>
                    <?php
                    echo esc_html__(
                        'Acompanhe desempenho, entrega digital e prontidão do conteúdo sem alterar as regras do Core.',
                        'facil-digital-core'
                    );
                    ?>
                </p>
            </div>

            <div class="fd-admin-a__hero-actions">
                <?php
                $reports =
                    $this->existingAdminLink(
                        [
                            'Relatórios',
                            'Relatorios',
                        ]
                    );

                if ($reports !== null) {
                    ?>
                    <a
                        class="button button-primary"
                        href="<?php
                        echo esc_url(
                            $reports['url']
                        );
                        ?>"
                    >
                        <?php
                        echo esc_html__(
                            'Relatórios técnicos',
                            'facil-digital-core'
                        );
                        ?>
                    </a>
                    <?php
                }
                ?>

                <a
                    class="button"
                    href="<?php
                    echo esc_url(
                        admin_url(
                            'admin.php?page=facil-digital-admin'
                        )
                    );
                    ?>"
                >
                    <?php
                    echo esc_html__(
                        'Visão geral',
                        'facil-digital-core'
                    );
                    ?>
                </a>
            </div>
        </header>
        <?php
    }

    private function renderTabs(
        string $current
    ): void {
        $tabs = [
            'overview' =>
                __(
                    'Resumo',
                    'facil-digital-core'
                ),
            'results' =>
                __(
                    'Resultados e Ranking',
                    'facil-digital-core'
                ),
            'delivery' =>
                __(
                    'PDFs e Downloads',
                    'facil-digital-core'
                ),
            'readiness' =>
                __(
                    'Readiness',
                    'facil-digital-core'
                ),
        ];

        ?>
        <nav
            class="fd-admin-c__tabs"
            aria-label="<?php
            echo esc_attr__(
                'Seções operacionais',
                'facil-digital-core'
            );
            ?>"
        >
            <?php
            foreach ($tabs as $key => $label) {
                ?>
                <a
                    class="<?php
                    echo esc_attr(
                        $current === $key
                        ? 'is-active'
                        : ''
                    );
                    ?>"
                    href="<?php
                    echo esc_url(
                        $this->tabUrl($key)
                    );
                    ?>"
                >
                    <?php
                    echo esc_html($label);
                    ?>
                </a>
                <?php
            }
            ?>
        </nav>
        <?php
    }

    private function renderOverview(): void
    {
        $resultData =
            $this->resultsData();

        $delivery =
            $this->deliveryData();

        $readiness =
            $this->readinessData();

        ?>
        <section class="fd-admin-c__metrics">
            <?php
            $this->metric(
                __(
                    'Tentativas registradas',
                    'facil-digital-core'
                ),
                (string) $resultData['total'],
                __(
                    'Resultados de simulados',
                    'facil-digital-core'
                ),
                'dashicons-editor-ol',
                'primary'
            );

            $this->metric(
                __(
                    'PDFs prontos',
                    'facil-digital-core'
                ),
                (string) $delivery['pdf_ready'],
                __(
                    'Entrega personalizada',
                    'facil-digital-core'
                ),
                'dashicons-pdf',
                'success'
            );

            $this->metric(
                __(
                    'Downloads',
                    'facil-digital-core'
                ),
                (string) $delivery['downloads'],
                __(
                    'Registros protegidos',
                    'facil-digital-core'
                ),
                'dashicons-download',
                'violet'
            );

            $this->metric(
                __(
                    'Alertas de conteúdo',
                    'facil-digital-core'
                ),
                (string) $readiness['alert_count'],
                $readiness['alert_count'] > 0
                ? __(
                    'Itens que pedem revisão',
                    'facil-digital-core'
                )
                : __(
                    'Nenhum bloqueio detectado',
                    'facil-digital-core'
                ),
                'dashicons-warning',
                $readiness['alert_count'] > 0
                ? 'amber'
                : 'success'
            );
            ?>
        </section>

        <div class="fd-admin-c__overview-grid">
            <section class="fd-admin-c__panel">
                <?php
                $this->sectionHeading(
                    __(
                        'Entrega digital',
                        'facil-digital-core'
                    ),
                    __(
                        'Estado atual dos arquivos e acessos.',
                        'facil-digital-core'
                    ),
                    $this->tabUrl(
                        'delivery'
                    ),
                    __(
                        'Abrir detalhes',
                        'facil-digital-core'
                    )
                );
                ?>

                <div class="fd-admin-c__mini-grid">
                    <?php
                    $this->miniMetric(
                        __(
                            'Entitlements',
                            'facil-digital-core'
                        ),
                        (string)
                        $delivery['entitlements']
                    );

                    $this->miniMetric(
                        __(
                            'PDFs',
                            'facil-digital-core'
                        ),
                        (string)
                        $delivery['pdf_total']
                    );

                    $this->miniMetric(
                        __(
                            'Prontos',
                            'facil-digital-core'
                        ),
                        (string)
                        $delivery['pdf_ready']
                    );

                    $this->miniMetric(
                        __(
                            'Falhas',
                            'facil-digital-core'
                        ),
                        (string)
                        $delivery['pdf_failed']
                    );
                    ?>
                </div>
            </section>

            <section class="fd-admin-c__panel">
                <?php
                $this->sectionHeading(
                    __(
                        'Readiness',
                        'facil-digital-core'
                    ),
                    __(
                        'Pontos que merecem atenção antes de publicar.',
                        'facil-digital-core'
                    ),
                    $this->tabUrl(
                        'readiness'
                    ),
                    __(
                        'Ver checklist',
                        'facil-digital-core'
                    )
                );
                ?>

                <div class="fd-admin-c__status-list">
                    <?php
                    foreach (
                        array_slice(
                            $readiness['checks'],
                            0,
                            6
                        )
                        as $check
                    ) {
                        $this->statusRow(
                            (string) $check['label'],
                            (string) $check['value'],
                            (string) $check['state']
                        );
                    }
                    ?>
                </div>
            </section>
        </div>

        <section class="fd-admin-c__panel">
            <?php
            $this->sectionHeading(
                __(
                    'Atalhos operacionais',
                    'facil-digital-core'
                ),
                __(
                    'Abra as telas técnicas do Core quando precisar investigar um registro específico.',
                    'facil-digital-core'
                )
            );
            ?>

            <?php
            $this->renderLegacyLinks();
            ?>
        </section>
        <?php
    }

    private function renderResults(): void
    {
        $data =
            $this->resultsData();

        ?>
        <section class="fd-admin-c__metrics">
            <?php
            $this->metric(
                __(
                    'Tentativas',
                    'facil-digital-core'
                ),
                (string) $data['total'],
                __(
                    'Registros encontrados',
                    'facil-digital-core'
                ),
                'dashicons-editor-ol',
                'primary'
            );

            $this->metric(
                __(
                    'Alunos',
                    'facil-digital-core'
                ),
                (string) $data['users'],
                __(
                    'Participantes distintos',
                    'facil-digital-core'
                ),
                'dashicons-groups',
                'success'
            );

            $this->metric(
                __(
                    'Média registrada',
                    'facil-digital-core'
                ),
                $data['average_label'],
                $data['score_column'] !== ''
                ? __(
                    'Campo de desempenho do Core',
                    'facil-digital-core'
                )
                : __(
                    'Sem coluna de nota detectada',
                    'facil-digital-core'
                ),
                'dashicons-chart-bar',
                'violet'
            );

            $this->metric(
                __(
                    'Tabela de origem',
                    'facil-digital-core'
                ),
                $data['table'] !== ''
                ? esc_html(
                    $this->shortTableName(
                        $data['table']
                    )
                )
                : '—',
                __(
                    'Detectada automaticamente',
                    'facil-digital-core'
                ),
                'dashicons-database',
                'amber'
            );
            ?>
        </section>

        <div class="fd-admin-c__split">
            <section class="fd-admin-c__panel">
                <?php
                $this->sectionHeading(
                    __(
                        'Resultados recentes',
                        'facil-digital-core'
                    ),
                    __(
                        'Leitura dos registros existentes; nenhuma pontuação é recalculada aqui.',
                        'facil-digital-core'
                    )
                );

                $this->renderAttemptTable(
                    $data
                );
                ?>
            </section>

            <section class="fd-admin-c__panel">
                <?php
                $this->sectionHeading(
                    __(
                        'Ranking oficial',
                        'facil-digital-core'
                    ),
                    __(
                        'O ranking continua sendo calculado pelo serviço/tela existente do Core.',
                        'facil-digital-core'
                    )
                );

                $ranking =
                    $this->existingAdminLink(
                        [
                            'Rankings',
                            'Ranking',
                        ]
                    );

                $results =
                    $this->existingAdminLink(
                        [
                            'Resultados',
                            'Resultado',
                        ]
                    );
                ?>

                <div class="fd-admin-c__callout">
                    <span
                        class="dashicons dashicons-awards"
                        aria-hidden="true"
                    ></span>

                    <div>
                        <strong>
                            <?php
                            echo esc_html__(
                                'Sem duplicar a regra de ranking',
                                'facil-digital-core'
                            );
                            ?>
                        </strong>

                        <p>
                            <?php
                            echo esc_html__(
                                'Este painel resume tentativas. Para posição oficial, filtros por simulado/concurso e critérios do Core, use a tela de ranking já existente.',
                                'facil-digital-core'
                            );
                            ?>
                        </p>

                        <div class="fd-admin-c__buttons">
                            <?php
                            if ($ranking !== null) {
                                ?>
                                <a
                                    class="button button-primary"
                                    href="<?php
                                    echo esc_url(
                                        $ranking['url']
                                    );
                                    ?>"
                                >
                                    <?php
                                    echo esc_html__(
                                        'Abrir Rankings',
                                        'facil-digital-core'
                                    );
                                    ?>
                                </a>
                                <?php
                            }

                            if ($results !== null) {
                                ?>
                                <a
                                    class="button"
                                    href="<?php
                                    echo esc_url(
                                        $results['url']
                                    );
                                    ?>"
                                >
                                    <?php
                                    echo esc_html__(
                                        'Abrir Resultados',
                                        'facil-digital-core'
                                    );
                                    ?>
                                </a>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <?php
    }

    private function renderDelivery(): void
    {
        $data =
            $this->deliveryData();

        ?>
        <section class="fd-admin-c__metrics">
            <?php
            $this->metric(
                __(
                    'Entitlements',
                    'facil-digital-core'
                ),
                (string) $data['entitlements'],
                __(
                    'Acessos comerciais',
                    'facil-digital-core'
                ),
                'dashicons-unlock',
                'primary'
            );

            $this->metric(
                __(
                    'PDFs prontos',
                    'facil-digital-core'
                ),
                (string) $data['pdf_ready'],
                sprintf(
                    esc_html__(
                        '%d PDFs registrados',
                        'facil-digital-core'
                    ),
                    (int) $data['pdf_total']
                ),
                'dashicons-pdf',
                'success'
            );

            $this->metric(
                __(
                    'PDFs com falha',
                    'facil-digital-core'
                ),
                (string) $data['pdf_failed'],
                __(
                    'Requerem investigação',
                    'facil-digital-core'
                ),
                'dashicons-warning',
                $data['pdf_failed'] > 0
                ? 'amber'
                : 'success'
            );

            $this->metric(
                __(
                    'Downloads',
                    'facil-digital-core'
                ),
                (string) $data['downloads'],
                __(
                    'Registros protegidos',
                    'facil-digital-core'
                ),
                'dashicons-download',
                'violet'
            );
            ?>
        </section>

        <div class="fd-admin-c__split">
            <section class="fd-admin-c__panel">
                <?php
                $this->sectionHeading(
                    __(
                        'PDFs recentes',
                        'facil-digital-core'
                    ),
                    __(
                        'Últimos arquivos personalizados registrados pelo Core.',
                        'facil-digital-core'
                    )
                );

                $this->renderGenericOperationalTable(
                    $data['pdf_rows'],
                    [
                        'id' =>
                            __(
                                'ID',
                                'facil-digital-core'
                            ),
                        'user' =>
                            __(
                                'Aluno',
                                'facil-digital-core'
                            ),
                        'product' =>
                            __(
                                'Produto',
                                'facil-digital-core'
                            ),
                        'order' =>
                            __(
                                'Pedido',
                                'facil-digital-core'
                            ),
                        'status' =>
                            __(
                                'Status',
                                'facil-digital-core'
                            ),
                        'date' =>
                            __(
                                'Data',
                                'facil-digital-core'
                            ),
                    ]
                );
                ?>
            </section>

            <section class="fd-admin-c__panel">
                <?php
                $this->sectionHeading(
                    __(
                        'Downloads recentes',
                        'facil-digital-core'
                    ),
                    __(
                        'Auditoria resumida dos acessos aos arquivos.',
                        'facil-digital-core'
                    )
                );

                $this->renderGenericOperationalTable(
                    $data['download_rows'],
                    [
                        'id' =>
                            __(
                                'ID',
                                'facil-digital-core'
                            ),
                        'user' =>
                            __(
                                'Aluno',
                                'facil-digital-core'
                            ),
                        'product' =>
                            __(
                                'Produto',
                                'facil-digital-core'
                            ),
                        'order' =>
                            __(
                                'Pedido',
                                'facil-digital-core'
                            ),
                        'date' =>
                            __(
                                'Data',
                                'facil-digital-core'
                            ),
                    ]
                );
                ?>
            </section>
        </div>

        <section class="fd-admin-c__panel">
            <?php
            $this->sectionHeading(
                __(
                    'Ferramentas técnicas',
                    'facil-digital-core'
                ),
                __(
                    'Use as telas originais do Core para operações específicas.',
                    'facil-digital-core'
                )
            );

            $links = [];

            foreach (
                [
                    [
                        'labels' =>
                            ['PDFs', 'PDF'],
                        'label' =>
                            __(
                                'Gerenciar PDFs',
                                'facil-digital-core'
                            ),
                    ],
                    [
                        'labels' =>
                            ['Downloads', 'Download'],
                        'label' =>
                            __(
                                'Auditoria de downloads',
                                'facil-digital-core'
                            ),
                    ],
                    [
                        'labels' =>
                            ['Alunos', 'Aluno'],
                        'label' =>
                            __(
                                'Ver alunos',
                                'facil-digital-core'
                            ),
                    ],
                ]
                as $candidate
            ) {
                $found =
                    $this->existingAdminLink(
                        $candidate['labels']
                    );

                if ($found !== null) {
                    $links[] = [
                        'label' =>
                            $candidate['label'],
                        'url' =>
                            $found['url'],
                    ];
                }
            }

            $this->renderButtonLinks(
                $links
            );
            ?>
        </section>
        <?php
    }

    private function renderReadiness(): void
    {
        $data =
            $this->readinessData();

        ?>
        <section class="fd-admin-c__metrics">
            <?php
            $this->metric(
                __(
                    'Apostilas',
                    'facil-digital-core'
                ),
                (string) $data['apostilas_total'],
                __(
                    'Produtos digitais',
                    'facil-digital-core'
                ),
                'dashicons-book-alt',
                'primary'
            );

            $this->metric(
                __(
                    'Sem capa',
                    'facil-digital-core'
                ),
                (string) $data['apostilas_no_cover'],
                __(
                    'Precisam de imagem',
                    'facil-digital-core'
                ),
                'dashicons-format-image',
                $data['apostilas_no_cover'] > 0
                ? 'amber'
                : 'success'
            );

            $this->metric(
                __(
                    'Sem PDF master',
                    'facil-digital-core'
                ),
                (string) $data['apostilas_no_master'],
                __(
                    'Arquivo protegido pendente',
                    'facil-digital-core'
                ),
                'dashicons-pdf',
                $data['apostilas_no_master'] > 0
                ? 'amber'
                : 'success'
            );

            $this->metric(
                __(
                    'Alertas',
                    'facil-digital-core'
                ),
                (string) $data['alert_count'],
                __(
                    'Checklist operacional',
                    'facil-digital-core'
                ),
                'dashicons-shield-alt',
                $data['alert_count'] > 0
                ? 'amber'
                : 'success'
            );
            ?>
        </section>

        <div class="fd-admin-c__split">
            <section class="fd-admin-c__panel">
                <?php
                $this->sectionHeading(
                    __(
                        'Checklist do ambiente',
                        'facil-digital-core'
                    ),
                    __(
                        'Leitura das configurações relevantes para operação.',
                        'facil-digital-core'
                    )
                );
                ?>

                <div class="fd-admin-c__status-list">
                    <?php
                    foreach ($data['checks'] as $check) {
                        $this->statusRow(
                            (string) $check['label'],
                            (string) $check['value'],
                            (string) $check['state']
                        );
                    }
                    ?>
                </div>
            </section>

            <section class="fd-admin-c__panel">
                <?php
                $this->sectionHeading(
                    __(
                        'Conteúdo pendente',
                        'facil-digital-core'
                    ),
                    __(
                        'Apostilas que ainda não atendem todos os elementos editoriais.',
                        'facil-digital-core'
                    )
                );

                $this->renderReadinessProducts(
                    $data['product_alerts']
                );
                ?>
            </section>
        </div>

        <?php
        $security =
            $this->existingAdminLink(
                [
                    'Segurança',
                    'Seguranca',
                ]
            );

        $goLive =
            $this->existingAdminLink(
                [
                    'Go-live',
                    'Go live',
                ]
            );

        $links = [];

        if ($security !== null) {
            $links[] = [
                'label' =>
                    __(
                        'Abrir Segurança',
                        'facil-digital-core'
                    ),
                'url' =>
                    $security['url'],
            ];
        }

        if ($goLive !== null) {
            $links[] = [
                'label' =>
                    __(
                        'Abrir Go-live',
                        'facil-digital-core'
                    ),
                'url' =>
                    $goLive['url'],
            ];
        }

        if ($links !== []) {
            ?>
            <section class="fd-admin-c__panel">
                <?php
                $this->sectionHeading(
                    __(
                        'Validações avançadas',
                        'facil-digital-core'
                    ),
                    __(
                        'As telas técnicas existentes continuam disponíveis para verificações detalhadas.',
                        'facil-digital-core'
                    )
                );

                $this->renderButtonLinks(
                    $links
                );
                ?>
            </section>
            <?php
        }
    }

    /**
     * @return array{
     *   table:string,
     *   total:int,
     *   users:int,
     *   average_label:string,
     *   score_column:string,
     *   rows:array<int,array<string,mixed>>
     * }
     */
    private function resultsData(): array
    {
        global $wpdb;

        $table =
            $this->firstExistingTable(
                [
                    $wpdb->prefix
                        . 'fd_simulation_attempts',
                    $wpdb->prefix
                        . 'fd_attempts',
                    $wpdb->prefix
                        . 'fd_results',
                ]
            );

        $empty = [
            'table' => '',
            'total' => 0,
            'users' => 0,
            'average_label' => '—',
            'score_column' => '',
            'rows' => [],
        ];

        if ($table === '') {
            return $empty;
        }

        $columns =
            $this->columns($table);

        $idColumn =
            $this->firstColumn(
                $columns,
                [
                    'id',
                    'attempt_id',
                ]
            );

        $userColumn =
            $this->firstColumn(
                $columns,
                [
                    'user_id',
                    'customer_id',
                    'student_id',
                ]
            );

        $scoreColumn =
            $this->firstColumn(
                $columns,
                [
                    'percentage',
                    'score_percentage',
                    'percent',
                    'score',
                    'final_score',
                ]
            );

        $total =
            (int)
            $wpdb->get_var(
                "SELECT COUNT(*) FROM `{$table}`"
            );

        $users = 0;

        if ($userColumn !== '') {
            $users =
                (int)
                $wpdb->get_var(
                    "SELECT COUNT(DISTINCT `{$userColumn}`) FROM `{$table}`"
                );
        }

        $averageLabel = '—';

        if ($scoreColumn !== '') {
            $average =
                $wpdb->get_var(
                    "SELECT AVG(CAST(`{$scoreColumn}` AS DECIMAL(12,2))) FROM `{$table}`"
                );

            if ($average !== null) {
                $averageLabel =
                    number_format_i18n(
                        (float) $average,
                        1
                    );

                if (
                    str_contains(
                        $scoreColumn,
                        'percent'
                    )
                    || str_contains(
                        $scoreColumn,
                        'percentage'
                    )
                ) {
                    $averageLabel .= '%';
                }
            }
        }

        $orderColumn =
            $idColumn !== ''
            ? $idColumn
            : $columns[0] ?? '';

        $rows = [];

        if ($orderColumn !== '') {
            $rawRows =
                $wpdb->get_results(
                    "SELECT * FROM `{$table}` ORDER BY `{$orderColumn}` DESC LIMIT 20",
                    ARRAY_A
                );

            if (is_array($rawRows)) {
                foreach ($rawRows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $rows[] =
                        $this->normalizeAttemptRow(
                            $row,
                            $columns
                        );
                }
            }
        }

        return [
            'table' => $table,
            'total' => $total,
            'users' => $users,
            'average_label' => $averageLabel,
            'score_column' => $scoreColumn,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{
     *   entitlements:int,
     *   pdf_total:int,
     *   pdf_ready:int,
     *   pdf_failed:int,
     *   downloads:int,
     *   pdf_rows:array<int,array<string,mixed>>,
     *   download_rows:array<int,array<string,mixed>>
     * }
     */
    private function deliveryData(): array
    {
        global $wpdb;

        $entitlementsTable =
            $this->firstExistingTable(
                [
                    $wpdb->prefix
                        . 'fd_entitlements',
                ]
            );

        $pdfTable =
            $this->firstExistingTable(
                [
                    $wpdb->prefix
                        . 'fd_pdf_files',
                    $wpdb->prefix
                        . 'fd_pdfs',
                ]
            );

        $downloadTable =
            $this->firstExistingTable(
                [
                    $wpdb->prefix
                        . 'fd_downloads',
                ]
            );

        $entitlements =
            $entitlementsTable !== ''
            ? $this->countRows(
                $entitlementsTable
            )
            : 0;

        $pdfTotal =
            $pdfTable !== ''
            ? $this->countRows($pdfTable)
            : 0;

        $pdfReady = 0;
        $pdfFailed = 0;

        if ($pdfTable !== '') {
            $columns =
                $this->columns(
                    $pdfTable
                );

            $statusColumn =
                $this->firstColumn(
                    $columns,
                    [
                        'status',
                        'state',
                    ]
                );

            if ($statusColumn !== '') {
                $pdfReady =
                    (int)
                    $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT COUNT(*) FROM `{$pdfTable}` WHERE `{$statusColumn}` = %s",
                            'ready'
                        )
                    );

                $failedStatuses = [
                    'failed',
                    'error',
                ];

                foreach (
                    $failedStatuses
                    as $status
                ) {
                    $pdfFailed +=
                        (int)
                        $wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT COUNT(*) FROM `{$pdfTable}` WHERE `{$statusColumn}` = %s",
                                $status
                            )
                        );
                }
            }
        }

        $downloads =
            $downloadTable !== ''
            ? $this->countRows(
                $downloadTable
            )
            : 0;

        return [
            'entitlements' =>
                $entitlements,
            'pdf_total' =>
                $pdfTotal,
            'pdf_ready' =>
                $pdfReady,
            'pdf_failed' =>
                $pdfFailed,
            'downloads' =>
                $downloads,
            'pdf_rows' =>
                $pdfTable !== ''
                ? $this->recentOperationalRows(
                    $pdfTable,
                    true
                )
                : [],
            'download_rows' =>
                $downloadTable !== ''
                ? $this->recentOperationalRows(
                    $downloadTable,
                    false
                )
                : [],
        ];
    }

    /**
     * @return array{
     *   apostilas_total:int,
     *   apostilas_no_cover:int,
     *   apostilas_no_master:int,
     *   alert_count:int,
     *   checks:array<int,array{
     *      label:string,
     *      value:string,
     *      state:string
     *   }>,
     *   product_alerts:array<int,array<string,mixed>>
     * }
     */
    private function readinessData(): array
    {
        $products =
            $this->apostilaProducts();

        $noCover = 0;
        $noMaster = 0;
        $productAlerts = [];

        foreach ($products as $product) {
            $missing = [];

            if ($product->get_image_id() <= 0) {
                $noCover++;
                $missing[] =
                    __(
                        'sem capa',
                        'facil-digital-core'
                    );
            }

            if (
                $this->masterStorageKey(
                    $product->get_id()
                )
                === ''
            ) {
                $noMaster++;
                $missing[] =
                    __(
                        'sem PDF master',
                        'facil-digital-core'
                    );
            }

            if (
                trim(
                    (string)
                    $product->get_regular_price()
                )
                === ''
            ) {
                $missing[] =
                    __(
                        'sem preço',
                        'facil-digital-core'
                    );
            }

            if ($missing !== []) {
                $productAlerts[] = [
                    'id' =>
                        $product->get_id(),
                    'name' =>
                        $product->get_name(),
                    'status' =>
                        $product->get_status(),
                    'missing' =>
                        $missing,
                ];
            }
        }

        $checks = [];

        $environment =
            wp_get_environment_type();

        $checks[] = [
            'label' =>
                __(
                    'Ambiente WordPress',
                    'facil-digital-core'
                ),
            'value' =>
                $environment,
            'state' =>
                in_array(
                    $environment,
                    [
                        'staging',
                        'production',
                    ],
                    true
                )
                ? 'ok'
                : 'warning',
        ];

        $checks[] = [
            'label' =>
                __(
                    'Idioma',
                    'facil-digital-core'
                ),
            'value' =>
                get_locale(),
            'state' =>
                get_locale()
                === 'pt_BR'
                ? 'ok'
                : 'warning',
        ];

        $blogPublic =
            (int)
            get_option(
                'blog_public',
                1
            );

        $indexOk =
            $environment === 'staging'
            ? $blogPublic === 0
            : true;

        $checks[] = [
            'label' =>
                __(
                    'Indexação no staging',
                    'facil-digital-core'
                ),
            'value' =>
                $blogPublic === 0
                ? __(
                    'Bloqueada',
                    'facil-digital-core'
                )
                : __(
                    'Aberta',
                    'facil-digital-core'
                ),
            'state' =>
                $indexOk
                ? 'ok'
                : 'warning',
        ];

        $storageReady = false;

        if (
            class_exists(
                PrivateStorage::class
            )
        ) {
            try {
                $storage =
                    new PrivateStorage();

                $storageReady =
                    $storage->isReady();
            } catch (Throwable $throwable) {
                unset($throwable);

                $storageReady = false;
            }
        }

        $checks[] = [
            'label' =>
                __(
                    'Storage privado',
                    'facil-digital-core'
                ),
            'value' =>
                $storageReady
                ? __(
                    'Pronto',
                    'facil-digital-core'
                )
                : __(
                    'Atenção',
                    'facil-digital-core'
                ),
            'state' =>
                $storageReady
                ? 'ok'
                : 'error',
        ];

        $woocommerceActive =
            defined('WC_VERSION');

        $checks[] = [
            'label' =>
                __(
                    'WooCommerce',
                    'facil-digital-core'
                ),
            'value' =>
                $woocommerceActive
                ? 'v' . WC_VERSION
                : __(
                    'Indisponível',
                    'facil-digital-core'
                ),
            'state' =>
                $woocommerceActive
                ? 'ok'
                : 'error',
        ];

        $mpSync =
            (string)
            get_option(
                '_mp_cron_sync_mode',
                ''
            );

        $checks[] = [
            'label' =>
                __(
                    'Fallback Mercado Pago',
                    'facil-digital-core'
                ),
            'value' =>
                $mpSync !== ''
                ? $mpSync
                : __(
                    'Não configurado',
                    'facil-digital-core'
                ),
            'state' =>
                $mpSync !== ''
                ? 'ok'
                : 'warning',
        ];

        $nextMpCron =
            wp_next_scheduled(
                'mercadopago_sync_pending_status_order_action'
            );

        $checks[] = [
            'label' =>
                __(
                    'Cron Mercado Pago',
                    'facil-digital-core'
                ),
            'value' =>
                $nextMpCron
                ? wp_date(
                    'd/m/Y H:i',
                    $nextMpCron
                )
                : __(
                    'Não agendado',
                    'facil-digital-core'
                ),
            'state' =>
                $nextMpCron
                ? 'ok'
                : 'warning',
        ];

        $adminEmail =
            (string)
            get_option(
                'admin_email',
                ''
            );

        $checks[] = [
            'label' =>
                __(
                    'E-mail administrativo',
                    'facil-digital-core'
                ),
            'value' =>
                $adminEmail !== ''
                ? $adminEmail
                : '—',
            'state' =>
                $adminEmail !== ''
                && !str_contains(
                    $adminEmail,
                    'example.test'
                )
                ? 'ok'
                : 'warning',
        ];

        $privacyId =
            (int)
            get_option(
                'wp_page_for_privacy_policy'
            );

        $termsId =
            (int)
            get_option(
                'woocommerce_terms_page_id'
            );

        $checks[] =
            $this->legalCheck(
                __(
                    'Política de Privacidade',
                    'facil-digital-core'
                ),
                $privacyId
            );

        $checks[] =
            $this->legalCheck(
                __(
                    'Termos de Uso',
                    'facil-digital-core'
                ),
                $termsId
            );

        $alertCount =
            count($productAlerts);

        foreach ($checks as $check) {
            if (
                $check['state']
                !== 'ok'
            ) {
                $alertCount++;
            }
        }

        return [
            'apostilas_total' =>
                count($products),
            'apostilas_no_cover' =>
                $noCover,
            'apostilas_no_master' =>
                $noMaster,
            'alert_count' =>
                $alertCount,
            'checks' =>
                $checks,
            'product_alerts' =>
                $productAlerts,
        ];
    }

    /**
     * @return array{
     *   label:string,
     *   value:string,
     *   state:string
     * }
     */
    private function legalCheck(
        string $label,
        int $pageId
    ): array {
        $post =
            $pageId > 0
            ? get_post($pageId)
            : null;

        if (
            !$post
            || $post->post_status
                !== 'publish'
        ) {
            return [
                'label' =>
                    $label,
                'value' =>
                    __(
                        'Não publicada',
                        'facil-digital-core'
                    ),
                'state' =>
                    'warning',
            ];
        }

        $content =
            trim(
                wp_strip_all_tags(
                    strip_shortcodes(
                        (string)
                        $post->post_content
                    )
                )
            );

        return [
            'label' =>
                $label,
            'value' =>
                $content !== ''
                ? __(
                    'Publicada',
                    'facil-digital-core'
                )
                : __(
                    'Sem conteúdo',
                    'facil-digital-core'
                ),
            'state' =>
                $content !== ''
                ? 'ok'
                : 'warning',
        ];
    }

    /**
     * @return array<int,WC_Product>
     */
    private function apostilaProducts(): array
    {
        if (
            !function_exists(
                'wc_get_products'
            )
            || !class_exists(
                ProductMetadata::class
            )
        ) {
            return [];
        }

        $products =
            wc_get_products(
                [
                    'status' =>
                        [
                            'publish',
                            'draft',
                            'pending',
                            'private',
                        ],
                    'limit' =>
                        500,
                    'orderby' =>
                        'date',
                    'order' =>
                        'DESC',
                ]
            );

        return
            array_values(
                array_filter(
                    $products,
                    static function (
                        $product
                    ): bool {
                        return
                            $product
                            instanceof WC_Product
                            && ProductMetadata::isApostila(
                                $product->get_id()
                            );
                    }
                )
            );
    }

    private function masterStorageKey(
        int $productId
    ): string {
        $metadata =
            get_post_meta(
                $productId
            );

        $needle =
            'masters/product-'
            . $productId
            . '/';

        foreach ($metadata as $values) {
            if (!is_array($values)) {
                $values = [$values];
            }

            foreach ($values as $value) {
                if (!is_scalar($value)) {
                    continue;
                }

                $string =
                    (string) $value;

                if (
                    str_starts_with(
                        $string,
                        $needle
                    )
                    && str_ends_with(
                        strtolower($string),
                        '.pdf'
                    )
                ) {
                    return $string;
                }
            }
        }

        return '';
    }

    /**
     * @param array<string,mixed> $row
     * @param array<int,string> $columns
     * @return array<string,mixed>
     */
    private function normalizeAttemptRow(
        array $row,
        array $columns
    ): array {
        $idColumn =
            $this->firstColumn(
                $columns,
                [
                    'id',
                    'attempt_id',
                ]
            );

        $userColumn =
            $this->firstColumn(
                $columns,
                [
                    'user_id',
                    'customer_id',
                    'student_id',
                ]
            );

        $simulationColumn =
            $this->firstColumn(
                $columns,
                [
                    'simulation_id',
                    'simulado_id',
                ]
            );

        $scoreColumn =
            $this->firstColumn(
                $columns,
                [
                    'percentage',
                    'score_percentage',
                    'percent',
                    'score',
                    'final_score',
                ]
            );

        $statusColumn =
            $this->firstColumn(
                $columns,
                [
                    'status',
                    'state',
                ]
            );

        $dateColumn =
            $this->firstColumn(
                $columns,
                [
                    'completed_at',
                    'finished_at',
                    'submitted_at',
                    'updated_at',
                    'created_at',
                ]
            );

        $userId =
            $userColumn !== ''
            ? (int)
                ($row[$userColumn] ?? 0)
            : 0;

        $simulationId =
            $simulationColumn !== ''
            ? (int)
                ($row[$simulationColumn] ?? 0)
            : 0;

        $score = '—';

        if ($scoreColumn !== '') {
            $raw =
                $row[$scoreColumn]
                ?? '';

            if (
                $raw !== ''
                && is_numeric($raw)
            ) {
                $score =
                    number_format_i18n(
                        (float) $raw,
                        1
                    );

                if (
                    str_contains(
                        $scoreColumn,
                        'percent'
                    )
                    || str_contains(
                        $scoreColumn,
                        'percentage'
                    )
                ) {
                    $score .= '%';
                }
            } elseif ($raw !== '') {
                $score =
                    (string) $raw;
            }
        }

        return [
            'id' =>
                $idColumn !== ''
                ? (string)
                    ($row[$idColumn] ?? '—')
                : '—',
            'user' =>
                $this->userLabel(
                    $userId
                ),
            'simulation' =>
                $this->simulationLabel(
                    $simulationId
                ),
            'score' =>
                $score,
            'status' =>
                $statusColumn !== ''
                ? (string)
                    ($row[$statusColumn] ?? '—')
                : '—',
            'date' =>
                $dateColumn !== ''
                ? $this->dateLabel(
                    $row[$dateColumn]
                    ?? ''
                )
                : '—',
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function recentOperationalRows(
        string $table,
        bool $includeStatus
    ): array {
        global $wpdb;

        $columns =
            $this->columns($table);

        if ($columns === []) {
            return [];
        }

        $idColumn =
            $this->firstColumn(
                $columns,
                [
                    'id',
                    'pdf_id',
                    'download_id',
                ]
            );

        $orderBy =
            $idColumn !== ''
            ? $idColumn
            : $columns[0];

        $rawRows =
            $wpdb->get_results(
                "SELECT * FROM `{$table}` ORDER BY `{$orderBy}` DESC LIMIT 12",
                ARRAY_A
            );

        if (!is_array($rawRows)) {
            return [];
        }

        $userColumn =
            $this->firstColumn(
                $columns,
                [
                    'user_id',
                    'customer_id',
                ]
            );

        $productColumn =
            $this->firstColumn(
                $columns,
                [
                    'product_id',
                ]
            );

        $orderColumn =
            $this->firstColumn(
                $columns,
                [
                    'order_id',
                ]
            );

        $statusColumn =
            $includeStatus
            ? $this->firstColumn(
                $columns,
                [
                    'status',
                    'state',
                ]
            )
            : '';

        $dateColumn =
            $this->firstColumn(
                $columns,
                [
                    'downloaded_at',
                    'generated_at',
                    'created_at',
                    'updated_at',
                ]
            );

        $rows = [];

        foreach ($rawRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $userId =
                $userColumn !== ''
                ? (int)
                    ($row[$userColumn] ?? 0)
                : 0;

            $productId =
                $productColumn !== ''
                ? (int)
                    ($row[$productColumn] ?? 0)
                : 0;

            $orderId =
                $orderColumn !== ''
                ? (int)
                    ($row[$orderColumn] ?? 0)
                : 0;

            $rows[] = [
                'id' =>
                    $idColumn !== ''
                    ? (string)
                        ($row[$idColumn] ?? '—')
                    : '—',
                'user' =>
                    $this->userLabel(
                        $userId
                    ),
                'product' =>
                    $this->productLabel(
                        $productId
                    ),
                'order' =>
                    $orderId > 0
                    ? '#'
                        . $orderId
                    : '—',
                'status' =>
                    $statusColumn !== ''
                    ? (string)
                        ($row[$statusColumn] ?? '—')
                    : '',
                'date' =>
                    $dateColumn !== ''
                    ? $this->dateLabel(
                        $row[$dateColumn]
                        ?? ''
                    )
                    : '—',
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function renderAttemptTable(
        array $data
    ): void {
        $rows =
            isset($data['rows'])
            && is_array($data['rows'])
            ? $data['rows']
            : [];

        if ($rows === []) {
            $this->emptyState(
                __(
                    'Nenhum resultado disponível.',
                    'facil-digital-core'
                ),
                __(
                    'Quando os alunos concluírem simulados, os registros aparecerão aqui.',
                    'facil-digital-core'
                )
            );

            return;
        }

        ?>
        <div class="fd-admin-c__table-wrap">
            <table class="fd-admin-c__table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>
                            <?php
                            echo esc_html__(
                                'Aluno',
                                'facil-digital-core'
                            );
                            ?>
                        </th>
                        <th>
                            <?php
                            echo esc_html__(
                                'Simulado',
                                'facil-digital-core'
                            );
                            ?>
                        </th>
                        <th>
                            <?php
                            echo esc_html__(
                                'Resultado',
                                'facil-digital-core'
                            );
                            ?>
                        </th>
                        <th>
                            <?php
                            echo esc_html__(
                                'Status',
                                'facil-digital-core'
                            );
                            ?>
                        </th>
                        <th>
                            <?php
                            echo esc_html__(
                                'Data',
                                'facil-digital-core'
                            );
                            ?>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    foreach ($rows as $row) {
                        ?>
                        <tr>
                            <td>
                                <?php
                                echo esc_html(
                                    (string)
                                    ($row['id'] ?? '—')
                                );
                                ?>
                            </td>
                            <td>
                                <?php
                                echo esc_html(
                                    (string)
                                    ($row['user'] ?? '—')
                                );
                                ?>
                            </td>
                            <td>
                                <?php
                                echo esc_html(
                                    (string)
                                    ($row['simulation'] ?? '—')
                                );
                                ?>
                            </td>
                            <td>
                                <strong>
                                    <?php
                                    echo esc_html(
                                        (string)
                                        ($row['score'] ?? '—')
                                    );
                                    ?>
                                </strong>
                            </td>
                            <td>
                                <?php
                                $this->simplePill(
                                    (string)
                                    ($row['status'] ?? '—')
                                );
                                ?>
                            </td>
                            <td>
                                <?php
                                echo esc_html(
                                    (string)
                                    ($row['date'] ?? '—')
                                );
                                ?>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,string> $columns
     */
    private function renderGenericOperationalTable(
        array $rows,
        array $columns
    ): void {
        if ($rows === []) {
            $this->emptyState(
                __(
                    'Nenhum registro disponível.',
                    'facil-digital-core'
                ),
                __(
                    'Os dados aparecerão automaticamente conforme a operação do site.',
                    'facil-digital-core'
                )
            );

            return;
        }

        ?>
        <div class="fd-admin-c__table-wrap">
            <table class="fd-admin-c__table">
                <thead>
                    <tr>
                        <?php
                        foreach ($columns as $label) {
                            ?>
                            <th>
                                <?php
                                echo esc_html($label);
                                ?>
                            </th>
                            <?php
                        }
                        ?>
                    </tr>
                </thead>

                <tbody>
                    <?php
                    foreach ($rows as $row) {
                        ?>
                        <tr>
                            <?php
                            foreach (
                                $columns
                                as $key => $label
                            ) {
                                unset($label);

                                ?>
                                <td>
                                    <?php
                                    if ($key === 'status') {
                                        $this->simplePill(
                                            (string)
                                            ($row[$key] ?? '—')
                                        );
                                    } else {
                                        echo esc_html(
                                            (string)
                                            ($row[$key] ?? '—')
                                        );
                                    }
                                    ?>
                                </td>
                                <?php
                            }
                            ?>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * @param array<int,array<string,mixed>> $products
     */
    private function renderReadinessProducts(
        array $products
    ): void {
        if ($products === []) {
            ?>
            <div class="fd-admin-c__ready">
                <span
                    class="dashicons dashicons-yes-alt"
                    aria-hidden="true"
                ></span>

                <div>
                    <strong>
                        <?php
                        echo esc_html__(
                            'Nenhuma pendência editorial detectada.',
                            'facil-digital-core'
                        );
                        ?>
                    </strong>

                    <p>
                        <?php
                        echo esc_html__(
                            'As apostilas cadastradas possuem capa, preço e referência de PDF master.',
                            'facil-digital-core'
                        );
                        ?>
                    </p>
                </div>
            </div>
            <?php

            return;
        }

        ?>
        <div class="fd-admin-c__readiness-products">
            <?php
            foreach ($products as $product) {
                ?>
                <article>
                    <div>
                        <strong>
                            <?php
                            echo esc_html(
                                (string)
                                $product['name']
                            );
                            ?>
                        </strong>

                        <small>
                            #<?php
                            echo esc_html(
                                (string)
                                $product['id']
                            );
                            ?>

                            ·

                            <?php
                            echo esc_html(
                                (string)
                                $product['status']
                            );
                            ?>
                        </small>
                    </div>

                    <span>
                        <?php
                        echo esc_html(
                            implode(
                                ', ',
                                array_map(
                                    'strval',
                                    (array)
                                    $product['missing']
                                )
                            )
                        );
                        ?>
                    </span>

                    <a
                        href="<?php
                        echo esc_url(
                            admin_url(
                                'admin.php?page=facil-digital-apostilas&fd_action=edit&product_id='
                                . (int)
                                $product['id']
                            )
                        );
                        ?>"
                    >
                        <?php
                        echo esc_html__(
                            'Corrigir',
                            'facil-digital-core'
                        );
                        ?>
                    </a>
                </article>
                <?php
            }
            ?>
        </div>
        <?php
    }

    private function renderLegacyLinks(): void
    {
        $candidates = [
            [
                'labels' =>
                    ['Resultados'],
                'label' =>
                    __(
                        'Resultados',
                        'facil-digital-core'
                    ),
                'icon' =>
                    'dashicons-chart-bar',
            ],
            [
                'labels' =>
                    ['Rankings', 'Ranking'],
                'label' =>
                    __(
                        'Rankings',
                        'facil-digital-core'
                    ),
                'icon' =>
                    'dashicons-awards',
            ],
            [
                'labels' =>
                    ['PDFs', 'PDF'],
                'label' =>
                    __(
                        'PDFs',
                        'facil-digital-core'
                    ),
                'icon' =>
                    'dashicons-pdf',
            ],
            [
                'labels' =>
                    ['Downloads', 'Download'],
                'label' =>
                    __(
                        'Downloads',
                        'facil-digital-core'
                    ),
                'icon' =>
                    'dashicons-download',
            ],
            [
                'labels' =>
                    ['Alunos', 'Aluno'],
                'label' =>
                    __(
                        'Alunos',
                        'facil-digital-core'
                    ),
                'icon' =>
                    'dashicons-groups',
            ],
            [
                'labels' =>
                    ['Relatórios', 'Relatorios'],
                'label' =>
                    __(
                        'Relatórios',
                        'facil-digital-core'
                    ),
                'icon' =>
                    'dashicons-media-spreadsheet',
            ],
            [
                'labels' =>
                    ['Segurança', 'Seguranca'],
                'label' =>
                    __(
                        'Segurança',
                        'facil-digital-core'
                    ),
                'icon' =>
                    'dashicons-shield',
            ],
        ];

        $links = [];

        foreach ($candidates as $candidate) {
            $found =
                $this->existingAdminLink(
                    $candidate['labels']
                );

            if ($found === null) {
                continue;
            }

            $links[] = [
                'label' =>
                    $candidate['label'],
                'url' =>
                    $found['url'],
                'icon' =>
                    $candidate['icon'],
            ];
        }

        if ($links === []) {
            $this->emptyState(
                __(
                    'Nenhum atalho técnico encontrado.',
                    'facil-digital-core'
                ),
                __(
                    'As métricas principais continuam disponíveis neste painel.',
                    'facil-digital-core'
                )
            );

            return;
        }

        ?>
        <div class="fd-admin-c__quick-grid">
            <?php
            foreach ($links as $link) {
                ?>
                <a
                    href="<?php
                    echo esc_url(
                        $link['url']
                    );
                    ?>"
                >
                    <span
                        class="dashicons <?php
                        echo esc_attr(
                            $link['icon']
                        );
                        ?>"
                        aria-hidden="true"
                    ></span>

                    <strong>
                        <?php
                        echo esc_html(
                            $link['label']
                        );
                        ?>
                    </strong>

                    <span aria-hidden="true">
                        →
                    </span>
                </a>
                <?php
            }
            ?>
        </div>
        <?php
    }

    /**
     * @param array<int,array{
     *   label:string,
     *   url:string
     * }> $links
     */
    private function renderButtonLinks(
        array $links
    ): void {
        if ($links === []) {
            return;
        }

        ?>
        <div class="fd-admin-c__buttons">
            <?php
            foreach ($links as $index => $link) {
                ?>
                <a
                    class="button <?php
                    echo esc_attr(
                        $index === 0
                        ? 'button-primary'
                        : ''
                    );
                    ?>"
                    href="<?php
                    echo esc_url(
                        $link['url']
                    );
                    ?>"
                >
                    <?php
                    echo esc_html(
                        $link['label']
                    );
                    ?>
                </a>
                <?php
            }
            ?>
        </div>
        <?php
    }

    private function sectionHeading(
        string $title,
        string $description,
        string $url = '',
        string $linkLabel = ''
    ): void {
        ?>
        <div class="fd-admin-c__section-heading">
            <div>
                <h2>
                    <?php
                    echo esc_html($title);
                    ?>
                </h2>

                <p>
                    <?php
                    echo esc_html(
                        $description
                    );
                    ?>
                </p>
            </div>

            <?php
            if (
                $url !== ''
                && $linkLabel !== ''
            ) {
                ?>
                <a
                    href="<?php
                    echo esc_url($url);
                    ?>"
                >
                    <?php
                    echo esc_html(
                        $linkLabel
                    );
                    ?>
                </a>
                <?php
            }
            ?>
        </div>
        <?php
    }

    private function metric(
        string $label,
        string $value,
        string $caption,
        string $icon,
        string $tone
    ): void {
        ?>
        <article
            class="fd-admin-a__metric fd-admin-a__metric--<?php
            echo esc_attr($tone);
            ?>"
        >
            <div class="fd-admin-a__metric-icon">
                <span
                    class="dashicons <?php
                    echo esc_attr($icon);
                    ?>"
                    aria-hidden="true"
                ></span>
            </div>

            <div>
                <span class="fd-admin-a__metric-label">
                    <?php
                    echo esc_html($label);
                    ?>
                </span>

                <strong>
                    <?php
                    echo wp_kses_post($value);
                    ?>
                </strong>

                <small>
                    <?php
                    echo esc_html(
                        $caption
                    );
                    ?>
                </small>
            </div>
        </article>
        <?php
    }

    private function miniMetric(
        string $label,
        string $value
    ): void {
        ?>
        <article>
            <strong>
                <?php
                echo esc_html($value);
                ?>
            </strong>

            <span>
                <?php
                echo esc_html($label);
                ?>
            </span>
        </article>
        <?php
    }

    private function statusRow(
        string $label,
        string $value,
        string $state
    ): void {
        ?>
        <div class="fd-admin-c__status-row">
            <span
                class="fd-admin-c__status-dot fd-admin-c__status-dot--<?php
                echo esc_attr($state);
                ?>"
                aria-hidden="true"
            ></span>

            <strong>
                <?php
                echo esc_html($label);
                ?>
            </strong>

            <span>
                <?php
                echo esc_html($value);
                ?>
            </span>
        </div>
        <?php
    }

    private function simplePill(
        string $value
    ): void {
        $normalized =
            $this->normalize($value);

        $tone =
            in_array(
                $normalized,
                [
                    'ready',
                    'completed',
                    'complete',
                    'processing',
                    'concluido',
                    'concluida',
                    'pronto',
                ],
                true
            )
            ? 'success'
            : (
                in_array(
                    $normalized,
                    [
                        'failed',
                        'error',
                        'erro',
                        'falha',
                    ],
                    true
                )
                ? 'danger'
                : 'neutral'
            );

        ?>
        <span
            class="fd-admin-c__pill fd-admin-c__pill--<?php
            echo esc_attr($tone);
            ?>"
        >
            <?php
            echo esc_html(
                $value !== ''
                ? $value
                : '—'
            );
            ?>
        </span>
        <?php
    }

    private function emptyState(
        string $title,
        string $description
    ): void {
        ?>
        <div class="fd-admin-c__empty">
            <span
                class="dashicons dashicons-info-outline"
                aria-hidden="true"
            ></span>

            <strong>
                <?php
                echo esc_html($title);
                ?>
            </strong>

            <p>
                <?php
                echo esc_html(
                    $description
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * @param array<int,string> $candidates
     */
    private function firstExistingTable(
        array $candidates
    ): string {
        global $wpdb;

        foreach ($candidates as $table) {
            $exists =
                $wpdb->get_var(
                    $wpdb->prepare(
                        'SHOW TABLES LIKE %s',
                        $table
                    )
                );

            if (
                is_string($exists)
                && $exists === $table
            ) {
                return $table;
            }
        }

        return '';
    }

    /**
     * @return array<int,string>
     */
    private function columns(
        string $table
    ): array {
        global $wpdb;

        $rows =
            $wpdb->get_results(
                "SHOW COLUMNS FROM `{$table}`",
                ARRAY_A
            );

        if (!is_array($rows)) {
            return [];
        }

        $columns = [];

        foreach ($rows as $row) {
            if (
                isset($row['Field'])
                && is_string($row['Field'])
            ) {
                $columns[] =
                    $row['Field'];
            }
        }

        return $columns;
    }

    /**
     * @param array<int,string> $columns
     * @param array<int,string> $candidates
     */
    private function firstColumn(
        array $columns,
        array $candidates
    ): string {
        foreach ($candidates as $candidate) {
            if (
                in_array(
                    $candidate,
                    $columns,
                    true
                )
            ) {
                return $candidate;
            }
        }

        return '';
    }

    private function countRows(
        string $table
    ): int {
        global $wpdb;

        return
            (int)
            $wpdb->get_var(
                "SELECT COUNT(*) FROM `{$table}`"
            );
    }

    private function userLabel(
        int $userId
    ): string {
        if ($userId <= 0) {
            return '—';
        }

        $user =
            get_userdata(
                $userId
            );

        if (!$user) {
            return '#' . $userId;
        }

        $label =
            trim(
                (string)
                $user->display_name
            );

        if ($label === '') {
            $label =
                (string)
                $user->user_login;
        }

        return
            $label
            . ' (#'
            . $userId
            . ')';
    }

    private function productLabel(
        int $productId
    ): string {
        if ($productId <= 0) {
            return '—';
        }

        $product =
            function_exists(
                'wc_get_product'
            )
            ? wc_get_product(
                $productId
            )
            : null;

        if (!$product) {
            return '#' . $productId;
        }

        return
            $product->get_name()
            . ' (#'
            . $productId
            . ')';
    }

    private function simulationLabel(
        int $simulationId
    ): string {
        if ($simulationId <= 0) {
            return '—';
        }

        global $wpdb;

        $table =
            $this->firstExistingTable(
                [
                    $wpdb->prefix
                        . 'fd_simulations',
                ]
            );

        if ($table === '') {
            return
                '#'
                . $simulationId;
        }

        $columns =
            $this->columns($table);

        $idColumn =
            $this->firstColumn(
                $columns,
                [
                    'id',
                    'simulation_id',
                ]
            );

        $titleColumn =
            $this->firstColumn(
                $columns,
                [
                    'title',
                    'name',
                ]
            );

        if (
            $idColumn === ''
            || $titleColumn === ''
        ) {
            return
                '#'
                . $simulationId;
        }

        $title =
            $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT `{$titleColumn}` FROM `{$table}` WHERE `{$idColumn}` = %d LIMIT 1",
                    $simulationId
                )
            );

        if (
            !is_scalar($title)
            || trim(
                (string) $title
            )
            === ''
        ) {
            return
                '#'
                . $simulationId;
        }

        return
            (string) $title
            . ' (#'
            . $simulationId
            . ')';
    }

    /**
     * @param mixed $value
     */
    private function dateLabel(
        $value
    ): string {
        if (
            $value === null
            || $value === ''
        ) {
            return '—';
        }

        if (is_numeric($value)) {
            $timestamp =
                (int) $value;

            if ($timestamp > 0) {
                return
                    wp_date(
                        'd/m/Y H:i',
                        $timestamp
                    );
            }
        }

        $timestamp =
            strtotime(
                (string) $value
            );

        if ($timestamp === false) {
            return
                (string) $value;
        }

        return
            wp_date(
                'd/m/Y H:i',
                $timestamp
            );
    }

    /**
     * @param array<int,string> $labels
     * @return array{
     *   label:string,
     *   page:string,
     *   url:string
     * }|null
     */
    private function existingAdminLink(
        array $labels
    ): ?array {
        global $submenu;

        if (
            !isset($submenu[self::PARENT_PAGE])
            || !is_array(
                $submenu[self::PARENT_PAGE]
            )
        ) {
            return null;
        }

        $needles =
            array_map(
                fn (
                    string $label
                ): string =>
                    $this->normalize(
                        $label
                    ),
                $labels
            );

        foreach (
            $submenu[self::PARENT_PAGE]
            as $item
        ) {
            $title =
                isset($item[0])
                ? $this->normalize(
                    wp_strip_all_tags(
                        (string) $item[0]
                    )
                )
                : '';

            if (
                !in_array(
                    $title,
                    $needles,
                    true
                )
            ) {
                continue;
            }

            $target =
                isset($item[2])
                ? (string) $item[2]
                : '';

            if ($target === '') {
                return null;
            }

            return [
                'label' =>
                    wp_strip_all_tags(
                        (string) $item[0]
                    ),
                'page' =>
                    $target,
                'url' =>
                    $this->adminTargetUrl(
                        $target
                    ),
            ];
        }

        return null;
    }

    private function adminTargetUrl(
        string $target
    ): string {
        if (
            str_contains(
                $target,
                '.php'
            )
        ) {
            return admin_url(
                $target
            );
        }

        return
            admin_url(
                'admin.php?page='
                . rawurlencode(
                    $target
                )
            );
    }

    private function tabUrl(
        string $tab
    ): string {
        return
            add_query_arg(
                [
                    'page' =>
                        self::PAGE,
                    'fd_tab' =>
                        $tab,
                ],
                admin_url(
                    'admin.php'
                )
            );
    }

    private function shortTableName(
        string $table
    ): string {
        global $wpdb;

        if (
            str_starts_with(
                $table,
                $wpdb->prefix
            )
        ) {
            return
                substr(
                    $table,
                    strlen(
                        $wpdb->prefix
                    )
                );
        }

        return $table;
    }

    private function currentPage(): string
    {
        return
            isset($_GET['page'])
            ? sanitize_key(
                wp_unslash(
                    (string)
                    $_GET['page']
                )
            )
            : '';
    }

    private function normalize(
        string $value
    ): string {
        return
            strtolower(
                trim(
                    remove_accents(
                        wp_strip_all_tags(
                            $value
                        )
                    )
                )
            );
    }

    private function guard(): void
    {
        if (
            current_user_can(
                self::CAPABILITY
            )
        ) {
            return;
        }

        wp_die(
            esc_html__(
                'Você não tem permissão para acessar a operação da Fácil Digital+.',
                'facil-digital-core'
            )
        );
    }
}