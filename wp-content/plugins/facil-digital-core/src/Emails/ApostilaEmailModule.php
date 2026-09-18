<?php

declare(strict_types=1);

namespace FacilDigital\Core\Emails;

use FacilDigital\Core\Contracts\ModuleInterface;
use FacilDigital\Core\PDFs\PdfFileRepository;
use WC_Order;

final class ApostilaEmailModule implements ModuleInterface
{
    private const SENT_META = '_fd_apostila_ready_email_sent';
    private const SEND_HOOK = 'facil_digital_send_apostila_ready_email';

    public function __construct(
        private readonly PdfFileRepository $files = new PdfFileRepository()
    ) {
    }

    public function register(): void
    {
        add_filter(
            'woocommerce_email_classes',
            [$this, 'registerEmail']
        );

        add_action(
            'facil_digital_pdf_ready',
            [$this, 'queueReadyEmail'],
            10,
            1
        );

        add_action(
            self::SEND_HOOK,
            [$this, 'sendReadyEmail'],
            10,
            1
        );
    }

    /**
     * @param array<string, \WC_Email> $emails
     * @return array<string, \WC_Email>
     */
    public function registerEmail(array $emails): array
    {
        if (!class_exists(\WC_Email::class)) {
            return $emails;
        }

        $emails[ApostilaReadyEmail::class] =
            new ApostilaReadyEmail();

        return $emails;
    }

    public function queueReadyEmail(int $pdfId): void
    {
        if ($pdfId <= 0) {
            return;
        }

        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action(
                self::SEND_HOOK,
                [$pdfId],
                'facil-digital',
                true
            );
            return;
        }

        wp_schedule_single_event(
            time() + 5,
            self::SEND_HOOK,
            [$pdfId]
        );
    }

    public function sendReadyEmail(int $pdfId): void
    {
        if ($pdfId <= 0) {
            return;
        }

        $pdf = $this->files->findById($pdfId);

        if (
            !is_array($pdf)
            || ($pdf['status'] ?? '') !== 'ready'
        ) {
            return;
        }

        $orderId = (int) ($pdf['order_id'] ?? 0);
        $productId = (int) ($pdf['product_id'] ?? 0);

        if ($orderId <= 0 || $productId <= 0) {
            return;
        }

        $order = wc_get_order($orderId);

        if (!$order instanceof WC_Order) {
            return;
        }

        $sent = $order->get_meta(
            self::SENT_META,
            true
        );

        if (!is_array($sent)) {
            $sent = [];
        }

        $sentKey = (string) $pdfId;

        if (isset($sent[$sentKey])) {
            return;
        }

        if (!function_exists('WC') || WC() === null) {
            return;
        }

        $emails = WC()->mailer()->get_emails();
        $email = $emails[ApostilaReadyEmail::class] ?? null;

        if (!$email instanceof ApostilaReadyEmail) {
            return;
        }

        $passwordEnabled =
            (int) ($pdf['password_enabled'] ?? 0) === 1;

        $sentSuccessfully = $email->trigger(
            $orderId,
            $productId,
            $passwordEnabled
        );

        if (!$sentSuccessfully) {
            error_log(
                sprintf(
                    'FD_APOSTILA_READY_EMAIL_NOT_SENT pdf_id=%d order_id=%d product_id=%d',
                    $pdfId,
                    $orderId,
                    $productId
                )
            );
            return;
        }

        $sent[$sentKey] = current_time(
            'mysql',
            true
        );

        $order->update_meta_data(
            self::SENT_META,
            $sent
        );
        $order->save_meta_data();
    }
}
