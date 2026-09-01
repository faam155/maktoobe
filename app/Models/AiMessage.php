<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['conversation_id', 'role', 'content', 'input_tokens', 'output_tokens', 'total_tokens'];

    protected function casts(): array
    {
        return ['input_tokens' => 'integer', 'output_tokens' => 'integer', 'total_tokens' => 'integer'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class);
    }
}
