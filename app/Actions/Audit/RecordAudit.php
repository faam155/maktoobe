<?php

namespace App\Actions\Audit;

use App\Models\AuditLog;
use App\Models\EventActivity;
use App\Services\Audit\SafeAuditMetadata;

class RecordAudit
{
    public function handle(?int $actorId, string $action, string $type, ?int $resourceId, array $metadata = [], ?string $source = null, mixed $at = null): void
    {
        if (! in_array($type, ['social', 'user', 'role', 'prompt', 'prompt_category', 'event', 'brand_guideline', 'system', 'ai_configuration'], true) || ! preg_match('/^[a-z][a-z0-9_.]{0,79}$/D', $action)) {
            throw new \InvalidArgumentException('Invalid audit identity.');
        }
        $values = ['actor_id' => $actorId, 'action' => $action, 'resource_type' => $type, 'resource_id' => $resourceId, 'metadata' => app(SafeAuditMetadata::class)->clean($metadata), 'created_at' => $at ?? now()];
        if ($source) {
            AuditLog::firstOrCreate(['source_key' => $source], $values);
        } else {
            AuditLog::create($values);
        }
    }

    public function account(object $row): void
    {
        $m = is_array($row->metadata) ? $row->metadata : (json_decode($row->metadata ?? '{}', true) ?? []);
        $type = match (true) {
            str_starts_with($row->action, 'prompt_category.') => 'prompt_category',
            str_starts_with($row->action, 'prompt.') => 'prompt',
            str_starts_with($row->action, 'role.') => 'role',
            str_starts_with($row->action, 'brand_guideline.') => 'brand_guideline',
            str_starts_with($row->action, 'notification.') => 'system',
            default => 'user'
        };
        $id = match ($type) {
            'prompt' => $m['prompt_id'] ?? null,'prompt_category' => $m['category_id'] ?? null,'role' => $m['role_id'] ?? null,'brand_guideline' => $m['guideline_id'] ?? null,'system' => $m['notice_id'] ?? null,default => $row->user_id
        };
        $this->handle($row->actor_id, $row->action, $type, $id, $m, 'account:'.$row->id, $row->created_at);
    }

    public function event(EventActivity $row): void
    {
        $this->handle($row->actor_id, $row->action, 'event', $row->event_id, $row->metadata ?? [], 'event:'.$row->id, $row->created_at);
    }
}
