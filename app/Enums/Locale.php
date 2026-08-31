<?php

namespace App\Enums;

enum Locale: string
{
    case English = 'en';
    case Arabic = 'ar';

    public function direction(): string
    {
        return $this === self::Arabic ? 'rtl' : 'ltr';
    }

    public static function resolve(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::English) : self::English;
    }
}
