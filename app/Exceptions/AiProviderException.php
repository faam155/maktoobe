<?php

namespace App\Exceptions;

use RuntimeException;

class AiProviderException extends RuntimeException
{
    public function __construct(public readonly string $safeCode, string $message = 'AI provider request failed.')
    {
        parent::__construct($message);
    }
}
