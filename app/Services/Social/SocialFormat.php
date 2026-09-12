<?php

namespace App\Services\Social;

class SocialFormat
{
    public static function number(int|float|string|null $value, int $decimals = 0): string
    {
        if ($value === null) {
            return '—';
        }
        $formatter = new \NumberFormatter(app()->getLocale(), \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

        return (string) $formatter->format((float) $value);
    }

    public static function compact(int|float $value): string
    {
        foreach ([1000000000 => 'billion', 1000000 => 'million', 1000 => 'thousand'] as $divisor => $label) {
            if (abs($value) >= $divisor * 10 || ($divisor >= 1000000 && abs($value) >= $divisor)) {
                return self::number($value / $divisor, 1).__('social.'.$label);
            }
        }

        return self::number($value);
    }
}
