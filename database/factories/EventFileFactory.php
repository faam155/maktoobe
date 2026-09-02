<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EventFileFactory extends Factory
{
    protected $model = EventFile::class;

    public function definition(): array
    {
        return ['event_id' => Event::factory(), 'category' => 'other', 'original_name' => 'notes.txt', 'storage_disk' => 'local',
            'storage_path' => fn (array $attributes) => 'event-files/'.$attributes['event_id'].'/'.Str::uuid().'.txt',
            'extension' => 'txt', 'mime_type' => 'text/plain', 'file_size' => 20, 'caption' => null, 'display_order' => 0, 'scan_status' => 'clean', 'uploaded_by' => User::factory()];
    }
}
