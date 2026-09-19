<?php

declare(strict_types=1);

namespace FacilDigital\Core\Courses;

use RuntimeException;
use TCPDF;

final class CertificateGenerationService
{
    public function __construct(
        private readonly CertificateRepository $certificates =
            new CertificateRepository(),
        private readonly EnrollmentRepository $enrollments =
            new EnrollmentRepository(),
        private readonly CoursePrivateStorage $storage =
            new CoursePrivateStorage()
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function generate(
        int $certificateId,
        bool $force = false
    ): array {
        $certificate =
            $this->certificates->findById(
                $certificateId
            );

        if (!is_array($certificate)) {
            throw new RuntimeException(
                'certificate_missing'
            );
        }

        $enrollment =
            $this->enrollments->findById(
                (int) (
                    $certificate[
                        'enrollment_id'
                    ]
                    ?? 0
                )
            );

        if (
            !is_array($enrollment)
            || (string) (
                $enrollment['status']
                ?? ''
            ) !== 'completed'
        ) {
            throw new RuntimeException(
                'certificate_enrollment_incomplete'
            );
        }

        if (
            !$force
            && (string) (
                $certificate['status']
                ?? ''
            ) === 'ready'
        ) {
            try {
                $this->storage
                    ->assertIntegrity(
                        (string) (
                            $certificate[
                                'storage_key'
                            ]
                            ?? ''
                        ),
                        isset(
                            $certificate[
                                'file_size'
                            ]
                        )
                            ? (int) $certificate[
                                'file_size'
                            ]
                            : null,
                        isset(
                            $certificate[
                                'sha256'
                            ]
                        )
                            ? (string) $certificate[
                                'sha256'
                            ]
                            : null
                    );

                return $certificate;
            } catch (RuntimeException) {
            }
        }

        $this->certificates
            ->recordGenerationAttempt(
                $certificateId
            );

        $verificationCode = (string) (
            $certificate[
                'verification_code'
            ]
            ?? ''
        );

        $storageKey = (string) (
            $certificate['storage_key']
            ?? ''
        );

        if ($storageKey === '') {
            $storageKey =
                $this->storage
                    ->certificateKey(
                        $certificateId,
                        $verificationCode
                    );
        }

        $destination =
            $this->storage->preparePath(
                $storageKey
            );

        $tempKey =
            $this->storage
                ->temporaryCertificateKey(
                    $certificateId
                );

        $tempPath =
            $this->storage->preparePath(
                $tempKey
            );

        try {
            $this->render(
                $certificate,
                $tempPath
            );

            $header = file_get_contents(
                $tempPath,
                false,
                null,
                0,
                5
            );

            if ($header !== '%PDF-') {
                throw new RuntimeException(
                    'certificate_pdf_invalid'
                );
            }

            if (
                !@rename(
                    $tempPath,
                    $destination
                )
            ) {
                if (
                    !copy(
                        $tempPath,
                        $destination
                    )
                ) {
                    throw new RuntimeException(
                        'certificate_store_failed'
                    );
                }

                @unlink($tempPath);
            }

            @chmod($destination, 0640);

            $size =
                filesize($destination);

            $sha256 = hash_file(
                'sha256',
                $destination
            );

            if (
                $size === false
                || $size <= 0
                || !is_string($sha256)
            ) {
                throw new RuntimeException(
                    'certificate_integrity_failed'
                );
            }

            $this->certificates->markReady(
                $certificateId,
                $storageKey,
                (int) $size,
                strtolower($sha256)
            );
        } catch (\Throwable $exception) {
            @unlink($tempPath);

            $errorCode = sanitize_key(
                $exception->getMessage()
            );

            if ($errorCode === '') {
                $errorCode =
                    'certificate_generation_failed';
            }

            try {
                $this->certificates
                    ->markFailed(
                        $certificateId,
                        $errorCode
                    );
            } catch (\Throwable) {
            }

            if (
                $exception
                instanceof RuntimeException
            ) {
                throw $exception;
            }

            throw new RuntimeException(
                'certificate_generation_failed'
            );
        }

        $ready =
            $this->certificates->findById(
                $certificateId
            );

        if (!is_array($ready)) {
            throw new RuntimeException(
                'certificate_missing'
            );
        }

        return $ready;
    }

    /**
     * @return array{certificate:array<string,mixed>,path:string}
     */
    public function authorizeDownload(
        int $userId,
        int $certificateId
    ): array {
        $certificate =
            $this->authorizeOwner(
                $userId,
                $certificateId,
                true
            );

        $path =
            $this->storage->assertIntegrity(
                (string) (
                    $certificate[
                        'storage_key'
                    ]
                    ?? ''
                ),
                isset(
                    $certificate['file_size']
                )
                    ? (int) $certificate[
                        'file_size'
                    ]
                    : null,
                isset(
                    $certificate['sha256']
                )
                    ? (string) $certificate[
                        'sha256'
                    ]
                    : null
            );

        return [
            'certificate' =>
                $certificate,
            'path' =>
                $path,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function authorizeOwner(
        int $userId,
        int $certificateId,
        bool $requireReady = false
    ): array {
        if (
            $userId <= 0
            || $certificateId <= 0
        ) {
            throw new RuntimeException(
                'certificate_access_denied'
            );
        }

        $certificate =
            $this->certificates->findById(
                $certificateId
            );

        if (!is_array($certificate)) {
            throw new RuntimeException(
                'certificate_access_denied'
            );
        }

        $enrollment =
            $this->enrollments->findById(
                (int) (
                    $certificate[
                        'enrollment_id'
                    ]
                    ?? 0
                )
            );

        if (
            !is_array($enrollment)
            || (int) (
                $enrollment['user_id']
                ?? 0
            ) !== $userId
            || (string) (
                $enrollment['status']
                ?? ''
            ) === 'revoked'
        ) {
            throw new RuntimeException(
                'certificate_access_denied'
            );
        }

        if (
            $requireReady
            && (string) (
                $certificate['status']
                ?? ''
            ) !== 'ready'
        ) {
            throw new RuntimeException(
                'certificate_not_ready'
            );
        }

        return $certificate;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function verification(
        string $verificationCode
    ): ?array {
        $verificationCode =
            sanitize_text_field(
                $verificationCode
            );

        if ($verificationCode === '') {
            return null;
        }

        $certificate =
            $this->certificates
                ->findByVerificationCode(
                    $verificationCode
                );

        if (
            !is_array($certificate)
            || (string) (
                $certificate['status']
                ?? ''
            ) !== 'ready'
        ) {
            return null;
        }

        $enrollment =
            $this->enrollments->findById(
                (int) (
                    $certificate[
                        'enrollment_id'
                    ]
                    ?? 0
                )
            );

        if (
            !is_array($enrollment)
            || (string) (
                $enrollment['status']
                ?? ''
            ) === 'revoked'
        ) {
            return null;
        }

        try {
            $this->storage
                ->assertIntegrity(
                    (string) (
                        $certificate[
                            'storage_key'
                        ]
                        ?? ''
                    ),
                    isset(
                        $certificate[
                            'file_size'
                        ]
                    )
                        ? (int) $certificate[
                            'file_size'
                        ]
                        : null,
                    isset(
                        $certificate[
                            'sha256'
                        ]
                    )
                        ? (string) $certificate[
                            'sha256'
                        ]
                        : null
                );
        } catch (RuntimeException) {
            return null;
        }

        return $certificate;
    }

    /**
     * @param array{certificate:array<string,mixed>,path:string} $authorization
     */
    public function stream(
        array $authorization
    ): never {
        $certificate =
            $authorization['certificate'];

        $path =
            $authorization['path'];

        $courseTitle = sanitize_title(
            (string) (
                $certificate[
                    'course_title_snapshot'
                ]
                ?? 'curso'
            )
        );

        if ($courseTitle === '') {
            $courseTitle = 'curso';
        }

        $filename =
            'certificado-'
            . $courseTitle
            . '.pdf';

        $size = filesize($path);

        if ($size === false) {
            throw new RuntimeException(
                'certificate_integrity_failed'
            );
        }

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        nocache_headers();

        header(
            'Content-Type: application/pdf'
        );

        header(
            'Content-Disposition: attachment; filename="'
            . $filename
            . '"; filename*=UTF-8\'\''
            . rawurlencode($filename)
        );

        header(
            'Content-Length: '
            . (string) $size
        );

        header(
            'X-Content-Type-Options: nosniff'
        );

        header(
            'X-Robots-Tag: noindex, nofollow'
        );

        readfile($path);
        exit;
    }

    /**
     * @param array<string, mixed> $certificate
     */
    private function render(
        array $certificate,
        string $destination
    ): void {
        $student = wp_strip_all_tags(
            (string) (
                $certificate[
                    'student_name_snapshot'
                ]
                ?? ''
            )
        );

        $course = wp_strip_all_tags(
            (string) (
                $certificate[
                    'course_title_snapshot'
                ]
                ?? ''
            )
        );

        $verificationCode =
            wp_strip_all_tags(
                (string) (
                    $certificate[
                        'verification_code'
                    ]
                    ?? ''
                )
            );

        if (
            $student === ''
            || $course === ''
            || $verificationCode === ''
        ) {
            throw new RuntimeException(
                'certificate_snapshot_invalid'
            );
        }

        $workload = max(
            0,
            (int) (
                $certificate[
                    'workload_minutes_snapshot'
                ]
                ?? 0
            )
        );

        $hours = intdiv(
            $workload,
            60
        );

        $minutes =
            $workload % 60;

        $workloadLabel =
            $hours > 0
                ? $hours . ' h'
                : $minutes . ' min';

        if (
            $hours > 0
            && $minutes > 0
        ) {
            $workloadLabel .=
                ' '
                . $minutes
                . ' min';
        }

        $verificationUrl = home_url(
            '/verificar-certificado/'
            . rawurlencode(
                $verificationCode
            )
            . '/'
        );

        $pdf = new TCPDF(
            'L',
            'mm',
            'A4',
            true,
            'UTF-8',
            false
        );

        $pdf->SetCreator(
            'Fácil Digital+'
        );

        $pdf->SetAuthor(
            'Fácil Digital+'
        );

        $pdf->SetTitle(
            'Certificado - '
            . $course
        );

        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);
        $pdf->SetMargins(18, 18, 18);
        $pdf->SetAutoPageBreak(
            false,
            0
        );

        $pdf->AddPage();

        $pdf->SetDrawColor(
            18,
            46,
            79
        );

        $pdf->SetLineWidth(1.8);
        $pdf->Rect(
            9,
            9,
            279,
            192
        );

        $pdf->SetDrawColor(
            194,
            151,
            62
        );

        $pdf->SetLineWidth(0.6);
        $pdf->Rect(
            13,
            13,
            271,
            184
        );

        $pdf->SetTextColor(
            18,
            46,
            79
        );

        $pdf->SetFont(
            'dejavusans',
            'B',
            21
        );

        $pdf->SetY(28);

        $pdf->Cell(
            0,
            12,
            'FÁCIL DIGITAL+',
            0,
            1,
            'C'
        );

        $pdf->SetFont(
            'dejavusans',
            'B',
            30
        );

        $pdf->SetY(51);

        $pdf->Cell(
            0,
            16,
            'CERTIFICADO',
            0,
            1,
            'C'
        );

        $pdf->SetTextColor(
            55,
            65,
            75
        );

        $pdf->SetFont(
            'dejavusans',
            '',
            13
        );

        $pdf->SetY(76);

        $pdf->Cell(
            0,
            8,
            'Certificamos que',
            0,
            1,
            'C'
        );

        $pdf->SetTextColor(
            18,
            46,
            79
        );

        $pdf->SetFont(
            'dejavusans',
            'B',
            22
        );

        $pdf->SetY(88);

        $pdf->MultiCell(
            0,
            12,
            $student,
            0,
            'C',
            false,
            1
        );

        $pdf->SetTextColor(
            55,
            65,
            75
        );

        $pdf->SetFont(
            'dejavusans',
            '',
            13
        );

        $pdf->SetY(111);

        $pdf->MultiCell(
            0,
            8,
            'concluiu com aproveitamento o curso',
            0,
            'C',
            false,
            1
        );

        $pdf->SetTextColor(
            18,
            46,
            79
        );

        $pdf->SetFont(
            'dejavusans',
            'B',
            18
        );

        $pdf->SetY(124);

        $pdf->MultiCell(
            0,
            10,
            $course,
            0,
            'C',
            false,
            1
        );

        $pdf->SetTextColor(
            55,
            65,
            75
        );

        $pdf->SetFont(
            'dejavusans',
            '',
            11
        );

        $pdf->SetY(149);

        $pdf->Cell(
            0,
            7,
            'Carga horária: '
            . $workloadLabel
            . '   •   Emissão: '
            . wp_date('d/m/Y'),
            0,
            1,
            'C'
        );

        $pdf->SetFont(
            'dejavusans',
            '',
            9
        );

        $pdf->SetY(169);

        $pdf->Cell(
            0,
            6,
            'Código de verificação: '
            . $verificationCode,
            0,
            1,
            'C'
        );

        $pdf->SetTextColor(
            80,
            90,
            100
        );

        $pdf->SetFont(
            'dejavusans',
            '',
            7.5
        );

        $pdf->SetY(179);

        $pdf->MultiCell(
            0,
            5,
            $verificationUrl,
            0,
            'C',
            false,
            1
        );

        $pdf->Output(
            $destination,
            'F'
        );
    }
}