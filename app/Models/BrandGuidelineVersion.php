<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BrandGuidelineVersion extends Model
{
    protected $fillable = ['brand_guideline_id', 'version', 'storage_disk', 'storage_path', 'original_name', 'extension', 'mime_type', 'file_size', 'extracted_text', 'extraction_status', 'scan_status', 'uploaded_by', 'is_active', 'activated_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'activated_at' => 'datetime', 'file_size' => 'integer'];
    }

    public function guideline(): BelongsTo
    {
        return $this->belongsTo(BrandGuideline::class, 'brand_guideline_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function aiRequests(): HasMany
    {
        return $this->hasMany(AiRequest::class);
    }
}
