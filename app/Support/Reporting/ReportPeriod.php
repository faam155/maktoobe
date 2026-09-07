<?php

namespace App\Support\Reporting;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReportPeriod
{
    public function __construct(public Carbon $start, public Carbon $end, public array $filters) {}

    public static function from(User $user, array $input): self
    {
        $data = Validator::make($input, [
            'period' => ['nullable', 'in:today,7days,30days,month,custom'],
            'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before:2100-01-01'],
            'to' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before:2100-01-01'],
        ])->validate();
        $key = $data['period'] ?? '30days';
        $today = now($user->timezone ?: 'UTC')->startOfDay();
        $start = match ($key) {
            'today' => $today->copy(),'7days' => $today->copy()->subDays(6),'month' => $today->copy()->startOfMonth(),'custom' => Carbon::createFromFormat('!Y-m-d', $data['from'], $user->timezone ?: 'UTC'),default => $today->copy()->subDays(29)
        };
        $end = $key === 'custom' ? Carbon::createFromFormat('!Y-m-d', $data['to'], $user->timezone ?: 'UTC')->addDay() : $today->copy()->addDay();
        if ($end->lte($start) || $start->diffInDays($end) > 366) {
            throw ValidationException::withMessages(['to' => __('reporting.invalid_range')]);
        }

        return new self($start->copy()->utc(), $end->copy()->utc(), ['period' => $key, 'from' => $start->toDateString(), 'to' => $end->copy()->subDay()->toDateString()]);
    }

    public function apply($query, string $column = 'created_at')
    {
        return $query->where($column, '>=', $this->start)->where($column, '<', $this->end);
    }
}
