<?php

declare(strict_types=1);

namespace FacilDigital\Core\PDFs;

use FacilDigital\Core\Contracts\ModuleInterface;

final class DownloadModule implements ModuleInterface
{
    public function __construct(
        private readonly DownloadService $service = new DownloadService()
    ) {
    }

    public function register(): void
    {
        add_action(
            'template_redirect',
            [$this, 'maybeDownload'],
            1
        );
    }

    public function maybeDownload(): void
    {
        if (!isset($_GET['fd_download'])) {
            return;
        }

        if (!is_user_logged_in()) {
            auth_redirect();
            exit;
        }

        $pdfFileId = absint($_GET['fd_download']);
        $nonce = isset($_GET['_wpnonce'])
            && is_string($_GET['_wpnonce'])
                ? sanitize_text_field(
                    wp_unslash($_GET['_wpnonce'])
                )
                : '';

        if (
            $pdfFileId <= 0
            || !wp_verify_nonce(
                $nonce,
                'fd_download_pdf_' . $pdfFileId
            )
        ) {
            wp_die(
                esc_html__('Link de download inválido ou expirado.', 'facil-digital-core'),
                esc_html__('Download bloqueado', 'facil-digital-core'),
                ['response' => 403]
            );
        }

        try {
            $authorization = $this->service->authorize(
                get_current_user_id(),
                $pdfFileId
            );
        } catch (\Throwable $exception) {
            $code = $exception->getMessage();
            $response = $code === 'download_limit_reached'
                ? 429
                : 403;

            wp_die(
                esc_html(
                    $code === 'download_limit_reached'
                        ? __('Seu limite de downloads desta compra foi atingido.', 'facil-digital-core')
                        : __('Você não possui autorização para baixar este arquivo.', 'facil-digital-core')
                ),
                esc_html__('Download bloqueado', 'facil-digital-core'),
                ['response' => $response]
            );
        }

        $this->service->stream($authorization);
    }

    public static function url(int $pdfFileId): string
    {
        if ($pdfFileId <= 0) {
            return '';
        }

        $base = wc_get_account_endpoint_url('apostilas');
        $url = add_query_arg(
            'fd_download',
            $pdfFileId,
            $base
        );

        return wp_nonce_url(
            $url,
            'fd_download_pdf_' . $pdfFileId
        );
    }
}
