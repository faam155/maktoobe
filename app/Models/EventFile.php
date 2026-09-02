<?php

namespace App\Models;

use App\Enums\EventFileCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['category', 'original_name', 'storage_disk', 'storage_path', 'mime_type', 'extension', 'file_size', 'caption', 'display_order', 'scan_status', 'uploaded_by'];

    protected $hidden = ['storage_disk', 'storage_path'];

    protected function casts(): array
    {
        return ['category' => EventFileCategory::class, 'file_size' => 'integer', 'display_order' => 'integer'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    public function isImage(): bool
    {
        return in_array($this->extension, ['png', 'jpg', 'jpeg', 'webp'], true) && str_starts_with($this->mime_type, 'image/');
    }
}
