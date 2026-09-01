<?php

declare(strict_types=1);

namespace FacilDigital\Core\Admin;

use FacilDigital\Core\Contests\ContestModule;
use FacilDigital\Core\Products\ProductMetadata;
use RuntimeException;
use Throwable;
use WC_Product;
use WC_Product_Simple;

/**
 * ADMIN-B - central de conteudo.
 *
 * WooCommerce permanece autoridade para produto/preco/status/capa.
 * O Core permanece autoridade para metadados, PDF, questoes e simulados.
 */
final class AdminContentModule
{
    private const PARENT = 'facil-digital';
    private const PAGE = 'facil-digital-apostilas';
    private const CAP = 'manage_options';
    private const SAVE_ACTION = 'fd_admin_b_save_apostila';
    private const CSV_SUPPORTED = true;

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu'], 110);
        add_action('admin_menu', [$this, 'organizeMenu'], 140);
        add_action('admin_enqueue_scripts', [$this, 'assets'], 30);
        add_action('admin_post_' . self::SAVE_ACTION, [$this, 'save']);
        add_action('admin_notices', [$this, 'contentNavNotice']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            self::PARENT,
            __('Apostilas', 'facil-digital-core'),
            __('Apostilas', 'facil-digital-core'),
            self::CAP,
            self::PAGE,
            [$this, 'render']
        );
    }

    public function organizeMenu(): void
    {
        global $submenu;
        if (!isset($submenu[self::PARENT]) || !is_array($submenu[self::PARENT])) {
            return;
        }

        $priority = [];
        $rest = [];
        foreach ($submenu[self::PARENT] as $item) {
            $label = $this->normalize(isset($item[0]) ? wp_strip_all_tags((string) $item[0]) : '');
            $slug = isset($item[2]) ? (string) $item[2] : '';

            if (self::CSV_SUPPORTED && str_contains($label, 'import')) {
                $item[0] = __('Importar CSV', 'facil-digital-core');
                if (isset($item[3])) {
                    $item[3] = __('Importar CSV', 'facil-digital-core');
                }
                $label = 'importar csv';
            }

            $slot = match (true) {
                $slug === 'facil-digital-admin' || $label === 'visao geral' => 10,
                $slug === 'facil-digital-vendas' || $label === 'vendas' => 20,
                $slug === self::PAGE || $label === 'apostilas' => 30,
                str_contains($label, 'banco de quest') => 40,
                $label === 'simulados' => 50,
                str_contains($label, 'import') => 60,
                default => null,
            };

            if ($slot === null) {
                $rest[] = $item;
                continue;
            }
            while (isset($priority[$slot])) {
                $slot++;
            }
            $priority[$slot] = $item;
        }
        ksort($priority);
        $submenu[self::PARENT] = array_values(array_merge($priority, $rest));
    }

    public function assets(string $hook): void
    {
        unset($hook);
        $page = $this->currentPage();
        if ($page !== self::PAGE && !$this->isContentPage($page)) {
            return;
        }

        wp_enqueue_style(
            'facil-digital-admin-a',
            plugins_url('assets/admin/admin-a.css', FACIL_DIGITAL_CORE_FILE),
            [],
            defined('FACIL_DIGITAL_CORE_VERSION') ? FACIL_DIGITAL_CORE_VERSION : null
        );
        wp_enqueue_style(
            'facil-digital-admin-b',
            plugins_url('assets/admin/admin-b.css', FACIL_DIGITAL_CORE_FILE),
            ['facil-digital-admin-a'],
            defined('FACIL_DIGITAL_CORE_VERSION') ? FACIL_DIGITAL_CORE_VERSION : null
        );

        if ($page === self::PAGE) {
            wp_enqueue_media();
            wp_enqueue_script(
                'facil-digital-admin-b',
                plugins_url('assets/admin/admin-b.js', FACIL_DIGITAL_CORE_FILE),
                [],
                defined('FACIL_DIGITAL_CORE_VERSION') ? FACIL_DIGITAL_CORE_VERSION : null,
                true
            );
        }
    }

    public function render(): void
    {
        $this->guard();
        $action = isset($_GET['fd_action']) ? sanitize_key(wp_unslash((string) $_GET['fd_action'])) : '';
        $id = isset($_GET['product_id']) ? absint(wp_unslash((string) $_GET['product_id'])) : 0;

        if (in_array($action, ['new', 'edit'], true)) {
            $this->renderEditor($action, $id);
            return;
        }
        $this->renderList();
    }

    public function save(): void
    {
        $this->guard();
        check_admin_referer('fd_admin_b_save_apostila', 'fd_admin_b_nonce');
        $id = isset($_POST['product_id']) ? absint(wp_unslash((string) $_POST['product_id'])) : 0;

        try {
            $product = $this->productForSave($id);
            $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash((string) $_POST['name'])) : '';
            if ($name === '') {
                throw new RuntimeException('Informe o título da apostila.');
            }

            $rawPrice = isset($_POST['regular_price']) ? sanitize_text_field(wp_unslash((string) $_POST['regular_price'])) : '';
            $price = function_exists('wc_format_decimal') ? wc_format_decimal($rawPrice) : $rawPrice;
            if ($price === '' || !is_numeric($price) || (float) $price < 0) {
                throw new RuntimeException('Informe um preço válido.');
            }

            $status = isset($_POST['status']) ? sanitize_key(wp_unslash((string) $_POST['status'])) : 'draft';
            if (!in_array($status, ['draft', 'publish'], true)) {
                $status = 'draft';
            }

            $imageId = isset($_POST['image_id']) ? absint(wp_unslash((string) $_POST['image_id'])) : 0;
            if ($imageId > 0 && !wp_attachment_is_image($imageId)) {
                $imageId = 0;
            }

            $product->set_name($name);
            $product->set_regular_price((string) $price);
            $product->set_description(isset($_POST['description']) ? wp_kses_post(wp_unslash((string) $_POST['description'])) : '');
            $product->set_short_description(isset($_POST['short_description']) ? wp_kses_post(wp_unslash((string) $_POST['short_description'])) : '');
            $product->set_status($status);
            $product->set_virtual(true);
            $product->set_downloadable(false);
            $product->set_sold_individually(true);
            $product->set_catalog_visibility('visible');
            $product->set_image_id($imageId);

            $savedId = (int) $product->save();
            if ($savedId <= 0) {
                throw new RuntimeException('Não foi possível salvar o produto no WooCommerce.');
            }

            $this->saveMetadata($savedId);
            $this->saveContest($savedId);
            clean_post_cache($savedId);
            wp_safe_redirect(add_query_arg('fd_notice', 'saved', $this->editorUrl($savedId)));
            exit;
        } catch (Throwable $e) {
            set_transient('fd_admin_b_error_' . get_current_user_id(), sanitize_text_field($e->getMessage()), MINUTE_IN_SECONDS);
            $url = $id > 0 ? $this->editorUrl($id) : $this->editorUrl();
            wp_safe_redirect($url);
            exit;
        }
    }

    private function renderList(): void
    {
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash((string) $_GET['s'])) : '';
        $status = isset($_GET['status']) ? sanitize_key(wp_unslash((string) $_GET['status'])) : '';
        $all = $this->products();
        $products = $all;

        if ($search !== '') {
            $needle = $this->normalize($search);
            $products = array_values(array_filter($products, fn (WC_Product $p): bool => str_contains($this->normalize($p->get_name()), $needle)));
        }
        if (in_array($status, ['publish', 'draft', 'pending', 'private'], true)) {
            $products = array_values(array_filter($products, static fn (WC_Product $p): bool => $p->get_status() === $status));
        }

        $published = count(array_filter($all, static fn (WC_Product $p): bool => $p->get_status() === 'publish'));
        $withMaster = count(array_filter($all, fn (WC_Product $p): bool => $this->masterKey($p->get_id()) !== ''));
        $withSims = count(array_filter($all, fn (WC_Product $p): bool => $this->meta($p->get_id(), 'HAS_SIMULATIONS', 'no') === 'yes'));
        ?>
        <div class="wrap fd-admin-a fd-admin-b">
            <?php $this->hero(__('Apostilas', 'facil-digital-core'), __('Cadastre e mantenha os produtos digitais sem sair do painel Fácil Digital+.', 'facil-digital-core')); ?>
            <?php $this->tabs(self::PAGE); ?>

            <section class="fd-admin-b__summary">
                <?php $this->summaryCard((string) count($all), __('Apostilas cadastradas', 'facil-digital-core')); ?>
                <?php $this->summaryCard((string) $published, __('Publicadas', 'facil-digital-core')); ?>
                <?php $this->summaryCard((string) $withMaster, __('Com PDF master', 'facil-digital-core')); ?>
                <?php $this->summaryCard((string) $withSims, __('Com simulados', 'facil-digital-core')); ?>
            </section>

            <section class="fd-admin-b__panel">
                <div class="fd-admin-b__toolbar">
                    <form method="get" class="fd-admin-b__filters">
                        <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE); ?>">
                        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Buscar apostila...">
                        <select name="status">
                            <option value=""><?php esc_html_e('Todos os status', 'facil-digital-core'); ?></option>
                            <option value="publish" <?php selected($status, 'publish'); ?>><?php esc_html_e('Publicado', 'facil-digital-core'); ?></option>
                            <option value="draft" <?php selected($status, 'draft'); ?>><?php esc_html_e('Rascunho', 'facil-digital-core'); ?></option>
                            <option value="pending" <?php selected($status, 'pending'); ?>><?php esc_html_e('Pendente', 'facil-digital-core'); ?></option>
                            <option value="private" <?php selected($status, 'private'); ?>><?php esc_html_e('Privado', 'facil-digital-core'); ?></option>
                        </select>
                        <button class="button" type="submit"><?php esc_html_e('Filtrar', 'facil-digital-core'); ?></button>
                        <?php if ($search !== '' || $status !== '') : ?>
                            <a class="button button-link" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE)); ?>"><?php esc_html_e('Limpar', 'facil-digital-core'); ?></a>
                        <?php endif; ?>
                    </form>
                    <a class="button button-primary" href="<?php echo esc_url($this->editorUrl()); ?>"><?php esc_html_e('Nova apostila', 'facil-digital-core'); ?></a>
                </div>
                <?php $this->productTable($products); ?>
            </section>

            <aside class="fd-admin-b__note">
                <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                <div><strong><?php esc_html_e('PDF master continua protegido pelo Core', 'facil-digital-core'); ?></strong><p><?php esc_html_e('O cadastro desta tela controla produto, capa e metadados. O arquivo original continua no storage privado e é gerenciado pelo módulo PDF Master na edição técnica do produto.', 'facil-digital-core'); ?></p></div>
            </aside>
        </div>
        <?php
    }

    /** @param array<int,WC_Product> $products */
    private function productTable(array $products): void
    {
        if ($products === []) {
            echo '<div class="fd-admin-b__empty"><span class="dashicons dashicons-book-alt"></span><strong>' . esc_html__('Nenhuma apostila encontrada.', 'facil-digital-core') . '</strong></div>';
            return;
        }
        ?>
        <div class="fd-admin-b__table-wrap"><table class="fd-admin-b__table"><thead><tr>
            <th><?php esc_html_e('Apostila', 'facil-digital-core'); ?></th><th><?php esc_html_e('Preço', 'facil-digital-core'); ?></th><th><?php esc_html_e('Concurso / Cargo', 'facil-digital-core'); ?></th><th><?php esc_html_e('PDF master', 'facil-digital-core'); ?></th><th><?php esc_html_e('Simulados', 'facil-digital-core'); ?></th><th><?php esc_html_e('Status', 'facil-digital-core'); ?></th><th><?php esc_html_e('Ações', 'facil-digital-core'); ?></th>
        </tr></thead><tbody>
        <?php foreach ($products as $product) :
            $id = $product->get_id();
            $cover = wp_get_attachment_image_url($product->get_image_id(), 'thumbnail');
            $master = $this->masterKey($id);
            $sims = $this->meta($id, 'HAS_SIMULATIONS', 'no') === 'yes';
            $contest = $this->contestLabel($id);
            $position = $this->meta($id, 'POSITION_NAME');
        ?>
            <tr>
                <td><div class="fd-admin-b__product"><div class="fd-admin-b__cover"><?php if (is_string($cover) && $cover !== '') : ?><img src="<?php echo esc_url($cover); ?>" alt=""><?php else : ?><span class="dashicons dashicons-book-alt"></span><?php endif; ?></div><div><strong><?php echo esc_html($product->get_name()); ?></strong><small>#<?php echo esc_html((string) $id); ?><?php $v = $this->meta($id, 'MATERIAL_VERSION'); if ($v !== '') { echo ' · ' . esc_html($v); } ?></small></div></div></td>
                <td><strong><?php echo wp_kses_post(wc_price((float) $product->get_regular_price())); ?></strong></td>
                <td><div class="fd-admin-b__stack"><strong><?php echo esc_html($contest !== '' ? $contest : '—'); ?></strong><small><?php echo esc_html($position !== '' ? $position : 'Sem cargo'); ?></small></div></td>
                <td><?php $this->pill($master !== '' ? 'Configurado' : 'Pendente', $master !== '' ? 'success' : 'warning'); ?></td>
                <td><?php $this->pill($sims ? 'Sim' : 'Não', $sims ? 'primary' : 'neutral'); ?></td>
                <td><?php $this->pill($this->statusLabel($product->get_status()), $product->get_status() === 'publish' ? 'success' : 'neutral'); ?></td>
                <td><div class="fd-admin-b__actions"><a href="<?php echo esc_url($this->editorUrl($id)); ?>"><?php esc_html_e('Editar', 'facil-digital-core'); ?></a><?php if ($product->get_status() === 'publish') : ?><a href="<?php echo esc_url(get_permalink($id)); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Ver', 'facil-digital-core'); ?></a><?php endif; ?></div></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php
    }

    private function renderEditor(string $action, int $id): void
    {
        $product = null;
        if ($action === 'edit') {
            $product = wc_get_product($id);
            if (!$product instanceof WC_Product || !$this->isApostila($id)) {
                wp_die(esc_html__('Apostila não encontrada.', 'facil-digital-core'));
            }
        }
        $new = !$product instanceof WC_Product;
        $v = $this->values($product);
        $errorKey = 'fd_admin_b_error_' . get_current_user_id();
        $error = get_transient($errorKey);
        delete_transient($errorKey);
        $saved = isset($_GET['fd_notice']) && sanitize_key(wp_unslash((string) $_GET['fd_notice'])) === 'saved';
        ?>
        <div class="wrap fd-admin-a fd-admin-b">
            <?php $this->hero($new ? __('Nova apostila', 'facil-digital-core') : __('Editar apostila', 'facil-digital-core'), $new ? __('Cadastre como rascunho, confira capa e PDF master e publique quando estiver pronta.', 'facil-digital-core') : __('Atualize os dados comerciais e técnicos da apostila.', 'facil-digital-core')); ?>
            <?php $this->tabs(self::PAGE); ?>
            <?php if ($saved) : ?><div class="notice notice-success inline"><p><?php esc_html_e('Apostila salva com sucesso.', 'facil-digital-core'); ?></p></div><?php endif; ?>
            <?php if (is_string($error) && $error !== '') : ?><div class="notice notice-error inline"><p><?php echo esc_html($error); ?></p></div><?php endif; ?>

            <form class="fd-admin-b__editor" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::SAVE_ACTION); ?>">
                <input type="hidden" name="product_id" value="<?php echo esc_attr((string) $v['product_id']); ?>">
                <?php wp_nonce_field('fd_admin_b_save_apostila', 'fd_admin_b_nonce'); ?>

                <div class="fd-admin-b__editor-main">
                    <?php $this->formCardStart('Comercial', 'Informações da apostila', 'Dados exibidos no catálogo e na página pública.'); ?>
                    <div class="fd-admin-b__fields">
                        <?php $this->field('name', 'Título', (string) $v['name'], true); ?>
                        <?php $this->field('regular_price', 'Preço (R$)', (string) $v['regular_price'], true, 'text', '14,50'); ?>
                        <?php $this->textarea('short_description', 'Descrição curta', (string) $v['short_description'], 3); ?>
                        <?php $this->textarea('description', 'Descrição completa', (string) $v['description'], 7); ?>
                    </div></section>

                    <?php $this->formCardStart('Classificação', 'Concurso e material'); ?>
                    <div class="fd-admin-b__fields">
                        <?php $this->contestField((int) $v['contest_term_id']); ?>
                        <?php $this->field('position_name', 'Cargo', (string) $v['position_name']); ?>
                        <?php $this->field('board', 'Banca', (string) $v['board']); ?>
                        <?php $this->field('exam_year', 'Ano', (string) $v['exam_year'], false, 'number'); ?>
                        <?php $this->field('page_count', 'Número de páginas', (string) $v['page_count'], false, 'number'); ?>
                        <?php $this->field('material_version', 'Versão do material', (string) $v['material_version'], false, 'text', '1.0'); ?>
                    </div></section>

                    <?php $this->formCardStart('Entrega digital', 'Proteção e acesso', 'O PDF personalizado continua sob controle do Fácil Digital Core.'); ?>
                    <div class="fd-admin-b__fields">
                        <?php $this->field('download_limit', 'Limite de downloads', (string) $v['download_limit'], false, 'number'); ?>
                        <div class="fd-admin-b__checks fd-admin-b__field--wide">
                            <?php $this->check('generate_personalized_pdf', 'Gerar PDF personalizado após a compra', (bool) $v['generate_personalized_pdf']); ?>
                            <?php $this->check('watermark_enabled', 'Aplicar marca d’água', (bool) $v['watermark_enabled']); ?>
                            <?php $this->check('pdf_password_enabled', 'Proteger PDF com senha', (bool) $v['pdf_password_enabled']); ?>
                            <?php $this->check('has_simulations', 'Esta apostila possui simulados', (bool) $v['has_simulations']); ?>
                        </div>
                    </div></section>
                </div>

                <aside class="fd-admin-b__editor-side">
                    <?php $this->formCardStart('Publicação', 'Status'); ?>
                    <label class="fd-admin-b__field"><span>Estado da apostila</span><select name="status"><option value="draft" <?php selected((string) $v['status'], 'draft'); ?>>Rascunho</option><option value="publish" <?php selected((string) $v['status'], 'publish'); ?>>Publicado</option></select></label>
                    <div class="fd-admin-b__publish-help"><span class="dashicons dashicons-info-outline"></span><p>Use Rascunho até conferir capa, PDF master e página pública.</p></div></section>

                    <?php $this->formCardStart('Capa', 'Imagem do produto'); ?>
                    <?php $this->mediaField((int) $v['image_id']); ?></section>

                    <?php if (!$new) : $master = $this->masterKey((int) $v['product_id']); ?>
                        <?php $this->formCardStart('Arquivo protegido', 'PDF master'); ?>
                        <div class="fd-admin-b__master"><?php $this->pill($master !== '' ? 'PDF configurado' : 'PDF pendente', $master !== '' ? 'success' : 'warning'); ?><p>O arquivo original permanece fora do public_html. Use o módulo PDF Master da edição técnica.</p><a class="button" href="<?php echo esc_url((string) get_edit_post_link((int) $v['product_id'], '')); ?>">Gerenciar PDF master</a></div></section>
                    <?php endif; ?>

                    <div class="fd-admin-b__save"><button class="button button-primary button-hero" type="submit">Salvar apostila</button><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE)); ?>">Voltar à lista</a></div>
                </aside>
            </form>
        </div>
        <?php
    }

    public function contentNavNotice(): void
    {
        $page = $this->currentPage();
        if ($page === '' || $page === self::PAGE || !$this->isContentPage($page)) {
            return;
        }
        echo '<div class="fd-admin-b-context"><div class="fd-admin-b-context__inner"><strong>Conteúdo Fácil Digital+</strong>';
        $this->tabs($page, true);
        echo '</div></div>';
    }

    private function hero(string $title, string $description): void
    {
        ?>
        <header class="fd-admin-a__hero fd-admin-b__hero"><div><span class="fd-admin-a__eyebrow">Fácil Digital+ · Conteúdo</span><h1><?php echo esc_html($title); ?></h1><p><?php echo esc_html($description); ?></p></div><div class="fd-admin-a__hero-actions"><a class="button button-primary" href="<?php echo esc_url($this->editorUrl()); ?>">Nova apostila</a><a class="button" href="<?php echo esc_url(home_url('/apostilas/')); ?>" target="_blank" rel="noopener noreferrer">Ver catálogo</a></div></header>
        <?php
    }

    private function summaryCard(string $value, string $label): void
    {
        echo '<article><strong>' . esc_html($value) . '</strong><span>' . esc_html($label) . '</span></article>';
    }

    private function tabs(string $current, bool $compact = false): void
    {
        $links = $this->contentLinks();
        if ($links === []) {
            return;
        }
        echo '<nav class="fd-admin-b__tabs ' . ($compact ? 'fd-admin-b__tabs--compact' : '') . '" aria-label="Navegação de conteúdo">';
        foreach ($links as $link) {
            echo '<a class="' . esc_attr($link['page'] === $current ? 'is-active' : '') . '" href="' . esc_url($link['url']) . '">' . esc_html($link['label']) . '</a>';
        }
        echo '</nav>';
    }

    /** @return array<int,array{label:string,page:string,url:string}> */
    private function contentLinks(): array
    {
        $links = [['label' => 'Apostilas', 'page' => self::PAGE, 'url' => admin_url('admin.php?page=' . self::PAGE)]];
        foreach ([
            [['Banco de Questões', 'Banco de Questoes'], 'Banco de Questões'],
            [['Simulados'], 'Simulados'],
            [['Importar CSV', 'Importação', 'Importacao'], self::CSV_SUPPORTED ? 'Importar CSV' : 'Importação'],
        ] as [$needles, $label]) {
            $item = $this->submenuByLabels($needles);
            if ($item !== null) {
                $links[] = ['label' => $label, 'page' => $item['page'], 'url' => $item['url']];
            }
        }
        return $links;
    }

    /** @param array<int,string> $labels @return array{page:string,url:string}|null */
    private function submenuByLabels(array $labels): ?array
    {
        global $submenu;
        if (!isset($submenu[self::PARENT]) || !is_array($submenu[self::PARENT])) {
            return null;
        }
        $needles = array_map(fn (string $v): string => $this->normalize($v), $labels);
        foreach ($submenu[self::PARENT] as $item) {
            $label = isset($item[0]) ? $this->normalize(wp_strip_all_tags((string) $item[0])) : '';
            if (!in_array($label, $needles, true)) {
                continue;
            }
            $target = isset($item[2]) ? (string) $item[2] : '';
            if ($target === '') {
                return null;
            }
            return ['page' => $target, 'url' => str_contains($target, '.php') ? admin_url($target) : admin_url('admin.php?page=' . rawurlencode($target))];
        }
        return null;
    }

    private function isContentPage(string $page): bool
    {
        foreach ($this->contentLinks() as $link) {
            if ($link['page'] === $page) {
                return true;
            }
        }
        return false;
    }

    /** @return array<int,WC_Product> */
    private function products(): array
    {
        if (!function_exists('wc_get_products')) {
            return [];
        }
        $items = wc_get_products(['status' => ['publish', 'draft', 'pending', 'private'], 'limit' => 500, 'orderby' => 'date', 'order' => 'DESC']);
        return array_values(array_filter($items, fn ($p): bool => $p instanceof WC_Product && $this->isApostila($p->get_id())));
    }

    private function productForSave(int $id): WC_Product_Simple
    {
        if ($id <= 0) {
            return new WC_Product_Simple();
        }
        $product = wc_get_product($id);
        if (!$product instanceof WC_Product_Simple || !$this->isApostila($id)) {
            throw new RuntimeException('Somente produtos simples marcados como apostila podem ser editados aqui.');
        }
        return $product;
    }

    private function saveMetadata(int $id): void
    {
        $this->setMeta($id, 'IS_APOSTILA', 'yes', true);
        $this->setMeta($id, 'POSITION_NAME', $this->postText('position_name'));
        $this->setMeta($id, 'BOARD', $this->postText('board'));
        $this->setMeta($id, 'EXAM_YEAR', $this->postIntString('exam_year'));
        $this->setMeta($id, 'PAGE_COUNT', $this->postIntString('page_count'));
        $this->setMeta($id, 'MATERIAL_VERSION', $this->postText('material_version'));
        $this->setMeta($id, 'HAS_SIMULATIONS', isset($_POST['has_simulations']) ? 'yes' : 'no');
        $limit = isset($_POST['download_limit']) ? max(1, absint(wp_unslash((string) $_POST['download_limit']))) : 5;
        $this->setMeta($id, 'DOWNLOAD_LIMIT', (string) $limit);
        $this->setMeta($id, 'GENERATE_PERSONALIZED_PDF', isset($_POST['generate_personalized_pdf']) ? 'yes' : 'no');
        $this->setMeta($id, 'WATERMARK_ENABLED', isset($_POST['watermark_enabled']) ? 'yes' : 'no');
        $this->setMeta($id, 'PDF_PASSWORD_ENABLED', isset($_POST['pdf_password_enabled']) ? 'yes' : 'no');
    }

    private function saveContest(int $id): void
    {
        $taxonomy = $this->contestTaxonomy();
        if ($taxonomy === '') {
            return;
        }
        $termId = isset($_POST['contest_term_id']) ? absint(wp_unslash((string) $_POST['contest_term_id'])) : 0;
        $result = wp_set_object_terms($id, $termId > 0 ? [$termId] : [], $taxonomy, false);
        if (is_wp_error($result)) {
            throw new RuntimeException($result->get_error_message());
        }
    }

    private function isApostila(int $id): bool
    {
        return class_exists(ProductMetadata::class) && method_exists(ProductMetadata::class, 'isApostila') && ProductMetadata::isApostila($id);
    }

    private function meta(int $id, string $constantName, string $default = ''): string
    {
        $constant = ProductMetadata::class . '::' . $constantName;
        if (!defined($constant)) {
            return $default;
        }
        $key = (string) constant($constant);
        if (method_exists(ProductMetadata::class, 'get')) {
            return (string) ProductMetadata::get($id, $key, $default);
        }
        $value = get_post_meta($id, $key, true);
        return $value !== '' ? (string) $value : $default;
    }

    private function setMeta(int $id, string $constantName, string $value, bool $required = false): void
    {
        $constant = ProductMetadata::class . '::' . $constantName;
        if (!defined($constant)) {
            if ($required) {
                throw new RuntimeException('Metadado obrigatório ausente no Core: ' . $constantName);
            }
            return;
        }
        $key = (string) constant($constant);
        if ($value === '') {
            delete_post_meta($id, $key);
        } else {
            update_post_meta($id, $key, $value);
        }
    }

    private function masterKey(int $id): string
    {
        $needle = 'masters/product-' . $id . '/';
        foreach (get_post_meta($id) as $values) {
            foreach ((array) $values as $value) {
                if (is_scalar($value)) {
                    $v = (string) $value;
                    if (str_starts_with($v, $needle) && str_ends_with(strtolower($v), '.pdf')) {
                        return $v;
                    }
                }
            }
        }
        return '';
    }

    private function contestTaxonomy(): string
    {
        $constant = ContestModule::class . '::TAXONOMY';
        if (!defined($constant)) {
            return '';
        }
        $taxonomy = (string) constant($constant);
        return $taxonomy !== '' && taxonomy_exists($taxonomy) ? $taxonomy : '';
    }

    private function contestLabel(int $id): string
    {
        $taxonomy = $this->contestTaxonomy();
        if ($taxonomy === '') {
            return '';
        }
        $terms = wp_get_object_terms($id, $taxonomy, ['fields' => 'names']);
        return is_wp_error($terms) || $terms === [] ? '' : implode(', ', array_map('strval', $terms));
    }

    private function contestTermId(int $id): int
    {
        $taxonomy = $this->contestTaxonomy();
        if ($taxonomy === '') {
            return 0;
        }
        $terms = wp_get_object_terms($id, $taxonomy, ['fields' => 'ids']);
        return is_wp_error($terms) || $terms === [] ? 0 : (int) $terms[0];
    }

    /** @return array<string,mixed> */
    private function values(?WC_Product $p): array
    {
        if (!$p instanceof WC_Product) {
            return ['product_id'=>0,'name'=>'','regular_price'=>'','description'=>'','short_description'=>'','status'=>'draft','image_id'=>0,'contest_term_id'=>0,'position_name'=>'','board'=>'','exam_year'=>'','page_count'=>'','material_version'=>'1.0','download_limit'=>'5','generate_personalized_pdf'=>true,'watermark_enabled'=>true,'pdf_password_enabled'=>true,'has_simulations'=>false];
        }
        $id = $p->get_id();
        return [
            'product_id'=>$id,'name'=>$p->get_name(),'regular_price'=>$p->get_regular_price(),'description'=>$p->get_description(),'short_description'=>$p->get_short_description(),'status'=>$p->get_status(),'image_id'=>$p->get_image_id(),'contest_term_id'=>$this->contestTermId($id),
            'position_name'=>$this->meta($id,'POSITION_NAME'),'board'=>$this->meta($id,'BOARD'),'exam_year'=>$this->meta($id,'EXAM_YEAR'),'page_count'=>$this->meta($id,'PAGE_COUNT'),'material_version'=>$this->meta($id,'MATERIAL_VERSION','1.0'),'download_limit'=>$this->meta($id,'DOWNLOAD_LIMIT','5'),
            'generate_personalized_pdf'=>$this->meta($id,'GENERATE_PERSONALIZED_PDF','yes')==='yes','watermark_enabled'=>$this->meta($id,'WATERMARK_ENABLED','yes')==='yes','pdf_password_enabled'=>$this->meta($id,'PDF_PASSWORD_ENABLED','yes')==='yes','has_simulations'=>$this->meta($id,'HAS_SIMULATIONS','no')==='yes',
        ];
    }

    private function contestField(int $selected): void
    {
        $taxonomy = $this->contestTaxonomy();
        $terms = $taxonomy !== '' ? get_terms(['taxonomy'=>$taxonomy,'hide_empty'=>false,'orderby'=>'name','order'=>'ASC']) : [];
        if (is_wp_error($terms)) { $terms = []; }
        echo '<label class="fd-admin-b__field"><span>Concurso</span><select name="contest_term_id"><option value="0">Selecione...</option>';
        foreach ($terms as $term) {
            echo '<option value="' . esc_attr((string) $term->term_id) . '" ' . selected($selected, (int) $term->term_id, false) . '>' . esc_html((string) $term->name) . '</option>';
        }
        echo '</select><small>O mesmo concurso pode ser usado nos simulados.</small></label>';
    }

    private function formCardStart(string $eyebrow, string $title, string $description = ''): void
    {
        echo '<section class="fd-admin-b__form-card"><div class="fd-admin-b__form-heading"><span class="fd-admin-a__eyebrow">' . esc_html($eyebrow) . '</span><h2>' . esc_html($title) . '</h2>';
        if ($description !== '') { echo '<p>' . esc_html($description) . '</p>'; }
        echo '</div>';
    }

    private function field(string $name, string $label, string $value, bool $required = false, string $type = 'text', string $placeholder = ''): void
    {
        echo '<label class="fd-admin-b__field"><span>' . esc_html($label) . ($required ? ' <em>*</em>' : '') . '</span><input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '"' . ($required ? ' required' : '') . '></label>';
    }

    private function textarea(string $name, string $label, string $value, int $rows): void
    {
        echo '<label class="fd-admin-b__field fd-admin-b__field--wide"><span>' . esc_html($label) . '</span><textarea name="' . esc_attr($name) . '" rows="' . esc_attr((string) $rows) . '">' . esc_textarea($value) . '</textarea></label>';
    }

    private function check(string $name, string $label, bool $checked): void
    {
        echo '<label class="fd-admin-b__check"><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked($checked, true, false) . '><span>' . esc_html($label) . '</span></label>';
    }

    private function mediaField(int $imageId): void
    {
        $url = $imageId > 0 ? wp_get_attachment_image_url($imageId, 'medium') : false;
        ?>
        <div class="fd-admin-b__media"><input type="hidden" name="image_id" class="fd-admin-b__media-id" value="<?php echo esc_attr((string) $imageId); ?>"><div class="fd-admin-b__media-preview"><?php if (is_string($url) && $url !== '') : ?><img src="<?php echo esc_url($url); ?>" alt=""><?php else : ?><span class="dashicons dashicons-format-image"></span><?php endif; ?></div><div class="fd-admin-b__media-actions"><button type="button" class="button fd-admin-b__media-select">Selecionar capa</button><button type="button" class="button button-link-delete fd-admin-b__media-remove" <?php if ($imageId <= 0) echo 'hidden'; ?>>Remover</button></div></div>
        <?php
    }

    private function pill(string $label, string $tone): void
    {
        echo '<span class="fd-admin-b__pill fd-admin-b__pill--' . esc_attr($tone) . '">' . esc_html($label) . '</span>';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) { 'publish'=>'Publicado','draft'=>'Rascunho','pending'=>'Pendente','private'=>'Privado',default=>$status };
    }

    private function editorUrl(int $id = 0): string
    {
        return add_query_arg(['page'=>self::PAGE,'fd_action'=>$id > 0 ? 'edit' : 'new','product_id'=>$id > 0 ? $id : false], admin_url('admin.php'));
    }

    private function postText(string $key): string
    {
        return isset($_POST[$key]) ? sanitize_text_field(wp_unslash((string) $_POST[$key])) : '';
    }

    private function postIntString(string $key): string
    {
        if (!isset($_POST[$key])) { return ''; }
        $value = absint(wp_unslash((string) $_POST[$key]));
        return $value > 0 ? (string) $value : '';
    }

    private function currentPage(): string
    {
        return isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
    }

    private function normalize(string $value): string
    {
        return strtolower(trim(remove_accents(wp_strip_all_tags($value))));
    }

    private function guard(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(esc_html__('Você não tem permissão para gerenciar o conteúdo da Fácil Digital+.', 'facil-digital-core'));
        }
    }
}
