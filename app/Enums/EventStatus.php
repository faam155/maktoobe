<?php

namespace App\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case Planned = 'planned';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return true;
        }

        return in_array($target, match ($this) {
            self::Draft => [self::Planned, self::Confirmed, self::Cancelled],
            self::Planned => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled => [],
        }, true);
    }
}
