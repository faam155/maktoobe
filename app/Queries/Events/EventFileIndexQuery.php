<?php

namespace App\Queries\Events;

use App\Enums\EventFileCategory;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EventFileIndexQuery
{
    public function handle(User $actor, Event $event, array $input): array
    {
        Gate::forUser($actor)->authorize('view', $event);
        $filters = Validator::make($input, ['category' => ['nullable', Rule::enum(EventFileCategory::class)], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']])->validate();
        $files = $event->files()->where('scan_status', 'clean')
            ->when(filled($filters['category'] ?? null), fn ($query) => $query->where('category', $filters['category']))
            ->with('uploader:id,name')->orderBy('display_order')->orderBy('id')->paginate(24)->withQueryString();

        return compact('event', 'files', 'filters');
    }
}
