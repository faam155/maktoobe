<?php

namespace App\Services\Events;

use App\Models\EventFile;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EventFileResponse
{
    public function handle(User $actor, EventFile $file, bool $preview = false): StreamedResponse
    {
        Gate::forUser($actor)->authorize('view', $file);
        abort_if($preview && ! $file->isImage(), 404);
        abort_unless($file->storage_disk === 'local' && preg_match('~^event-files/'.$file->event_id.'/[a-f0-9-]{36}\.(png|jpe?g|webp|pdf|docx|xlsx|txt)$~D', $file->storage_path), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($file->storage_path), 404);
        $headers = ['Content-Type' => $file->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "default-src 'none'; sandbox", 'Referrer-Policy' => 'no-referrer'];

        return $disk->response($file->storage_path, $file->original_name, $headers, $preview ? 'inline' : 'attachment');
    }
}
