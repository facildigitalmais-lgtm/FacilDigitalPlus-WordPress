<?php

declare(strict_types=1);

namespace FacilDigital\Core\PDFs;

use RuntimeException;

final class PdfGenerationException extends RuntimeException
{
    public function __construct(
        private readonly string $errorCode
    ) {
        parent::__construct($errorCode);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
