<?php

namespace App\Actions\Events;

use App\Enums\EventFileCategory;
use App\Models\EventFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UpdateEventFile
{
    public function handle(User $actor, EventFile $file, array $input): void
    {
        Gate::forUser($actor)->authorize('update', $file);
        $data = Validator::make($input, ['caption' => ['nullable', 'string', 'max:500'], 'display_order' => ['required', 'integer', 'between:0,1000000'], 'category' => ['required', Rule::enum(EventFileCategory::class)]])->validate();
        if ($data['category'] === 'photos' && ! $file->isImage()) {
            throw ValidationException::withMessages(['category' => __('event_files.photos_only')]);
        }
        DB::transaction(function () use ($actor, $file, $data) {
            $file = EventFile::whereKey($file->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $file);
            $file->update($data);
            $file->event->activities()->create(['actor_id' => $actor->id, 'action' => 'event.file_updated', 'metadata' => ['file_id' => $file->id], 'created_at' => now()]);
        });
    }
}
