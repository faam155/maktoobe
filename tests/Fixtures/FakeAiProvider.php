<?php

namespace Tests\Fixtures;

use App\Contracts\AiProvider;
use App\Data\AiGenerationResult;
use App\Exceptions\AiProviderException;

class FakeAiProvider implements AiProvider
{
    public static ?string $failure = null;

    public static array $calls = [];

    public function generate(array $messages, string $model, array $settings, string $safetyIdentifier): AiGenerationResult
    {
        self::$calls[] = compact('messages', 'model', 'settings', 'safetyIdentifier');
        if (self::$failure) {
            throw new AiProviderException(self::$failure);
        }

        return new AiGenerationResult('Mocked assistant response', 'resp_test', 12, 8, 20);
    }

    public static function reset(): void
    {
        self::$failure = null;
        self::$calls = [];
    }
}
