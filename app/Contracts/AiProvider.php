<?php

namespace App\Contracts;

use App\Data\AiGenerationResult;

interface AiProvider
{
    /** @param array<int, array{role:string, content:string}> $messages */
    public function generate(array $messages, string $model, array $settings, string $safetyIdentifier): AiGenerationResult;
}
