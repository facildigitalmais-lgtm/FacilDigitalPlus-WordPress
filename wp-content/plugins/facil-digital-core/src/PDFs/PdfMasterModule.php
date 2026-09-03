<?php

declare(strict_types=1);

namespace FacilDigital\Core\PDFs;

use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\Core\Capabilities;
use FacilDigital\Core\Products\ProductMetadata;

final class PdfMasterModule implements ModuleInterface
{
    private const FILE_FIELD = 'fd_master_pdf';

    public function __construct(
        private readonly MasterPdfService $service = new MasterPdfService()
    ) {
    }

    public function register(): void
    {
        add_action(
            'facil_digital_product_pdf_fields',
            [$this, 'renderFields']
        );

        add_action(
            'facil_digital_save_product_pdf_fields',
            [$this, 'saveFields']
        );

        add_action(
            'post_edit_form_tag',
            [$this, 'enableMultipartForm'],
            10,
            1
        );

        add_action(
            'admin_notices',
            [$this, 'renderNotice']
        );
    }

    public function enableMultipartForm(\WP_Post $post): void
    {
        if ($post->post_type !== 'product') {
            return;
        }

        echo ' enctype="multipart/form-data"';
    }

    public function renderFields(int $productId): void
    {
        if (!ProductMetadata::isApostila($productId)) {
            return;
        }

        $configured = $this->service->hasMaster($productId);

        echo '<p class="form-field">';
        echo '<label for="' . esc_attr(self::FILE_FIELD) . '">';
        echo esc_html__('PDF master privado', 'facil-digital-core');
        echo '</label>';
        echo '<input type="file" accept="application/pdf,.pdf" name="';
        echo esc_attr(self::FILE_FIELD);
        echo '" id="' . esc_attr(self::FILE_FIELD) . '">';
        echo '<span class="description">';

        echo esc_html(
            $configured
                ? __('Master privado configurado. Enviar outro PDF substitui o arquivo atual.', 'facil-digital-core')
                : __('O master será armazenado fora da raiz pública do WordPress.', 'facil-digital-core')
        );

        echo '</span>';
        echo '</p>';
    }

    public function saveFields(int $productId): void
    {
        if (!current_user_can(Capabilities::MANAGE_APOSTILAS)) {
            return;
        }

        if (!isset($_FILES[self::FILE_FIELD])) {
            return;
        }

        $file = $_FILES[self::FILE_FIELD];
        if (!is_array($file)) {
            return;
        }

        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            return;
        }

        if ($uploadError !== UPLOAD_ERR_OK) {
            $this->storeNotice(
                'error',
                __('O servidor não conseguiu receber o PDF master. Tente enviar o arquivo novamente.', 'facil-digital-core')
            );

            return;
        }

        try {
            $this->service->importUploaded(
                $productId,
                $file
            );

            $this->storeNotice(
                'success',
                __('PDF master privado atualizado.', 'facil-digital-core')
            );
        } catch (\Throwable) {
            $this->storeNotice(
                'error',
                __('Não foi possível salvar o PDF master. Verifique se o arquivo é um PDF válido e tente novamente.', 'facil-digital-core')
            );
        }
    }

    public function renderNotice(): void
    {
        $userId = get_current_user_id();
        if ($userId <= 0) {
            return;
        }

        $key = 'fd_pdf_master_notice_' . $userId;
        $notice = get_transient($key);
        delete_transient($key);

        if (!is_array($notice)) {
            return;
        }

        $type = ($notice['type'] ?? '') === 'success'
            ? 'success'
            : 'error';
        $message = isset($notice['message'])
            && is_string($notice['message'])
                ? $notice['message']
                : '';

        if ($message === '') {
            return;
        }

        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr($type),
            esc_html($message)
        );
    }

    private function storeNotice(
        string $type,
        string $message
    ): void {
        $userId = get_current_user_id();
        if ($userId <= 0) {
            return;
        }

        set_transient(
            'fd_pdf_master_notice_' . $userId,
            [
                'type' => $type,
                'message' => $message,
            ],
            MINUTE_IN_SECONDS
        );
    }
}
