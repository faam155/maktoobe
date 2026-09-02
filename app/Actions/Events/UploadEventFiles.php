<?php

namespace App\Actions\Events;

use App\Contracts\PrivateFileScanner;
use App\Enums\EventFileCategory;
use App\Models\Event;
use App\Models\EventFile;
use App\Models\User;
use App\Services\Events\EventFileInspector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class UploadEventFiles
{
    public function handle(User $actor, Event $event, array $input): void
    {
        Gate::forUser($actor)->authorize('create', [EventFile::class, $event]);
        $data = Validator::make($input, [
            'category' => ['required', Rule::enum(EventFileCategory::class)],
            'caption' => ['nullable', 'string', 'max:500'],
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => ['required', 'file', 'max:2048', 'extensions:pdf,docx,png,jpg,jpeg,webp,txt'],
        ])->validate();
        if (array_sum(array_map(fn ($file) => $file->getSize(), $data['files'])) > 6 * 1024 * 1024) {
            throw ValidationException::withMessages(['files' => __('event_files.total_limit')]);
        }
        $prepared = [];
        // PHP upload temp files are the non-addressable quarantine. Nothing is
        // published to application storage or metadata until the whole batch passes.
        foreach ($data['files'] as $file) {
            $metadata = app(EventFileInspector::class)->inspect($file);
            if ($data['category'] === 'photos' && ! str_starts_with($metadata['mime_type'], 'image/')) {
                throw ValidationException::withMessages(['files' => __('event_files.photos_only')]);
            }
            app(PrivateFileScanner::class)->assertSafe($file);
            $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
            if (! mb_check_encoding($name, 'UTF-8')) {
                throw ValidationException::withMessages(['files' => __('event_files.invalid')]);
            }
            $name = mb_substr(preg_replace('/[\x00-\x1f\x7f]/u', '', $name), 0, 180);
            $prepared[] = ['file' => $file, 'metadata' => $metadata + [
                'category' => $data['category'], 'caption' => $data['caption'] ?? null,
                'original_name' => $name, 'storage_disk' => 'local',
                'storage_path' => 'event-files/'.$event->id.'/'.Str::uuid().'.'.$metadata['extension'],
                'file_size' => $file->getSize(), 'uploaded_by' => $actor->id, 'scan_status' => 'clean',
            ]];
        }
        $written = [];
        try {
            foreach ($prepared as $item) {
                $written[] = $item['metadata']['storage_path'];
                if (! Storage::disk('local')->putFileAs('', $item['file'], $item['metadata']['storage_path'])) {
                    throw ValidationException::withMessages(['files' => __('event_files.failed')]);
                }
            }
            DB::transaction(function () use ($actor, $event, $prepared) {
                $locked = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('create', [EventFile::class, $locked]);
                $order = (int) $locked->files()->max('display_order');
                foreach ($prepared as $item) {
                    $file = $locked->files()->create($item['metadata'] + ['display_order' => min(++$order, 1000000)]);
                    $locked->activities()->create(['actor_id' => $actor->id, 'action' => 'event.file_uploaded', 'metadata' => ['file_id' => $file->id, 'category' => $file->category->value], 'created_at' => now()]);
                }
            });
        } catch (Throwable $exception) {
            foreach ($written as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }
    }
}
