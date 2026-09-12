<?php

namespace App\Services\Social;

class SocialMath
{
    public static function percent(?float $value, ?float $base): ?float
    {
        return $value === null || $base === null || $base <= 0 ? null : round($value / $base * 100, 2);
    }

    public static function change(?float $current, ?float $previous): array
    {
        return ['absolute' => $current === null || $previous === null ? null : $current - $previous,
            'percent' => $current === null || $previous === null ? null : ($previous == 0 && $current == 0 ? 0.0 : self::percent($current - $previous, $previous))];
    }

    public static function engagements(array $row, array $components): ?int
    {
        foreach ($components as $key) {
            if (! isset($row[$key])) {
                return null;
            }
        }

        return (int) array_sum(array_intersect_key($row, array_flip($components)));
    }

    public static function score(array $actual, array $targets, array $weights, array $thresholds): array
    {
        $sum = 0;
        $weight = 0;
        foreach ($weights as $metric => $w) {
            if ($w <= 0) {
                continue;
            }
            $ratio = self::percent($actual[$metric] ?? null, $targets[$metric] ?? null);
            if ($ratio === null) {
                return ['value' => null, 'status' => 'unavailable'];
            }
            $sum += min(100, max(0, $ratio)) * $w;
            $weight += $w;
        }
        if (! $weight) {
            return ['value' => null, 'status' => 'unavailable'];
        }
        $value = round($sum / $weight, 1);
        arsort($thresholds);
        foreach ($thresholds as $status => $threshold) {
            if ($value >= $threshold) {
                return compact('value', 'status');
            }
        }

        return ['value' => $value, 'status' => 'critical'];
    }
}
