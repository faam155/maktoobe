<?php

namespace App\Support\Identity;

use Illuminate\Support\Str;

class Identifiers
{
    public static function canonical(mixed $value): string
    {
        return is_string($value) ? Str::lower(trim($value)) : '';
    }

    public static function phone(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        $value = strtr(trim($value), array_combine(
            preg_split('//u', '٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY),
            str_split('01234567890123456789')
        ));
        $value = preg_replace('/[\s().-]+/u', '', $value);
        if (str_starts_with($value, '00')) {
            $value = '+'.substr($value, 2);
        }

        return $value === '' ? null : $value;
    }

    public static function digest(string $value): string
    {
        return hash_hmac('sha256', $value, config('app.key'));
    }
}
