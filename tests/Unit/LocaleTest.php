<?php

namespace Tests\Unit;

use App\Enums\Locale;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LocaleTest extends TestCase
{
    #[DataProvider('localeCases')]
    public function test_only_supported_locales_can_set_document_direction(mixed $input, string $locale, string $direction): void
    {
        $resolved = Locale::resolve($input);

        $this->assertSame($locale, $resolved->value);
        $this->assertSame($direction, $resolved->direction());
    }

    public static function localeCases(): array
    {
        return [
            ['en', 'en', 'ltr'], ['ar', 'ar', 'rtl'], ['fr', 'en', 'ltr'],
            [null, 'en', 'ltr'], [[], 'en', 'ltr'], ['<script>', 'en', 'ltr'],
        ];
    }
}
