<?php

namespace App\Models;

use App\Enums\AiRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRequest extends Model
{
    protected $fillable = ['user_id', 'conversation_id', 'prompt_id', 'prompt_revision', 'prompt_snapshot', 'user_message_id', 'assistant_message_id', 'client_operation_id', 'model', 'status', 'settings_snapshot', 'provider_request_id', 'input_tokens', 'output_tokens', 'total_tokens', 'failure_code', 'requested_at', 'started_at', 'finished_at', 'cancelled_at'];

    protected function casts(): array
    {
        return ['status' => AiRequestStatus::class, 'settings_snapshot' => 'array', 'prompt_snapshot' => 'encrypted', 'prompt_revision' => 'integer', 'requested_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class);
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    public function userMessage(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'user_message_id');
    }

    public function assistantMessage(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'assistant_message_id');
    }
}
