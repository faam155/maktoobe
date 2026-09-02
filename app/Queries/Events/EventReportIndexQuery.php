<?php

namespace App\Queries\Events;

use App\Enums\EventReportType;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class EventReportIndexQuery
{
    public function handle(User $actor, Event $event, array $input): array
    {
        Gate::forUser($actor)->authorize('view', $event);
        Validator::make($input, ['pre_page' => ['nullable', 'integer', 'min:1', 'max:100000'], 'post_page' => ['nullable', 'integer', 'min:1', 'max:100000']])->validate();
        $reports = $event->reports()->with('currentVersion.file.uploader')->get()->keyBy(fn ($report) => $report->type->value);
        $sections = collect(EventReportType::cases())->map(function ($type) use ($reports) {
            $report = $reports->get($type->value);
            $pageName = $type === EventReportType::PreEvent ? 'pre_page' : 'post_page';
            $versions = $report?->versions()->with('file.uploader')->orderByDesc('version_number')->paginate(10, ['*'], $pageName)->withQueryString();

            return compact('type', 'report', 'versions');
        });

        return compact('event', 'sections');
    }
}
