<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromptUse extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'prompt_id', 'ai_request_id', 'kind', 'client_operation_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    public function aiRequest(): BelongsTo
    {
        return $this->belongsTo(AiRequest::class);
    }
}
