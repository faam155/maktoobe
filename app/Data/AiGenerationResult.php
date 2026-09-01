<?php

namespace App\Data;

readonly class AiGenerationResult
{
    public function __construct(public string $content, public ?string $providerRequestId = null, public ?int $inputTokens = null, public ?int $outputTokens = null, public ?int $totalTokens = null) {}
}
