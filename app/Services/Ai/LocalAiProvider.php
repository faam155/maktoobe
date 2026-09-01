<?php

namespace App\Services\Ai;

use App\Contracts\AiProvider;
use App\Data\AiGenerationResult;
use App\Exceptions\AiProviderException;

class LocalAiProvider implements AiProvider
{
    public function generate(array $messages, string $model, array $settings, string $safetyIdentifier): AiGenerationResult
    {
        if (! app()->environment(['local', 'browser', 'testing'])) {
            throw new AiProviderException('not_configured');
        }

        return new AiGenerationResult(__('ai.local_response'));
    }
}
