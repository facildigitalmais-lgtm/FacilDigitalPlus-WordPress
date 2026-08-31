<?php

declare(strict_types=1);

namespace FacilDigital\Core\Products;

use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Core\Capabilities;
use WC_Product;

final class ProductMetadata implements ModuleInterface
{
    public const IS_APOSTILA = '_fd_is_apostila';
    public const POSITION_NAME = '_fd_position_name';
    public const BOARD = '_fd_board';
    public const EXAM_YEAR = '_fd_exam_year';
    public const PAGE_COUNT = '_fd_page_count';
    public const MATERIAL_VERSION = '_fd_material_version';
    public const HAS_SIMULATIONS = '_fd_has_simulations';
    public const DOWNLOAD_LIMIT = '_fd_download_limit';
    public const GENERATE_PERSONALIZED_PDF = '_fd_generate_personalized_pdf';
    public const WATERMARK_ENABLED = '_fd_watermark_enabled';
    public const PDF_PASSWORD_ENABLED = '_fd_pdf_password_enabled';
    public const MASTER_PDF_KEY = '_fd_master_pdf_key';

    private const NONCE_ACTION = 'fd_save_product_metadata';
    private const NONCE_NAME = '_fd_product_metadata_nonce';

    /**
     * @var list<string>
     */
    private const BOOLEAN_KEYS = [
        self::IS_APOSTILA,
        self::HAS_SIMULATIONS,
        self::GENERATE_PERSONALIZED_PDF,
        self::WATERMARK_ENABLED,
        self::PDF_PASSWORD_ENABLED,
    ];

    public function register(): void
    {
        add_filter(
            'woocommerce_product_data_tabs',
            [$this, 'addProductDataTab']
        );

        add_action(
            'woocommerce_product_data_panels',
            [$this, 'renderProductDataPanel']
        );

        add_action(
            'woocommerce_process_product_meta',
            [$this, 'saveProductData'],
            20
        );

        /*
         * Apostilas geram um unico direito de acesso
         * por produto para a conta compradora.
         *
         * Quantidade superior a 1 nao representa
         * direitos adicionais de entitlement.
         */
        add_filter(
            'woocommerce_is_sold_individually',
            [$this, 'soldIndividually'],
            20,
            2
        );
    }

    public function soldIndividually(
        bool $soldIndividually,
        WC_Product $product
    ): bool {
        if (
            self::isApostila(
                (int) $product->get_id()
            )
        ) {
            return true;
        }

        return $soldIndividually;
    }

    /**
     * @param array<string, mixed> $tabs
     * @return array<string, mixed>
     */
    public function addProductDataTab(array $tabs): array
    {
        $tabs['facil_digital'] = [
            'label' => __('Fácil Digital+', 'facil-digital-core'),
            'target' => 'facil_digital_product_data',
            'class' => ['show_if_simple'],
            'priority' => 70,
        ];

        return $tabs;
    }

    public function renderProductDataPanel(): void
    {
        global $post;

        $productId = $post instanceof \WP_Post
            ? (int) $post->ID
            : 0;

        wp_nonce_field(
            self::NONCE_ACTION,
            self::NONCE_NAME
        );

        echo '<div id="facil_digital_product_data" class="panel woocommerce_options_panel hidden">';

        echo '<div class="options_group">';

        woocommerce_wp_checkbox([
            'id' => self::IS_APOSTILA,
            'label' => __('Apostila Fácil Digital+', 'facil-digital-core'),
            'description' => __(
                'Ativa as regras próprias de apostila, entitlement e entrega protegida.',
                'facil-digital-core'
            ),
            'value' => self::get($productId, self::IS_APOSTILA, 'no'),
        ]);

        woocommerce_wp_text_input([
            'id' => self::POSITION_NAME,
            'label' => __('Cargo', 'facil-digital-core'),
            'value' => self::get($productId, self::POSITION_NAME),
        ]);

        woocommerce_wp_text_input([
            'id' => self::BOARD,
            'label' => __('Banca', 'facil-digital-core'),
            'value' => self::get($productId, self::BOARD),
        ]);

        woocommerce_wp_text_input([
            'id' => self::EXAM_YEAR,
            'label' => __('Ano', 'facil-digital-core'),
            'type' => 'number',
            'custom_attributes' => [
                'min' => '2000',
                'max' => '2100',
                'step' => '1',
            ],
            'value' => self::get($productId, self::EXAM_YEAR),
        ]);

        woocommerce_wp_text_input([
            'id' => self::PAGE_COUNT,
            'label' => __('Número de páginas', 'facil-digital-core'),
            'type' => 'number',
            'custom_attributes' => [
                'min' => '1',
                'step' => '1',
            ],
            'value' => self::get($productId, self::PAGE_COUNT),
        ]);

        woocommerce_wp_text_input([
            'id' => self::MATERIAL_VERSION,
            'label' => __('Versão do material', 'facil-digital-core'),
            'value' => self::get(
                $productId,
                self::MATERIAL_VERSION,
                '1'
            ),
        ]);

        echo '</div>';
        echo '<div class="options_group">';

        woocommerce_wp_checkbox([
            'id' => self::HAS_SIMULATIONS,
            'label' => __('Possui simulados', 'facil-digital-core'),
            'value' => self::get($productId, self::HAS_SIMULATIONS, 'no'),
        ]);

        woocommerce_wp_text_input([
            'id' => self::DOWNLOAD_LIMIT,
            'label' => __('Limite de downloads', 'facil-digital-core'),
            'type' => 'number',
            'custom_attributes' => [
                'min' => '1',
                'max' => '100',
                'step' => '1',
            ],
            'value' => self::get(
                $productId,
                self::DOWNLOAD_LIMIT,
                '5'
            ),
        ]);

        woocommerce_wp_checkbox([
            'id' => self::GENERATE_PERSONALIZED_PDF,
            'label' => __('Gerar PDF personalizado', 'facil-digital-core'),
            'value' => self::get(
                $productId,
                self::GENERATE_PERSONALIZED_PDF,
                'yes'
            ),
        ]);

        woocommerce_wp_checkbox([
            'id' => self::WATERMARK_ENABLED,
            'label' => __("Aplicar marca-d'água", 'facil-digital-core'),
            'value' => self::get(
                $productId,
                self::WATERMARK_ENABLED,
                'yes'
            ),
        ]);

        woocommerce_wp_checkbox([
            'id' => self::PDF_PASSWORD_ENABLED,
            'label' => __('Aplicar senha no PDF', 'facil-digital-core'),
            'value' => self::get(
                $productId,
                self::PDF_PASSWORD_ENABLED,
                'yes'
            ),
        ]);

        do_action(
            'facil_digital_product_pdf_fields',
            $productId
        );

        echo '</div>';
        echo '</div>';
    }

    public function saveProductData(int $productId): void
    {
        if (!current_user_can(Capabilities::MANAGE_APOSTILAS)) {
            return;
        }

        $nonce = isset($_POST[self::NONCE_NAME])
            && is_string($_POST[self::NONCE_NAME])
                ? sanitize_text_field(
                    wp_unslash($_POST[self::NONCE_NAME])
                )
                : '';

        if (
            $nonce === ''
            || !wp_verify_nonce($nonce, self::NONCE_ACTION)
        ) {
            return;
        }

        foreach (self::BOOLEAN_KEYS as $key) {
            update_post_meta(
                $productId,
                $key,
                isset($_POST[$key]) ? 'yes' : 'no'
            );
        }

        $this->saveText(
            $productId,
            self::POSITION_NAME
        );

        $this->saveText(
            $productId,
            self::BOARD
        );

        $this->saveInteger(
            $productId,
            self::EXAM_YEAR,
            2000,
            2100
        );

        $this->saveInteger(
            $productId,
            self::PAGE_COUNT,
            1,
            100000
        );

        $this->saveInteger(
            $productId,
            self::DOWNLOAD_LIMIT,
            1,
            100
        );

        $version = isset($_POST[self::MATERIAL_VERSION])
            && is_string($_POST[self::MATERIAL_VERSION])
                ? sanitize_text_field(
                    wp_unslash($_POST[self::MATERIAL_VERSION])
                )
                : '';

        update_post_meta(
            $productId,
            self::MATERIAL_VERSION,
            $version !== '' ? $version : '1'
        );

        if (!self::isApostila($productId)) {
            return;
        }

        $product = wc_get_product($productId);

        if (!$product instanceof WC_Product) {
            return;
        }

        $product->set_virtual(true);
        $product->set_downloadable(false);
        $product->set_tax_status('none');
        $product->save();

        do_action(
            'facil_digital_save_product_pdf_fields',
            $productId
        );
    }

    public static function get(
        int $productId,
        string $key,
        string $default = ''
    ): string {
        $value = get_post_meta(
            $productId,
            $key,
            true
        );

        if (!is_scalar($value)) {
            return $default;
        }

        $normalized = (string) $value;

        return $normalized !== ''
            ? $normalized
            : $default;
    }

    public static function isApostila(int $productId): bool
    {
        return self::get(
            $productId,
            self::IS_APOSTILA,
            'no'
        ) === 'yes';
    }

    private function saveText(
        int $productId,
        string $key
    ): void {
        $value = isset($_POST[$key])
            && is_string($_POST[$key])
                ? sanitize_text_field(
                    wp_unslash($_POST[$key])
                )
                : '';

        update_post_meta(
            $productId,
            $key,
            $value
        );
    }

    private function saveInteger(
        int $productId,
        string $key,
        int $minimum,
        int $maximum
    ): void {
        $raw = isset($_POST[$key])
            && is_scalar($_POST[$key])
                ? (string) wp_unslash($_POST[$key])
                : '';

        if (trim($raw) === '') {
            delete_post_meta(
                $productId,
                $key
            );
            return;
        }

        $value = absint($raw);

        if ($value < $minimum) {
            $value = $minimum;
        }

        if ($value > $maximum) {
            $value = $maximum;
        }

        update_post_meta(
            $productId,
            $key,
            (string) $value
        );
    }
}
