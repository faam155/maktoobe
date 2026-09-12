<?php

namespace App\Services\Social;

use App\Models\SocialMediaAccount;
use Carbon\CarbonImmutable as Date;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SocialFilters
{
    public const PERIODS = ['this_month', 'last_month', 'current_quarter', 'previous_quarter', 'current_year', 'previous_year', 'last_30', 'last_90', 'custom'];

    public const COMPARISONS = ['previous_period', 'previous_month', 'previous_quarter', 'previous_year', 'same_period_last_year'];

    public static function make(SocialMediaAccount $account, array $input): array
    {
        $v = Validator::make($input, [
            'period' => ['nullable', 'in:'.implode(',', self::PERIODS)], 'compare' => ['nullable', 'in:'.implode(',', self::COMPARISONS)],
            'from' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_if:period,custom', 'date_format:Y-m-d', 'after_or_equal:from'],
            'content_type_id' => ['nullable', 'integer', 'min:1'], 'campaign_id' => ['nullable', 'integer', 'min:1'],
            'event_id' => ['nullable', 'integer', 'min:1'], 'language' => ['nullable', 'in:ar,en,bilingual,other'],
            'granularity' => ['nullable', 'in:day,week,month'], 'top' => ['nullable', 'integer', 'in:5,10,20'],
        ])->validate();
        $now = Date::now($account->timezone)->startOfDay();
        $period = $v['period'] ?? 'this_month';
        [$start,$end] = match ($period) {
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'current_quarter' => [$now->startOfQuarter(), $now->endOfQuarter()],
            'previous_quarter' => [$now->subQuarter()->startOfQuarter(), $now->subQuarter()->endOfQuarter()],
            'current_year' => [$now->startOfYear(), $now->endOfYear()],
            'previous_year' => [$now->subYear()->startOfYear(), $now->subYear()->endOfYear()],
            'last_30' => [$now->subDays(29), $now], 'last_90' => [$now->subDays(89), $now],
            'custom' => [Date::parse($v['from'], $account->timezone), Date::parse($v['to'], $account->timezone)],
            default => [$now->startOfMonth(), $now->endOfMonth()],
        };
        if ($start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['to' => __('social.range_limit')]);
        }
        $compare = $v['compare'] ?? 'previous_period';
        [$cs,$ce] = match ($compare) {
            'previous_month' => [$start->subMonthNoOverflow()->startOfMonth(), $start->subMonthNoOverflow()->endOfMonth()],
            'previous_quarter' => [$start->subQuarter()->startOfQuarter(), $start->subQuarter()->endOfQuarter()],
            'previous_year' => [$start->subYear()->startOfYear(), $start->subYear()->endOfYear()],
            'same_period_last_year' => [$start->subYearNoOverflow(), $end->subYearNoOverflow()],
            default => [$start->subDays((int) $start->startOfDay()->diffInDays($end->startOfDay()) + 1), $start->subDay()],
        };

        return array_merge($v, ['period' => $period, 'compare' => $compare, 'from' => $start->toDateString(), 'to' => $end->toDateString(),
            'comparison_from' => $cs->toDateString(), 'comparison_to' => $ce->toDateString(),
            'granularity' => $v['granularity'] ?? ($start->diffInDays($end) > 90 ? 'month' : 'day'), 'top' => $v['top'] ?? 5]);
    }

    public static function contentFiltered(array $f): bool
    {
        return filled($f['content_type_id'] ?? null) || filled($f['campaign_id'] ?? null) || filled($f['event_id'] ?? null) || filled($f['language'] ?? null);
    }
}
