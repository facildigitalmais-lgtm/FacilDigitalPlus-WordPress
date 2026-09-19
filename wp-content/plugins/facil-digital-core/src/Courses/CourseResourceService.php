<?php

declare(strict_types=1);

namespace FacilDigital\Core\Courses;

use RuntimeException;

final class CourseResourceService
{
    private const MAX_FILE_SIZE = 26214400;

    /**
     * @var array<string, string>
     */
    private const ALLOWED_MIMES = [
        'pdf' => 'application/pdf',
        'zip' => 'application/zip',
        'txt' => 'text/plain',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png' => 'image/png',
    ];

    public function __construct(
        private readonly LessonResourceRepository $resources =
            new LessonResourceRepository(),
        private readonly LessonRepository $lessons =
            new LessonRepository(),
        private readonly EnrollmentRepository $enrollments =
            new EnrollmentRepository(),
        private readonly CoursePrivateStorage $storage =
            new CoursePrivateStorage()
    ) {
    }

    /**
     * @param array<string, mixed> $file
     */
    public function upload(
        int $courseId,
        int $lessonId,
        string $title,
        array $file,
        int $sortOrder = 0,
        string $status = 'active'
    ): int {
        $lesson = $this->lessons->findById(
            $lessonId
        );

        if (
            !is_array($lesson)
            || (int) (
                $lesson['course_id']
                ?? 0
            ) !== $courseId
        ) {
            throw new RuntimeException(
                'lesson_resource_lesson_invalid'
            );
        }

        $title = sanitize_text_field($title);

        if ($title === '') {
            throw new RuntimeException(
                'lesson_resource_title_invalid'
            );
        }

        $error = (int) (
            $file['error']
            ?? UPLOAD_ERR_NO_FILE
        );

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                'lesson_resource_upload_failed'
            );
        }

        $tmpName = isset($file['tmp_name'])
            && is_string($file['tmp_name'])
                ? $file['tmp_name']
                : '';

        $originalFilename = isset($file['name'])
            && is_string($file['name'])
                ? sanitize_file_name(
                    $file['name']
                )
                : '';

        if (
            $tmpName === ''
            || !is_file($tmpName)
            || !is_readable($tmpName)
            || $originalFilename === ''
        ) {
            throw new RuntimeException(
                'lesson_resource_upload_invalid'
            );
        }

        $size = filesize($tmpName);

        if (
            $size === false
            || $size <= 0
            || $size > self::MAX_FILE_SIZE
        ) {
            throw new RuntimeException(
                'lesson_resource_file_size_invalid'
            );
        }

        if (!function_exists(
            'wp_check_filetype_and_ext'
        )) {
            require_once ABSPATH
                . 'wp-admin/includes/file.php';
        }

        $checked = wp_check_filetype_and_ext(
            $tmpName,
            $originalFilename,
            self::ALLOWED_MIMES
        );

        $extension = isset($checked['ext'])
            && is_string($checked['ext'])
                ? strtolower($checked['ext'])
                : '';

        $mimeType = isset($checked['type'])
            && is_string($checked['type'])
                ? sanitize_mime_type(
                    $checked['type']
                )
                : '';

        if (
            $extension === ''
            || $mimeType === ''
        ) {
            throw new RuntimeException(
                'lesson_resource_file_type_invalid'
            );
        }

        $storageKey =
            $this->storage->resourceKey(
                $courseId,
                $lessonId,
                $extension
            );

        $destination =
            $this->storage->preparePath(
                $storageKey
            );

        $stored = false;

        if (is_uploaded_file($tmpName)) {
            $stored = move_uploaded_file(
                $tmpName,
                $destination
            );
        } elseif (
            wp_get_environment_type()
            === 'development'
        ) {
            $stored = copy(
                $tmpName,
                $destination
            );
        }

        if (!$stored) {
            throw new RuntimeException(
                'lesson_resource_store_failed'
            );
        }

        @chmod($destination, 0640);

        try {
            $storedSize =
                filesize($destination);

            $sha256 = hash_file(
                'sha256',
                $destination
            );

            if (
                $storedSize === false
                || $storedSize <= 0
                || !is_string($sha256)
            ) {
                throw new RuntimeException(
                    'lesson_resource_integrity_failed'
                );
            }

            return $this->resources->create(
                $courseId,
                $lessonId,
                $title,
                $storageKey,
                $originalFilename,
                $mimeType,
                [
                    'file_size' =>
                        (int) $storedSize,
                    'sha256' =>
                        strtolower($sha256),
                    'sort_order' =>
                        max(0, $sortOrder),
                    'status' =>
                        $status,
                ]
            );
        } catch (\Throwable $exception) {
            @unlink($destination);
            throw $exception;
        }
    }

    public function delete(
        int $courseId,
        int $resourceId
    ): void {
        $resource =
            $this->resources->findById(
                $resourceId
            );

        if (
            !is_array($resource)
            || (int) (
                $resource['course_id']
                ?? 0
            ) !== $courseId
        ) {
            throw new RuntimeException(
                'lesson_resource_missing'
            );
        }

        $storageKey = (string) (
            $resource['storage_key']
            ?? ''
        );

        $this->resources->delete(
            $resourceId
        );

        if ($storageKey !== '') {
            try {
                $this->storage->delete(
                    $storageKey
                );
            } catch (RuntimeException $exception) {
                error_log(
                    sprintf(
                        'FD_COURSE_RESOURCE_FILE_DELETE_FAILED resource_id=%d code=%s',
                        $resourceId,
                        sanitize_key(
                            $exception->getMessage()
                        )
                    )
                );
            }
        }
    }

    /**
     * @return array{resource:array<string,mixed>,path:string}
     */
    public function authorize(
        int $userId,
        int $resourceId
    ): array {
        if (
            $userId <= 0
            || $resourceId <= 0
        ) {
            throw new RuntimeException(
                'course_resource_access_denied'
            );
        }

        $resource =
            $this->resources->findById(
                $resourceId
            );

        if (
            !is_array($resource)
            || (string) (
                $resource['status']
                ?? ''
            ) !== 'active'
        ) {
            throw new RuntimeException(
                'course_resource_access_denied'
            );
        }

        $lesson = $this->lessons->findById(
            (int) (
                $resource['lesson_id']
                ?? 0
            )
        );

        if (
            !is_array($lesson)
            || (int) (
                $lesson['course_id']
                ?? 0
            ) !== (int) (
                $resource['course_id']
                ?? 0
            )
            || (string) (
                $lesson['status']
                ?? ''
            ) !== 'published'
        ) {
            throw new RuntimeException(
                'course_resource_access_denied'
            );
        }

        $courseId = (int) (
            $resource['course_id']
            ?? 0
        );

        if (!$this->userHasCourseAccess(
            $userId,
            $courseId
        )) {
            throw new RuntimeException(
                'course_resource_access_denied'
            );
        }

        $path =
            $this->storage->assertIntegrity(
                (string) (
                    $resource['storage_key']
                    ?? ''
                ),
                isset($resource['file_size'])
                    ? (int) $resource[
                        'file_size'
                    ]
                    : null,
                isset($resource['sha256'])
                    ? (string) $resource[
                        'sha256'
                    ]
                    : null
            );

        return [
            'resource' => $resource,
            'path' => $path,
        ];
    }

    /**
     * @param array{resource:array<string,mixed>,path:string} $authorization
     */
    public function stream(
        array $authorization
    ): never {
        $resource =
            $authorization['resource'];

        $path =
            $authorization['path'];

        $filename = sanitize_file_name(
            (string) (
                $resource[
                    'original_filename'
                ]
                ?? 'recurso'
            )
        );

        $filename = str_replace(
            ["\r", "\n", '"'],
            '',
            $filename
        );

        if ($filename === '') {
            $filename = 'recurso';
        }

        $size = filesize($path);

        if ($size === false) {
            throw new RuntimeException(
                'course_resource_integrity_failed'
            );
        }

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        nocache_headers();

        header(
            'Content-Type: '
            . sanitize_mime_type(
                (string) (
                    $resource['mime_type']
                    ?? 'application/octet-stream'
                )
            )
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

    private function userHasCourseAccess(
        int $userId,
        int $courseId
    ): bool {
        foreach (
            $this->enrollments->forUser(
                $userId
            )
            as $enrollment
        ) {
            if (
                (int) (
                    $enrollment['course_id']
                    ?? 0
                ) !== $courseId
                || !in_array(
                    (string) (
                        $enrollment['status']
                        ?? ''
                    ),
                    [
                        'active',
                        'completed',
                    ],
                    true
                )
            ) {
                continue;
            }

            $expiresAt = (string) (
                $enrollment['expires_at']
                ?? ''
            );

            if (
                $expiresAt !== ''
                && strtotime(
                    $expiresAt . ' UTC'
                ) < time()
            ) {
                continue;
            }

            return true;
        }

        return false;
    }
}