<?php

namespace App\Exceptions;

use RuntimeException;

class CloudflareAiException extends RuntimeException
{
    public function __construct(
        public readonly string $safeMessage,
        public readonly int $httpStatus = 502,
        public readonly string $errorCode = 'cloudflare_error',
    ) {
        parent::__construct($safeMessage);
    }
}
