<?php

namespace App\Services\Identity;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class LocalInbox
{
    public function store(array $message): string
    {
        if (! app()->environment(['local', 'testing', 'browser'])) {
            throw new RuntimeException('Local authentication delivery is unavailable in this environment.');
        }
        $id = (string) Str::uuid();
        Storage::disk('local')->put('auth-inbox/'.app()->environment().'/'.$id.'.enc', Crypt::encryptString(json_encode(
            [...$message, 'created_at' => now()->toIso8601String()], JSON_THROW_ON_ERROR
        )));

        return $id;
    }

    public function messages(): array
    {
        if (! app()->environment(['local', 'testing', 'browser'])) {
            throw new RuntimeException('Local inbox is unavailable.');
        }
        $disk = Storage::disk('local');

        return collect($disk->files('auth-inbox/'.app()->environment()))->map(fn ($path) => [
            'id' => basename($path, '.enc'), ...json_decode(Crypt::decryptString($disk->get($path)), true, flags: JSON_THROW_ON_ERROR),
        ])->sortBy('created_at')->values()->all();
    }
}
