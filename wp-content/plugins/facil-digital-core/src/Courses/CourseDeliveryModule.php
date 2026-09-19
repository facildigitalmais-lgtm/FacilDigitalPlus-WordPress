<?php

declare(strict_types=1);

namespace FacilDigital\Core\Courses;

use FacilDigital\Core\Contracts\ModuleInterface;

final class CourseDeliveryModule implements ModuleInterface
{
    private const VERIFY_QUERY_VAR =
        'fd_verify_certificate';

    private const REWRITE_OPTION =
        'facil_digital_certificate_rewrite_version';

    private const REWRITE_VERSION = '1.0.0';

    public function __construct(
        private readonly CourseResourceService $resources =
            new CourseResourceService(),
        private readonly CertificateGenerationService $certificates =
            new CertificateGenerationService()
    ) {
    }

    public function register(): void
    {
        add_action(
            'init',
            [$this, 'registerRewrite'],
            20
        );

        add_filter(
            'query_vars',
            [$this, 'queryVars']
        );

        add_action(
            'wp_loaded',
            [$this, 'maybeFlushRewriteRules'],
            70
        );

        add_action(
            'template_redirect',
            [$this, 'maybeHandleRequest'],
            1
        );

        add_action(
            'facil_digital_certificate_pending',
            [$this, 'queueCertificate'],
            10,
            1
        );

        add_action(
            'facil_digital_generate_certificate',
            [$this, 'generatePendingCertificate'],
            10,
            1
        );
    }

    public function registerRewrite(): void
    {
        add_rewrite_rule(
            '^verificar-certificado/([A-Za-z0-9_-]+)/?$',
            'index.php?'
            . self::VERIFY_QUERY_VAR
            . '=$matches[1]',
            'top'
        );
    }

    /**
     * @param list<string> $vars
     * @return list<string>
     */
    public function queryVars(array $vars): array
    {
        $vars[] =
            self::VERIFY_QUERY_VAR;

        return array_values(
            array_unique($vars)
        );
    }

    public function maybeFlushRewriteRules(): void
    {
        if (
            get_option(
                self::REWRITE_OPTION,
                ''
            ) === self::REWRITE_VERSION
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

    public function maybeHandleRequest(): void
    {
        if (
            isset(
                $_GET['fd_course_resource']
            )
        ) {
            $this->downloadResource();
        }

        if (
            isset(
                $_GET[
                    'fd_course_certificate'
                ]
            )
        ) {
            $this->downloadCertificate();
        }

        if (
            isset(
                $_GET[
                    'fd_certificate_retry'
                ]
            )
        ) {
            $this->retryCertificate();
        }

        $verificationCode =
            get_query_var(
                self::VERIFY_QUERY_VAR
            );

        if (
            is_string($verificationCode)
            && $verificationCode !== ''
        ) {
            $this->renderVerification(
                $verificationCode
            );
        }
    }

    public function queueCertificate(
        int $certificateId
    ): void {
        if ($certificateId <= 0) {
            return;
        }

        if (
            function_exists(
                'as_enqueue_async_action'
            )
        ) {
            if (
                function_exists(
                    'as_has_scheduled_action'
                )
                && as_has_scheduled_action(
                    'facil_digital_generate_certificate',
                    [$certificateId],
                    'facil-digital-courses'
                )
            ) {
                return;
            }

            as_enqueue_async_action(
                'facil_digital_generate_certificate',
                [$certificateId],
                'facil-digital-courses',
                true
            );

            return;
        }

        $this->generatePendingCertificate(
            $certificateId
        );
    }

    public function generatePendingCertificate(
        int $certificateId
    ): void {
        if ($certificateId <= 0) {
            return;
        }

        try {
            $this->certificates->generate(
                $certificateId
            );
        } catch (\Throwable $exception) {
            error_log(
                sprintf(
                    'FD_COURSE_CERTIFICATE_GENERATION_FAILED certificate_id=%d code=%s',
                    $certificateId,
                    sanitize_key(
                        $exception->getMessage()
                    )
                )
            );
        }
    }

    public static function resourceUrl(
        int $resourceId
    ): string {
        if ($resourceId <= 0) {
            return '';
        }

        $base =
            wc_get_account_endpoint_url(
                CourseAccountModule::COURSES_ENDPOINT
            );

        $url = add_query_arg(
            'fd_course_resource',
            $resourceId,
            $base
        );

        return wp_nonce_url(
            $url,
            'fd_course_resource_'
            . $resourceId
        );
    }

    public static function certificateUrl(
        int $certificateId
    ): string {
        if ($certificateId <= 0) {
            return '';
        }

        $base =
            wc_get_account_endpoint_url(
                CourseAccountModule::CERTIFICATES_ENDPOINT
            );

        $url = add_query_arg(
            'fd_course_certificate',
            $certificateId,
            $base
        );

        return wp_nonce_url(
            $url,
            'fd_course_certificate_'
            . $certificateId
        );
    }

    public static function retryUrl(
        int $certificateId
    ): string {
        if ($certificateId <= 0) {
            return '';
        }

        $base =
            wc_get_account_endpoint_url(
                CourseAccountModule::CERTIFICATES_ENDPOINT
            );

        $url = add_query_arg(
            'fd_certificate_retry',
            $certificateId,
            $base
        );

        return wp_nonce_url(
            $url,
            'fd_certificate_retry_'
            . $certificateId
        );
    }

    public static function verificationUrl(
        string $verificationCode
    ): string {
        $verificationCode = trim(
            $verificationCode
        );

        if ($verificationCode === '') {
            return '';
        }

        return home_url(
            '/verificar-certificado/'
            . rawurlencode(
                $verificationCode
            )
            . '/'
        );
    }

    private function downloadResource(): never
    {
        if (!is_user_logged_in()) {
            auth_redirect();
            exit;
        }

        $resourceId = absint(
            $_GET['fd_course_resource']
            ?? 0
        );

        $nonce =
            $this->requestNonce();

        if (
            $resourceId <= 0
            || !wp_verify_nonce(
                $nonce,
                'fd_course_resource_'
                . $resourceId
            )
        ) {
            $this->blocked(
                'Link de recurso inválido ou expirado.'
            );
        }

        try {
            $authorization =
                $this->resources->authorize(
                    get_current_user_id(),
                    $resourceId
                );
        } catch (\Throwable) {
            $this->blocked(
                'Você não possui autorização para baixar este recurso.'
            );
        }

        $this->resources->stream(
            $authorization
        );
    }

    private function downloadCertificate(): never
    {
        if (!is_user_logged_in()) {
            auth_redirect();
            exit;
        }

        $certificateId = absint(
            $_GET[
                'fd_course_certificate'
            ]
            ?? 0
        );

        $nonce =
            $this->requestNonce();

        if (
            $certificateId <= 0
            || !wp_verify_nonce(
                $nonce,
                'fd_course_certificate_'
                . $certificateId
            )
        ) {
            $this->blocked(
                'Link de certificado inválido ou expirado.'
            );
        }

        try {
            $authorization =
                $this->certificates
                    ->authorizeDownload(
                        get_current_user_id(),
                        $certificateId
                    );
        } catch (\Throwable) {
            $this->blocked(
                'Você não possui autorização para baixar este certificado.'
            );
        }

        $this->certificates->stream(
            $authorization
        );
    }

    private function retryCertificate(): never
    {
        if (!is_user_logged_in()) {
            auth_redirect();
            exit;
        }

        $certificateId = absint(
            $_GET[
                'fd_certificate_retry'
            ]
            ?? 0
        );

        $nonce =
            $this->requestNonce();

        if (
            $certificateId <= 0
            || !wp_verify_nonce(
                $nonce,
                'fd_certificate_retry_'
                . $certificateId
            )
        ) {
            $this->blocked(
                'Ação de certificado inválida ou expirada.'
            );
        }

        try {
            $this->certificates
                ->authorizeOwner(
                    get_current_user_id(),
                    $certificateId,
                    false
                );

            $this->certificates->generate(
                $certificateId,
                true
            );

            $status = 'ready';
        } catch (\Throwable) {
            $status = 'failed';
        }

        wp_safe_redirect(
            add_query_arg(
                'fd_certificate_status',
                $status,
                wc_get_account_endpoint_url(
                    CourseAccountModule::CERTIFICATES_ENDPOINT
                )
            )
        );

        exit;
    }

    private function renderVerification(
        string $verificationCode
    ): never {
        $certificate =
            $this->certificates
                ->verification(
                    $verificationCode
                );

        $valid =
            is_array($certificate);

        status_header(
            $valid ? 200 : 404
        );

        nocache_headers();

        header(
            'Content-Type: text/html; charset='
            . get_bloginfo('charset')
        );

        header(
            'X-Robots-Tag: noindex, nofollow'
        );

        $siteName =
            get_bloginfo('name');

        echo '<!doctype html><html lang="pt-BR"><head>';
        echo '<meta charset="';
        echo esc_attr(
            get_bloginfo('charset')
        );
        echo '">';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>';
        echo esc_html(
            $valid
                ? 'Certificado válido'
                : 'Certificado não encontrado'
        );
        echo ' — ';
        echo esc_html($siteName);
        echo '</title>';
        echo '<style>body{margin:0;background:#f3f5f7;color:#142e4f;font-family:Arial,sans-serif}.fdv{max-width:760px;margin:60px auto;padding:20px}.fdv-card{background:#fff;border:1px solid #dfe4e9;border-radius:16px;padding:34px;box-shadow:0 8px 30px rgba(0,0,0,.06)}.fdv-ok{color:#176b3a}.fdv-bad{color:#9b1c1c}.fdv h1{margin:10px 0 24px}.fdv dl{display:grid;grid-template-columns:180px 1fr;gap:12px 18px}.fdv dt{font-weight:700}.fdv dd{margin:0}.fdv a{color:#2271b1}@media(max-width:600px){.fdv{margin:20px auto}.fdv-card{padding:22px}.fdv dl{grid-template-columns:1fr;gap:4px}.fdv dd{margin-bottom:12px}}</style>';
        echo '</head><body><main class="fdv"><section class="fdv-card">';
        echo '<strong>Fácil Digital+</strong>';

        if (!$valid) {
            echo '<h1 class="fdv-bad">Certificado não encontrado</h1>';
            echo '<p>O código informado não corresponde a um certificado válido e ativo.</p>';
        } else {
            $date = (string) (
                $certificate[
                    'generated_at'
                ]
                ?? $certificate[
                    'updated_at'
                ]
                ?? ''
            );

            $timestamp =
                $date !== ''
                    ? strtotime(
                        $date . ' UTC'
                    )
                    : false;

            $workload = max(
                0,
                (int) (
                    $certificate[
                        'workload_minutes_snapshot'
                    ]
                    ?? 0
                )
            );

            echo '<h1 class="fdv-ok">✓ Certificado válido</h1>';
            echo '<dl>';

            echo '<dt>Aluno</dt><dd>';
            echo esc_html(
                (string) (
                    $certificate[
                        'student_name_snapshot'
                    ]
                    ?? ''
                )
            );
            echo '</dd>';

            echo '<dt>Curso</dt><dd>';
            echo esc_html(
                (string) (
                    $certificate[
                        'course_title_snapshot'
                    ]
                    ?? ''
                )
            );
            echo '</dd>';

            echo '<dt>Carga horária</dt><dd>';
            echo esc_html(
                number_format(
                    $workload / 60,
                    1,
                    ',',
                    '.'
                )
                . ' h'
            );
            echo '</dd>';

            echo '<dt>Emissão</dt><dd>';
            echo esc_html(
                $timestamp !== false
                    ? wp_date(
                        'd/m/Y',
                        $timestamp
                    )
                    : '—'
            );
            echo '</dd>';

            echo '<dt>Código</dt><dd><strong>';
            echo esc_html(
                (string) (
                    $certificate[
                        'verification_code'
                    ]
                    ?? ''
                )
            );
            echo '</strong></dd>';

            echo '</dl>';
        }

        echo '<p><a href="';
        echo esc_url(
            home_url('/')
        );
        echo '">Voltar para ';
        echo esc_html($siteName);
        echo '</a></p>';

        echo '</section></main></body></html>';
        exit;
    }

    private function requestNonce(): string
    {
        return isset($_GET['_wpnonce'])
            && is_string(
                $_GET['_wpnonce']
            )
                ? sanitize_text_field(
                    wp_unslash(
                        $_GET['_wpnonce']
                    )
                )
                : '';
    }

    private function blocked(
        string $message
    ): never {
        wp_die(
            esc_html($message),
            esc_html__(
                'Acesso bloqueado',
                'facil-digital-core'
            ),
            ['response' => 403]
        );
    }
}